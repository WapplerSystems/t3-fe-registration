<?php

declare(strict_types=1);

namespace WapplerSystems\FeRegistration\Hooks;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime\Lifecycle\AfterFormStateInitializedInterface;

/**
 * Prefills the values of forms that opt-in via `renderingOptions.prefillFromFeUser: true`
 * with the data of the currently logged-in frontend user. Only the initial render is
 * affected — once the user has submitted the form, the submitted values are kept.
 */
class AfterFormStateInitializedHook implements AfterFormStateInitializedInterface
{
    public function afterFormStateInitialized(FormRuntime $formRuntime): void
    {
        $renderingOptions = $formRuntime->getFormDefinition()->getRenderingOptions();
        if (empty($renderingOptions['prefillFromFeUser'])) {
            return;
        }

        $formState = $formRuntime->getFormState();
        if ($formState === null || $formState->getLastDisplayedPageIndex() >= 0) {
            // form has already been submitted at least once — keep user input
            return;
        }

        $context = GeneralUtility::makeInstance(Context::class);
        if (!(bool)$context->getPropertyFromAspect('frontend.user', 'isLoggedIn', false)) {
            return;
        }
        $userId = (int)$context->getPropertyFromAspect('frontend.user', 'id', 0);
        if ($userId === 0) {
            return;
        }

        $userRow = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users')
            ->select(['*'], 'fe_users', ['uid' => $userId])
            ->fetchAssociative();
        if (!is_array($userRow)) {
            return;
        }

        foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $element) {
            if (!method_exists($element, 'getProperties')) {
                continue;
            }
            $properties = $element->getProperties();
            $column = $properties['mapOnDatabaseColumn'] ?? null;
            if (!is_string($column) || $column === '' || !array_key_exists($column, $userRow)) {
                continue;
            }
            $formState->setFormValue($element->getIdentifier(), (string)$userRow[$column]);
        }
    }
}