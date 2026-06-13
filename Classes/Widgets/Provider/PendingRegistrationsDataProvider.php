<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Pending double-opt-in registrations.
 *
 * Pulls tx_feregistration_domain_model_confirmationrequest rows that have
 * NOT been completed yet (completion_date = 0) AND have not expired
 * (expires_at = 0 OR expires_at > now). Sort key is crdate, newest first —
 * surfaces the requests that an admin might still want to chase down or
 * resend an email for.
 */
readonly class PendingRegistrationsDataProvider implements FeRegistrationListDataProviderInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getItems(int $limit = 10): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable(
            'tx_feregistration_domain_model_confirmationrequest'
        );
        $now = time();
        $rows = $qb
            ->select('uid', 'email', 'encoded_values', 'crdate')
            ->from('tx_feregistration_domain_model_confirmationrequest')
            ->where(
                $qb->expr()->eq(
                    'completion_date',
                    $qb->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)
                ),
                $qb->expr()->or(
                    $qb->expr()->eq(
                        'expires_at',
                        $qb->createNamedParameter(0, \Doctrine\DBAL\ParameterType::INTEGER)
                    ),
                    $qb->expr()->gt(
                        'expires_at',
                        $qb->createNamedParameter($now, \Doctrine\DBAL\ParameterType::INTEGER)
                    ),
                ),
            )
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->executeQuery()
            ->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            // encoded_values is a JSON blob of the original form payload;
            // pull first_name/last_name/company from it for the widget but
            // tolerate any shape (older rows, custom forms) by falling back
            // to empty strings.
            $decoded = is_string($row['encoded_values'])
                ? (json_decode($row['encoded_values'], true) ?: [])
                : [];
            $firstName = (string)($decoded['firstName'] ?? $decoded['first_name'] ?? '');
            $lastName = (string)($decoded['lastName'] ?? $decoded['last_name'] ?? '');
            $company = (string)($decoded['company'] ?? '');
            $items[] = [
                'uid' => (int)$row['uid'],
                'editTable' => 'tx_feregistration_domain_model_confirmationrequest',
                'email' => (string)$row['email'],
                'name' => trim($firstName . ' ' . $lastName),
                'company' => $company,
                'timestamp' => (int)$row['crdate'],
            ];
        }
        return $items;
    }
}