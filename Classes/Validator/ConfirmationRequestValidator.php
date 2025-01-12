<?php

namespace WapplerSystems\FeRegistration\Validator;


use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validator for not empty values.
 *
 * @api
 */
class ConfirmationRequestValidator extends AbstractValidator
{

    protected $supportedOptions = [
        'pid' => [null, 'Storage page ID for confirmation request records', 'int'],
        'uriBuilder' => [null, 'uriBuilder', UriBuilder::class],
    ];


    /**
     * Checks if the given property ($propertyValue) is not empty (NULL, empty string, empty array or empty object).
     *
     * @param mixed $value The value that should be confirmed
     * @throws Exception
     */
    public function isValid($value): void
    {
        DebugUtility::debug($this->options);

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_feregistration_domain_model_confirmationrequest');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('uid', 'is_confirmed', 'confirmation_hash')
            ->from('tx_feregistration_domain_model_confirmationrequest')
            ->setMaxResults(1)
            ->where($queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($value)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter((int)$this->options['pid'])),
            )
            )->executeQuery()->fetchAssociative();

        if ($row !== false) {

            if ((int)$row['is_confirmed'] === 0) {

                /** @var UriBuilder $uriBuilder */
                $uriBuilder = $this->options['uriBuilder'];
                $link = $uriBuilder
                    ->reset()
                    ->setCreateAbsoluteUri(true)
                    ->setTargetPageUid($GLOBALS['TSFE']->id)
                    ->setArguments(['email' => $value])
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
