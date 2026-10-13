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

namespace App\Tests;

use App\Tests\Support\CheckFailed;

/*
 * Shared bootstrap for the suites; not a suite of its own. A suite must install its
 * CLI guard and its error handler before it requires this file.
 *
 * Provides, in this order:
 * 1) An autoloader for App\ (src/), App\Tests\ (tests/) and CitOmni\Infrastructure\
 *    (vendor/citomni/infrastructure/src/). Composer's autoloader is never loaded,
 *    because it could load a real kernel class before its double is in place.
 * 2) The kernel doubles of tests/Support/Kernel/ under their citomni/kernel names.
 * 3) The case runner and its checks.
 *
 * Notes:
 * - Only code that uses the real Db service needs vendor/, so "composer install"
 *   must have run before a database suite.
 * - Code under test that needs a kernel feature the doubles lack fails loudly.
 *   Extend the double, never the code under test.
 */


// -- 1. Autoloader -------------------------------------------------------------

\spl_autoload_register(static function (string $class): void {
	// Longest prefix first: App\Tests\ is also under App\.
	static $roots = [
		'App\\Tests\\'              => __DIR__ . '/',
		'App\\'                     => __DIR__ . '/../src/',
		'CitOmni\\Infrastructure\\' => __DIR__ . '/../vendor/citomni/infrastructure/src/',
	];

	foreach ($roots as $prefix => $dir) {
		if (\str_starts_with($class, $prefix)) {
			$file = $dir . \str_replace('\\', '/', \substr($class, \strlen($prefix))) . '.php';
			if (\is_file($file)) {
				require $file;
			}
			return;
		}
	}
});


// -- 2. Kernel doubles ---------------------------------------------------------

\class_alias(Support\Kernel\App::class, 'CitOmni\\Kernel\\App');
\class_alias(Support\Kernel\Cfg::class, 'CitOmni\\Kernel\\Cfg');
\class_alias(Support\Kernel\BaseService::class, 'CitOmni\\Kernel\\Service\\BaseService');
\class_alias(Support\Kernel\BaseRepository::class, 'CitOmni\\Kernel\\Repository\\BaseRepository');
\class_alias(Support\Kernel\BaseOperation::class, 'CitOmni\\Kernel\\Operation\\BaseOperation');


// -- 3. Case runner ------------------------------------------------------------

$passed  = 0;
$failed  = 0;
$skipped = 0;


/**
 * Run one case and report it.
 *
 * Keeps a failing case from ending the suite: The failure is reported, and the next
 * case runs.
 *
 * Behavior:
 * - A PASS line with the case name on stdout when the case returns.
 * - A FAIL line with the case name and the reason on stderr when the case throws:
 *     - The message alone for a CheckFailed.
 *     - The class, message, file and line for anything else.
 * - Frees the case's objects before it returns, so the database connections that the
 *   case opened are closed before the next case opens new ones.
 *
 * @param  string  $name  Case name, shown in the report.
 * @param  \Closure  $case  Code that throws to fail.
 * @return void
 */
function test(string $name, \Closure $case): void {
	global $passed, $failed;

	try {
		$case();
		++$passed;
		\fwrite(\STDOUT, "PASS {$name}\n");
	} catch (CheckFailed $e) {
		++$failed;
		\fwrite(\STDERR, "FAIL {$name} - " . oneLine($e->getMessage()) . "\n");
	} catch (\Throwable $e) {
		++$failed;
		\fwrite(\STDERR, "FAIL {$name} - " . oneLine($e::class . ': ' . $e->getMessage() . ' at ' . \basename($e->getFile()) . ':' . $e->getLine()) . "\n");
	} finally {
		// An App double and its Db service refer to each other, so only the cycle
		// collector frees them and closes the connection.
		\gc_collect_cycles();
	}
}


/**
 * Report an optional check that did not run.
 *
 * Counts it in the totals of done(), so a skipped check stays visible.
 *
 * @param  string  $name  Case name.
 * @param  string  $reason  Why it did not run, with what enables it.
 * @return void
 */
function skip(string $name, string $reason): void {
	global $skipped;

	++$skipped;
	\fwrite(\STDOUT, "SKIP {$name}: {$reason}\n");
}


/**
 * Print the totals and end the suite.
 *
 * Behavior:
 * - Exit code 0 when no case failed, otherwise 1.
 *
 * @return never
 */
function done(): never {
	global $passed, $failed, $skipped;

	\fwrite(\STDOUT, "{$passed} passed, {$failed} failed" . ($skipped > 0 ? ", {$skipped} skipped" : '') . "\n");
	exit($failed === 0 ? 0 : 1);
}


/**
 * Fail the current case unless the condition holds.
 *
 * @param  bool  $condition  Condition that must hold.
 * @param  string  $message  Reason reported when it does not.
 * @return void
 * @throws \App\Tests\Support\CheckFailed  When the condition is false.
 */
function check(bool $condition, string $message): void {
	if (!$condition) {
		throw new CheckFailed($message);
	}
}


/**
 * Fail the current case unless the action throws the expected class.
 *
 * @param  class-string<\Throwable>  $class  Expected exception class; a subclass also matches.
 * @param  \Closure  $action  Code that must throw.
 * @return \Throwable  The thrown exception, for further checks.
 * @throws \App\Tests\Support\CheckFailed  When nothing or something else is thrown.
 */
function throws(string $class, \Closure $action): \Throwable {
	try {
		$action();
	} catch (CheckFailed $e) {
		throw $e;
	} catch (\Throwable $e) {
		check($e instanceof $class, 'Expected ' . $class . ', got ' . $e::class . ': ' . $e->getMessage());

		return $e;
	}

	throw new CheckFailed('Expected ' . $class . ', nothing was thrown');
}


/**
 * Collapse a multi-line message to one line.
 *
 * @param  string  $text
 * @return string  The lines joined with " | ".
 */
function oneLine(string $text): string {
	return \trim((string)\preg_replace('/\s*\R\s*/', ' | ', $text));
}
