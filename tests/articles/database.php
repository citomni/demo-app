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
use App\Repository\ArticleRepository;
use App\Tests\Support\Kernel\App;
use App\Tests\Support\TestDatabase;

/*
 * Database suite for the article example against a real MySQL/MariaDB server:
 * the SQL of ArticleRepository on the schema in sql/citomni_demo-app.sql, and
 * PublishArticle end to end, through the real Db service of citomni/infrastructure.
 *
 * The run creates one database, citomni_demo_app_articles_test_<random>, imports
 * sql/citomni_demo-app.sql into it and drops only that database in finally. Every
 * case starts from an empty demo_articles table.
 *
 * Usage:
 *   php tests/articles/database.php
 *
 * Notes:
 * - Connects through App\Tests\Support\TestDatabase, which owns the server and
 *   the password. Workers inherit the environment, the password included.
 * - Needs vendor/ from "composer install" for the Db service.
 * - The multi-process check runs only with CITOMNI_TEST_PARALLEL=1. It starts
 *   tests/articles/worker.php several times at once. Their ready signals, start
 *   gate and output are files in a run directory below the system temp
 *   directory, which the check removes; every wait has a deadline.
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\mysqli_report(\MYSQLI_REPORT_ERROR | \MYSQLI_REPORT_STRICT);

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


/** Word minimum in the cases' cfg; small, so the fixtures stay readable. */
const MIN_WORDS = 5;

/** Number of worker processes in the multi-process check. */
const RACE_WORKERS = 4;


// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

/**
 * Create an App double with the real Db service on the run's database and the word minimum in cfg.
 *
 * @param  string  $database  The run's database.
 * @return App
 */
function app(string $database): App {
	return TestDatabase::app($database, ['articles' => ['min_words_to_publish' => MIN_WORDS]]);
}


/**
 * Empty demo_articles and restart its ids at 1.
 *
 * Lets a case start from a known state, whatever the cases before it left behind.
 *
 * @param  \mysqli  $admin  The run's admin connection.
 * @return void
 */
function emptyTable(\mysqli $admin): void {
	$admin->query('TRUNCATE TABLE demo_articles');
}


/**
 * Read one row of demo_articles on the admin connection, past the code under test.
 *
 * @param  \mysqli  $admin  The run's admin connection.
 * @param  int  $id  Article id.
 * @return array<string,mixed>|null  Row, or null when it does not exist.
 */
function row(\mysqli $admin, int $id): ?array {
	return $admin->execute_query('SELECT id, title, body, status, created_at, published_at FROM demo_articles WHERE id = ?', [$id])->fetch_assoc();
}


/**
 * Build a body of the given number of words.
 *
 * @param  int  $words
 * @return string
 */
function body(int $words): string {
	return \trim(\str_repeat('word ', $words));
}


/**
 * Create the multi-process check's run directory below the system temp directory.
 *
 * A random name keeps parallel runs apart and lets removeRaceDir() recognize the directory.
 *
 * @return string  Absolute path ending in citomni_demo_app_articles_race_{12 hex digits}.
 */
function createRaceDir(): string {
	$dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'citomni_demo_app_articles_race_' . \bin2hex(\random_bytes(6));
	\mkdir($dir, 0700);

	return $dir;
}


/**
 * Remove a directory that createRaceDir() made, with its files.
 *
 * Leaves any other path alone, so a wrong argument cannot delete anything else.
 *
 * @param  string  $dir  Path returned by createRaceDir().
 * @return void
 */
function removeRaceDir(string $dir): void {
	if (\preg_match('/citomni_demo_app_articles_race_[0-9a-f]{12}$/', $dir) !== 1 || !\is_dir($dir)) {
		return;
	}

	foreach (\scandir($dir) as $entry) {
		if ($entry !== '.' && $entry !== '..') {
			\unlink($dir . \DIRECTORY_SEPARATOR . $entry);
		}
	}
	\rmdir($dir);
}


/**
 * Start worker number $n.
 *
 * The worker writes its stdout and stderr to files in the run directory, so that a failure
 * message can show what it wrote.
 *
 * @param  string  $dir  Run directory.
 * @param  int  $n  Worker number.
 * @param  string  $database  The run's database.
 * @param  int  $articleId  Article the worker publishes.
 * @return resource  Process handle from proc_open().
 * @throws \RuntimeException  When the process cannot start.
 */
