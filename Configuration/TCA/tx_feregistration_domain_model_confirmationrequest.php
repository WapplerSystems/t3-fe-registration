<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest',
        'label' => 'email',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden'
        ],
        'searchFields' => 'email, given_name, family_name, company, customer_number, confirmation_hash',
        'iconfile' => 'EXT:fe_registration/Resources/Public/Icons/DoubleOptIn.png',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'interface' => [
    ],
    'types' => [
        '1' => [
            'showitem' => 'email, encoded_values, confirmation_hash, confirmation_date, is_confirmed, last_sent'
        ]
    ],
    'columns' => [
        'email' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.email',
            'config' => [
                'type' => 'input',
                'size' => '30',
                'readOnly' => 1
            ]
        ],
        'encoded_values' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.encoded_values',
            'config' => [
                'type' => 'text',
                'readOnly' => 1
            ]
        ],
        'is_confirmed' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.is_confirmed',
            'config' => [
                'type' => 'check',
                'readOnly' => 1
            ]
        ],
        'confirmation_hash' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.confirmation_hash',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'readOnly' => 1
            ]
        ],
        'confirmation_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.confirmation_date',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'datetime',
                'checkbox' => 0,
                'readOnly' => 1
            ]
        ],
        'last_sent' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.last_sent',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'datetime',
                'checkbox' => 0,
                'readOnly' => 1
            ]
        ],
    ]
];
