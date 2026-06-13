<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Widgets\Provider;

use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Latest frontend user registrations.
 *
 * Pulls fe_users sorted by crdate desc — the moment the account was
 * actually created (after a successful DOI flow). Deleted/hidden rows
 * are excluded by TYPO3's default restrictions on the QueryBuilder.
 */
readonly class LatestRegistrationsDataProvider implements FeRegistrationListDataProviderInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    public function getItems(int $limit = 10): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $rows = $qb
            ->select('uid', 'email', 'first_name', 'last_name', 'company', 'crdate')
            ->from('fe_users')
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->executeQuery()
            ->fetchAllAssociative();

        $items = [];
        foreach ($rows as $row) {
            $name = trim(((string)$row['first_name']) . ' ' . ((string)$row['last_name']));
            $items[] = [
                'uid' => (int)$row['uid'],
                'editTable' => 'fe_users',
                'email' => (string)$row['email'],
                'name' => $name,
                'company' => (string)$row['company'],
                'timestamp' => (int)$row['crdate'],
            ];
        }
        return $items;
    }
}