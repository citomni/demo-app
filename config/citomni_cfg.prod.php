<?php
declare(strict_types=1);
/*
 * SPDX-License-Identifier: MIT
 * Copyright (C) 2012-present Lars Grove Mortensen
 *
 * CitOmni Kernel - Common prod configuration overlay scaffold.
 * Source:  https://github.com/citomni/kernel
 * License: See the LICENSE file for full terms.
 */

/*
 * ------------------------------------------------------------------
 * COMMON CONFIG OVERLAY (prod)
 * ------------------------------------------------------------------
 * This file overrides /config/citomni_cfg.php for the "prod" environment.
 *
 * Merge model (cfg, last wins):
 *   vendor mode baseline -> provider common/mode cfg -> app common base -> app mode base -> this file -> app mode prod overlay
 *
 * Typical usage:
 *   - Add production-only overrides for settings shared by both HTTP and CLI.
 *   - Keep HTTP-only production settings in /config/citomni_http_cfg.prod.php.
 *   - Keep CLI-only production settings in /config/citomni_cli_cfg.prod.php.
 *
 * Policy:
 *   - Keep this overlay small, explicit, and audit-friendly.
 *   - Do not mirror vendor, provider, or base app config here.
 *   - Prefer deterministic values over auto-detection for shared production settings.
 *   - Pure data only. No logic, closures, factories, or side effects.
 *
 * Notes:
 *   - CITOMNI_ENVIRONMENT must equal "prod" for this file to load.
 *   - Returning [] means there are no common prod overrides.
 * ------------------------------------------------------------------
 */
return [

	// Database of this environment. Without a db block, the Db service of
	// citomni/infrastructure uses its baseline: host localhost, user citomni,
	// database citomni. Set only the keys that differ. The password never goes
	// in cfg: Db reads the secret 'db.password' from var/secrets/app.secret.prod.php.
	// 'db' => [
		// 'host' => 'db.example.com',
		// 'name' => 'demo_app',
		// 'user' => 'demo_app',
	// ],

	// Mail transport of this environment. SMTP on port 587 with required
	// STARTTLS is the baseline of citomni/infrastructure, so set only the host
	// and the account. With a username, the mailer reads the password from the
	// secret 'mail.smtp.password' in var/secrets/app.secret.prod.php; it never
	// goes in cfg.
	// 'mail' => [
		// 'smtp' => [
			// 'host'     => 'smtp.example.com',
			// 'username' => 'noreply@example.com',
		// ],
	// ],

];
