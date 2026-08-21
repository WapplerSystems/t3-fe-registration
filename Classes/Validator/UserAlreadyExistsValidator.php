<?php

namespace WapplerSystems\FeRegistration\Validator;


use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;
use WapplerSystems\FeRegistration\Service\AddressLookupService;

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

        // The query lives in AddressLookupService so this validator and the finisher-side
        // guard in ConfirmationRequestFinisher cannot drift apart. It only counts rows
        // that actually block: already confirmed, or still inside the open DOI window.
        $exists = GeneralUtility::makeInstance(AddressLookupService::class)
            ->hasBlockingConfirmationRequest((string)$value, (int)$this->options['confirmationRequestPid']);

        if ($exists) {
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
