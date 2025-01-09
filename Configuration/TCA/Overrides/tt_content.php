<?php

/**
 * Disable not needed fields in tt_content
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;


$contentTypeName = ExtensionUtility::registerPlugin(
    'fe_registration',
    'Registration',
    'Frontend User Registration'
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
