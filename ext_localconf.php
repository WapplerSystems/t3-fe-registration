<?php

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\FeRegistration\Controller\RegistrationController;


ExtensionUtility::configurePlugin(
    'fe_registration',
    'Registration',
    [
        RegistrationController::class => 'new, confirm'
    ],
    [
        RegistrationController::class => 'new, confirm'
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'fe_registration',
    'ResendConfirmationEmail',
    [
        RegistrationController::class => 'resendConfirmationEmail'
    ],
    [
        RegistrationController::class => 'resendConfirmationEmail'
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

$iconRegistry = GeneralUtility::makeInstance(
    IconRegistry::class
);
$iconRegistry->registerIcon(
    'plugin-feregistration',
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

