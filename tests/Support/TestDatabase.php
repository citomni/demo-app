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

use App\Tests\Support\Kernel\App;
use CitOmni\Infrastructure\Service\Db;

/**
 * TestDatabase: Test support for suites that run against a real database server.
 *
 * Gives each database suite a database of its own on the local test server, and an App double
 * whose db service is the real Db service of citomni/infrastructure on that database. The
 * app's own configuration is never loaded.
 *
 * Rules:
 * - Read the password only from CITOMNI_TEST_PASSWORD. Never write it to disk, print it or
 *   pass it on a command line.
 * - Drop only databases whose name has the form that create() generates.
 *
 * Typical usage:
 *   $admin = TestDatabase::connect();
 *   $database = TestDatabase::create($admin, 'articles');
 *   try {
 *       $admin->select_db($database);
 *       TestDatabase::import($admin, \file_get_contents($root . '/sql/citomni_demo-app.sql'));
 *       $app = TestDatabase::app($database, ['articles' => ['min_words_to_publish' => 5]]);
 *   } finally {
 *       TestDatabase::drop($admin, $database);
 *   }
 */
class TestDatabase {

	/** Prefix of every database that a suite of this app creates. */
	private const NAME_PREFIX = 'citomni_demo_app_';


	/**
	 * Connect to the test server as root, optionally with a default database.
	 *
	 * Behavior:
	 * - Connects to 127.0.0.1:3306 over TCP and uses the utf8mb4 charset.
	 * - Uses CITOMNI_TEST_PASSWORD as the password; unset means no password.
	 *
	 * @param  string  $database  Database to select, or '' for none.
	 * @return \mysqli
	 */
	public static function connect(string $database = ''): \mysqli {
		// Unset CITOMNI_TEST_PASSWORD means no password.
		$connection = new \mysqli('127.0.0.1', 'root', (string)\getenv('CITOMNI_TEST_PASSWORD'), $database, 3306);
		$connection->set_charset('utf8mb4');

		return $connection;
	}


	/**
	 * Create the run's database.
	 *
	 * A random name gives every run a database of its own, so runs never share data, and lets
	 * drop() tell a suite's database from any other.
	 *
	 * @param  \mysqli  $admin  Connection without a default database.
	 * @param  string  $suite  Suite name: lowercase letters, digits and underscores.
	 * @return string  Name of the new database: citomni_demo_app_{suite}_test_{12 hex digits}.
	 * @throws \InvalidArgumentException  When the suite name has other characters.
	 */
	public static function create(\mysqli $admin, string $suite): string {
		if (\preg_match('/^[a-z0-9_]+$/', $suite) !== 1) {
			throw new \InvalidArgumentException("Invalid suite name for a test database: '{$suite}'");
		}

		$database = self::NAME_PREFIX . $suite . '_test_' . \bin2hex(\random_bytes(6));
		$admin->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

		return $database;
	}


	/**
	 * Drop a database that create() made.
	 *
	 * Refuses any other name, so a wrong argument cannot drop a database that no suite created.
	 *
	 * @param  \mysqli  $admin  Connection to the test server.
	 * @param  string  $database  Name returned by create().
	 * @return void
	 * @throws \LogicException  When the name does not have the form that create() generates.
	 */
	public static function drop(\mysqli $admin, string $database): void {
		if (\preg_match('/^citomni_demo_app_[a-z0-9_]+_test_[0-9a-f]{12}$/', $database) !== 1) {
			throw new \LogicException('Refusing to drop a database that no suite created: ' . $database);
		}

		$admin->query('DROP DATABASE IF EXISTS `' . $database . '`');
	}


	/**
	 * Run an SQL script with one or more statements.
	 *
	 * Reads and discards every result, so the connection is ready for the next query.
	 *
	 * @param  \mysqli  $connection  Connection with the target database selected.
	 * @param  string  $sql  Script, such as the content of sql/citomni_demo-app.sql.
	 * @return void
	 */
	public static function import(\mysqli $connection, string $sql): void {
		$connection->multi_query($sql);

		do {
			$result = $connection->store_result();
			if ($result instanceof \mysqli_result) {
				$result->free();
			}
		} while ($connection->more_results() && $connection->next_result());
	}


	/**
	 * Create an App double with the real Db service on the run's database.
	 *
	 * @param  string  $database  Name returned by create().
	 * @param  array<string,mixed>  $cfg  Further cfg nodes that the code under test reads.
	 * @return App  App with the services db and secrets.
	 * @throws \RuntimeException  When citomni/infrastructure is missing from vendor/, because "composer install" has not run.
	 */
	public static function app(string $database, array $cfg = []): App {
		if (!\class_exists(Db::class)) {
			throw new \RuntimeException('citomni/infrastructure was not found in vendor/. Run "composer install" in the app root first.');
		}

		$app = new App(['db' => [
			'host'    => '127.0.0.1',
			'user'    => 'root',
			'name'    => $database,
			'port'    => 3306,
			'charset' => 'utf8mb4',
		]] + $cfg);

		// A secrets service that answers only db.password, from memory, so the password never
		// reaches a file.
		$app->set('secrets', new class((string)\getenv('CITOMNI_TEST_PASSWORD')) {

			/**
			 * Keep the password in memory only.
			 *
			 * @param  string  $password  Password of the test server; '' for none.
			 */
			public function __construct(private readonly string $password) {
			}

			/**
			 * Return the secret stored under the key.
			 *
			 * @param  string  $key
			 * @return string
			 * @throws \OutOfBoundsException  When the key is not db.password.
			 */
			public function get(string $key): string {
				return $key === 'db.password' ? $this->password : throw new \OutOfBoundsException("Unknown secret: '{$key}'");
			}

			/**
			 * Keep the password out of var_dump() output.
			 *
			 * @return array{}
			 */
			public function __debugInfo(): array {
				return [];
			}

		});

		$app->set('db', new Db($app));

		return $app;
	}

}
