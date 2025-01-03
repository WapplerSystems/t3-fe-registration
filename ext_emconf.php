<?php
$EM_CONF['fe_registration'] = [
    'title' => 'Frontend User Registration',
    'description' => 'Frontend User Registration based on form with double opt in',
    'category' => 'frontend',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'author' => 'Sven Wappler',
    'author_email' => 'typo3YYYY@wappler.systems',
    'author_company' => 'WapplerSystems',
    'version' => '13.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.4.99',
            'form' => '13.0.0-13.4.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
