<?php

declare(strict_types=1);


namespace WapplerSystems\FeRegistration\Evaluation;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 *
 * @internal
 */
class FeUsersDatabaseField
{
    /**
     *
     * @param string $value The field value to be evaluated
     * @return string Evaluated field value
     */
    public function evaluateFieldValue(string $value): string
    {
        // Hole die Connection zur Datenbank
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users');
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('fe_users');
        $columnNames = array_map(function ($column) {
            return $column->getName();
        }, $columns);

        if (in_array($value, $columnNames, true)) {
            return $value;
        }
        return '';
    }
}
