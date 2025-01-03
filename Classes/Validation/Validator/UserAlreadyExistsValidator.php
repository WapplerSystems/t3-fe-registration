<?php

namespace WapplerSystems\FeRegistration\Validation\Validator;


use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Checks optin records and fe_users for existing or completed records
 *
 * @api
 */
class UserAlreadyExistsValidator extends AbstractValidator
{

    protected $supportedOptions = [
        'optInPid' => [null, 'Storage page ID for opt in records', 'int'],
        'feUsersPid' => [null, 'Storage page ID for fe_users records', 'int'],
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_feregistration_domain_model_optin');
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder
            ->select('uid')
            ->from('tx_feregistration_domain_model_optin')
            ->where($queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($value)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter((int)$this->options['optInPid'])),
            )
        )->executeQuery()->fetchOne();

        if ($count > 0) {
            $this->addError(
                $this->translateErrorMessage(
                    'validator.optInAlreadyExists.true',
                    'fe_registration'
                ),
                1591107223
            );
        }



    }
}
