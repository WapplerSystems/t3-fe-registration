<?php

namespace WapplerSystems\FeRegistration\Validator;


use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;
use WapplerSystems\FeRegistration\Service\AddressLookupService;

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
        // The query lives in AddressLookupService so this validator and the finisher-side
        // guard in ConfirmationRequestFinisher cannot drift apart.
        $exists = GeneralUtility::makeInstance(AddressLookupService::class)
            ->hasFeUserWithUsername((string)$value, (int)$this->options['pid']);

        if ($exists) {
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
