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

use App\Operation\SendContactMessage;
use App\Repository\ContactMessageRepository;
use App\Tests\Support\BruteForceDouble;
use App\Tests\Support\Kernel\App;
use App\Util\ContactMessage;

/*
 * Isolated suite for the contact form: the field rules of ContactMessage, the guard
 * of ContactMessageRepository, and the decisions of SendContactMessage.
 *
 * The app double registers no db service. Code that reaches persistence throws the
 * double's \RuntimeException, so a case can tell a refused message from a stored one
 * without a database. SendContactMessage gets a BruteForceDouble, which shows what
 * was counted.
 *
 * Usage:
 *   php tests/contact/run.php
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


/**
 * Build an e-mail address of the given length, for the cases at the length limit.
 *
 * Behavior:
 * - A local part of up to 64 characters, then domain labels of at most 63 characters and ".com".
 * - FILTER_VALIDATE_EMAIL accepts the address up to 254 characters.
 *
 * @param  int  $length  Length in characters, at least 7.
 * @return string
 * @throws \InvalidArgumentException  When the length is below 7, the length of a@b.com.
 */
function emailOfLength(int $length): string {
	if ($length < 7) {
		throw new \InvalidArgumentException('An e-mail address of this shape needs at least 7 characters.');
	}

	// The local part leaves at least one character for the domain labels.
	$local = \str_repeat('a', \min(64, $length - 6));
	$rest = $length - \strlen($local) - \strlen('@.com');
	$labels = [];

	// Labels of at most 63 characters, joined by dots. Each label is charged one extra
	// character for the dot before the next label.
	while ($rest > 0) {
		$label = \min(63, $rest);

		// A remainder of exactly one character would hold only the dot, so the label gives one up.
		if ($rest - $label === 1) {
			$label--;
		}

		$labels[] = \str_repeat('b', $label);
		$rest -= $label + 1;
	}

	return $local . '@' . \implode('.', $labels) . '.com';
}


/**
 * Create an app double with the given bruteForce double and no db service.
 *
 * @param  BruteForceDouble  $bruteForce
 * @return App
 */
function appWith(BruteForceDouble $bruteForce): App {
	$app = new App();
	$app->set('bruteForce', $bruteForce);

	return $app;
}


/**
 * Fail the current case unless the action gets as far as the database.
 *
 * The app double has no db service, so an action that reaches it throws the double's
 * \RuntimeException for app->db.
 *
 * @param  \Closure  $action  Code that must reach the database.
 * @return void
 * @throws \App\Tests\Support\CheckFailed  When the action throws anything else, or nothing.
 */
function reachesDatabase(\Closure $action): void {
	$e = throws(\RuntimeException::class, $action);

	check(\str_contains($e->getMessage(), 'app->db'), 'Unexpected error: ' . $e->getMessage());
}


// -- 1. ContactMessage ---------------------------------------------------------
// The reason codes are compared as strings on purpose: They are a public vocabulary,
// so renaming one is a breaking change.

test('ContactMessage accepts a message at every limit', function (): void {
	$email = emailOfLength(ContactMessage::MAX_LENGTHS['email']);

	check(\strlen($email) === 254, 'The fixture address has ' . \strlen($email) . ' characters');
	check(ContactMessage::problems(\str_repeat('é', 100), $email, \str_repeat('é', 5000)) === [], 'A message at the limits was refused');
	check(ContactMessage::problems('Ada', 'ada@example.com', 'Hello') === [], 'A short message was refused');
});

test('ContactMessage reports required for every empty field, in form order', function (): void {
	$problems = ContactMessage::problems('', '', '');

	check($problems === ['name' => 'required', 'email' => 'required', 'message' => 'required'], 'Unexpected problems: ' . \json_encode($problems));
});

test('ContactMessage reports too_long one character above each limit', function (): void {
	$problems = ContactMessage::problems(\str_repeat('é', 101), emailOfLength(255), \str_repeat('é', 5001));

	check($problems === ['name' => 'too_long', 'email' => 'too_long', 'message' => 'too_long'], 'Unexpected problems: ' . \json_encode($problems));
});

test('ContactMessage reports invalid for a malformed e-mail address and for invalid UTF-8', function (): void {
	check(ContactMessage::problems('Ada', 'ada.example.com', 'Hello') === ['email' => 'invalid'], 'A malformed address was accepted');

	$problems = ContactMessage::problems("Ad\xC3", 'ada@example.com', "Hel\xFFlo");
	check($problems === ['name' => 'invalid', 'message' => 'invalid'], 'Unexpected problems: ' . \json_encode($problems));
});


