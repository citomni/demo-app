<?php
declare(strict_types=1);
/*
 * This file is part of the CitOmni framework.
 * Low overhead, high performance, ready for anything.
 *
 * For more information, visit https://github.com/citomni
 *
 * Copyright (c) 2012-present Lars Grove Mortensen
 * SPDX-License-Identifier: MIT
 *
 * For full copyright, trademark, and license information,
 * please see the LICENSE file distributed with this source code.
 */

namespace App\Tests\Support\Kernel;

/**
 * BaseOperation: Test double for CitOmni\Kernel\Operation\BaseOperation.
 *
 * Gives Operations under test the App double and the init() hook of the kernel base, without
 * loading the kernel. It must be loaded under the name CitOmni\Kernel\Operation\BaseOperation,
 * and never next to the real kernel.
 *
 * Rules:
 * - Behave as the kernel base does in every member it has.
 */
abstract class BaseOperation {

	/** The app the Operation works with. */
	protected App $app;


	/**
	 * Keep the app, then call the subclass's init() when it has one.
	 *
	 * @param  App  $app
	 */
	public function __construct(App $app) {
		$this->app = $app;

		if (\method_exists($this, 'init')) {
			$this->init();
		}
	}

}
