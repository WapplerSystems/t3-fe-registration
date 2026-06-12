<?php

declare(strict_types=1);

/*
 * Inspired by and partially taken from the Neos.Form package (www.neos.io)
 */

namespace WapplerSystems\FeRegistration\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface as ExtbaseConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Factory\FormFactoryInterface;
use TYPO3\CMS\Form\Mvc\Configuration\ConfigurationManagerInterface as ExtFormConfigurationManagerInterface;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use WapplerSystems\FeRegistration\Domain\Model\ConfirmationRequest;
use WapplerSystems\FeRegistration\Form\Factory\RegistrationPatchFormFactory;

/**
 * Main Entry Point to render a Form into a Fluid Template
 *
 * Usage
 * =====
 *
 * Default::
 *
 *    {namespace formvh=TYPO3\CMS\Form\ViewHelpers}
 *    <formvh:render factoryClass="NameOfYourCustomFactoryClass" />
 *
 * The factory class must implement :php:`TYPO3\CMS\Form\Domain\Factory\FormFactoryInterface`.
 *
 * Scope: frontend
 */
final class RenderViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function __construct(
        private readonly FormPersistenceManagerInterface $formPersistenceManager,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('persistenceIdentifier', 'string', 'The persistence identifier for the form.');
        $this->registerArgument('factoryClass', 'mixed', 'The fully qualified class name of the factory or the object', false, ArrayFormFactory::class);
        $this->registerArgument('prototypeName', 'string', 'Name of the prototype to use');
        $this->registerArgument('overrideConfiguration', 'array', 'factory specific configuration', false, []);
    }

    public function render(): ?string
    {
        $persistenceIdentifier = $this->arguments['persistenceIdentifier'];
        $prototypeName = $this->arguments['prototypeName'];
        $overrideConfiguration = $this->arguments['overrideConfiguration'];
        /** @var RequestInterface $request */
        $request = $this->renderingContext->getAttribute(ServerRequestInterface::class);
        // @todo: formvh:render() does not make sense without a persistenceIdentifier, does it?
        if (!empty($persistenceIdentifier)) {
            // The ConfigurationManager of ext:form needs ext:extbase ConfigurationManager to retrieve basic TS
            // settings. ConfigurationManager of extbase should *usually* only be called in extbase context and
            // needs a Request, which is usually set by extbase bootstrap.
            // We are however (most likely) not in extbase context here.
            // To prevent a fallback of extbase ConfigurationManager to $GLOBALS['TYPO3_REQUEST'], we set
            // the request explicitly here, to then fetch $formSettings from ext:form ConfigurationManager.
            // $typoScriptSettings is hand over to load() to apply TS overrides for single forms, see #92408.
            $extbaseConfigurationManager = GeneralUtility::makeInstance(ExtbaseConfigurationManagerInterface::class);
            $extbaseConfigurationManager->setRequest($request);
            $typoScriptSettings = $extbaseConfigurationManager->getConfiguration(ExtbaseConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, 'form');
            $formConfiguration = $this->formPersistenceManager->load($persistenceIdentifier, $typoScriptSettings, $request);

            // YAML stores `finishers` as a numerically-keyed sequence; the
            // RegistrationController's $overrideConfiguration uses identifier
            // strings as keys (e.g. 'ConfirmationRequest'). A naive
            // mergeRecursiveWithOverrule() leaves both representations side by
            // side, so every finisher that exists in both ends up registered
            // twice — the symptom: duplicate ConfirmationRequest DB rows and
            // two confirmation emails per submit.
            //
            // Merge finishers separately, identifier-by-identifier: for any
            // identifier that exists in BOTH lists, the override entry fully
            // replaces the YAML entry (mixing options would produce hybrid
            // configs like templateName from override + templateRootPaths
            // from YAML resolving to a non-existent template path).
            // Identifiers exclusive to one side are preserved as-is.
            $yamlFinishers = isset($formConfiguration['finishers']) && is_array($formConfiguration['finishers'])
                ? self::keyFinishersByIdentifier($formConfiguration['finishers']) : [];
            $overrideFinishers = isset($overrideConfiguration['finishers']) && is_array($overrideConfiguration['finishers'])
                ? self::keyFinishersByIdentifier($overrideConfiguration['finishers']) : [];
            $mergedFinishers = array_replace($yamlFinishers, $overrideFinishers);
            unset($formConfiguration['finishers'], $overrideConfiguration['finishers']);

            ArrayUtility::mergeRecursiveWithOverrule($formConfiguration, $overrideConfiguration);
            // Keep identifier-string keys: RegistrationPatchFormFactory
            // detects pre-confirmation mode by checking
            // `$finisherName === 'ConfirmationRequest'` on the array keys.
            // Re-indexing to numeric here would silently flip that detection
            // to false and slice the email-bearing page out of the form.
            $formConfiguration['finishers'] = $mergedFinishers;
            $overrideConfiguration = $formConfiguration;
            $overrideConfiguration['persistenceIdentifier'] = $persistenceIdentifier;
        }
        if (empty($prototypeName)) {
            $prototypeName = $overrideConfiguration['prototypeName'] ?? 'standard';
        }
        if (is_object($this->arguments['factoryClass'])) {
            $factory = $this->arguments['factoryClass'];
        } else {
            // Even though getContainer() is internal, we can't get container injected here due to static scope
            /** @var FormFactoryInterface $factory */
            $factory = GeneralUtility::getContainer()->get($this->arguments['factoryClass']);
        }
        $formDefinition = $factory->build($overrideConfiguration, $prototypeName, $request);

        $form = $formDefinition->bind($request);
        if ($formDefinition->getRenderingOptions()['afterConfirmation'] ?? false) {
            $confirmationRequest = $formDefinition->getRenderingOptions()['confirmationRequest'] ?? null;
            if ($confirmationRequest instanceof ConfirmationRequest) {
                $values = $confirmationRequest->getDecodedValues();
            } elseif ($factory instanceof RegistrationPatchFormFactory) {
                $values = $factory->getPreDefinedValues();
            } else {
                $values = [];
            }
            foreach ($values as $fieldIdentifier => $value) {
                $form->getFormState()->setFormValue($fieldIdentifier, $value);
            }
        }

        return $form->render();
    }

    /**
     * Re-key a list of finisher configurations by their `identifier`.
     *
     * Form-framework YAML stores finishers as a numerically indexed list of
     * `{identifier, options}` entries; mergeRecursiveWithOverrule() with a
     * map keyed by identifier would otherwise produce a duplicated stack.
     * Falls back to the original key when an entry has no identifier (which
     * would be a malformed config anyway).
     *
     * @param array<int|string, mixed> $finishers
     * @return array<int|string, mixed>
     */
    private static function keyFinishersByIdentifier(array $finishers): array
    {
        $keyed = [];
        foreach ($finishers as $key => $finisher) {
            $identifier = is_array($finisher) ? ($finisher['identifier'] ?? null) : null;
            $keyed[is_string($identifier) && $identifier !== '' ? $identifier : $key] = $finisher;
        }
        return $keyed;
    }
}
