<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Finishers\RedirectFinisher;
use WapplerSystems\FeRegistration\Event\BeforeFrontendUserDeletedEvent;

/**
 * Soft-deletes (and anonymizes) the currently logged-in frontend user, terminates
 * all of their sessions, and redirects to a configurable page.
 *
 * The email-confirmation gate is enforced by CurrentUserEmailValidator; this
 * finisher repeats the email check as defense-in-depth so a misconfigured form
 * can never wipe the wrong account.
 *
 * Redirect target lookup order:
 *   1. finisher option "pageUid"            (per-form override)
 *   2. site setting "feRegistration.deleteRedirectPageId"
 *   3. no redirect (renders the form's default response)
 */
class DeleteAccountFinisher extends RedirectFinisher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $defaultOptions = [
        'pageUid' => 0,
        'emailField' => 'email',
        'additionalParameters' => '',
        'statusCode' => 303,
        'fragment' => '',
        'siteSettingKey' => 'feRegistration.deleteRedirectPageId',
    ];

    public function __construct(
        private readonly Context $context,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    protected function executeInternal(): void
    {
        if (!(bool)$this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false)) {
            throw new FinisherException('A logged-in frontend user is required.', 1748256301);
        }
        $userId = (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($userId === 0) {
            throw new FinisherException('Could not determine the frontend user id.', 1748256302);
        }

        $emailField = (string)$this->parseOption('emailField');
        $submittedEmail = trim((string)($this->finisherContext->getFormValues()[$emailField] ?? ''));
        if ($submittedEmail === '') {
            throw new FinisherException('No email address submitted.', 1748256303);
        }

        $connection = $this->connectionPool->getConnectionForTable('fe_users');
        $userRow = $connection->select(
            ['uid', 'username', 'email', 'first_name', 'last_name'],
            'fe_users',
            ['uid' => $userId]
        )->fetchAssociative();

        if (!$userRow) {
            throw new FinisherException('Logged-in fe_user not found.', 1748256304);
        }
        if (strcasecmp($submittedEmail, (string)($userRow['email'] ?? '')) !== 0) {
            throw new FinisherException('Email confirmation does not match the logged-in account.', 1748256305);
        }

        $updateData = $this->buildAnonymizedUpdateData($userId);

        $event = new BeforeFrontendUserDeletedEvent($userRow, $updateData);
        $this->eventDispatcher->dispatch($event);
        $updateData = $event->getUpdateData();

        $connection->update('fe_users', $updateData, ['uid' => $userId]);
        $this->invalidateSessions($userId);

        $this->logger?->info('Frontend user account deleted (soft-delete + anonymize)', [
            'uid' => $userId,
            'username' => (string)($userRow['username'] ?? ''),
        ]);

        $pageUid = $this->resolveRedirectPageUid();
        if ($pageUid <= 0) {
            $this->finisherContext->cancel();
            return;
        }

        $additionalParameters = (string)$this->parseOption('additionalParameters');
        $additionalParameters = '&' . ltrim($additionalParameters, '&');
        $statusCode = (int)$this->parseOption('statusCode');
        $fragment = (string)$this->parseOption('fragment');

        $this->finisherContext->cancel();
        $this->redirect($pageUid, $additionalParameters, $fragment, $statusCode);
    }

    /**
     * Build the soft-delete + anonymization payload. Listeners can mutate this
     * via BeforeFrontendUserDeletedEvent (e.g. to clear extra fields).
     */
    protected function buildAnonymizedUpdateData(int $userId): array
    {
        $now = time();
        $placeholder = 'deleted-user-' . $userId;

        return [
            'deleted' => 1,
            'disable' => 1,
            'tstamp' => $now,
            'username' => $placeholder . '@example.invalid',
            'email' => '',
            'first_name' => '',
            'last_name' => '',
            'name' => '',
            'title' => '',
            'company' => '',
            'address' => '',
            'telephone' => '',
            'fax' => '',
            'www' => '',
            'image' => 0,
            // wipe the password hash so the (soft-deleted) row can never authenticate
            'password' => '',
        ];
    }

    protected function resolveRedirectPageUid(): int
    {
        $optionPageUid = (int)str_replace('pages_', '', (string)$this->parseOption('pageUid'));
        if ($optionPageUid > 0) {
            return $optionPageUid;
        }

        $site = $this->finisherContext->getRequest()->getAttribute('site');
        if ($site instanceof Site) {
            $settingKey = (string)$this->parseOption('siteSettingKey');
            $pageId = (int)$site->getSettings()->get($settingKey, 0);
            if ($pageId > 0) {
                return $pageId;
            }
        }

        return 0;
    }

    protected function invalidateSessions(int $userId): void
    {
        $sessionManager = GeneralUtility::makeInstance(SessionManager::class);
        $backend = $sessionManager->getSessionBackend('FE');
        $sessionManager->invalidateAllSessionsByUserId($backend, $userId);
    }
}