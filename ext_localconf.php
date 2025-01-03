<?php

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\FeRegistration\Controller\DoubleOptInController;


ExtensionUtility::configurePlugin(
    'fe_registration',
    'DoubleOptIn',
    [
        DoubleOptInController::class => 'validation'
    ],
    [
        DoubleOptInController::class => 'validation'
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'fe_registration',
    'ResendOptinEmail',
    [
        DoubleOptInController::class => 'resendOptInEmail'
    ],
    [
        DoubleOptInController::class => 'resendOptInEmail'
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

$iconRegistry = GeneralUtility::makeInstance(
    IconRegistry::class
);
$iconRegistry->registerIcon(
    'plugin-formextended',
    SvgIconProvider::class,
    ['source' => 'EXT:fe_registration/Resources/Public/Icons/PluginDoubleOptIn.svg']
);


ExtensionManagementUtility::addTypoScriptSetup(
    'module.tx_form {
    settings {
        yamlConfigurations {
            321 = EXT:fe_registration/Configuration/Yaml/FormSetup.yaml
        }
    }
}'
);

