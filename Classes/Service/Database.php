<?php

namespace WapplerSystems\FeRegistration\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;

class Database
{


    public function createFeUser(array $formValues, array $settings): void
    {


        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users');
        $schemaManager = $connection->createSchemaManager();
        $columns = array_keys($schemaManager->listTableColumns('fe_users'));

        $dbValues = [
            'pid' => (int)$settings['feUserStoragePid'],
            'usergroup' => $settings['usergroups'] ?? '',
            'username' => $formValues[$settings['identifierFieldName']],
            'crdate' => time(),
            'tstamp' => time(),
            'deleted' => 0,
            'disable' => 0,
        ];

        foreach ($formValues as $key => $value) {
            if (in_array($key, $columns, true)) {
                $dbValues[$key] = $value;
            }
            if (in_array(GeneralUtility::camelCaseToLowerCaseUnderscored($key), $columns, true)) {
                $dbValues[GeneralUtility::camelCaseToLowerCaseUnderscored($key)] = $value;
            }
        }


        $queryBuilder
            ->insert('fe_users')
            ->values($dbValues)
            ->executeStatement();


    }


}
