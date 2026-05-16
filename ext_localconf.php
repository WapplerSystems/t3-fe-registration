<?php

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\FeRegistration\Controller\RegistrationController;
use WapplerSystems\FeRegistration\Routing\Aspect\PersistedFieldValueMapper;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['PersistedFieldValueMapper'] = PersistedFieldValueMapper::class;


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


$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1739725421] = \WapplerSystems\FeRegistration\Hooks\FormInitializationHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1739725421] = \WapplerSystems\FeRegistration\Hooks\BeforeRenderingHook::class;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterFormStateInitialized'][1739725421] = \WapplerSystems\FeRegistration\Hooks\AfterFormStateInitializedHook::class;
