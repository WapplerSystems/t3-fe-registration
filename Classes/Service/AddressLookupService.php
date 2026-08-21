<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * "Is this email address already taken?" — the single source of truth.
 *
 * Deliberately free of constructor dependencies: the callers are two Extbase validators
 * and a form finisher, and validators are instantiated without dependency injection, so
 * anything requiring constructor arguments cannot be reached from there via
 * GeneralUtility::makeInstance().
 *
 * Kept separate from DatabaseService so the form validators and the enumeration guard in
 * ConfirmationRequestFinisher always ask the same question the same way.
 */
class AddressLookupService
{
    private const REQUEST_TABLE = 'tx_feregistration_domain_model_confirmationrequest';

    /**
     * Is there a confirmation request for this address that blocks a new one?
     *
     * Blocking means: already confirmed (the visitor is mid-registration or done), or still
     * inside the open double-opt-in window. Expired, abandoned rows are ignored so someone
     * who walked away can register again without waiting for the cleanup command.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function hasBlockingConfirmationRequest(string $email, int $confirmationRequestPid): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::REQUEST_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return (bool)$queryBuilder
            ->count('uid')
            ->from(self::REQUEST_TABLE)
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('email', $queryBuilder->createNamedParameter($email)),
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($confirmationRequestPid, ParameterType::INTEGER)),
                    $queryBuilder->expr()->or(
                        // Confirmed rows block regardless of any expiry: that address is
                        // mid-registration or done.
                        $queryBuilder->expr()->gt('confirmation_date', 0),
                        // Unconfirmed rows only block while their window is still open.
                        // `expires_at = 0` deliberately does NOT block: it used to mean
                        // "never expires", but ConfirmationRequest::isExpired() now treats
                        // a missing date as expired, and the two must agree — otherwise
                        // such a row would block new signups forever while its own link
                        // no longer works.
                        $queryBuilder->expr()->gt('expires_at', $queryBuilder->createNamedParameter(time(), ParameterType::INTEGER)),
                    ),
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Is there already an fe_user with this address as username?
     *
     * Deleted and disabled rows count — an address tied to a soft-deleted account must not
     * silently become available again.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function hasFeUserWithUsername(string $email, int $feUserStoragePid): bool
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('fe_users');
        $queryBuilder->getRestrictions()->removeAll();

        return (bool)$queryBuilder
            ->count('uid')
            ->from('fe_users')
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('username', $queryBuilder->createNamedParameter($email)),
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($feUserStoragePid, ParameterType::INTEGER)),
                )
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Combined check used by the finisher-side guard that replaced the form validators.
     *
     * Pass 0 for a storage pid to skip that half of the check.
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function isAddressBlocked(string $email, int $confirmationRequestPid, int $feUserStoragePid): bool
    {
        if ($confirmationRequestPid > 0 && $this->hasBlockingConfirmationRequest($email, $confirmationRequestPid)) {
            return true;
        }

        return $feUserStoragePid > 0 && $this->hasFeUserWithUsername($email, $feUserStoragePid);
    }
}
