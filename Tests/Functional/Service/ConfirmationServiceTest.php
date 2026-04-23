<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Service\ConfirmationService;

class ConfirmationServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'wapplersystems/form_extended',
        'wapplersystems/fe-registration',
    ];

    private ConfirmationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Domain/Repository/Fixtures/confirmation_requests.csv');
        $this->service = $this->get(ConfirmationService::class);
    }

    #[Test]
    public function findByHashReturnsMatchingRequest(): void
    {
        $result = $this->service->findByHash('hash-unconfirmed-1111');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        self::assertSame('unconfirmed@example.com', $result->getEmail());
    }

    #[Test]
    public function findByHashReturnsNullForUnknownHash(): void
    {
        $result = $this->service->findByHash('does-not-exist');

        self::assertNull($result);
    }

    #[Test]
    public function findUnconfirmedByEmailReturnsUnconfirmedRequest(): void
    {
        $result = $this->service->findUnconfirmedByEmail('unconfirmed@example.com');

        self::assertInstanceOf(ConfirmationRequest::class, $result);
        self::assertFalse($result->isConfirmed());
    }

    #[Test]
    public function findUnconfirmedByEmailReturnsNullForConfirmed(): void
    {
        $result = $this->service->findUnconfirmedByEmail('confirmed@example.com');

        self::assertNull($result);
    }

    #[Test]
    public function setRequestConfirmedSetsConfirmationDate(): void
    {
        $request = $this->service->findByHash('hash-unconfirmed-1111');
        self::assertFalse($request->isConfirmed());

        $this->service->setRequestConfirmed($request);

        self::assertTrue($request->isConfirmed());
        self::assertInstanceOf(\DateTime::class, $request->getConfirmationDate());
    }

    #[Test]
    public function setRegistrationCompletedSetsCompletionDate(): void
    {
        $request = $this->service->findByHash('hash-confirmed-2222');
        self::assertFalse($request->isRegistrationCompleted());

        $this->service->setRegistrationCompleted($request);

        self::assertTrue($request->isRegistrationCompleted());
        self::assertInstanceOf(\DateTime::class, $request->getCompletionDate());
    }
}