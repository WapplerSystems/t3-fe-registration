<?php

namespace WapplerSystems\FeRegistration\Validator;


use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Checks confirmationRequest records and fe_users for existing or completed records
 *
 * @api
 */
class UserAlreadyExistsValidator extends AbstractValidator
{

    protected $supportedOptions = [
        'confirmationRequestPid' => [null, 'Storage page ID for opt in records', 'int'],
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_feregistration_domain_model_confirmationrequest');
        $queryBuilder->getRestrictions()->removeAll();
        $now = time();
        $count = $queryBuilder
            ->select('uid')
            ->from('tx_feregistration_domain_model_confirmationrequest')
            ->where($queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($value)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter((int)$this->options['confirmationRequestPid'])),
                // Only consider rows that are actually blocking: either already
                // confirmed (user is mid-registration or done) or still inside
                // the open DOI window. Expired, abandoned, unconfirmed rows are
                // ignored so a user who walked away can register again without
                // waiting for the cleanup command to sweep the table.
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->gt('confirmation_date', 0),
                    $queryBuilder->expr()->eq('expires_at', 0),
                    $queryBuilder->expr()->gt('expires_at', $queryBuilder->createNamedParameter($now)),
                ),
            )
            )->executeQuery()->fetchOne();

        if ($count > 0) {
            $this->addError(
                $this->translateErrorMessage(
                    'validator.confirmationRequest.true',
                    'fe_registration'
                ),
                1591107223
            );
        }


    }
}
