<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Validator;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validates that the submitted value matches the password of the currently
 * logged-in frontend user. Produces a field-level error so the form re-renders
 * with the error attached to the current-password input.
 */
class CurrentPasswordValidator extends AbstractValidator
{
    public function isValid($value): void
    {
        $context = GeneralUtility::makeInstance(Context::class);
        if (!(bool)$context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false)) {
            $this->addError(
                $this->translateErrorMessage('validator.currentPassword.notLoggedIn', 'fe_registration')
                    ?: 'You must be logged in to change your password.',
                1748256101
            );
            return;
        }

        $userId = (int)$context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($userId === 0 || !is_string($value) || $value === '') {
            $this->addError(
                $this->translateErrorMessage('validator.currentPassword.wrong', 'fe_registration')
                    ?: 'The current password is incorrect.',
                1748256102
            );
            return;
        }

        $userRow = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users')
            ->select(['password'], 'fe_users', ['uid' => $userId])
            ->fetchAssociative();

        if (!$userRow || !is_string($userRow['password']) || $userRow['password'] === '') {
            $this->addError(
                $this->translateErrorMessage('validator.currentPassword.wrong', 'fe_registration')
                    ?: 'The current password is incorrect.',
                1748256103
            );
            return;
        }

        try {
            $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)
                ->get($userRow['password'], 'FE');
        } catch (\Throwable) {
            $this->addError(
                $this->translateErrorMessage('validator.currentPassword.wrong', 'fe_registration')
                    ?: 'The current password is incorrect.',
                1748256104
            );
            return;
        }

        if (!$hashInstance->checkPassword($value, $userRow['password'])) {
            $this->addError(
                $this->translateErrorMessage('validator.currentPassword.wrong', 'fe_registration')
                    ?: 'The current password is incorrect.',
                1748256105
            );
        }
    }
}