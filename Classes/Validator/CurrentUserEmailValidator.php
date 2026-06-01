<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Validator;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validates that the submitted email address matches the email of the
 * currently logged-in frontend user (case-insensitive). Used as
 * confirmation gate before destructive actions like account deletion.
 */
class CurrentUserEmailValidator extends AbstractValidator
{
    public function isValid($value): void
    {
        $context = GeneralUtility::makeInstance(Context::class);
        if (!(bool)$context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false)) {
            $this->addError(
                $this->translateErrorMessage('validator.currentUserEmail.notLoggedIn', 'fe_registration')
                    ?: 'You must be logged in.',
                1748256201
            );
            return;
        }

        $userId = (int)$context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($userId === 0 || !is_string($value) || trim($value) === '') {
            $this->addError(
                $this->translateErrorMessage('validator.currentUserEmail.mismatch', 'fe_registration')
                    ?: 'The email address does not match your account.',
                1748256202
            );
            return;
        }

        $userRow = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users')
            ->select(['email'], 'fe_users', ['uid' => $userId])
            ->fetchAssociative();

        $userEmail = is_array($userRow) ? (string)($userRow['email'] ?? '') : '';

        if ($userEmail === '' || strcasecmp(trim($value), trim($userEmail)) !== 0) {
            $this->addError(
                $this->translateErrorMessage('validator.currentUserEmail.mismatch', 'fe_registration')
                    ?: 'The email address does not match your account.',
                1748256203
            );
        }
    }
}