// -- 2. ContactMessageRepository -----------------------------------------------
// The guard keeps out only what its columns would not hold unchanged; the form rules
// belong to the callers.

test('ContactMessageRepository refuses a value that does not fit its column before it reaches the database', function (): void {
	$messages = new ContactMessageRepository(new App());

	throws(\InvalidArgumentException::class, fn() => $messages->insert(\str_repeat('é', 101), 'ada@example.com', 'Hello'));
	throws(\InvalidArgumentException::class, fn() => $messages->insert('Ada', emailOfLength(255), 'Hello'));
	throws(\InvalidArgumentException::class, fn() => $messages->insert("Ad\xC3", 'ada@example.com', 'Hello'));

	// 32768 two-byte characters: The message column limit counts bytes, not characters.
	throws(\InvalidArgumentException::class, fn() => $messages->insert('Ada', 'ada@example.com', \str_repeat('é', 32768)));
});

test('ContactMessageRepository checks the trimmed values and leaves the form rules to its callers', function (): void {
	$messages = new ContactMessageRepository(new App());

	reachesDatabase(fn() => $messages->insert(' ' . \str_repeat('é', 100) . " \n", 'ada@example.com', 'Hello'));
	reachesDatabase(fn() => $messages->insert('', 'ada.example.com', ''));
});


// -- 3. SendContactMessage -----------------------------------------------------
// Statuses are compared as strings, like the reason codes: Adapters may pass them on,
// so renaming one is a breaking change.

test('SendContactMessage accepts a valid message: preflight() reports ok, and execute() stores it before it counts it', function (): void {
	$bruteForce = new BruteForceDouble();
	$sending = new SendContactMessage(appWith($bruteForce));
	$result = $sending->preflight(' Ada ', ' ada@example.com ', 'Hello', '203.0.113.7');

	check($result === ['status' => 'ok', 'message_id' => null, 'retry_after_seconds' => 0, 'problems' => []], 'Unexpected result: ' . \json_encode($result));
	check($bruteForce->statusCalls === [['context' => 'contact', 'identifier' => 'ada@example.com', 'ip' => '203.0.113.7']], 'Unexpected status() calls: ' . \json_encode($bruteForce->statusCalls));
	check($bruteForce->recordCalls === [], 'preflight() counted the message');

	reachesDatabase(fn() => $sending->execute(' Ada ', ' ada@example.com ', 'Hello', '203.0.113.7'));
	check($bruteForce->recordCalls === [], 'execute() counted the message before it was stored');
});

test('SendContactMessage reports invalid with the problems of the trimmed fields, and neither stores nor counts the message', function (): void {
	$bruteForce = new BruteForceDouble();
	$sending = new SendContactMessage(appWith($bruteForce));
	$fields = [" \t ", ' ada.example.com ', \str_repeat('é', 5001)];
	$expected = [
		'status'              => 'invalid',
		'message_id'          => null,
		'retry_after_seconds' => 0,
		'problems'            => ['name' => 'required', 'email' => 'invalid', 'message' => 'too_long'],
	];

	check(($result = $sending->preflight(...$fields, ip: '203.0.113.7')) === $expected, 'Unexpected preflight() result: ' . \json_encode($result));
	check(($result = $sending->execute(...$fields, ip: '203.0.113.7')) === $expected, 'Unexpected execute() result: ' . \json_encode($result));
	check($bruteForce->recordCalls === [], 'A refused message was counted');
});

test('SendContactMessage reports rate_limited before it checks the fields, and neither stores nor counts the message', function (): void {
	$bruteForce = new BruteForceDouble(120);
	$sending = new SendContactMessage(appWith($bruteForce));
	$expected = ['status' => 'rate_limited', 'message_id' => null, 'retry_after_seconds' => 120, 'problems' => []];

	check(($result = $sending->preflight('', 'ada@example.com', '', '203.0.113.7')) === $expected, 'Unexpected preflight() result: ' . \json_encode($result));
	check(($result = $sending->execute('', 'ada@example.com', '', '203.0.113.7')) === $expected, 'Unexpected execute() result: ' . \json_encode($result));
	check($bruteForce->recordCalls === [], 'A refused message was counted');
});

test('SendContactMessage asks for no rate limit without an e-mail address and an IP', function (): void {
	// A blocking double: Had status() been asked, the result would be rate_limited.
	$bruteForce = new BruteForceDouble(120);
	$result = (new SendContactMessage(appWith($bruteForce)))->execute('Ada', ' ', 'Hello', null);

	check($result['status'] === 'invalid' && $result['problems'] === ['email' => 'required'], 'Unexpected result: ' . \json_encode($result));
	check($bruteForce->statusCalls === [], 'status() was asked without a sender');
});

done();
