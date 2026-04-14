<?php

namespace WapplerSystems\FeRegistration\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 */
class EmailAddressRepository extends Repository
{

    public function createQuery()
    {
        $query = parent::createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query;
    }

    public function findByTablenameAndUidForeignAndFieldname(string $tablename, int $foreignUid, string $fieldname) : QueryResultInterface
    {
        $query = $this->createQuery();
        return $query->matching(
            $query->logicalAnd(
                $query->equals('tablename', $tablename),
                $query->equals('uid_foreign', $foreignUid),
                $query->equals('fieldname', $fieldname)
            )
        )->execute();
    }


}
