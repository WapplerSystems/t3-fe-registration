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
            $extFormConfigurationManager = GeneralUtility::makeInstance(ExtFormConfigurationManagerInterface::class);
            $formSettings = $extFormConfigurationManager->getYamlConfiguration($typoScriptSettings, true);
            // @todo: Make this VH non-static, get FormPersistenceManagerInterface injected, removed 'public: true' in its AsAlias
            $formPersistenceManager = GeneralUtility::makeInstance(FormPersistenceManagerInterface::class);
            $formConfiguration = $formPersistenceManager->load($persistenceIdentifier, $formSettings, $typoScriptSettings);
            ArrayUtility::mergeRecursiveWithOverrule($formConfiguration, $overrideConfiguration);
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
        return $form->render();
    }
}
