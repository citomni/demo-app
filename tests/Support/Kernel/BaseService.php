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
 * BaseService: Test double for CitOmni\Kernel\Service\BaseService.
 *
 * Gives Services under test the App double, their options and the init() hook of the kernel
 * base, without loading the kernel. It must be loaded under the name
 * CitOmni\Kernel\Service\BaseService, and never next to the real kernel.
 *
 * Rules:
 * - Behave as the kernel base does in every member it has.
 */
abstract class BaseService {

	/** The app the service works with. */
	protected App $app;

	/** @var array<string,mixed> Options, as a service map entry would give them. */
	protected array $options;


	/**
	 * Keep the app and the options, then call the subclass's init() when it has one.
	 *
	 * @param  App  $app
	 * @param  array<string,mixed>  $options  Options, as a service map entry would give them.
	 */
	public function __construct(App $app, array $options = []) {
		$this->app = $app;
		$this->options = $options;

		if (\method_exists($this, 'init')) {
			$this->init();
		}
	}

}
