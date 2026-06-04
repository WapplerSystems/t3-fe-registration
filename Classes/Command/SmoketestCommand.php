<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Command;

use Doctrine\DBAL\ParameterType;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WapplerSystems\FeRegistration\Event\BeforeFrontendUserDeletedEvent;
use WapplerSystems\FeRegistration\Service\ConfirmationService;
use WapplerSystems\FeRegistration\Service\DatabaseService;
use WapplerSystems\FeRegistration\Validator\UserAlreadyExistsValidator;
use WapplerSystems\FormExtended\Utility\Uuid;

/**
 * End-to-end smoketest for the DOI registration flow.
 *
 * Walks a single synthetic registration through the full lifecycle against the
 * live database — create request → validator → confirm → create fe_user (with
 * PrivEsc guard check) → complete → delete account → purge.
 *
 * Use after every upgrade or local patch to catch regressions before they hit
 * the FE. Idempotent: rows are cleaned up at the end unless --keep is passed.
 *
 *     ddev typo3 feregistration:smoketest
 *     ddev typo3 feregistration:smoketest --email=demo@example.com --keep
 *     ddev typo3 feregistration:smoketest --storage-pid=42
 */
#[AsCommand(
    name: 'feregistration:smoketest',
    description: 'End-to-end smoketest of the DOI registration flow against the live DB'
)]
class SmoketestCommand extends Command
{
    private const REQUEST_TABLE = 'tx_feregistration_domain_model_confirmationrequest';
    private const FE_USERS_TABLE = 'fe_users';
    private const STEP_COUNT = 6;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ConfirmationService $confirmationService,
        private readonly DatabaseService $databaseService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Synthetic email to register; defaults to a random smoketest+<rand>@example.invalid',
                ''
            )
            ->addOption(
                'storage-pid',
                null,
                InputOption::VALUE_REQUIRED,
                'pid used for both the ConfirmationRequest row and the fe_user row',
                '0'
            )
            ->addOption(
                'keep',
                null,
                InputOption::VALUE_NONE,
                'Keep created rows in the DB for inspection (default: delete them on the way out)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string)$input->getOption('email');
        if ($email === '') {
            $email = 'smoketest+' . bin2hex(random_bytes(4)) . '@example.invalid';
        }
        $pid = (int)$input->getOption('storage-pid');
        $keep = (bool)$input->getOption('keep');

        $io->writeln(sprintf(
            '<info>fe_registration smoketest</info>  email=<comment>%s</comment>  pid=<comment>%d</comment>  keep=<comment>%s</comment>',
            $email,
            $pid,
            $keep ? 'yes' : 'no'
        ));
        $io->newLine();

        $started = microtime(true);
        $createdRequestUid = 0;
        $createdFeUserUid = 0;

        try {
            $createdRequestUid = $this->step1CreateConfirmationRequest($io, $email, $pid);
            $this->step2ValidatorBlocksDuplicate($io, $email, $pid);
            $this->step3ConfirmByHash($io, $createdRequestUid);
            $createdFeUserUid = $this->step4CreateFeUserWithoutPrivEsc($io, $email, $pid, $createdRequestUid);
            $this->step5CompleteRegistration($io, $createdRequestUid);
            $this->step6PurgeOnUserDeletion($io, $createdFeUserUid, $createdRequestUid);

            $io->newLine();
            $io->success(sprintf('All checks passed. (%.1fs)', microtime(true) - $started));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->newLine();
            $io->error(['SMOKETEST FAILED', $e->getMessage()]);
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        } finally {
            if (!$keep) {
                $this->cleanup($createdRequestUid, $createdFeUserUid, $email);
            } else {
                // The request row is intentionally purged in step 6 (that IS
                // the test). With --keep we only retain whatever survived the
                // full flow: the fe_user row plus, if the flow aborted before
                // step 6, the request row.
                $survivors = [];
                if ($createdRequestUid > 0 && is_array($this->fetchRequestRow($createdRequestUid))) {
                    $survivors[] = 'request uid=' . $createdRequestUid;
                }
                if ($createdFeUserUid > 0) {
                    $survivors[] = 'fe_user uid=' . $createdFeUserUid;
                }
                $io->writeln('<comment>Kept rows: ' . ($survivors === [] ? 'none' : implode(', ', $survivors)) . '</comment>');
            }
        }
    }

    private function step1CreateConfirmationRequest(SymfonyStyle $io, string $email, int $pid): int
    {
        $this->printStep($io, 1, 'create confirmation request');

        $connection = $this->connectionPool->getConnectionForTable(self::REQUEST_TABLE);
        $now = time();
        $hash = Uuid::generate();
        $connection->insert(self::REQUEST_TABLE, [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
            'email' => $email,
            'confirmation_hash' => $hash,
            'encoded_values' => json_encode([
                'email' => $email,
                'firstName' => 'Smoke',
                'lastName' => 'Test',
            ]),
            'last_sent' => $now,
            'expires_at' => $now + 7 * 86400,
            'confirmation_date' => 0,
            'completion_date' => 0,
        ]);
        $uid = (int)$connection->lastInsertId();

        $this->printOk($io, 'uid ' . $uid);

        return $uid;
    }

    private function step2ValidatorBlocksDuplicate(SymfonyStyle $io, string $email, int $pid): void
    {
        $this->printStep($io, 2, 'verify validator blocks duplicate');

        $validator = new UserAlreadyExistsValidator();
        $validator->setOptions(['confirmationRequestPid' => $pid]);
        $result = $validator->validate($email);

        if (!$result->hasErrors()) {
            throw new \RuntimeException(
                'UserAlreadyExistsValidator did NOT flag the duplicate; the DB query regressed.'
            );
        }

        $this->printOk($io, '');
    }

    private function step3ConfirmByHash(SymfonyStyle $io, int $requestUid): void
    {
        $this->printStep($io, 3, 'confirm via hash');

        $hash = $this->fetchRequestColumn($requestUid, 'confirmation_hash');
        $request = $this->confirmationService->findByHash($hash);
        if ($request === null) {
            throw new \RuntimeException('findByHash() did not return the freshly inserted row');
        }
        $this->confirmationService->setRequestConfirmed($request);

        $row = $this->fetchRequestRow($requestUid);
        if ((int)($row['confirmation_date'] ?? 0) === 0) {
            throw new \RuntimeException('confirmation_date was not persisted after setRequestConfirmed()');
        }

        $this->printOk($io, '');
    }

    private function step4CreateFeUserWithoutPrivEsc(
        SymfonyStyle $io,
        string $email,
        int $pid,
        int $requestUid
    ): int {
        $this->printStep($io, 4, 'create fe_user (privesc guard)');

        $configuredUsergroups = '1';

        // Payload deliberately tries to smuggle system-controlled values so we
        // can assert the createFeUser blacklist is still in effect.
        $values = [
            'email' => $email,
            'firstName' => 'Smoke',
            'lastName' => 'Test',
            // privilege-escalation attempts:
            'usergroup' => '9999',
            'pid' => 999999,
            'disable' => 0,
            'deleted' => 0,
            'is_online' => 999999,
            'felogin_forgotHash' => 'attacker-controlled',
            'tstamp' => 1,
            // legitimate linkage to the ConfirmationRequest:
            'registrationRequest' => $requestUid,
        ];
        $settings = [
            'feUserStoragePid' => $pid,
            'usergroups' => $configuredUsergroups,
            'identifierFieldName' => 'email',
        ];

        $row = $this->databaseService->createFeUser($values, $settings);
        $feUserUid = (int)$row['uid'];

        $created = $this->connectionPool
            ->getConnectionForTable(self::FE_USERS_TABLE)
            ->select(
                ['uid', 'pid', 'username', 'usergroup', 'disable', 'deleted', 'is_online', 'felogin_forgotHash', 'registration_request'],
                self::FE_USERS_TABLE,
                ['uid' => $feUserUid]
            )
            ->fetchAssociative();

        if (!is_array($created)) {
            throw new \RuntimeException('fe_user row could not be read back after creation');
        }

        $assertions = [
            'pid (no smuggled pid)' => [(int)$created['pid'], $pid],
            'usergroup (no smuggled group)' => [(string)$created['usergroup'], $configuredUsergroups],
            'username (= identifierFieldName)' => [(string)$created['username'], $email],
            'is_online (must stay 0)' => [(int)$created['is_online'], 0],
            'felogin_forgotHash (must stay empty)' => [(string)$created['felogin_forgotHash'], ''],
            'registration_request (= linkage)' => [(int)$created['registration_request'], $requestUid],
        ];
        foreach ($assertions as $label => [$actual, $expected]) {
            if ($actual !== $expected) {
                throw new \RuntimeException(sprintf(
                    'PrivEsc guard failed on %s — expected %s, got %s',
                    $label,
                    var_export($expected, true),
                    var_export($actual, true)
                ));
            }
        }

        $this->printOk($io, 'uid ' . $feUserUid);

        return $feUserUid;
    }

    private function step5CompleteRegistration(SymfonyStyle $io, int $requestUid): void
    {
        $this->printStep($io, 5, 'complete registration');

        $hash = $this->fetchRequestColumn($requestUid, 'confirmation_hash');
        $request = $this->confirmationService->findByHash($hash);
        if ($request === null) {
            throw new \RuntimeException('findByHash() lost the row between steps');
        }
        $this->confirmationService->setRegistrationCompleted($request);

        $row = $this->fetchRequestRow($requestUid);
        if (!is_array($row)) {
            throw new \RuntimeException('ConfirmationRequest row disappeared after completion');
        }
        if ((int)($row['completion_date'] ?? 0) === 0) {
            throw new \RuntimeException('completion_date was not persisted after setRegistrationCompleted()');
        }
        $encoded = (string)($row['encoded_values'] ?? '');
        if ($encoded !== '' && $encoded !== '[]') {
            throw new \RuntimeException(sprintf(
                'encoded_values was not cleared after completion (got %s…)',
                substr($encoded, 0, 60)
            ));
        }

        $this->printOk($io, 'encoded_values cleared');
    }

    private function step6PurgeOnUserDeletion(SymfonyStyle $io, int $feUserUid, int $requestUid): void
    {
        $this->printStep($io, 6, 'delete account + purge linked request');

        $userRow = $this->connectionPool
            ->getConnectionForTable(self::FE_USERS_TABLE)
            ->select(['uid', 'email', 'registration_request'], self::FE_USERS_TABLE, ['uid' => $feUserUid])
            ->fetchAssociative();
        if (!is_array($userRow)) {
            throw new \RuntimeException('Could not re-read the fe_user row before dispatching the delete event');
        }

        $event = new BeforeFrontendUserDeletedEvent($userRow, []);
        $this->eventDispatcher->dispatch($event);

        $stillThere = $this->fetchRequestRow($requestUid);
        if (is_array($stillThere)) {
            throw new \RuntimeException(sprintf(
                'PurgeConfirmationRequestOnUserDeletion did not remove ConfirmationRequest uid=%d',
                $requestUid
            ));
        }

        $this->printOk($io, 'request row gone');
    }

    private function cleanup(int $requestUid, int $feUserUid, string $email): void
    {
        // Belt-and-suspenders: delete by uid AND by email/username, so a step
        // that failed AFTER the INSERT but BEFORE assigning the uid to the
        // outer scope (e.g. createFeUser succeeded, then the SELECT-back
        // crashed) still gets cleaned up.
        $feUsers = $this->connectionPool->getConnectionForTable(self::FE_USERS_TABLE);
        if ($feUserUid > 0) {
            $feUsers->delete(self::FE_USERS_TABLE, ['uid' => $feUserUid]);
        }
        $feUsers->delete(self::FE_USERS_TABLE, ['username' => $email]);

        $this->connectionPool
            ->getConnectionForTable(self::REQUEST_TABLE)
            ->delete(self::REQUEST_TABLE, ['email' => $email]);
    }

    private function fetchRequestRow(int $uid): array|false
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(self::REQUEST_TABLE);
        $qb->getRestrictions()->removeAll();

        return $qb->select('*')
            ->from(self::REQUEST_TABLE)
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();
    }

    private function fetchRequestColumn(int $uid, string $column): string
    {
        $row = $this->fetchRequestRow($uid);
        if (!is_array($row)) {
            throw new \RuntimeException(sprintf('ConfirmationRequest uid=%d disappeared between steps', $uid));
        }

        return (string)($row[$column] ?? '');
    }

    private function printStep(SymfonyStyle $io, int $n, string $label): void
    {
        $body = $label . ' ';
        $io->write(sprintf('  [%d/%d] %s ', $n, self::STEP_COUNT, str_pad($body, 50, '.')));
    }

    private function printOk(SymfonyStyle $io, string $detail): void
    {
        if ($detail === '') {
            $io->writeln('<info>OK</info>');

            return;
        }
        $io->writeln(sprintf('<info>OK</info> <comment>(%s)</comment>', $detail));
    }
}