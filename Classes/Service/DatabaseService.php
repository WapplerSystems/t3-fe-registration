<?php

namespace WapplerSystems\FeRegistration\Service;

use Doctrine\DBAL\ParameterType;
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

class DatabaseService
{


    public function createFeUser(array $values, array $settings): int
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
            'username' => $values[$settings['identifierFieldName']],
            'crdate' => time(),
            'tstamp' => time(),
            'deleted' => 0,
            'disable' => 0,
        ];

        foreach ($values as $key => $value) {
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

        return (int)$connection->lastInsertId();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findFeUserByConfirmationRequest(ConfirmationRequest $confirmationRequest): array|false
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $return = $queryBuilder
            ->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('registration_request', $queryBuilder->createNamedParameter($confirmationRequest->getUid(), ParameterType::INTEGER))
            )
            ->executeQuery()->fetchAssociative();
        return $return;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFeUser(int $feUserUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $return = $queryBuilder
            ->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($feUserUid, ParameterType::INTEGER))
            )
            ->executeQuery()->fetchAssociative();
        return $return;
    }

    public function updateFeUserPassword(mixed $feUserUid, string $password): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $queryBuilder
            ->update('fe_users')
            ->set('password', $password)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($feUserUid, ParameterType::INTEGER))
            )
            ->executeStatement();
    }

    public function updateFeUser(mixed $feUserUid, array $values)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users');
        $schemaManager = $connection->createSchemaManager();
        $columns = array_keys($schemaManager->listTableColumns('fe_users'));

        $dbValues = [
        ];

        foreach ($values as $key => $value) {
            if (in_array($key, $columns, true)) {
                $dbValues[$key] = $value;
            }
            if (in_array(GeneralUtility::camelCaseToLowerCaseUnderscored($key), $columns, true)) {
                $dbValues[GeneralUtility::camelCaseToLowerCaseUnderscored($key)] = $value;
            }
        }

        $queryBuilder
            ->update('fe_users');
        foreach ($dbValues as $key => $value) {
            $queryBuilder->set($key, $value);
        }
        $queryBuilder
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($feUserUid, ParameterType::INTEGER))
            )
            ->executeStatement();

    }


}
