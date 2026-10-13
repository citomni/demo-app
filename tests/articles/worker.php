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

use App\Operation\PublishArticle;
use App\Tests\Support\TestDatabase;

/*
 * Worker process for the multi-process publishing check of the article suite.
 * Not a suite of its own.
 *
 * Connects to <database>, creates <ready file> and waits up to 10 seconds for
 * <gate file>. Its caller must create the gate only when every worker is ready,
 * so that their PublishArticle calls for <article id> race each other. The
 * worker then writes the Operation's result to stdout as one JSON line, or exits
 * with code 3 when the gate never opened.
 *
 * The word minimum is 1: The race is about the draft state, not the policy.
 * The password reaches the worker only through the inherited environment, never
 * on the command line.
 *
 * Usage:
 *   php tests/articles/worker.php <database> <article id> <ready file> <gate file>
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\mysqli_report(\MYSQLI_REPORT_ERROR | \MYSQLI_REPORT_STRICT);

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';

[, $database, $articleId, $ready, $gate] = $argv + [null, '', '0', '', ''];

$app = TestDatabase::app($database, ['articles' => ['min_words_to_publish' => 1]]);

// Connect before reporting ready, so every worker starts publishing at once.
$app->db->fetchValue('SELECT 1');
\touch($ready);

$deadline = \microtime(true) + 10;
while (!\is_file($gate)) {
	if (\microtime(true) > $deadline) {
		\fwrite(\STDERR, "worker.php: the start gate did not open within 10 seconds\n");
		exit(3);
	}
	\usleep(1_000);
}

$result = (new PublishArticle($app))->execute((int)$articleId);
\fwrite(\STDOUT, \json_encode($result, \JSON_THROW_ON_ERROR) . "\n");
