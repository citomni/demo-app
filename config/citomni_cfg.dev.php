<?php
declare(strict_types=1);
/*
 * SPDX-License-Identifier: MIT
 * Copyright (C) 2012-present Lars Grove Mortensen
 *
 * CitOmni Kernel - Common dev configuration overlay scaffold.
 * Source:  https://github.com/citomni/kernel
 * License: See the LICENSE file for full terms.
 */

/*
 * ------------------------------------------------------------------
 * COMMON CONFIG OVERLAY (dev)
 * ------------------------------------------------------------------
 * This file overrides /config/citomni_cfg.php for the "dev" environment.
 *
 * Merge model (cfg, last wins):
 *   vendor mode baseline -> provider common/mode cfg -> app common base -> app mode base -> this file -> app mode dev overlay
 *
 * Typical usage:
 *   - Add dev-only overrides for settings shared by both HTTP and CLI.
 *   - Keep HTTP-only diagnostics in /config/citomni_http_cfg.dev.php.
 *   - Keep CLI-only diagnostics in /config/citomni_cli_cfg.dev.php.
 *
 * Policy:
 *   - Keep this overlay small and intentional.
 *   - Only declare keys you intend to override from common app config.
 *   - Do not mirror vendor, provider, or base app config here.
 *   - Pure data only. No logic, closures, factories, or side effects.
 *
 * Notes:
 *   - CITOMNI_ENVIRONMENT must equal "dev" for this file to load.
 *   - Returning [] means there are no common dev overrides.
 * ------------------------------------------------------------------
 */
return [

	// Database of this environment. Without a db block, the Db service of
	// citomni/infrastructure uses its baseline: host localhost, user citomni,
	// database citomni. Set only the keys that differ. The password never goes
	// in cfg: Db reads the secret 'db.password' from var/secrets/app.secret.dev.php.
	// 'db' => [
		// 'host'		=> 'localhost',
		// 'name'		=> 'citomni',
		// 'user'		=> 'citomni',
	// ],

	// Mail transport of this environment. Point dev at a local mail catcher,
	// such as Mailpit on port 1025, so that no message reaches a real inbox.
	// Encryption 'none' sends in plain text, which only a local catcher should
	// accept. Without a username the mailer does not authenticate, so dev needs
	// no 'mail.smtp.password' secret.
	// 'mail' => [
		// 'smtp' => [
			// 'host'       => 'localhost',
			// 'port'       => 1025,
			// 'encryption' => 'none',
		// ],
	// ],

];
