<?php
declare(strict_types=1);

/*
 * CitOmni Installer - Local application scaffold settings
 *
 * Purpose:
 * - Provides deterministic placeholder values for citomni/installer.
 * - Lists the create-only scaffold targets this app does not want (omit).
 * - Keeps DevKit identity values in the app repository.
 * - Contains no secrets.
 */

return [
	'placeholders' => [
		'APP_KEY' => 'demo-app',
		'APP_NAME' => 'Demo App',
		'PROD_ROOT_URL' => 'https://demo.citomni.com',
		'STAGE_ROOT_URL' => 'https://stagedemo.citomni.com',
		'PACKAGE_NAME' => 'citomni/demo-app',
		'LICENSE_KEY' => 'mit',
		'COMPOSER_LICENSE' => 'MIT',
		'YEAR' => '2026',
		'OPERATOR_NAME' => 'CitOmni Core Team',
		'OPERATOR_EMAIL' => 'support@citomni.com',
		'OPERATOR_URL' => 'https://www.citomni.com',
		'PUBLIC_CONTACT_NAME' => 'CitOmni Core Team',
		'PUBLIC_CONTACT_EMAIL' => 'support@citomni.com',
		'PUBLIC_CONTACT_PHONE' => '(+45) 25 31 79 01',
		'PUBLIC_CONTACT_URL' => 'https://www.citomni.com',
		'TECHNICAL_SUPPORT_NAME' => 'CitOmni Core Team',
		'TECHNICAL_SUPPORT_EMAIL' => 'support@citomni.com',
		'TECHNICAL_SUPPORT_PHONE' => '(+45) 25 31 79 01',
		'TECHNICAL_SUPPORT_URL' => 'https://www.citomni.com',
		'DEVELOPER_NAME' => 'Lars Grove Mortensen',
		'DEVELOPER_EMAIL' => 'lars@grove-mortensen.dk',
		'DEVELOPER_URL' => 'https://www.citomni.com',
		'COPYRIGHT_HOLDER_NAME' => 'Lars Grove Mortensen',
		'LANGUAGE' => 'da',
		'ICU_LOCALE' => 'da_DK',
		'TIMEZONE' => 'Europe/Copenhagen',
		'CHARSET' => 'UTF-8',
	],
	'omit' => [
		// The demo app keeps only its example Controllers.
		'src/Http/Controller/AppController.php',
	],
];
