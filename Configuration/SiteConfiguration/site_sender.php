<?php

return [
    'ctrl' => [
        'label' => 'email',
        'title' => 'LLL:EXT:form_extended/Resources/Private/Language/locallang_siteconfiguration_tca.xlf:site_sender.ctrl.title',
        'typeicon_classes' => [
            'default' => 'mimetypes-x-email',
        ],
    ],
    'columns' => [
        'email' => [
            'label' => 'LLL:EXT:form_extended/Resources/Private/Language/locallang_siteconfiguration_tca.xlf:site_sender.email',
            'description' => 'LLL:EXT:form_extended/Resources/Private/Language/siteconfiguration_fieldinformation.xlf:site_sender.email',
            'config' => [
                'type' => 'email',
                'required' => true,
                'eval' => 'trim',
                'placeholder' => 'LLL:EXT:form_extended/Resources/Private/Language/locallang_siteconfiguration_tca.xlf:site_sender.email.placeholder',
            ],
        ],
        'name' => [
            'label' => 'LLL:EXT:form_extended/Resources/Private/Language/locallang_siteconfiguration_tca.xlf:site_sender.name',
            'description' => 'LLL:EXT:form_extended/Resources/Private/Language/siteconfiguration_fieldinformation.xlf:site_sender.name',
            'config' => [
                'type' => 'input',
                'required' => true,
                'eval' => 'trim',
                'placeholder' => 'LLL:EXT:form_extended/Resources/Private/Language/locallang_siteconfiguration_tca.xlf:site_sender.name.placeholder',
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'email,name',
        ],
    ],
];
