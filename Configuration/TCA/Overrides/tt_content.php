<?php

/**
 * Disable not needed fields in tt_content
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;


$contentTypeName = ExtensionUtility::registerPlugin(
    'fe_registration',
    'Registration',
    'LLL:EXT:fe_registration/Resources/Private/Language/locallang_db.xlf:plugin.registration.title',
    'plugin-feregistration'
);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    'pi_flexform',
    $contentTypeName,
    'after:palette:headers'
);

ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:fe_registration/Configuration/FlexForms/Registration.xml',
    $contentTypeName
);
