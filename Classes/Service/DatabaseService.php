<?php

namespace WapplerSystems\FeRegistration\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Type\BitSet;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\PseudoFileReference;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;

class DatabaseService
{


    public function createFeUser(array $values, array $settings): array
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

        $dbValues['uid'] = $connection->lastInsertId();

        return $dbValues;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findFeUserByConfirmationRequest(ConfirmationRequest $confirmationRequest): array|false
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $restrictions = $queryBuilder->getRestrictions();
        $restrictions->removeByType(HiddenRestriction::class);
        $queryBuilder->setRestrictions($restrictions);
        return $queryBuilder
            ->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('registration_request', $queryBuilder->createNamedParameter($confirmationRequest->getUid(), ParameterType::INTEGER))
            )
            ->executeQuery()->fetchAssociative();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getFeUser(int $feUserUid) : array|false
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

    public function setRegistrationCompletedOfUser($feUserUid) {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $queryBuilder
            ->update('fe_users')
            ->set('registration_completed', 1)
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

        $feUser = $this->getFeUser($feUserUid);

        $dbValues = [
        ];

        foreach ($values as $formFieldKey => $value) {
            $formFieldKey = str_replace('-', '_', $formFieldKey);
            $convertedFormFieldKey = null;
            if (in_array($formFieldKey, $columns, true)) {
                $convertedFormFieldKey = $formFieldKey;
            }
            if (in_array(GeneralUtility::camelCaseToLowerCaseUnderscored($formFieldKey), $columns, true)) {
                $convertedFormFieldKey = GeneralUtility::camelCaseToLowerCaseUnderscored($formFieldKey);
            }


            if (isset($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey])) {

                // check if field is bitmask field
                if ($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey]['config']['type'] === 'check' && count($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey]['config']['items']) > 1) {
                    $value = 0;
                    if (is_array($value)) {
                        $bitSet = new BitSet();
                        foreach ($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey]['config']['items'] as $key => $item) {
                            if (($item['value'] ?? null) !== null) {
                                if (in_array($item['value'],$value)) {
                                    $bitSet->set($key+1);
                                }
                            }
                        }
                        $value = $bitSet->__toInt();
                    }
                }
                if ($value instanceof PseudoFileReference) {
                    $pseudoFileReference = $value;
                    $queryBuilderFileReference = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
                        'sys_file_reference'
                    );
                    $queryBuilderFileReference
                        ->insert('sys_file_reference')
                        ->values([
                            'uid_local' => $pseudoFileReference->getOriginalResource()->getOriginalFile()->getUid(),
                            'uid_foreign' => $feUserUid,
                            'tablenames' => 'fe_users',
                            'fieldname' => $convertedFormFieldKey,
                            'crdate' => time(),
                            'tstamp' => time(),
                            'pid' => $feUser['pid'],
                        ])
                        ->executeStatement();

                    $value = 1;
                }

                $dbValues[$convertedFormFieldKey] = $value;
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
