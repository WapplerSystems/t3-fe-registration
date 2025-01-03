<?php

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$featureSiteEmail = GeneralUtility::makeInstance(ExtensionConfiguration::class)
    ->get('form_extended', 'featureSiteEmail');

if ($featureSiteEmail) {

    $GLOBALS['SiteConfiguration']['site']['columns']['senders'] = [
        'label' => 'LLL:EXT:form/Resources/Private/Language/Database.xlf:tt_content.finishersDefinition.EmailToSender.senderAddress.label',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'site_sender',
            'appearance' => [
                'enabledControls' => [
                    'info' => false,
                ],
            ],
        ],
    ];

    $GLOBALS['SiteConfiguration']['site']['types'][0]['showitem'] .= ',--div--;LLL:EXT:form/Resources/Private/Language/locallang.xlf:form_new_wizard_title,senders';
}
