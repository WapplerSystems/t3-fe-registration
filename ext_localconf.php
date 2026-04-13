<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\FeRegistration\Controller\RegistrationController;


ExtensionUtility::configurePlugin(
    'fe_registration',
    'Registration',
    [
        RegistrationController::class => 'new, confirm, success, confirmationMailSent'
    ],
    [
        RegistrationController::class => 'new, confirm, success'
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


ExtensionManagementUtility::addTypoScriptSetup(
    'module.tx_form {
    settings {
        yamlConfigurations {
            341 = EXT:fe_registration/Configuration/Yaml/FormSetup.yaml
        }
    }
}'
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1739725421] = \WapplerSystems\FeRegistration\Hooks\FormInitializationHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1739725421] = \WapplerSystems\FeRegistration\Hooks\BeforeRenderingHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterFormStateInitialized'][1739725421] = \WapplerSystems\FeRegistration\Hooks\AfterFormStateInitializedHook::class;
