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
use App\Tests\Support\TestDatabase;
use App\Util\ContactMessage;

/*
 * Database suite for the contact form against a real MySQL/MariaDB server: the SQL
 * of ContactMessageRepository on the schema in sql/citomni_demo-app.sql, through
 * the real Db service of citomni/infrastructure, a message that SendContactMessage
 * stores, and the column sizes behind ContactMessage::MAX_LENGTHS.
 *
 * The run creates one database, citomni_demo_app_contact_test_<random>, imports
 * sql/citomni_demo-app.sql into it and drops only that database in finally. Every
 * case starts from an empty demo_contact_messages table.
 *
 * Usage:
 *   php tests/contact/database.php
 *
 * Notes:
 * - Connects through App\Tests\Support\TestDatabase, which owns the server and
 *   the password.
 * - Needs vendor/ from "composer install" for the Db service.
 * - SendContactMessage gets App\Tests\Support\BruteForceDouble instead of the
 *   bruteForce service, so the run needs no counter table.
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\mysqli_report(\MYSQLI_REPORT_ERROR | \MYSQLI_REPORT_STRICT);

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

/**
 * Create an App double with the real Db service on the run's database.
 *
 * @param  string  $database  The run's database.
 * @return App
 */
function app(string $database): App {
	return TestDatabase::app($database);
}


/**
 * Empty demo_contact_messages and restart its ids at 1.
 *
 * Lets a case start from a known state, whatever the cases before it left behind.
 *
 * @param  \mysqli  $admin  The run's admin connection.
 * @return void
 */
function emptyTable(\mysqli $admin): void {
	$admin->query('TRUNCATE TABLE demo_contact_messages');
}


/**
 * Read one row of demo_contact_messages on the admin connection, past the code under test.
 *
 * @param  \mysqli  $admin  The run's admin connection.
 * @param  int  $id  Message id.
 * @return array<string,mixed>|null  Row, or null when it does not exist.
 */
function row(\mysqli $admin, int $id): ?array {
	return $admin->execute_query('SELECT id, name, email, message, created_at FROM demo_contact_messages WHERE id = ?', [$id])->fetch_assoc();
}


// ----------------------------------------------------------------
// Cases
// ----------------------------------------------------------------

$admin = TestDatabase::connect();
$database = TestDatabase::create($admin, 'contact');

try {
	$admin->select_db($database);
	TestDatabase::import($admin, \file_get_contents(\dirname(__DIR__, 2) . '/sql/citomni_demo-app.sql'));


	// -- 1. ContactMessageRepository -------------------------------------------

	test('insert() stores the trimmed message and returns its id', function () use ($admin, $database): void {
		emptyTable($admin);

		$id = (new ContactMessageRepository(app($database)))->insert("  Ada \n", ' ada@example.com ', "\n Hello CitOmni. \n");
		$row = row($admin, $id);

		check($id === 1, 'Expected id 1, got ' . $id);
		check($row['name'] === 'Ada' && $row['email'] === 'ada@example.com' && $row['message'] === 'Hello CitOmni.', 'Unexpected values: ' . \json_encode($row));
		check($row['created_at'] !== null, 'created_at is not set');
	});

	test('insert() stores values at every limit intact', function () use ($admin, $database): void {
		emptyTable($admin);

		$name = \str_repeat('é', ContactMessage::MAX_LENGTHS['name']);
		$email = \str_repeat('a', 64) . '@' . \str_repeat('b', 63) . '.' . \str_repeat('c', 63) . '.' . \str_repeat('d', 61);
		$message = \str_repeat('é', ContactMessage::MAX_LENGTHS['message']);

		$row = row($admin, (new ContactMessageRepository(app($database)))->insert($name, $email, $message));

		check(\strlen($email) === ContactMessage::MAX_LENGTHS['email'], 'The fixture address has ' . \strlen($email) . ' characters');
		check($row['name'] === $name && $row['email'] === $email && $row['message'] === $message, 'A stored value differs from the value at the limit');
	});


	// -- 2. SendContactMessage ---------------------------------------------------

	test('SendContactMessage::execute() stores the trimmed message and counts it for the e-mail address and the IP', function () use ($admin, $database): void {
		emptyTable($admin);

		$app = app($database);
		$bruteForce = new BruteForceDouble();
		$app->set('bruteForce', $bruteForce);

		$result = (new SendContactMessage($app))->execute("  Ada \n", ' ada@example.com ', "\n Hello CitOmni. \n", '203.0.113.7');
		check($result === ['status' => 'sent', 'message_id' => 1, 'retry_after_seconds' => 0, 'problems' => []], 'Unexpected result: ' . \json_encode($result));

		$row = row($admin, 1);
		check($row['name'] === 'Ada' && $row['email'] === 'ada@example.com' && $row['message'] === 'Hello CitOmni.', 'Unexpected values: ' . \json_encode($row));
		check($bruteForce->recordCalls === [['context' => 'contact', 'identifier' => 'ada@example.com', 'ip' => '203.0.113.7']], 'Unexpected record() calls: ' . \json_encode($bruteForce->recordCalls));
	});


	// -- 3. Schema ---------------------------------------------------------------

	test('The schema refuses a name or e-mail address one character above ContactMessage::MAX_LENGTHS', function () use ($admin): void {
		emptyTable($admin);

		// Strict mode, the default of MySQL 8 and MariaDB 10.6, refuses a value that does not fit.
		foreach (['name', 'email'] as $column) {
			$values = ['name' => 'Ada', 'email' => 'ada@example.com'];
			$values[$column] = \str_repeat('é', ContactMessage::MAX_LENGTHS[$column] + 1);

			$e = throws(\mysqli_sql_exception::class, fn() => $admin->execute_query(
				"INSERT INTO demo_contact_messages (name, email, message) VALUES (?, ?, 'Hello')",
				[$values['name'], $values['email']]
			));
			check(\str_contains($e->getMessage(), "'" . $column . "'"), 'Unexpected error: ' . $e->getMessage());
		}
	});
} finally {
	// The name was generated by TestDatabase::create(), which drop() verifies.
	TestDatabase::drop($admin, $database);
}

done();
