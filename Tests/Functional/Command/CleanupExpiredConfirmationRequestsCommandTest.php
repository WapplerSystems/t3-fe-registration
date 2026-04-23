<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Tests\Functional\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WapplerSystems\FeRegistration\Command\CleanupExpiredConfirmationRequestsCommand;

class CleanupExpiredConfirmationRequestsCommandTest extends FunctionalTestCase
{
    private const TABLE = 'tx_feregistration_domain_model_confirmationrequest';

    protected array $testExtensionsToLoad = [
        'wapplersystems/form_extended',
        'wapplersystems/fe-registration',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/cleanup_requests.csv');
    }

    private function getRecordCount(): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')->from(self::TABLE)->executeQuery()->fetchOne();
    }

    private function recordExists(string $email): bool
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return (bool)$queryBuilder
            ->count('uid')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email)))
            ->executeQuery()
            ->fetchOne();
    }

    #[Test]
    public function commandRemovesExpiredUncompletedRequests(): void
    {
        $command = $this->get(CleanupExpiredConfirmationRequestsCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        // expired@example.com has expires_at in the past and no completion -> removed
        self::assertFalse($this->recordExists('expired@example.com'));
    }

    #[Test]
    public function commandKeepsActiveRequests(): void
    {
        $command = $this->get(CleanupExpiredConfirmationRequestsCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([]);

        // active@example.com has expires_at in the future -> kept
        self::assertTrue($this->recordExists('active@example.com'));
    }

    #[Test]
    public function commandKeepsCompletedRequestsEvenIfExpired(): void
    {
        $command = $this->get(CleanupExpiredConfirmationRequestsCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([]);

        // completed@example.com has completion_date set -> kept
        self::assertTrue($this->recordExists('completed@example.com'));
    }

    #[Test]
    public function commandRemovesOldRequestsWithoutExpiryBasedOnDaysOption(): void
    {
        $command = $this->get(CleanupExpiredConfirmationRequestsCommand::class);
        $tester = new CommandTester($command);
        $tester->execute(['--days' => '1']);

        // old-no-expiry@example.com has no expires_at and crdate far in the past -> removed
        self::assertFalse($this->recordExists('old-no-expiry@example.com'));
    }

    #[Test]
    public function commandKeepsRecentRequestsWithoutExpiry(): void
    {
        $command = $this->get(CleanupExpiredConfirmationRequestsCommand::class);
        $tester = new CommandTester($command);
        $tester->execute(['--days' => '30']);

        // recent-no-expiry@example.com has no expires_at but crdate in the future -> kept
        self::assertTrue($this->recordExists('recent-no-expiry@example.com'));
    }

    #[Test]
    public function commandOutputsSuccessMessage(): void
    {
        $command = $this->get(CleanupExpiredConfirmationRequestsCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertStringContainsString('Removed', $tester->getDisplay());
        self::assertStringContainsString('expired/stale confirmation requests', $tester->getDisplay());
    }
}