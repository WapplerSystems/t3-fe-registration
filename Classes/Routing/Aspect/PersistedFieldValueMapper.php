<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Routing\Aspect;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Routing\Aspect\PersistedMappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Maps a route segment to/from a record field value (not the record uid),
 * so non-numeric identifiers like UUIDs can be used directly in URLs
 * without triggering cHash. The value is passed through unchanged when
 * it exists in the configured column.
 */
final class PersistedFieldValueMapper implements PersistedMappableAspectInterface, StaticMappableAspectInterface
{
    private readonly string $tableName;
    private readonly string $routeFieldName;

    public function __construct(array $settings)
    {
        $tableName = $settings['tableName'] ?? null;
        $routeFieldName = $settings['routeFieldName'] ?? null;

        if (!is_string($tableName) || $tableName === '') {
            throw new \InvalidArgumentException('tableName must be a non-empty string', 1747350002);
        }
        if (!is_string($routeFieldName) || $routeFieldName === '') {
            throw new \InvalidArgumentException('routeFieldName must be a non-empty string', 1747350003);
        }

        $this->tableName = $tableName;
        $this->routeFieldName = $routeFieldName;
    }

    public function generate(string $value): ?string
    {
        return $this->exists($value) ? $value : null;
    }

    public function resolve(string $value): ?string
    {
        return $this->exists($value) ? $value : null;
    }

    private function exists(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->tableName);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder
            ->count('uid')
            ->from($this->tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    $this->routeFieldName,
                    $queryBuilder->createNamedParameter($value)
                )
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }
}