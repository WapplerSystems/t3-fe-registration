<?php

/**
 * Disable not needed fields in tt_content
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist']['formextended_doubleoptin'] = 'select_key,pages,recursive';

/**
 * Include Flexform
 */
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist']['formextended_doubleoptin'] = 'pi_flexform';
ExtensionManagementUtility::addPiFlexFormValue(
    'formextended_doubleoptin',
    'FILE:EXT:form_extended/Configuration/FlexForms/DoubleOptIn.xml'
);
