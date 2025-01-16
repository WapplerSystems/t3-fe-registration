<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest',
        'label' => 'email',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'dividers2tabs' => true,
        'searchFields' => 'email, confirmation_hash',
        'iconfile' => 'EXT:fe_registration/Resources/Public/Icons/DoubleOptIn.png',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'interface' => [
    ],
    'types' => [
        '1' => [
            'showitem' => 'email, encoded_values, confirmation_hash, confirmation_date, last_sent'
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
