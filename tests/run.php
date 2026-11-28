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

/*
 * Run the app's suites and the suites of the packages it installs, each in its own PHP
 * process: first the isolated suites (tests/<suite>/run.php), then the database suites
 * (tests/<suite>/database.php), then tests/run.php of each package in vendor/ whose
 * vendor is in VENDORS. A package's runner is run like a suite: one process, one
 * summary line.
 *
 * Each suite gets a process of its own, because a suite may replace framework
 * classes with class_alias doubles and install its own error handler. Each
 * suite's output is passed through unchanged under a heading that also selects it:
 * "== <suite>/<script>" is the path for running an app suite alone, and
 * "== <vendor>/<package>" the argument for running a package's suites alone. The
 * last line, under "== total", adds up the totals.
 *
 * Usage:
 *   php tests/run.php                          (the app and its packages)
 *   php tests/run.php app                      (the app's suites only)
 *   php tests/run.php citomni/demo-provider    (one package; name several to run several)
 *   php tests/words/run.php                    (one suite alone)
 *
 * Notes:
 * - Suites run with the same PHP binary and php.ini as this script, and with -n
 *   when this script runs with -n. -d settings are not passed on.
 * - Environment variables such as CITOMNI_TEST_PARALLEL and CITOMNI_TEST_PASSWORD
 *   reach the suites, because child processes inherit the environment.
 * - CITOMNI_TEST_VENDOR is set to this app's vendor/ for every suite, so the
 *   package suites load the packages that this app installs.
 * - Nothing is skipped for a missing test database server: A suite that cannot
 *   reach it fails.
 * - A suite counts as one failure when it exits non-zero with no failed checks,
 *   or when its last line is not a summary (for example after a fatal error).
 * - A package without tests/run.php, such as one installed from a dist archive, is
 *   listed under "== packages without suites". It is a failure only when it is named.
 */

/** Vendors whose installed packages have their suites run, as Composer vendor names. */
const VENDORS = ['citomni'];

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$vendor = \dirname(__DIR__) . '/vendor';
$named  = \array_values(\array_unique(\array_slice($argv, 1)));

$passed  = 0;
$failed  = 0;
$skipped = 0;


// -- 1. Choose the suites ------------------------------------------------------
// Pairs of heading and script: the app's suites first, then the packages' runners.

$runs = [];
if ($named === [] || \in_array('app', $named, true)) {
	// Isolated suites first, then database suites; each group in alphabetical order.
	foreach (['run.php', 'database.php'] as $name) {
		$found = \glob(__DIR__ . '/*/' . $name) ?: [];
		\sort($found);
		foreach ($found as $script) {
			$runs[] = [\basename(\dirname($script)) . '/' . \basename($script), $script];
		}
	}
}
$appSuites = \count($runs);

$packages = \array_values(\array_diff($named, ['app']));
if ($named === []) {
	foreach (VENDORS as $name) {
		foreach (\glob($vendor . '/' . $name . '/*', \GLOB_ONLYDIR) ?: [] as $dir) {
			$packages[] = $name . '/' . \basename($dir);
		}
	}
	\sort($packages);
}

$withoutSuites = [];
foreach ($packages as $package) {
	$script = $vendor . '/' . $package . '/tests/run.php';
	if (\is_file($script)) {
		$runs[] = [$package, $script];
	} elseif ($named === []) {
		$withoutSuites[] = $package;
	} else {
		$failed++;
		\fwrite(\STDERR, "FAIL {$package} - " . (\is_dir($vendor . '/' . $package) ? 'has no tests/run.php' : 'is not installed in vendor/') . "\n");
	}
}

if ($runs === []) {
	\fwrite(\STDERR, "FAIL no suites found\n");
	exit(1);
}


// -- 2. Run them ---------------------------------------------------------------

// Same php.ini as this process: the loaded file, the default lookup, or none at all.
$iniArgs = match (true) {
	\php_ini_loaded_file() !== false   => ['-c', \php_ini_loaded_file()],
	\php_ini_scanned_files() !== false => [],
	default                            => ['-n'],
};

// PHP resolves the path of a linked package to its checkout, so a package suite learns this
// app's vendor/ only from the environment. The value here replaces an inherited one.
$env = ['CITOMNI_TEST_VENDOR' => $vendor] + \getenv();

foreach ($runs as $i => [$heading, $script]) {
	\fwrite(\STDOUT, ($i > 0 ? "\n" : '') . "== {$heading}\n");

	// Stdout is piped so the summary line can be read; stderr goes straight through.
	// It is passed as a fresh php://stderr, not as STDERR: proc_open() first seeks a
	// stream to the position PHP recorded for it, 0 for an unused STDERR, which would
	// rewind out.txt in "php tests/run.php > out.txt 2>&1".
	$proc = \proc_open([\PHP_BINARY, ...$iniArgs, $script], [
		0 => ['pipe', 'r'],
		1 => ['pipe', 'w'],
		2 => ['file', 'php://stderr', 'w'],
	], $pipes, null, $env);
	if (!\is_resource($proc)) {
		throw new \RuntimeException('Cannot start suite: ' . $heading);
	}
	\fclose($pipes[0]);

	$last = '';
	while (($line = \fgets($pipes[1])) !== false) {
		\fwrite(\STDOUT, $line);
		if (\trim($line) !== '') {
			$last = \rtrim($line, "\r\n");
		}
	}
	\fclose($pipes[1]);
	$exit = \proc_close($proc);

	if (\preg_match('/^(\d+) passed, (\d+) failed(?:, (\d+) skipped)?$/', $last, $m) !== 1) {
		$failed++;
		\fwrite(\STDERR, "FAIL {$heading} - exit code {$exit} and no summary line\n");
		continue;
	}

	$passed  += (int)$m[1];
	$failed  += (int)$m[2];
	$skipped += (int)($m[3] ?? 0);

	if ($exit !== 0 && (int)$m[2] === 0) {
		$failed++;
		\fwrite(\STDERR, "FAIL {$heading} - exit code {$exit} although no check failed\n");
	}
}


// -- 3. Report -----------------------------------------------------------------

if ($withoutSuites !== []) {
	\fwrite(\STDOUT, "\n== packages without suites\n" . \implode("\n", $withoutSuites) . "\n");
}

$packageRuns = \count($runs) - $appSuites;
$counts = [];
if ($appSuites > 0) {
	$counts[] = $appSuites . ($appSuites === 1 ? ' suite' : ' suites');
}
if ($packageRuns > 0) {
	$counts[] = $packageRuns . ($packageRuns === 1 ? ' package' : ' packages');
}

\fwrite(\STDOUT, "\n== total (" . \implode(', ', $counts) . ")\n");
\fwrite(\STDOUT, "{$passed} passed, {$failed} failed" . ($skipped > 0 ? ", {$skipped} skipped" : '') . "\n");
exit($failed === 0 ? 0 : 1);
