<?php

namespace WapplerSystems\FeRegistration\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\PseudoFileReference;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;

class ContentElementService
{

    public function __construct(readonly ConnectionPool $connectionPool)
    {
    }


    public function findFeRegistrationPlugin(int $currentPageId, int $currentLanguageUid): ?array
    {

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');

        // Inhaltselement mit CType 'feregistration' und aktueller Sprache suchen
        return $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($currentPageId, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('feregistration_registration')),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($currentLanguageUid, ParameterType::INTEGER))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

    }


}
