<?php
declare(strict_types=1);
/*
 * SPDX-License-Identifier: MIT
 * Copyright (C) 2012-present Lars Grove Mortensen
 *
 * CitOmni Kernel - Common stage configuration overlay scaffold.
 * Source:  https://github.com/citomni/kernel
 * License: See the LICENSE file for full terms.
 */

/*
 * ------------------------------------------------------------------
 * COMMON CONFIG OVERLAY (stage)
 * ------------------------------------------------------------------
 * This file overrides /config/citomni_cfg.php for the "stage" environment.
 *
 * Merge model (cfg, last wins):
 *   vendor mode baseline -> provider common/mode cfg -> app common base -> app mode base -> this file -> app mode stage overlay
 *
 * Typical usage:
 *   - Add stage-only overrides for settings shared by both HTTP and CLI.
 *   - Keep HTTP-only staging settings in /config/citomni_http_cfg.stage.php.
 *   - Keep CLI-only staging settings in /config/citomni_cli_cfg.stage.php.
 *
 * Policy:
 *   - Keep this overlay small and intentional.
 *   - Stage should mirror production posture unless a shared setting must differ.
 *   - Do not mirror vendor, provider, or base app config here.
 *   - Pure data only. No logic, closures, factories, or side effects.
 *
 * Notes:
 *   - CITOMNI_ENVIRONMENT must equal "stage" for this file to load.
 *   - Returning [] means there are no common stage overrides.
 * ------------------------------------------------------------------
 */
return [

	// Database of this environment. Without a db block, the Db service of
	// citomni/infrastructure uses its baseline: host localhost, user citomni,
	// database citomni. Set only the keys that differ. The password never goes
	// in cfg: Db reads the secret 'db.password' from var/secrets/app.secret.stage.php.
	// 'db' => [
		// 'host' => 'db-stage.example.com',
		// 'name' => 'demo_app',
		// 'user' => 'demo_app',
	// ],

];
