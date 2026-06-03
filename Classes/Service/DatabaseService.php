<?php

namespace WapplerSystems\FeRegistration\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\PseudoFileReference;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Domain\Repository\ConfirmationRequestRepository;

class DatabaseService
{

    public function __construct(readonly ConfirmationRequestRepository $confirmationRequestRepository)
    {
    }


    /**
     * Columns that must never be populated from user-submitted form values, so a
     * crafted form field whose identifier collides with a fe_users column can't
     * escalate privileges (e.g. by smuggling `usergroup`, `pid`, or `disable`).
     * The trusted defaults further down win regardless because they are written
     * after the user-controlled mapping.
     */
    private const PROTECTED_FE_USER_COLUMNS = [
        'uid', 'pid', 'tstamp', 'crdate',
        'deleted', 'disable', 'hidden',
        'starttime', 'endtime',
        'usergroup',
        'lockToDomain', 'lockToIP',
        'TSconfig',
        'is_online', 'lastlogin', 'last_login', 'failure',
        'felogin_forgotHash',
    ];

    public function createFeUser(array $values, array $settings): array
    {

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users');
        $schemaManager = $connection->createSchemaManager();
        $columns = array_keys($schemaManager->listTableColumns('fe_users'));

        $dbValues = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $columns, true)) {
                $column = $key;
            } elseif (in_array(GeneralUtility::camelCaseToLowerCaseUnderscored($key), $columns, true)) {
                $column = GeneralUtility::camelCaseToLowerCaseUnderscored($key);
            } else {
                continue;
            }
            if (in_array($column, self::PROTECTED_FE_USER_COLUMNS, true)) {
                continue;
            }
            $dbValues[$column] = $value;
        }

        // Trusted, system-controlled values — applied last so nothing in $values
        // can override them, even by colliding with a fe_users column name.
        $dbValues['pid'] = (int)$settings['feUserStoragePid'];
        $dbValues['usergroup'] = $settings['usergroups'] ?? '';
        $dbValues['username'] = $values[$settings['identifierFieldName']];
        $dbValues['crdate'] = time();
        $dbValues['tstamp'] = time();
        $dbValues['deleted'] = 0;
        // `disable` is set by ConfirmationService::requestToFeUser from the
        // `feUserMustConfirmed` site setting and is always overwritten before
        // it reaches this method, so reading it back here is trustworthy.
        $dbValues['disable'] = isset($values['disable']) && (int)$values['disable'] !== 0 ? 1 : 0;

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
        $restrictions = $queryBuilder->getRestrictions();
        $restrictions->removeByType(HiddenRestriction::class);
        $queryBuilder->setRestrictions($restrictions);
        return $queryBuilder
            ->select('*')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($feUserUid, ParameterType::INTEGER))
            )
            ->executeQuery()->fetchAssociative();
    }

    public function updateFeUserPassword(int $feUserUid, string $password): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $restrictions = $queryBuilder->getRestrictions();
        $restrictions->removeByType(HiddenRestriction::class);
        $queryBuilder->setRestrictions($restrictions);
        $queryBuilder
            ->update('fe_users')
            ->set('password', $password)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($feUserUid, ParameterType::INTEGER))
            )
            ->executeStatement();
    }


    public function updateFeUser(int $feUserUid, array $values): void
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(
            'fe_users'
        );
        $restrictions = $queryBuilder->getRestrictions();
        $restrictions->removeByType(HiddenRestriction::class);
        $queryBuilder->setRestrictions($restrictions);
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
                if ($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey]['config']['type'] === 'check' && count($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey]['config']['items'] ?? []) > 1) {
                    if (is_array($value)) {
                        $strBitSet = '';
                        foreach ($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey]['config']['items'] as $key => $item) {
                            if (($item['value'] ?? null) !== null) {
                                if (in_array($item['value'], $value, true)) {
                                    $strBitSet .= '1';
                                } else {
                                    $strBitSet .= '0';
                                }
                            }
                        }
                        $value = bindec(strrev($strBitSet));
                    } else {
                        $value = 0;
                    }
                }
                if ($GLOBALS['TCA']['fe_users']['columns'][$convertedFormFieldKey]['config']['type'] === 'file') {
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
                    } else {
                        $value = 0;
                    }
                }


                $dbValues[$convertedFormFieldKey] = $value;
            }

        }

        $queryBuilder
            ->update('fe_users');
        foreach ($dbValues as $key => $value) {
            $queryBuilder->set($key, $queryBuilder->createNamedParameter($value));
        }
        $queryBuilder
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($feUserUid, ParameterType::INTEGER))
            )
            ->executeStatement();

    }




}
