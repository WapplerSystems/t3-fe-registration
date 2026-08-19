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
use WapplerSystems\FeRegistration\Service\DatabaseService;

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

        // Map every form element that carries a `mapOnDatabaseColumn` property.
        // prepareData() existed but was never called, so $databaseData only ever
        // held what the finisher's own `mapping` option provided — and forms that
        // rely on mapOnDatabaseColumn (like Registration) left `username` unset,
        // which made the duplicate check below emit an "Undefined array key"
        // warning that TYPO3's error handler escalates into a 500.
        //
        // In this extension mapOnDatabaseColumn is authored as an element
        // *property* (see UpdateProfileFinisher and AfterFormStateInitializedHook),
        // not under the finisher's `elements` option, so the per-element config is
        // collected from the renderables. An explicit `elements.<identifier>`
        // finisher option still wins, which keeps the upstream syntax working.
        $databaseData = $this->prepareData($this->collectElementsConfiguration(), $databaseData);

        // This finisher writes to fe_users directly instead of going through
        // DatabaseService, so it has to apply the same protection: drop every
        // system-controlled column. Both sources feeding $databaseData are
        // backend-configurable — the finisher's `mapping` option and the
        // elements' mapOnDatabaseColumn properties — and FeUsersDatabaseField
        // only checks that a mapping target is a real fe_users column, not that
        // it is safe. Without this filter an editor with form permissions could
        // map a field onto `usergroup`, `disable` or `pid` and grant frontend
        // privileges from a public form.
        foreach (DatabaseService::PROTECTED_FE_USER_COLUMNS as $protectedColumn) {
            unset($databaseData[$protectedColumn]);
        }

        // pid is system-controlled but legitimately needed here, so restore the
        // trusted value from the finisher option after the filter.
        $databaseData['pid'] = $this->parseOption('pid');
        $databaseData['tstamp'] = time();

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

            // Without a username there is nothing usable to insert: the row could
            // never serve as a login, and the duplicate check below would be
            // meaningless. This is the normal situation in a double-opt-in flow —
            // after confirmation the form only carries a pseudo placeholder field,
            // so the registration payload lives on the ConfirmationRequest and is
            // written by CompleteRegistrationFinisher instead. Bail out quietly
            // rather than inserting an empty fe_users row (which is what this
            // finisher used to do on every registration) or throwing, which would
            // surface as a 500 at the very end of an otherwise successful signup.
            if (($databaseData['username'] ?? '') === '') {
                $this->logger?->info(
                    'FeUser finisher skipped: no username could be mapped from the submitted form values.',
                    ['form' => $this->finisherContext->getFormRuntime()->getFormDefinition()->getIdentifier()]
                );
                return;
            }

            // check if user with same username or email already exists
            $existingUser = $this->databaseConnection->select(
                ['uid'],
                $table,
                [
                    'username' => $databaseData['username'],
                ]
            )->fetchAssociative();
            if ($existingUser) {
                // user already exists → update by the existing uid instead of inserting
                $databaseData['uid'] = (int)$existingUser['uid'];
                $this->databaseConnection->update(
                    $table,
                    $databaseData,
                    ['uid' => $databaseData['uid']]
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
     * Build the per-element configuration prepareData() consumes.
     *
     * Element properties (mapOnDatabaseColumn, hashPassword, dateFormat,
     * skipIfValueIsEmpty, …) form the base; anything set explicitly under the
     * finisher's `elements` option overrides it.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function collectElementsConfiguration(): array
    {
        $fromOption = (array)$this->parseOption('elements');
        $configuration = [];

        foreach ($this->finisherContext->getFormRuntime()->getFormDefinition()->getRenderablesRecursively() as $element) {
            if (!method_exists($element, 'getProperties')) {
                continue;
            }
            $properties = $element->getProperties();
            if (!isset($properties['mapOnDatabaseColumn'])) {
                continue;
            }
            $configuration[$element->getIdentifier()] = $properties;
        }

        foreach ($fromOption as $identifier => $elementConfiguration) {
            if (!is_array($elementConfiguration)) {
                continue;
            }
            $configuration[$identifier] = array_merge($configuration[$identifier] ?? [], $elementConfiguration);
        }

        return $configuration;
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
