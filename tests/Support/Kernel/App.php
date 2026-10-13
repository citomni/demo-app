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
 * App: Test double for CitOmni\Kernel\App.
 *
 * Gives code under test the cfg and the services that a case sets up, and nothing else: No
 * config files, no providers and no service map. A case that registers no db service thereby
 * proves that the code under test never reaches the database. It must be loaded under the
 * name CitOmni\Kernel\App, and never next to the real kernel.
 *
 * Rules:
 * - Behave as the kernel does in every member it has; a missing feature is added here, never
 *   worked around in the code under test.
 */
final class App {

	/** Cfg double over the array that the constructor was given. */
	public readonly Cfg $cfg;

	/** @var array<string,object> Registered services by id. */
	private array $services = [];


	/**
	 * Create an app with the given configuration and no services.
	 *
	 * @param  array<string,mixed>  $cfg  Configuration, as the kernel would have merged it.
	 */
	public function __construct(array $cfg = []) {
		$this->cfg = new Cfg($cfg);
	}


	/**
	 * Register a service instance under its service id.
	 *
	 * A later call with the same id replaces the earlier service, so a case can wrap a service
	 * that it registered before.
	 *
	 * Typical usage:
	 *   $app = new App(['articles' => ['min_words_to_publish' => 5]]);
	 *   $app->set('db', $db);
	 *
	 * @param  string  $id  Service id, e.g. 'db'.
	 * @param  object  $service
	 * @return void
	 */
	public function set(string $id, object $service): void {
		$this->services[$id] = $service;
	}


	/**
	 * Return the service registered under the id, as $this->app->db does in the kernel.
	 *
	 * @param  string  $id  Service id.
	 * @return object
	 * @throws \RuntimeException  When no service is registered under the id.
	 */
	public function __get(string $id): object {
		return $this->services[$id] ?? throw new \RuntimeException("Unknown app component: app->{$id}");
	}


	/**
	 * Check whether a service is registered under the id.
	 *
	 * @param  string  $id  Service id.
	 * @return bool
	 */
	public function hasService(string $id): bool {
		return isset($this->services[$id]);
	}

}