function startWorker(string $dir, int $n, string $database, int $articleId) {
	// Same php.ini as this process: the loaded file, the default lookup, or none at all.
	$iniArgs = match (true) {
		\php_ini_loaded_file() !== false   => ['-c', \php_ini_loaded_file()],
		\php_ini_scanned_files() !== false => [],
		default                            => ['-n'],
	};

	// No environment is passed, so the worker inherits CITOMNI_TEST_PASSWORD.
	$proc = \proc_open(
		[\PHP_BINARY, ...$iniArgs, __DIR__ . '/worker.php', $database, (string)$articleId, "{$dir}/ready{$n}", "{$dir}/go"],
		[
			0 => ['pipe', 'r'],
			1 => ['file', "{$dir}/out{$n}", 'w'],
			2 => ['file', "{$dir}/err{$n}", 'w'],
		],
		$pipes
	);
	if (!\is_resource($proc)) {
		throw new \RuntimeException('Cannot start worker.php');
	}
	\fclose($pipes[0]);

	return $proc;
}


/**
 * Wait until every file exists, or until the time is up.
 *
 * @param  list<string>  $files  Paths.
 * @param  float  $seconds  Longest wait.
 * @return bool  True when every file exists.
 */
function waitForFiles(array $files, float $seconds): bool {
	$deadline = \microtime(true) + $seconds;

	do {
		\clearstatcache();
		if (\array_filter($files, static fn(string $file): bool => !\is_file($file)) === []) {
			return true;
		}
		\usleep(5_000);
	} while (\microtime(true) < $deadline);

	return false;
}


/**
 * Wait for a worker to exit, and stop it when the deadline passes.
 *
 * A worker that hangs must not hang the suite.
 *
 * @param  resource  $proc  Process handle from startWorker().
 * @param  float  $deadline  microtime(true) value after which the worker is stopped.
 * @return int  Exit code, or -1 when the worker had to be stopped.
 */
function waitForExit($proc, float $deadline): int {
	while (($status = \proc_get_status($proc))['running']) {
		if (\microtime(true) > $deadline) {
			\proc_terminate($proc);
			\proc_close($proc);

			return -1;
		}
		\usleep(5_000);
	}
	\proc_close($proc);

	return $status['exitcode'];
}


/**
 * Read what worker number $n wrote, for a failure message.
 *
 * @param  string  $dir  Run directory.
 * @param  int  $n  Worker number.
 * @return string  Its stdout and stderr.
 */
function workerOutput(string $dir, int $n): string {
	$read = static fn(string $file): string => \is_file($file) ? \trim((string)\file_get_contents($file)) : '';

	return "worker {$n}: stdout [" . $read("{$dir}/out{$n}") . '], stderr [' . $read("{$dir}/err{$n}") . ']';
}


// ----------------------------------------------------------------
// Cases
// ----------------------------------------------------------------

$admin = TestDatabase::connect();
$database = TestDatabase::create($admin, 'articles');

