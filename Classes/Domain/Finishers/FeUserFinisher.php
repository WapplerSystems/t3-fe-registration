<?php
namespace WapplerSystems\FeRegistration\Domain\Finishers;


use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Event\FeUserDatabaseDataEvent;

/**
 * Finisher to save form values to fe_users table
 */
class FeUserFinisher extends \TYPO3\CMS\Form\Domain\Finishers\SaveToDatabaseFinisher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    protected $defaultOptions = [
        'whereClause' => [],
        'mapping' => [],
        'pid' => null,
        'dataProcessors' => []
    ];


    /**
     * Executes this finisher
     * @see AbstractFinisher::execute()
     *
     * @throws FinisherException
     */
    protected function executeInternal(): void
    {
        $this->process(0);
    }

    /**
     * Perform the current database operation
     *
     * @param int $iterationCount
     */
    protected function process(int $iterationCount): void
    {
        $this->throwExceptionOnInconsistentConfiguration();

        $table = 'fe_users';
        $mappingValues = $this->parseOption('mapping');


        $this->databaseConnection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);

        $databaseData = [];

        $databaseData['pid'] = $this->parseOption('pid');
        $databaseData['tstamp'] = time();


        $databaseData = array_merge($databaseData, $mappingValues);

        $eventDispatcher = GeneralUtility::makeInstance(EventDispatcher::class);
        $event = new FeUserDatabaseDataEvent($databaseData);
        $event = $eventDispatcher->dispatch($event);
        $databaseData = $event->getDatabaseData();

        if ($databaseData['uid'] ?? false) {
            // Update existing record
            $whereClause = ['uid' => (int)$databaseData['uid']];

            $this->databaseConnection->update(
                $table,
                $databaseData,
                $whereClause
            );

        } else {
            // Insert new record

            // check if user with same username or email already exists
            $existingUser = $this->databaseConnection->select(
                ['uid'],
                $table,
                [
                    'username' => $databaseData['username'],
                ]
            )->fetchAssociative();
            if ($existingUser) {
                // user already exists, do not insert
                $whereClause = ['uid' => (int)$databaseData['uid']];
                $this->databaseConnection->update(
                    $table,
                    $databaseData,
                    $whereClause
                );

                $this->finisherContext->getFormRuntime()->getFormDefinition()->setRenderingOption('user', $databaseData);
                return;
            }

            $databaseData['crdate'] = time();
            try {
                $this->databaseConnection->insert($table, $databaseData);
                $databaseData['uid'] = (int)$this->databaseConnection->lastInsertId($table);
            } catch (\Exception $e) {
                $this->logger?->error('Failed to create fe_user', [
                    'username' => $databaseData['username'] ?? '',
                    'exception' => $e->getMessage(),
                ]);
                throw $e;
            }

        }

        $this->finisherContext->getFormRuntime()->getFormDefinition()->setRenderingOption('user', $databaseData);
    }


    /**
     * Prepare data for saving to database
     *
     * @param array $elementsConfiguration
     * @param array $databaseData
     * @return mixed
     */
    protected function prepareData(array $elementsConfiguration, array $databaseData): array
    {
        foreach ($this->getFormValues() as $elementIdentifier => $elementValue) {
            if (
                ($elementValue === null || $elementValue === '')
                && isset($elementsConfiguration[$elementIdentifier])
                && isset($elementsConfiguration[$elementIdentifier]['skipIfValueIsEmpty'])
                && $elementsConfiguration[$elementIdentifier]['skipIfValueIsEmpty'] === true
            ) {
                continue;
            }
            if (
                ($elementValue === null || $elementValue === '')
                && isset($elementsConfiguration[$elementIdentifier])
                && isset($elementsConfiguration[$elementIdentifier]['valueIfValueIsEmpty'])
            ) {
                $elementValue = $elementsConfiguration[$elementIdentifier]['valueIfValueIsEmpty'];
            }

            if (
                !isset($elementsConfiguration[$elementIdentifier])
                || !isset($elementsConfiguration[$elementIdentifier]['mapOnDatabaseColumn'])
            ) {
                continue;
            }

            if ($elementValue instanceof FileReference) {
                if (isset($elementsConfiguration[$elementIdentifier]['saveFileIdentifierInsteadOfUid'])) {
                    $saveFileIdentifierInsteadOfUid = (bool)$elementsConfiguration[$elementIdentifier]['saveFileIdentifierInsteadOfUid'];
                } else {
                    $saveFileIdentifierInsteadOfUid = false;
                }

                if ($saveFileIdentifierInsteadOfUid) {
                    $elementValue = $elementValue->getOriginalResource()->getCombinedIdentifier();
                } else {
                    $elementValue = $elementValue->getOriginalResource()->getProperty('uid_local');
                }
            } elseif (is_array($elementValue)) {
                $elementValue = implode(',', $elementValue);
            } elseif ($elementValue instanceof \DateTimeInterface) {
                $format = $elementsConfiguration[$elementIdentifier]['dateFormat'] ?? 'U';
                $elementValue = $elementValue->format($format);
            } elseif (isset($elementsConfiguration[$elementIdentifier]['hashPassword']) && $elementsConfiguration[$elementIdentifier]['hashPassword'] === true) {
                $hashInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');
                $elementValue = $hashInstance->getHashedPassword($elementValue);
            }

            $fields = explode(',',$elementsConfiguration[$elementIdentifier]['mapOnDatabaseColumn']);
            foreach ($fields as $field) {
                $databaseData[$field] = $elementValue;
            }
        }
        return $databaseData;
    }

    /**
     * Throws an exception if some inconsistent configuration
     * are detected.
     *
     * @throws FinisherException
     */
    protected function throwExceptionOnInconsistentConfiguration(): void
    {
        parent::throwExceptionOnInconsistentConfiguration();

        if (
            $this->options['pid'] === null
        ) {
            throw new FinisherException(
                'An empty option "pid" is not allowed.',
                1595979076
            );
        }
    }


}
