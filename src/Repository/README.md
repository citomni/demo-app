# Repository

A Repository is the persistence boundary. It owns the SQL and datastore IO for one focused persistence area, and nothing else.

## Contract

- `class ArticleRepository extends \CitOmni\Kernel\Repository\BaseRepository`. Name: `<Entity>Repository`.
- Constructor contract: `new ArticleRepository(App $app)`, inherited. Callers instantiate it explicitly with `new ArticleRepository($this->app)`.
- Never registered in a service map and never reached through `$this->app->serviceId`.

## Rules

- All SQL goes through `$this->app->db`, the Db service of citomni/infrastructure. Never open a driver connection yourself. The API is in `src/Service/Db.php` of citomni/infrastructure; the usual calls are `fetchValue()`, `fetchRow()`, `fetchAll()`, `exists()`, `insert()`, `insertBatch()`, `update()`, `delete()`, `execute()` and `transaction()`.
- No transport or cross-cutting work: no request, session, cookie, csrf, flash, response or tplEngine, no mail, no translated text (txt), no general logging, no workflow decisions.
- `valueToSql` only when the Repository deliberately accepts form or UI input that must be normalized at the persistence boundary. `valueFromSql` only when it deliberately returns values shaped for edit forms. Neither is business validation or presentation formatting.
- Validate persistence-relevant input early and cheaply.
- Return stable, documented scalars or array shapes (`@return array{id:int,title:string}|null`). Cast raw row values before returning them.
- Datetime columns hold local times. The Db service derives the session time zone from PHP's timezone, which the kernel takes from cfg `locale.timezone`, so `NOW()` in SQL and PHP's clock agree. Cfg `db.timezone` replaces the derived zone; an app that sets it sets it to the zone of `locale.timezone`. A method that takes a `\DateTimeImmutable` converts it to PHP's timezone before it formats it for a column.
- A Repository-local transaction is fine for a sequence this Repository owns. A transaction that spans a workflow belongs to the Operation. Db does not nest transactions (`beginTransaction()` throws while one is active), so a method that opens its own transaction cannot run inside an Operation's transaction; say so in its PHPDoc.
- Keep methods small, boring and reviewable.

## Failures

- Let `DbConnectException`, `DbQueryException`, `ValueToSqlException` and `ValueFromSqlException` bubble. Callers rely on their type, code, previous exception and classifiers such as `DbQueryException::isDuplicateEntry()` and `isDeadlock()`.
- `\InvalidArgumentException` for invalid method input.
- A custom exception only when the public contract needs a persistence-specific failure that differs from the lower-layer one. Translate only then, and keep the original as previous.

## Changing an existing Repository

- Keep public method names, signatures, return shapes and exception behavior unless the task changes them.
- Change only what the task needs, in the file's existing style.

## Example

[`ArticleRepository.php`](ArticleRepository.php):

- `findById()` returns one row with cast values, or null.
- `findPublishedById()` puts the status in the WHERE clause, so a draft is null for public callers without a status check in each of them.
- `listPublished()` and `listDrafts()` select only the columns their callers need, with a limit.
- `insertDraft()` checks the title against the column size before it inserts.
- `markPublished()` is a guarded state change: the UPDATE only matches a draft, and the method reports whether it published.
- Status values reach SQL as parameters from `ArticleStatus`, never as literals.

[`ContactMessageRepository.php`](ContactMessageRepository.php): `insert()` trims the values and refuses only what its columns would not hold unchanged: invalid UTF-8 and values longer than the column. The form rules of the `ContactMessage` Util belong to its caller, `SendContactMessage`.

Tests: [`tests/articles/database.php`](../../tests/articles/database.php) and [`tests/contact/database.php`](../../tests/contact/database.php), against the real schema through the real Db service.
