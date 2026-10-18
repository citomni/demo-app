<?php
declare(strict_types=1);
/*
 * SPDX-License-Identifier: MIT
 * Copyright (C) 2012-present Lars Grove Mortensen
 *
 * CitOmni Kernel - Common app configuration scaffold.
 * Source:  https://github.com/citomni/kernel
 * License: See the LICENSE file for full terms.
 */

/*
 * ------------------------------------------------------------------
 * COMMON APPLICATION CONFIGURATION
 * ------------------------------------------------------------------
 * This file defines app-wide settings shared by HTTP and CLI runtimes.
 *
 * POLICY:
 *   - Keep this file minimal and ONLY declare keys that are genuinely shared
 *     across runtime modes.
 *   - Put HTTP-specific settings in /config/citomni_http_cfg.php.
 *   - Put CLI-specific settings in /config/citomni_cli_cfg.php.
 *   - Do not mirror vendor/provider baseline config here.
 *
 * MERGE MODEL (cfg, last wins):
 *   1) Vendor mode baseline:       CFG_HTTP or CFG_CLI from the active runtime package
 *   2) Provider common cfg:        CFG_COMMON from registries listed in /config/providers.php
 *   3) Provider mode cfg:          CFG_HTTP or CFG_CLI from registries listed in /config/providers.php
 *   4) App common base:            /config/citomni_cfg.php        (this file)
 *   5) App mode base:              /config/citomni_http_cfg.php or /config/citomni_cli_cfg.php
 *   6) App common env overlay:     /config/citomni_cfg.{ENV}.php
 *   7) App mode env overlay:       /config/citomni_http_cfg.{ENV}.php or /config/citomni_cli_cfg.{ENV}.php
 *
 *   {ENV} comes from CITOMNI_ENVIRONMENT ('dev' | 'stage' | 'prod').
 *
 * MERGE RULES:
 *   - Associative arrays merge recursively; per-key, the last source wins.
 *   - Numeric arrays (lists) are replaced wholesale by the last source.
 *   - Empty values ('', 0, false, null, []) are still valid overrides and they win.
 *
 * ACCESS IN CODE:
 *   - $this->app->cfg->identity->app_name
 *   - $this->app->cfg->identity->operator->name
 *   - $this->app->cfg->identity->public_contact->email
 *   - $this->app->cfg->identity->technical_support->email
 *   - $this->app->cfg->identity->developer->name
 *   - $this->app->cfg->locale->timezone
 *
 * NOTES:
 *   - This file is app-owned after installation.
 *   - Missing config files are ignored by runtime for backwards compatibility.
 *   - Existing config files must return values accepted by Arr::normalizeConfig().
 *   - Official scaffolded config files return arrays.
 * ------------------------------------------------------------------
 */
