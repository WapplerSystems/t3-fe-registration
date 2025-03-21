<?php

namespace WapplerSystems\FeRegistration\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 */
class EmailAddress extends AbstractEntity
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
    protected string $name = '';



    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }


}
