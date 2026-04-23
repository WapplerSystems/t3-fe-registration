<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'feregistration:cleanup',
    description: 'Remove expired and unconfirmed confirmation requests'
)]
class CleanupExpiredConfirmationRequestsCommand extends Command
{

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            'd',
            InputOption::VALUE_OPTIONAL,
            'Remove unconfirmed requests older than this many days (fallback if no expires_at is set)',
            30
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int)$input->getOption('days');
        $table = 'tx_feregistration_domain_model_confirmationrequest';
        $now = time();
        $cutoff = $now - ($days * 86400);

        $connection = $this->connectionPool->getConnectionForTable($table);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $count = $queryBuilder
            ->delete($table)
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('completion_date', 0),
                    $queryBuilder->expr()->or(
                        // Has explicit expiration and is expired
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->gt('expires_at', 0),
                            $queryBuilder->expr()->lt('expires_at', $queryBuilder->createNamedParameter($now))
                        ),
                        // No expiration set, but older than --days cutoff
                        $queryBuilder->expr()->and(
                            $queryBuilder->expr()->eq('expires_at', 0),
                            $queryBuilder->expr()->lt('crdate', $queryBuilder->createNamedParameter($cutoff))
                        )
                    )
                )
            )
            ->executeStatement();

        $io->success(sprintf('Removed %d expired/stale confirmation requests.', $count));

        return Command::SUCCESS;
    }
}