<?php

namespace WapplerSystems\FeRegistration\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;

/**
 */
class ConfirmationRequestRepository extends Repository
{

    public function findOneByConfirmationHash(string $confirmationHash): ?ConfirmationRequest
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('confirmationHash', $confirmationHash)
        );
        $query->setLimit(1);
        /** @var ConfirmationRequest|null $result */
        $result = $query->execute()->getFirst();
        return $result;
    }

    public function createQuery()
    {
        $query = parent::createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query;
    }

    public function findUnconfirmedByEmail(string $email): ?ConfirmationRequest
    {
        $query = $this->createQuery();
        $constraints = [
            $query->equals('email', $email),
            $query->equals('confirmationDate', null),
        ];
        $query->matching(
            $query->logicalAnd(...$constraints)
        );
        $query->setLimit(1);
        /** @var ConfirmationRequest|null $result */
        $result = $query->execute()->getFirst();
        return $result;
    }


}
