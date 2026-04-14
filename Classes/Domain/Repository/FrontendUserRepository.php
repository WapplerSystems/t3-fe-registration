<?php

namespace WapplerSystems\FeRegistration\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Model\FrontendUser;

/**
 */
class FrontendUserRepository extends Repository
{

    /**
     * @todo Replace with event dispatcher approach
     */
    public function findByConfirmationRequest(ConfirmationRequest $confirmationRequest): ?FrontendUser
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('email', $confirmationRequest->getEmail())
        );
        $query->setLimit(1);
        /** @var FrontendUser|null $result */
        $result = $query->execute()->getFirst();
        return $result;
    }

    public function createQuery()
    {
        $query = parent::createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query;
    }



}
