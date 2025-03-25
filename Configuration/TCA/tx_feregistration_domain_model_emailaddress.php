<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_sender',
        'label' => 'name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
        'searchFields' => 'name,email',
        'hideTable' => true,
        //'iconfile' => 'EXT:myext/Resources/Public/Icons/tx_myext_contacts.svg',
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
            ],
        ],
        'uid_forgein' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'tablename' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'name' => [
            'exclude' => false,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_sender.name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required'
            ],
        ],
        'email' => [
            'exclude' => false,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_sender.email',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required,email'
            ],
        ],
    ],
    'types' => [
        '1' => ['showitem' => 'name, email'],
    ],
];
