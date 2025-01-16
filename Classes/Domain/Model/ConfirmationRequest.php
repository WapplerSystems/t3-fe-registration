<?php

namespace WapplerSystems\FeRegistration\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use WapplerSystems\FormExtended\Utility\Uuid;

/**
 */
class ConfirmationRequest extends AbstractEntity
{

    /**
     * email
     *
     * @var string
     */
    protected string $email = '';

    /**
     * @var string
     */
    protected string $encodedValues = '';

    /**
     *
     * @var string
     */
    protected string $confirmationHash = '';

    /**
     *
     */
    protected ?\DateTime $confirmationDate = null;


    protected ?\DateTime $lastSent = null;


    public function __construct()
    {
        if (!$this->confirmationHash) {
            $this->confirmationHash = Uuid::generate();
        }
    }

    /**
     * Returns the email
     *
     * @return string $email
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Sets the email
     *
     * @param string $email
     * @return void
     */
    public function setEmail($email)
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
        return json_decode($this->getEncodedValues(), true);
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




}
