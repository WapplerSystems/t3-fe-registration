<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Tests\Functional\Domain\Repository;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;

class ConfirmationRequestRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wapplersystems/form_extended',
        'wapplersystems/fe-registration',
    ];

    private ConfirmationRequestRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/confirmation_requests.csv');
        $this->repository = $this->get(ConfirmationRequestRepository::class);
    }

    #[Test]
    public function findOneByConfirmationHashReturnsMatchingRequest(): void
    {
        $result = $this->repository->findOneByConfirmationHash('hash-unconfirmed-1111');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        self::assertSame('unconfirmed@example.com', $result->getEmail());
    }

    #[Test]
    public function findOneByConfirmationHashReturnsNullForUnknownHash(): void
    {
        $result = $this->repository->findOneByConfirmationHash('nonexistent-hash');

        self::assertNull($result);
    }

    #[Test]
    public function findOneByConfirmationHashReturnsNullForEmptyHash(): void
    {
        $result = $this->repository->findOneByConfirmationHash('');

        self::assertNull($result);
    }

    #[Test]
    public function findUnconfirmedByEmailReturnsUnconfirmedRequest(): void
    {
        $result = $this->repository->findUnconfirmedByEmail('unconfirmed@example.com');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        self::assertSame('unconfirmed@example.com', $result->getEmail());
        self::assertFalse($result->isConfirmed());
    }

    #[Test]
    public function findUnconfirmedByEmailReturnsNullForConfirmedEmail(): void
    {
        $result = $this->repository->findUnconfirmedByEmail('confirmed@example.com');

        self::assertNull($result);
    }

    #[Test]
    public function findUnconfirmedByEmailReturnsNullForCompletedEmail(): void
    {
        $result = $this->repository->findUnconfirmedByEmail('completed@example.com');

        self::assertNull($result);
    }

    #[Test]
    public function findUnconfirmedByEmailReturnsNullForUnknownEmail(): void
    {
        $result = $this->repository->findUnconfirmedByEmail('unknown@example.com');

        self::assertNull($result);
    }

    #[Test]
    public function findOneByConfirmationHashIgnoresStoragePage(): void
    {
        // Hash exists on pid=10, repository should find it regardless of storage page
        $result = $this->repository->findOneByConfirmationHash('hash-completed-3333');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        self::assertSame('completed@example.com', $result->getEmail());
    }

    #[Test]
    public function foundRequestHasCorrectDecodedValues(): void
    {
        $result = $this->repository->findOneByConfirmationHash('hash-completed-3333');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        $values = $result->getDecodedValues();
        self::assertSame('John', $values['firstName']);
    }

    #[Test]
    public function foundCompletedRequestReportsCorrectState(): void
    {
        $result = $this->repository->findOneByConfirmationHash('hash-completed-3333');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        self::assertTrue($result->isConfirmed());
        self::assertTrue($result->isRegistrationCompleted());
    }

    #[Test]
    public function foundExpiredRequestReportsExpired(): void
    {
        $result = $this->repository->findOneByConfirmationHash('hash-expired-4444');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        self::assertTrue($result->isExpired());
    }
}