<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;

class ConfirmationRequestTest extends UnitTestCase
{
    private ConfirmationRequest $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ConfirmationRequest();
    }

    #[Test]
    public function constructorGeneratesConfirmationHash(): void
    {
        self::assertNotEmpty($this->subject->getConfirmationHash());
    }

    #[Test]
    public function confirmationHashIsUuidFormat(): void
    {
        $hash = $this->subject->getConfirmationHash();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $hash
        );
    }

    #[Test]
    public function twoInstancesHaveDifferentHashes(): void
    {
        $other = new ConfirmationRequest();
        self::assertNotSame($this->subject->getConfirmationHash(), $other->getConfirmationHash());
    }

    #[Test]
    public function emailCanBeSetAndRetrieved(): void
    {
        $this->subject->setEmail('test@example.com');
        self::assertSame('test@example.com', $this->subject->getEmail());
    }

    #[Test]
    public function emailDefaultsToEmptyString(): void
    {
        self::assertSame('', $this->subject->getEmail());
    }

    #[Test]
    public function encodedValuesCanBeSetAndRetrieved(): void
    {
        $this->subject->setEncodedValues('{"name":"Test"}');
        self::assertSame('{"name":"Test"}', $this->subject->getEncodedValues());
    }

    #[Test]
    public function decodedValuesReturnsArray(): void
    {
        $values = ['firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com'];
        $this->subject->setDecodedValues($values);
        self::assertSame($values, $this->subject->getDecodedValues());
    }

    #[Test]
    public function decodedValuesThrowsOnInvalidJson(): void
    {
        $this->subject->setEncodedValues('invalid json');
        $this->expectException(\JsonException::class);
        $this->subject->getDecodedValues();
    }

    #[Test]
    public function isConfirmedReturnsFalseByDefault(): void
    {
        self::assertFalse($this->subject->isConfirmed());
    }

    #[Test]
    public function isConfirmedReturnsTrueWhenConfirmationDateIsSet(): void
    {
        $this->subject->setConfirmationDate(new \DateTime());
        self::assertTrue($this->subject->isConfirmed());
    }

    #[Test]
    public function isRegistrationCompletedReturnsFalseByDefault(): void
    {
        self::assertFalse($this->subject->isRegistrationCompleted());
    }

    #[Test]
    public function isRegistrationCompletedReturnsTrueWhenCompletionDateIsSet(): void
    {
        $this->subject->setCompletionDate(new \DateTime());
        self::assertTrue($this->subject->isRegistrationCompleted());
    }

    #[Test]
    public function confirmationDateCanBeSetToNull(): void
    {
        $this->subject->setConfirmationDate(new \DateTime());
        self::assertTrue($this->subject->isConfirmed());

        $this->subject->setConfirmationDate(null);
        self::assertFalse($this->subject->isConfirmed());
    }

    #[Test]
    public function isExpiredReturnsFalseByDefault(): void
    {
        self::assertFalse($this->subject->isExpired());
    }

    #[Test]
    public function isExpiredReturnsFalseWhenExpiresAtIsInFuture(): void
    {
        $future = new \DateTime('+1 hour');
        $this->subject->setExpiresAt($future);
        self::assertFalse($this->subject->isExpired());
    }

    #[Test]
    public function isExpiredReturnsTrueWhenExpiresAtIsInPast(): void
    {
        $past = new \DateTime('-1 hour');
        $this->subject->setExpiresAt($past);
        self::assertTrue($this->subject->isExpired());
    }

    #[Test]
    public function isExpiredReturnsFalseWhenExpiresAtIsNull(): void
    {
        $this->subject->setExpiresAt(null);
        self::assertFalse($this->subject->isExpired());
    }

    #[Test]
    public function lastSentCanBeSetAndRetrieved(): void
    {
        $date = new \DateTime('2025-01-15 12:00:00');
        $this->subject->setLastSent($date);
        self::assertSame($date, $this->subject->getLastSent());
    }

    #[Test]
    public function lastSentDefaultsToNull(): void
    {
        self::assertNull($this->subject->getLastSent());
    }

    #[Test]
    public function confirmationHashCanBeOverwritten(): void
    {
        $customHash = 'custom-hash-value';
        $this->subject->setConfirmationHash($customHash);
        self::assertSame($customHash, $this->subject->getConfirmationHash());
    }

    #[Test]
    public function expiresAtCanBeSetAndRetrieved(): void
    {
        $date = new \DateTime('+7 days');
        $this->subject->setExpiresAt($date);
        self::assertSame($date, $this->subject->getExpiresAt());
    }

    #[Test]
    public function completionDateCanBeSetAndRetrieved(): void
    {
        $date = new \DateTime();
        $this->subject->setCompletionDate($date);
        self::assertSame($date, $this->subject->getCompletionDate());
    }

    #[Test]
    public function confirmationDateCanBeSetAndRetrieved(): void
    {
        $date = new \DateTime();
        $this->subject->setConfirmationDate($date);
        self::assertSame($date, $this->subject->getConfirmationDate());
    }
}