# Contact suite

Checks for the contact form: `\App\Util\ContactMessage`, `\App\Repository\ContactMessageRepository` and `\App\Operation\SendContactMessage`. The real classes run on the kernel doubles in `tests/Support/Kernel/`, without Composer. `SendContactMessage` gets `\App\Tests\Support\BruteForceDouble` instead of the bruteForce service, so no case needs its counter table or cfg. The suite has two entry points, which `tests/run.php` runs as separate executions.

| Entry point | Needs |
|---|---|
| `run.php` | Nothing: isolated checks with an app double that has no db service. |
| `database.php` | A MySQL/MariaDB server, and `vendor/` from `composer install` for the real Db service of citomni/infrastructure. |

`ContactController` itself is not covered: the suites test the layers below the adapters.

## Run

```
php tests/contact/run.php
php tests/contact/database.php
```

Expected results, in that order:

```
10 passed, 0 failed
4 passed, 0 failed
```

`PASS` lines go to stdout, `FAIL` lines to stderr. The last line holds the totals, and the exit code is 1 when a check fails.

## Database

`database.php` connects as `root` to `127.0.0.1:3306` over TCP, with the password from `CITOMNI_TEST_PASSWORD`, as described in [`tests/README.md`](../README.md). Each run creates its own `citomni_demo_app_contact_test_<random>` database, imports `sql/citomni_demo-app.sql` and drops only that database in `finally`. Every check starts from an empty `demo_contact_messages` table. If a run is interrupted, remove any leftover `citomni_demo_app_contact_test_*` database after checking that no run is still going.

## How it works

`ContactMessage::MAX_LENGTHS` must match the columns of `demo_contact_messages`. Two checks pin that from both sides: the insert at every limit fails when a limit is larger than its column, and the schema check fails when a column is larger than its limit.

## Checks

| Check | Covers |
|---|---|
| ContactMessage accepts a message at every limit | Limits are inclusive and count characters; a 254-character address passes the format check |
| ContactMessage reports required for every empty field, in form order | `required`, field order |
| ContactMessage reports too_long one character above each limit | `too_long` for all three fields |
| ContactMessage reports invalid for a malformed e-mail address and for invalid UTF-8 | `invalid` |
| ContactMessageRepository refuses a value that does not fit its column before it reaches the database | The guard: invalid UTF-8, characters for `name` and `email`, bytes for `message` |
| ContactMessageRepository checks the trimmed values and leaves the form rules to its callers | Trimming before the check; a message that `ContactMessage` refuses reaches the database |
| SendContactMessage accepts a valid message: preflight() reports ok, and execute() stores it before it counts it | `ok`, the trimmed address in `status()`, nothing counted by `preflight()` or before the insert |
| SendContactMessage reports invalid with the problems of the trimmed fields, and neither stores nor counts the message | `invalid` with all three reasons, from both methods |
| SendContactMessage reports rate_limited before it checks the fields, and neither stores nor counts the message | `rate_limited` with `retry_after_seconds`, ahead of invalid fields |
| SendContactMessage asks for no rate limit without an e-mail address and an IP | No `status()` call without a sender |
| insert() stores the trimmed message and returns its id | Insert, trimming, `created_at` |
| insert() stores values at every limit intact | Multibyte values at the limits; fails when a limit exceeds its column |
| SendContactMessage::execute() stores the trimmed message and counts it for the e-mail address and the IP | `sent` with `message_id`, the stored row, one `record()` call |
| The schema refuses a name or e-mail address one character above ContactMessage::MAX_LENGTHS | The `name` and `email` column sizes, by column name; fails when a column exceeds its limit |

`php tests/run.php` runs every suite in the app.
