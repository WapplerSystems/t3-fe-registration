<?php

namespace WapplerSystems\FeRegistration\Validator;


use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validator for not empty values.
 *
 * @api
 */
class FeUserValidator extends AbstractValidator
{

    protected $supportedOptions = [
        'pid' => [null, 'Storage page ID for fe_user records', 'int'],
    ];

    /**
     * Checks if the given property ($propertyValue) is not empty (NULL, empty string, empty array or empty object).
     *
     * @param mixed $value The value that should be validated
     * @throws Exception
     */
    public function isValid($value): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder->count('uid')
            ->from('fe_users')
            ->where($queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($value)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter((int)$this->options['pid'])),
            )
        )->executeQuery()->fetchOne();

        if ($count > 0) {
            $this->addError(
                $this->translateErrorMessage(
                    'validator.feUsernameAlreadyExists.true',
                    'fe_registration'
                ),
                1591107223
            );
        }
    }
}
