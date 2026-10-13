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
 * Cfg: Test double for CitOmni\Kernel\Cfg.
 *
 * Gives code under test the kernel's read semantics over a plain array, so that a case
 * declares exactly the cfg that the code reads. It must be loaded under the name
 * CitOmni\Kernel\Cfg, so that type checks against the kernel class accept it.
 *
 * Rules:
 * - Behave as the kernel does in every member it has.
 */
final class Cfg {

	/**
	 * Wrap one configuration node.
	 *
	 * @param  array<string,mixed>  $data  Node data.
	 */
	public function __construct(private readonly array $data) {
	}


	/**
	 * Read one key of this node.
	 *
	 * @param  string  $key
	 * @return mixed  A nested node for an empty or associative array, otherwise the value; a list stays an array.
	 * @throws \OutOfBoundsException  When the key does not exist at this node.
	 */
	public function __get(string $key): mixed {
		if (!\array_key_exists($key, $this->data)) {
			throw new \OutOfBoundsException("Unknown cfg key: '{$key}'");
		}

		$value = $this->data[$key];

		return \is_array($value) && ($value === [] || !\array_is_list($value)) ? new self($value) : $value;
	}


	/**
	 * Check whether the key exists at this node.
	 *
	 * @param  string  $key
	 * @return bool  True when the key exists, also when its value is null.
	 */
	public function __isset(string $key): bool {
		return \array_key_exists($key, $this->data);
	}


	/**
	 * Return the node's data as a plain array.
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return $this->data;
	}

}
