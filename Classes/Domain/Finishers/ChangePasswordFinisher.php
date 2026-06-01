<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\PasswordPolicy\PasswordPolicyAction;
use TYPO3\CMS\Core\PasswordPolicy\PasswordPolicyValidator;
use TYPO3\CMS\Core\PasswordPolicy\Validator\Dto\ContextData;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\FrontendLogin\Event\PasswordChangeEvent;

/**
 * Changes the password of the currently logged-in frontend user.
 *
 * The current password is expected to be pre-validated by CurrentPasswordValidator;
 * this finisher re-checks it as defense-in-depth, validates the new password against
 * the configured FE password policy, hashes & writes it, invalidates all sessions of
 * the user, and dispatches felogin's PasswordChangeEvent so existing listeners (audit
 * logs etc.) keep working.
 */
class ChangePasswordFinisher extends AbstractFinisher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $defaultOptions = [
        'currentPasswordField' => 'currentPassword',
        'newPasswordField' => 'newPassword',
        'invalidateSessions' => true,
    ];

    public function __construct(
        private readonly Context $context,
        private readonly PasswordHashFactory $passwordHashFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    protected function executeInternal(): void
    {
        if (!(bool)$this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false)) {
            throw new FinisherException('A logged-in frontend user is required.', 1748256001);
        }

        $userId = (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($userId === 0) {
            throw new FinisherException('Could not determine the frontend user id.', 1748256002);
        }

        $currentPasswordField = (string)$this->parseOption('currentPasswordField');
        $newPasswordField = (string)$this->parseOption('newPasswordField');

        $formValues = $this->finisherContext->getFormValues();
        $currentPassword = (string)($formValues[$currentPasswordField] ?? '');
        $newPasswordRaw = $formValues[$newPasswordField] ?? '';
        // AdvancedPassword posts [password, confirmation]; the form runtime already
        // validates equality so element [0] is authoritative.
        $newPassword = is_array($newPasswordRaw)
            ? (string)($newPasswordRaw[0] ?? '')
            : (string)$newPasswordRaw;

        if ($currentPassword === '' || $newPassword === '') {
            throw new FinisherException('Empty password value received.', 1748256003);
        }

        $connection = $this->connectionPool->getConnectionForTable('fe_users');
        $userRow = $connection->select(
            ['uid', 'username', 'password', 'first_name', 'last_name', 'email'],
            'fe_users',
            ['uid' => $userId]
        )->fetchAssociative();

        if (!$userRow) {
            throw new FinisherException('Logged-in fe_user not found.', 1748256004);
        }

        try {
            $currentHashInstance = $this->passwordHashFactory->get((string)$userRow['password'], 'FE');
        } catch (\Throwable) {
            throw new FinisherException('Current password is incorrect.', 1748256005);
        }
        if (!$currentHashInstance->checkPassword($currentPassword, (string)$userRow['password'])) {
            throw new FinisherException('Current password is incorrect.', 1748256006);
        }

        $this->validateAgainstPasswordPolicy($newPassword, $userRow);

        $hashedPassword = $this->passwordHashFactory->getDefaultHashInstance('FE')->getHashedPassword($newPassword);

        $this->eventDispatcher->dispatch(
            new PasswordChangeEvent($userRow, $hashedPassword, $newPassword, $this->finisherContext->getRequest())
        );

        $connection->update(
            'fe_users',
            ['password' => $hashedPassword, 'tstamp' => time()],
            ['uid' => $userId]
        );

        if ((bool)$this->parseOption('invalidateSessions')) {
            $this->invalidateSessions($userId);
        }

        $this->logger?->info('Frontend user password changed', ['uid' => $userId]);
    }

    protected function validateAgainstPasswordPolicy(string $newPassword, array $userRow): void
    {
        $passwordPolicy = $GLOBALS['TYPO3_CONF_VARS']['FE']['passwordPolicy'] ?? 'default';
        $validator = GeneralUtility::makeInstance(
            PasswordPolicyValidator::class,
            PasswordPolicyAction::UPDATE_USER_PASSWORD,
            is_string($passwordPolicy) ? $passwordPolicy : ''
        );

        $contextData = new ContextData(
            loginMode: 'FE',
            currentPasswordHash: (string)$userRow['password']
        );
        $contextData->setData('currentUsername', (string)$userRow['username']);
        $contextData->setData('currentFirstname', (string)($userRow['first_name'] ?? ''));
        $contextData->setData('currentLastname', (string)($userRow['last_name'] ?? ''));

        if ($validator->isValidPassword($newPassword, $contextData)) {
            return;
        }

        throw new FinisherException(
            'Password policy violation: ' . implode(' ', $validator->getValidationErrors()),
            1748256007
        );
    }

    protected function invalidateSessions(int $userId): void
    {
        $sessionManager = GeneralUtility::makeInstance(SessionManager::class);
        $backend = $sessionManager->getSessionBackend('FE');
        $sessionManager->invalidateAllSessionsByUserId($backend, $userId);
    }
}