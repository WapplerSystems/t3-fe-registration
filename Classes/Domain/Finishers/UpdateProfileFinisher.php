<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Domain\Finishers;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use WapplerSystems\FeRegistration\Event\FeUserDatabaseDataEvent;

/**
 * Updates the profile of the currently logged-in frontend user from form values.
 *
 * Each form element whose definition contains a `mapOnDatabaseColumn` property is
 * persisted into the corresponding fe_users column. The current user's email and
 * the protected `password`/`username` columns are skipped by default to avoid
 * accidental hijacking through hand-crafted form payloads.
 */
class UpdateProfileFinisher extends AbstractFinisher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array<string, mixed>
     */
    protected $defaultOptions = [
        'protectedFields' => ['username', 'password', 'usergroup', 'pid', 'uid', 'deleted', 'disable'],
    ];

    public function __construct(
        private readonly Context $context,
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    protected function executeInternal(): void
    {
        if (!(bool)$this->context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false)) {
            throw new FinisherException('A logged-in frontend user is required.', 1748333001);
        }

        $userId = (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($userId === 0) {
            throw new FinisherException('Could not determine the frontend user id.', 1748333002);
        }

        $elements = $this->finisherContext->getFormRuntime()->getFormDefinition()->getRenderablesRecursively();
        $formValues = $this->finisherContext->getFormValues();
        $databaseData = ['tstamp' => time()];

        $protected = array_map('strval', (array)$this->parseOption('protectedFields'));

        foreach ($elements as $element) {
            if (!method_exists($element, 'getProperties')) {
                continue;
            }
            $identifier = $element->getIdentifier();
            $properties = $element->getProperties();
            $column = $properties['mapOnDatabaseColumn'] ?? null;
            if (!is_string($column) || $column === '') {
                continue;
            }
            if (in_array($column, $protected, true)) {
                continue;
            }
            if (!array_key_exists($identifier, $formValues)) {
                continue;
            }

            $value = $formValues[$identifier];
            if (is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            } elseif (is_bool($value)) {
                $value = $value ? 1 : 0;
            } elseif ($value === null) {
                $value = '';
            }
            $databaseData[$column] = $value;
        }

        $event = $this->eventDispatcher->dispatch(new FeUserDatabaseDataEvent($databaseData));
        $databaseData = $event->getDatabaseData();

        if (count($databaseData) <= 1) {
            return;
        }

        $this->connectionPool->getConnectionForTable('fe_users')->update(
            'fe_users',
            $databaseData,
            ['uid' => $userId]
        );

        $this->finisherContext->getFormRuntime()->getFormDefinition()->setRenderingOption('user', $databaseData + ['uid' => $userId]);
        $this->logger?->info('Frontend user profile updated', ['uid' => $userId, 'columns' => array_keys($databaseData)]);
    }
}