# Articles suite

Checks for the article example: `\App\Policy\PublishingPolicy`, `\App\Repository\ArticleRepository` and `\App\Operation\PublishArticle`. The real classes run on the kernel doubles in `tests/Support/Kernel/`, without Composer. The suite has two entry points, which `tests/run.php` runs as separate executions.

| Entry point | Needs |
|---|---|
| `run.php` | Nothing: isolated checks with an app double that has no services. |
| `database.php` | A MySQL/MariaDB server, and `vendor/` from `composer install` for the real Db service of citomni/infrastructure. |

## Run

```
php tests/articles/run.php
php tests/articles/database.php
```

Expected results, in that order:

```
8 passed, 0 failed
12 passed, 0 failed, 1 skipped
```

The skipped check starts several worker processes at once. It runs only when `CITOMNI_TEST_PARALLEL` is `1`:

```
:: cmd
set "CITOMNI_TEST_PARALLEL=1"
php tests/articles/database.php

# PowerShell
$env:CITOMNI_TEST_PARALLEL = '1'
php tests/articles/database.php
```

Expected result of `database.php` with the multi-process check:

```
13 passed, 0 failed
```

`PASS` and `SKIP` lines go to stdout, `FAIL` lines to stderr. The last line holds the totals, and the exit code is 1 when a check fails.

## Database

`database.php` connects as `root` to `127.0.0.1:3306` over TCP. The password is read from `CITOMNI_TEST_PASSWORD`; when it is not set, no password is sent. Use a local server whose databases may be created and dropped. The app's configuration is never loaded. The workers inherit the password through the environment; it is never passed on a command line.

Each run creates its own `citomni_demo_app_articles_test_<random>` database (`utf8mb4_unicode_ci`), imports `sql/citomni_demo-app.sql` and drops only that database in `finally`. Every check starts from an empty `demo_articles` table. If a run is interrupted, remove any leftover `citomni_demo_app_articles_test_*` database after checking that no run is still going.

When the server is down, `database.php` fails; nothing is skipped.

## How it works

| File | Role |
|---|---|
| `run.php` | Isolated checks. The app double registers no `db` service, so code that reached the database would fail with "Unknown app component: app->db" instead of the expected exception. |
| `database.php` | Checks against the real schema through the real Db service. |
| `worker.php` | Child for the multi-process check. Opens its connection, reports ready through a file, waits for the start gate, then publishes through the real `PublishArticle` and writes the result as JSON. The gate opens only when every worker is ready. Ready files, gate and output live in a run directory below the system temp directory, which the check removes. Every wait has a deadline, and a failure message quotes each worker's stdout and stderr. |

The state_changed check needs a competing write between the Operation's read and its write. It wraps the real Db service: right before the first `execute()`, the wrapper publishes the article on the admin connection. That check, and the second call in the markPublished() check, fail when `markPublished()` loses its `AND status = ?` guard. The multi-process check confirms the outcome under real concurrency, but cannot reliably catch that regression: a second write within the same second leaves the row unchanged, and the server then reports 0 affected rows.

The listPublished() check pins the documented order, but it proves the `id DESC` tie-breaker only when the server sorts the rows itself, as MariaDB 10.11 does for this small fixture. A server that reads `ix_demo_articles_status_published` backwards returns tied rows in the same order without the tie-breaker.

The PublishArticle guard check also checks where the exception was thrown: `ArticleRepository::findById()` rejects the same id with the same exception, so without that check the case would pass without the Operation's guard.

## Checks

| Check | Covers |
|---|---|
| PublishingPolicy allows a draft that reaches the word minimum | The minimum is inclusive |
| PublishingPolicy refuses a draft below the word minimum with body_too_short | One word short, empty body |
| PublishingPolicy refuses every status but draft with not_draft | `published` and unknown statuses |
| PublishingPolicy lists every reason, in check order | Several reasons at once |
| PublishingPolicy reads the word minimum from cfg articles.min_words_to_publish | The cfg key |
| ArticleRepository rejects ids and limits below 1 before it reaches the database | Argument guards |
| ArticleRepository rejects an empty or too long title before it reaches the database | Title guards |
| PublishArticle rejects an article id below 1 with its own guard | Argument guard, throw site |
| insertDraft() stores a trimmed draft and returns its id | Insert, defaults |
| insertDraft() stores a title of exactly 200 characters intact | The limit counts characters, as the column does |
| findById() returns the article with cast values, or null | Row shape, types, missing id |
| findPublishedById() returns a published article with cast values, and null for a draft or a missing id | The status filter, row shape, types; fails when the `AND status = ?` filter is removed |
| listPublished() returns only published articles, newest first, within the limit | Filter, order with `id` as tie-breaker, limit, row shape |
| listDrafts() returns only drafts, newest first, within the limit | Filter, order, limit, row shape |
| markPublished() publishes a draft once and stamps published_at | Guarded state change |
| The schema refuses a status that is not exactly draft or published, and a published row without published_at | The two check constraints in `sql/citomni_demo-app.sql`, by name: an unknown status, trailing and leading spaces, another letter case, a tab and `''`; the exact value is stored |
| PublishArticle publishes a draft that PublishingPolicy allows | `published` |
| PublishArticle returns not_found for a missing article | `not_found` |
| PublishArticle refuses with the reasons of PublishingPolicy and changes nothing | `refused` with policy reasons |
| PublishArticle reports state_changed when the article is published between its read and its write | `refused` with `state_changed` |
| Concurrent PublishArticle calls publish a draft exactly once | With `CITOMNI_TEST_PARALLEL=1` only: 4 processes publish the same draft at once. One must win; the others are refused with `not_draft` or `state_changed`. |

`php tests/run.php` runs every suite in the app.
