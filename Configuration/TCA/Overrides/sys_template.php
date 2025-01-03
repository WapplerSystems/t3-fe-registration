<?php

/***************
* Add content element PageTSConfig
*/

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

ExtensionManagementUtility::registerPageTSConfigFile(
'fe-registration',
'Configuration/TsConfig/NewContentElement.tsconfig',
'Frontend Registration'
);

ExtensionManagementUtility::addStaticFile('fe_registration', 'Configuration/TypoScript/', 'Frontend Registration');