return [


	/*
	 *------------------------------------------------------------------
	 * APPLICATION IDENTITY
	 *------------------------------------------------------------------
	 * Shared app identity and role-based contact information available
	 * to both HTTP and CLI contexts.
	 *
	 * ROLES:
	 * - operator: The person or legal entity responsible for operating the app.
	 * - public_contact: The contact identity intended for users or the public.
	 * - technical_support: The primary contact for technical support of the app.
	 * - developer: The primary person or organization developing the app.
	 *
	 * NOTES:
	 * - Roles may refer to the same person or organization.
	 * - Role-specific contact details should describe that role, not be copied
	 *   merely to keep the structures symmetrical.
	 * - URLs below identify the relevant person, organization, or contact point;
	 *   they are not the app's runtime or public root URL.
	 */
	'identity' => [

		// Human-readable application name.
		// - Used wherever the app needs to identify itself by name.
		// - Keep it short and recognizable, e.g. "Webshop.dk" or "Import Worker".
		'app_name' => 'Demo App',

		// Person or legal entity responsible for operating the application.
		// - This identifies who stands behind the deployed app, not who owns its code.
		// - For an organization-operated app, use the full legal entity name.
		'operator' => [
			'name'  => 'CitOmni Core Team',
			'email' => 'support@citomni.com',
			'url'   => 'https://www.citomni.com',
		],

		// Public contact identity presented to users or the general public.
		// - Use the name or brand users should recognize as their contact point.
		// - Suitable for contact pages, messages, footers, or provider packages.
		'public_contact' => [
			'name'  => 'CitOmni Core Team',
			'email' => 'support@citomni.com',
			'phone' => '(+45) 25 31 79 01',
			'url'   => 'https://www.citomni.com',
		],

		// Primary technical support contact for this application.
		// - Represents the first technical contact point, such as an internal IT
		//   department, service desk, hosting team, or external provider.
		// - It may differ from the developer when support is handled in tiers.
		'technical_support' => [
			'name'  => 'CitOmni Core Team',
			'email' => 'support@citomni.com',
			'phone' => '(+45) 25 31 79 01',
			'url'   => 'https://www.citomni.com',
		],

		// Primary person or organization responsible for developing the application.
		// - Identifies software authorship/maintenance, not legal ownership of the app.
		// - May also be used when generating package or project metadata.
		'developer' => [
			'name'  => 'Lars Grove Mortensen',
			'email' => 'lars@grove-mortensen.dk',
			'url'   => 'https://www.citomni.com',
		],
	],


	/*
	 *------------------------------------------------------------------
	 * LOCALE
	 *------------------------------------------------------------------
	 * Language and formatting settings shared by this app.
	 *
	 * POLICY:
	 * - Keep 'language' for HTML and i18n signals.
	 * - Use 'icu_locale' for Intl/ICU date and number formatting.
	 * - 'timezone' is the application default for time operations and formatting.
	 * - 'charset' is applied by the runtime where relevant.
	 *
	 * REQUIREMENTS:
	 * - PHP ext-intl must be installed for ICU-based formatting.
	 */
	'locale' => [
		'language'   => 'da', // i.e. da
		'icu_locale' => 'da_DK', // i.e. da_DK
		'timezone'   => 'Europe/Copenhagen', // i.e. Europe/Copenhagen
		'charset'    => 'UTF-8', // i.e. UTF-8
	],


	/*
	 *------------------------------------------------------------------
	 * ARTICLES
	 *------------------------------------------------------------------
	 * Rules of the article example.
	 */
	'articles' => [
		'min_words_to_publish' => 20,  // PublishingPolicy refuses drafts with fewer words.
		'words_per_minute'     => 200, // Reading speed behind the reading time on article pages.
	],


	/*
	 *------------------------------------------------------------------
	 * SITE NOTICES (citomni/demo-provider)
	 *------------------------------------------------------------------
	 * The package ships every key in its Registry. Set only the keys
	 * that differ; the others keep the package baseline.
	 */
	'demo_provider' => [
		'notices' => [
			'max_visible' => 2, // Current notices above the page header; the baseline shows 3.
		],
	],


	/*
	 *------------------------------------------------------------------
	 * SECURITY
	 *------------------------------------------------------------------
	 * Rate limit of contact messages: the context "contact" of the
	 * bruteForce service in citomni/infrastructure. Each stored message
	 * counts, and reaching a maximum blocks for retry_after_seconds. Common
	 * to both modes, so an HTTP and a CLI adapter apply the same limit.
	 * The counters live in the table from citomni_bruteforce.sql.
	 */
	'security' => [
		'bruteforce' => [
			'contact' => [
				'max_identifier_attempts' => 3,    // Messages per e-mail address within interval_minutes.
				'max_ip_attempts'         => 10,   // Messages per public IP within interval_minutes.
				'interval_minutes'        => 60,
				'retry_after_seconds'     => 3600,
			],
		],
	],


	/*
	 *------------------------------------------------------------------
	 * MAIL
	 *------------------------------------------------------------------
	 * Sender of the mailer service of citomni/infrastructure, the same in
	 * every environment and in both modes; a mail block only in the HTTP
	 * files would leave CLI code without a sender. The transport belongs to
	 * each environment, in config/citomni_cfg.{ENV}.php. This app sends no
	 * mail, so the block is an example. Until mail.from.email is set, using
	 * the mailer throws MailerConfigException.
	 */
	// 'mail' => [
		// 'from' => [
			// 'email' => 'noreply@example.com',
			// 'name'  => 'Demo App',
		// ],
	// ],


];
