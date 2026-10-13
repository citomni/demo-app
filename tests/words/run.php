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

use App\Util\Words;

/*
 * Isolated suite for App\Util\Words: word counts and reading time.
 *
 * The cases call Words directly, with no app, no doubles and no database; they
 * rely on Words staying a pure Util.
 *
 * Usage:
 *   php tests/words/run.php
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


// -- 1. count() ----------------------------------------------------------------

test('count() is 0 for an empty or whitespace-only text', function (): void {
	check(Words::count('') === 0, 'Empty text did not count 0 words');
	check(Words::count(" \t\r\n ") === 0, 'Whitespace-only text did not count 0 words');
});

test('count() splits on spaces, tabs and line breaks, also when they repeat', function (): void {
	check(Words::count("one  two\tthree\nfour\r\n\r\nfive") === 5, 'Expected 5 words');
});

test('count() keeps punctuation and non-ASCII letters inside their word', function (): void {
	check(Words::count('Ærø, Øst-Åby! ok.') === 3, 'Expected 3 words');
});

test('count() splits on Unicode spaces such as the no-break space', function (): void {
	check(Words::count("one\u{00A0}two\u{2003}three\u{3000}four") === 4, 'Expected 4 words');
});

test('count() rejects invalid UTF-8 instead of counting 0 words', function (): void {
	throws(\InvalidArgumentException::class, fn() => Words::count("valid \xC3\x28 invalid"));
});


// -- 2. readingMinutes() -------------------------------------------------------

test('readingMinutes() rounds up to whole minutes', function (): void {
	check(Words::readingMinutes('word', 200) === 1, 'One word did not take 1 minute');
	check(Words::readingMinutes(\str_repeat('word ', 200), 200) === 1, '200 words did not take 1 minute');
	check(Words::readingMinutes(\str_repeat('word ', 201), 200) === 2, '201 words did not take 2 minutes');
});

test('readingMinutes() is 0 for a text without words', function (): void {
	check(Words::readingMinutes(" \n ", 200) === 0, 'A text without words did not take 0 minutes');
});

test('readingMinutes() rejects a reading speed below 1', function (): void {
	throws(\InvalidArgumentException::class, fn() => Words::readingMinutes('word', 0));
	throws(\InvalidArgumentException::class, fn() => Words::readingMinutes('word', -200));
});

done();
