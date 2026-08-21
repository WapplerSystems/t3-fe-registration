<?php

namespace WapplerSystems\FeRegistration\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use Symfony\Component\Uid\Uuid;

/**
 */
class ConfirmationRequest extends AbstractEntity
{

    protected string $email = '';

    protected string $encodedValues = '';

    protected string $confirmationHash = '';

    protected ?\DateTime $confirmationDate = null;

    protected ?\DateTime $lastSent = null;

    protected ?\DateTime $completionDate = null;

    protected ?\DateTime $expiresAt = null;


    public function __construct()
    {
        $this->confirmationHash = Uuid::v4()->toRfc4122();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }


    /**
     * Sets the confirmationHash
     *
     * @param string $confirmationHash
     * @return void
     */
    public function setConfirmationHash($confirmationHash): void
    {
        $this->confirmationHash = $confirmationHash;
    }

    public function getConfirmationHash(): string
    {
        return $this->confirmationHash;
    }


    /**
     * Returns the confirmationDate
     *
     * @return \DateTime $confirmationDate
     */
    public function getConfirmationDate(): ?\DateTime
    {
        return $this->confirmationDate;
    }

    /**
     * Sets the confirmationDate
     *
     * @param \DateTime $confirmationDate
     * @return void
     */
    public function setConfirmationDate(?\DateTime $confirmationDate): void
    {
        $this->confirmationDate = $confirmationDate;
    }


    /**
     * @return string
     */
    public function getEncodedValues(): string
    {
        return $this->encodedValues;
    }

    /**
     * @param string $encodedValues
     */
    public function setEncodedValues(string $encodedValues): void
    {
        $this->encodedValues = $encodedValues;
    }

    /**
     * @return array
     */
    public function getDecodedValues(): array
    {
        return json_decode($this->getEncodedValues(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function setDecodedValues(array $values): void
    {
        $this->setEncodedValues(json_encode($values));
    }

    public function getLastSent(): ?\DateTime
    {
        return $this->lastSent;
    }

    public function setLastSent(?\DateTime $lastSent): void
    {
        $this->lastSent = $lastSent;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmationDate !== null;
    }

    public function isRegistrationCompleted(): bool
    {
        return $this->completionDate !== null;
    }

    public function getCompletionDate(): ?\DateTime
    {
        return $this->completionDate;
    }

    public function setCompletionDate(?\DateTime $completionDate): void
    {
        $this->completionDate = $completionDate;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    /**
     * Treats a missing expiry date as expired.
     *
     * Fail-closed on purpose: the previous `$expiresAt !== null && …` returned false for
     * a row without a date, so such a double-opt-in link stayed valid forever and the
     * cleanup command could only ever catch it through its `--days` fallback. Rows land
     * in that state whenever ConfirmationRequestFinisher runs with `expirationDays: 0`.
     *
     * A confirmation link is short-lived by nature; if we cannot tell how long it should
     * live, the safe answer is "no longer".
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return true;
        }

        return $this->expiresAt < new \DateTime();
    }

}
