<?php

namespace WapplerSystems\FeRegistration\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 */
class ConfirmationRequestRepository extends Repository
{

    public function createQuery()
    {
        $query = parent::createQuery();
        $query->getQuerySettings()->setRespectStoragePage(FALSE);
        return $query;
    }

    public function findUnconfirmedByEmail(mixed $email)
    {
        $query = $this->createQuery();
        $constraints = [
            $query->equals('email', $email),
            $query->equals('confirmationDate', null),
        ];
        $query->matching(
            $query->logicalAnd(...$constraints)
        );
        return $query->execute();
    }


}
