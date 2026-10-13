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

namespace App\Tests\Support;

/**
 * BruteForceDouble: Test double for the bruteForce service of citomni/infrastructure.
 *
 * Gives code under test a fixed rate limit verdict and keeps the arguments of every call, so
 * that a case can check what was asked and what was counted without the service's table or
 * cfg. A case registers it under the service id: $app->set('bruteForce', $double).
 *
 * Rules:
 * - Keep the signatures of the real service in every member it has.
 * - Return only the keys of the real shapes that a case needs, so that code under test that
 *   reads another key fails loudly. Add the key here when a case needs it.
 */
class BruteForceDouble {

	/** @var list<array{context:string,identifier:?string,ip:?string}> Arguments of every status() call, oldest first. */
	public array $statusCalls = [];

	/** @var list<array{context:string,identifier:?string,ip:?string}> Arguments of every record() call, oldest first. */
	public array $recordCalls = [];


	/**
	 * Create a double with a fixed verdict.
	 *
	 * @param  int  $retryAfterSeconds  0 for a sender that is not blocked; otherwise the seconds that status() reports while it blocks.
	 */
	public function __construct(private readonly int $retryAfterSeconds = 0) {
	}


	/**
	 * Keep the arguments and report the fixed verdict.
	 *
	 * @param  string  $context
	 * @param  ?string  $identifier
	 * @param  ?string  $ip
	 * @return array{blocked:bool,retry_after_seconds:int}
	 * @see \CitOmni\Infrastructure\Service\BruteForce::status()  The full shape.
	 */
	public function status(string $context, ?string $identifier = null, ?string $ip = null): array {
		$this->statusCalls[] = ['context' => $context, 'identifier' => $identifier, 'ip' => $ip];

		return [
			'blocked'             => $this->retryAfterSeconds > 0,
			'retry_after_seconds' => $this->retryAfterSeconds,
		];
	}


	/**
	 * Keep the arguments of a counted attempt.
	 *
	 * @param  string  $context
	 * @param  ?string  $identifier
	 * @param  ?string  $ip
	 * @return void
	 */
	public function record(string $context, ?string $identifier = null, ?string $ip = null): void {
		$this->recordCalls[] = ['context' => $context, 'identifier' => $identifier, 'ip' => $ip];
	}

}