try {
	$admin->select_db($database);
	TestDatabase::import($admin, \file_get_contents(\dirname(__DIR__, 2) . '/sql/citomni_demo-app.sql'));


	// -- 1. ArticleRepository --------------------------------------------------

	test('insertDraft() stores a trimmed draft and returns its id', function () use ($admin, $database): void {
		emptyTable($admin);

		$id = (new ArticleRepository(app($database)))->insertDraft("  Hello CitOmni \n", 'First body.');
		$row = row($admin, $id);

		check($id === 1, 'Expected id 1, got ' . $id);
		check($row['title'] === 'Hello CitOmni', 'Title was not trimmed: ' . $row['title']);
		check($row['body'] === 'First body.', 'Body changed: ' . $row['body']);
		check($row['status'] === 'draft', 'Status is ' . $row['status']);
		check($row['created_at'] !== null && $row['published_at'] === null, 'Unexpected timestamps');
	});

	test('insertDraft() stores a title of exactly 200 characters intact', function () use ($admin, $database): void {
		emptyTable($admin);

		$title = \str_repeat('é', 200);
		$id = (new ArticleRepository(app($database)))->insertDraft($title, '');

		check(row($admin, $id)['title'] === $title, 'The stored title differs from the 200-character title');
	});

	test('findById() returns the article with cast values, or null', function () use ($admin, $database): void {
		emptyTable($admin);
		$articles = new ArticleRepository(app($database));

		$id = $articles->insertDraft('Title', 'Body');
		$article = $articles->findById($id);

		check(\array_keys($article) === ['id', 'title', 'body', 'status', 'created_at', 'published_at'], 'Unexpected keys: ' . \implode(', ', \array_keys($article)));
		check($article['id'] === $id && $article['title'] === 'Title' && $article['body'] === 'Body' && $article['status'] === 'draft', 'Unexpected values');
		check(\is_string($article['created_at']) && $article['published_at'] === null, 'Unexpected timestamps');
		check($articles->findById($id + 1) === null, 'A missing id returned a row');
	});

	test('findPublishedById() returns a published article with cast values, and null for a draft or a missing id', function () use ($admin, $database): void {
		emptyTable($admin);
		$admin->query(
			"INSERT INTO demo_articles (id, title, body, status, published_at) VALUES
			 (1, 'Published', 'Body', 'published', '2026-01-01 10:00:00'),
			 (2, 'Draft', 'Body', 'draft', NULL)"
		);
		$articles = new ArticleRepository(app($database));

		check($articles->findPublishedById(1) === ['id' => 1, 'title' => 'Published', 'body' => 'Body', 'published_at' => '2026-01-01 10:00:00'], 'Unexpected row shape or values');
		check($articles->findPublishedById(2) === null, 'A draft was returned');
		check($articles->findPublishedById(3) === null, 'A missing id returned a row');
	});

	test('listPublished() returns only published articles, newest first, within the limit', function () use ($admin, $database): void {
		emptyTable($admin);
		$admin->query(
			"INSERT INTO demo_articles (id, title, body, status, published_at) VALUES
			 (1, 'Oldest', 'x', 'published', '2026-01-01 10:00:00'),
			 (2, 'Draft', 'x', 'draft', NULL),
			 (3, 'Newest', 'x', 'published', '2026-01-03 10:00:00'),
			 (4, 'Same time, lower id', 'x', 'published', '2026-01-02 10:00:00'),
			 (5, 'Same time, higher id', 'x', 'published', '2026-01-02 10:00:00')"
		);
		$articles = new ArticleRepository(app($database));

		$all = $articles->listPublished(10);
		check(\array_column($all, 'id') === [3, 5, 4, 1], 'Unexpected order: ' . \implode(', ', \array_column($all, 'id')));
		check($all[0] === ['id' => 3, 'title' => 'Newest', 'published_at' => '2026-01-03 10:00:00'], 'Unexpected row shape or values');
		check(\array_column($articles->listPublished(2), 'id') === [3, 5], 'The limit was not applied');
	});

	test('listDrafts() returns only drafts, newest first, within the limit', function () use ($admin, $database): void {
		emptyTable($admin);
		$admin->query(
			"INSERT INTO demo_articles (id, title, body, status, created_at, published_at) VALUES
			 (1, 'Oldest draft', 'a', 'draft', '2026-01-01 10:00:00', NULL),
			 (2, 'Published', 'b', 'published', '2026-01-05 10:00:00', '2026-01-05 11:00:00'),
			 (3, 'Newest draft', 'c', 'draft', '2026-01-03 10:00:00', NULL),
			 (4, 'Middle draft', 'd', 'draft', '2026-01-02 10:00:00', NULL)"
		);
		$articles = new ArticleRepository(app($database));

		check(\array_column($articles->listDrafts(10), 'id') === [3, 4, 1], 'Unexpected order');
		check($articles->listDrafts(1) === [['id' => 3, 'title' => 'Newest draft', 'body' => 'c', 'status' => 'draft']], 'Unexpected row shape, values or limit');
	});

	test('markPublished() publishes a draft once and stamps published_at', function () use ($admin, $database): void {
		emptyTable($admin);
		$articles = new ArticleRepository(app($database));
		$id = $articles->insertDraft('Title', body(MIN_WORDS));

		check($articles->markPublished($id) === true, 'The first call did not publish');
		$published = row($admin, $id);
		check($published['status'] === 'published' && $published['published_at'] !== null, 'The row was not published');

		// An old stamp makes a second write visible: NOW() differs from it, whereas
		// a write within the same second would leave the row unchanged.
		$admin->execute_query("UPDATE demo_articles SET published_at = '2026-01-01 00:00:00' WHERE id = ?", [$id]);
		check($articles->markPublished($id) === false, 'The second call published again');
		check(row($admin, $id)['published_at'] === '2026-01-01 00:00:00', 'The second call changed published_at');
		check($articles->markPublished($id + 1) === false, 'A missing id was published');
	});

	test('The schema refuses a status that is not exactly draft or published, and a published row without published_at', function () use ($admin): void {
		emptyTable($admin);

		// MySQL and MariaDB both name the violated constraint in the message. The collation of the
		// column ignores trailing spaces in comparisons, so only a check that compares bytes refuses
		// them. Every row has published_at, so only the status check can refuse it.
		foreach (['archived', 'draft ', 'published  ', ' draft', 'Draft', "draft\t", ''] as $status) {
			$e = throws(\mysqli_sql_exception::class, fn() => $admin->execute_query("INSERT INTO demo_articles (title, body, status, published_at) VALUES ('Title', 'Body', ?, '2026-01-01 10:00:00')", [$status]));
			check(\str_contains($e->getMessage(), 'chk_demo_articles_status'), \json_encode($status) . ': unexpected error: ' . $e->getMessage());
		}

		$admin->query("INSERT INTO demo_articles (title, body) VALUES ('Title', 'Body')");
		$e = throws(\mysqli_sql_exception::class, fn() => $admin->query("UPDATE demo_articles SET status = 'published' WHERE id = 1"));
		check(\str_contains($e->getMessage(), 'chk_demo_articles_published_at'), 'Unexpected error: ' . $e->getMessage());

		$admin->query("UPDATE demo_articles SET status = 'published', published_at = '2026-01-01 10:00:00' WHERE id = 1");
		check(row($admin, 1)['status'] === 'published', 'The exact value was not stored as given');
	});


	// -- 2. PublishArticle -----------------------------------------------------

	test('PublishArticle publishes a draft that PublishingPolicy allows', function () use ($admin, $database): void {
		emptyTable($admin);
		$app = app($database);
		$id = (new ArticleRepository($app))->insertDraft('Title', body(MIN_WORDS));

		$result = (new PublishArticle($app))->execute($id);

		check($result === ['status' => 'published', 'article_id' => $id, 'reasons' => []], 'Unexpected result: ' . \json_encode($result));
		check(row($admin, $id)['status'] === 'published', 'The row was not published');
	});

	test('PublishArticle returns not_found for a missing article', function () use ($admin, $database): void {
		emptyTable($admin);

		$result = (new PublishArticle(app($database)))->execute(42);

		check($result === ['status' => 'not_found', 'article_id' => 42, 'reasons' => []], 'Unexpected result: ' . \json_encode($result));
	});

	test('PublishArticle refuses with the reasons of PublishingPolicy and changes nothing', function () use ($admin, $database): void {
		emptyTable($admin);
		$app = app($database);
		$articles = new ArticleRepository($app);

		$short = $articles->insertDraft('Short', body(MIN_WORDS - 1));
		$result = (new PublishArticle($app))->execute($short);
		check($result === ['status' => 'refused', 'article_id' => $short, 'reasons' => ['body_too_short']], 'Unexpected result: ' . \json_encode($result));
		check(row($admin, $short)['status'] === 'draft', 'A refused draft was published');

		$published = $articles->insertDraft('Published', body(MIN_WORDS));
		$articles->markPublished($published);
		$before = row($admin, $published);
		$result = (new PublishArticle($app))->execute($published);
		check($result === ['status' => 'refused', 'article_id' => $published, 'reasons' => ['not_draft']], 'Unexpected result: ' . \json_encode($result));
		check(row($admin, $published) === $before, 'Refusing a published article changed it');
	});

	test('PublishArticle reports state_changed when the article is published between its read and its write', function () use ($admin, $database): void {
		emptyTable($admin);
		$app = app($database);
		$id = (new ArticleRepository($app))->insertDraft('Title', body(MIN_WORDS));
		$competitorPublishedAt = '2026-01-01 12:00:00';

		// The real Db service, wrapped. Right before the first write, a competing
		// writer publishes the same article on the admin connection: PublishingPolicy
		// has approved a draft that is no longer a draft when the UPDATE runs.
		$app->set('db', new class($app->db, $admin, $id, $competitorPublishedAt) {

			/** Whether the competing write has run. */
			private bool $competed = false;

			/**
			 * Wrap the real Db service.
			 *
			 * @param  object  $db  Real Db service.
			 * @param  \mysqli  $admin  Connection of the competing writer.
			 * @param  int  $id  Article that the competing writer publishes.
			 * @param  string  $publishedAt  published_at that the competing writer stamps.
			 */
			public function __construct(private readonly object $db, private readonly \mysqli $admin, private readonly int $id, private readonly string $publishedAt) {
			}

			/**
			 * Run the competing write once, then the statement on the real Db service.
			 *
			 * @param  string  $sql
			 * @param  array  $params  Bound parameters, as the real Db service takes them.
			 * @return int  Affected rows, as the real Db service reports them.
			 */
			public function execute(string $sql, array $params = []): int {
				if (!$this->competed) {
					$this->competed = true;
					$this->admin->execute_query("UPDATE demo_articles SET status = 'published', published_at = ? WHERE id = ?", [$this->publishedAt, $this->id]);
				}

				return $this->db->execute($sql, $params);
			}

			/**
			 * Pass every other call to the real Db service.
			 *
			 * @param  string  $method
			 * @param  list<mixed>  $args
			 * @return mixed
			 */
			public function __call(string $method, array $args): mixed {
				return $this->db->{$method}(...$args);
			}

		});

		$result = (new PublishArticle($app))->execute($id);

		check($result === ['status' => 'refused', 'article_id' => $id, 'reasons' => ['state_changed']], 'Unexpected result: ' . \json_encode($result));
		check(row($admin, $id)['published_at'] === $competitorPublishedAt, 'The Operation overwrote the competing publish');
	});


	// -- 3. Optional multi-process check ---------------------------------------

	$raceName = 'Concurrent PublishArticle calls publish a draft exactly once';

	if (\getenv('CITOMNI_TEST_PARALLEL') !== '1') {
		skip($raceName, 'set CITOMNI_TEST_PARALLEL=1 to run multi-process checks');
	} else {
		test($raceName, function () use ($admin, $database): void {
			emptyTable($admin);
			$id = (new ArticleRepository(app($database)))->insertDraft('Race', body(MIN_WORDS));

			$dir = createRaceDir();
			$procs = [];
			try {
				for ($n = 0; $n < RACE_WORKERS; $n++) {
					$procs[$n] = startWorker($dir, $n, $database, $id);
				}

				// Open the start gate only when every worker is connected and ready.
				$ready = waitForFiles(\array_map(static fn(int $n): string => "{$dir}/ready{$n}", \array_keys($procs)), 10.0);
				\touch("{$dir}/go");

				$exits = [];
				$deadline = \microtime(true) + 20.0;
				foreach ($procs as $n => $proc) {
					$exits[$n] = waitForExit($proc, $deadline);
					unset($procs[$n]);
				}

				$report = \implode('; ', \array_map(static fn(int $n): string => workerOutput($dir, $n), \array_keys($exits)));
				check($ready, 'Not every worker was ready within 10 seconds. ' . $report);
				check($exits === \array_fill(0, RACE_WORKERS, 0), 'Exit codes ' . \implode(', ', $exits) . '. ' . $report);

				$published = 0;
				foreach (\array_keys($exits) as $n) {
					$result = \json_decode((string)\file_get_contents("{$dir}/out{$n}"), true, 512, \JSON_THROW_ON_ERROR);
					if ($result['status'] === 'published') {
						++$published;
						continue;
					}
					// A loser either read the article after the winner's commit (not_draft)
					// or waited for the winner's row lock (state_changed).
					check($result['status'] === 'refused' && \in_array($result['reasons'], [['not_draft'], ['state_changed']], true), 'Unexpected result. ' . workerOutput($dir, $n));
				}

				check($published === 1, $published . ' workers published the article. ' . $report);
				check(row($admin, $id)['status'] === 'published', 'The article is not published');
			} finally {
				// Only workers that an exception left behind are still listed.
				foreach ($procs as $proc) {
					\proc_terminate($proc);
					\proc_close($proc);
				}
				removeRaceDir($dir);
			}
		});
	}
} finally {
	// The name was generated by TestDatabase::create(), which drop() verifies.
	TestDatabase::drop($admin, $database);
}

done();
