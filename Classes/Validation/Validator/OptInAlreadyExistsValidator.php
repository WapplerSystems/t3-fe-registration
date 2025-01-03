<?php

namespace WapplerSystems\FeRegistration\Validation\Validator;


use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validator for not empty values.
 *
 * @api
 */
class OptInAlreadyExistsValidator extends AbstractValidator
{

    protected $supportedOptions = [
        'pid' => [null, 'Storage page ID for opt in records', 'int'],
        'resendUri' => [null, 'Ajax uri for resend', 'string'],
        'uriBuilder' => [null, 'uriBuilder', UriBuilder::class],
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
        $row = $queryBuilder
            ->select('uid', 'is_validated', 'validation_hash')
            ->from('tx_feregistration_domain_model_optin')
            ->where($queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($value)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter((int)$this->options['pid'])),
            )
        )->executeQuery()->fetchAssociative();

        if (count($row) > 0) {

            if ((int)$row['is_validated'] === 0) {

                /** @var UriBuilder $uriBuilder */
                $uriBuilder = $this->options['uriBuilder'];
                $link = $uriBuilder
                    ->reset()
                    ->setCreateAbsoluteUri(true)
                    ->setTargetPageUid($GLOBALS['TSFE']->id)
                    ->setArguments(['hash' => $row['validation_hash']])
                    ->setTargetPageType(1735853778)
                    ->buildFrontendUri();


                $this->addError(
                    $this->translateErrorMessage(
                        'validator.optInAlreadyExists.resend',
                        'fe_registration',
                        [$link]
                    ),
                    1735853778
                );
                return;
            }

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
