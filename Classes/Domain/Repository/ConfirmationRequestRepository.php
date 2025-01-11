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


}
