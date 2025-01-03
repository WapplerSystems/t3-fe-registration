<?php

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use WapplerSystems\FormExtended\Controller\DoubleOptInController;


ExtensionUtility::configurePlugin(
    'form_extended',
    'DoubleOptIn',
    [
        DoubleOptInController::class => 'validation'
    ],
    [
        DoubleOptInController::class => 'validation'
    ]
);

ExtensionUtility::configurePlugin(
    'form_extended',
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
    ['source' => 'EXT:form_extended/Resources/Public/Icons/PluginDoubleOptIn.svg']
);

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Form\Mvc\Property\TypeConverter\UploadedFileReferenceConverter::class] = [
    'className' => WapplerSystems\FormExtended\Mvc\Property\TypeConverter\UploadedFileReferenceConverter::class
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Backend\Form\FormDataProvider\SiteTcaInline::class] = [
    'className' => WapplerSystems\FormExtended\Form\FormDataProvider\SiteTcaInline::class
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][TYPO3\CMS\Backend\Form\FormDataProvider\SiteDatabaseEditRow::class] = [
    'className' => WapplerSystems\FormExtended\Form\FormDataProvider\SiteDatabaseEditRow::class
];


ExtensionManagementUtility::addTypoScriptSetup(
    'module.tx_form {
    settings {
        yamlConfigurations {
            321 = EXT:form_extended/Configuration/Yaml/FormSetup.yaml
        }
    }
}'
);

$featureSiteEmail = GeneralUtility::makeInstance(ExtensionConfiguration::class)
    ->get('form_extended', 'featureSiteEmail');
if ($featureSiteEmail) {
    ExtensionManagementUtility::addTypoScriptSetup(
        'module.tx_form {
            settings {
                yamlConfigurations {
                    322 = EXT:form_extended/Configuration/Yaml/Features/SiteEmail/Feature.yaml
                }
            }
        }'
    );
}

