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
            'showitem' => 'email, encoded_values, confirmation_hash, confirmation_date, last_sent, completion_date'
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
                'type' => 'datetime',
                'size' => 20,
                'checkbox' => 0,
                'readOnly' => 1
            ]
        ],
        'last_sent' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.last_sent',
            'config' => [
                'type' => 'datetime',
                'size' => 20,
                'checkbox' => 0,
                'readOnly' => 1
            ]
        ],
        // The column existed in ext_tables.sql and the model had the property, but it was
        // missing here — and Extbase only persists what TCA describes. So every request
        // ever written kept expires_at = 0: the expiry that ConfirmationRequestFinisher
        // computed was silently dropped, links never expired, and the cleanup command
        // could only reach such rows through its --days fallback.
        'expires_at' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.expires_at',
            'config' => [
                'type' => 'datetime',
                'size' => 20,
                'checkbox' => 0,
                'readOnly' => 1
            ]
        ],
        'completion_date' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:tx_feregistration_domain_model_confirmationrequest.completion_date',
            'config' => [
                'type' => 'datetime',
                'size' => 20,
                'checkbox' => 0,
                'readOnly' => 1
            ]
        ],
    ]
];
