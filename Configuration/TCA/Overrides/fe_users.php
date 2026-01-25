<?php
declare(strict_types=1);


use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;


$newSysFileReferenceColumns = [
    'registration_request' => [
        'exclude' => true,
        'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:registration_request',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_feregistration_domain_model_confirmationrequest',
            'appearance' => [
                'showNewRecordLink' => false,
            ]
        ]
    ]
];

ExtensionManagementUtility::addTCAcolumns('fe_users', $newSysFileReferenceColumns);

ExtensionManagementUtility::addToAllTCAtypes('fe_users', 'registration_request', '', '');

