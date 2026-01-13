<?php

namespace WapplerSystems\FeRegistration\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 */
class FrontendUserRepository extends Repository
{

    public function createQuery()
    {
        $query = parent::createQuery();
        $query->getQuerySettings()->setRespectStoragePage(FALSE);
        return $query;
    }



}
