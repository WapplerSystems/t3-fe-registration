<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\EventListener;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\ConnectionPool;
use WapplerSystems\FeRegistration\Event\BeforeFrontendUserDeletedEvent;

/**
 * Hard-deletes the tx_feregistration_domain_model_confirmationrequest row
 * linked to a frontend user that is being soft-deleted, so the JSON-encoded
 * original form payload in `encoded_values` (address, telephone, hashed
 * password, …) does not survive the account deletion. GDPR counterpart to
 * DeleteAccountFinisher.
 */
final class PurgeConfirmationRequestOnUserDeletion implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const TABLE = 'tx_feregistration_domain_model_confirmationrequest';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    #[AsEventListener('fe-registration/purge-confirmation-request-on-user-deletion')]
    public function __invoke(BeforeFrontendUserDeletedEvent $event): void
    {
        $requestUid = (int)($event->getUserRow()['registration_request'] ?? 0);
        if ($requestUid <= 0) {
            return;
        }

        $affected = $this->connectionPool
            ->getConnectionForTable(self::TABLE)
            ->delete(self::TABLE, ['uid' => $requestUid]);

        $this->logger?->info('Purged ConfirmationRequest on fe_user deletion', [
            'fe_user_uid' => $event->getUserId(),
            'confirmation_request_uid' => $requestUid,
            'affected_rows' => $affected,
        ]);
    }
}