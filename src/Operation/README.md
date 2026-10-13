# Operation

An Operation is one concrete, transport-agnostic domain action. It owns the decision graph of a workflow, and its results are domain-shaped arrays that its adapter translates to HTTP, CLI or job output.

## When an Operation earns its existence

- The logic is shared by several adapters (HTTP and CLI, or a job handler).
- It coordinates several Repositories or side effects.
- It performs a meaningful state transition.
- It holds a non-trivial domain read or computation that would otherwise put domain rules in an adapter.

Simple persistence with no workflow goes from the adapter straight to a Repository. When asked for an Operation that would only wrap one Repository call, say so.

## Naming

- A verb-centric name for one concrete domain action: `PublishArticle`, `FinalizeOrder`, `ResetPassword`.
- No `Operation` suffix; the namespace already says it.
- No generic buckets such as `Manager`, `Helper`, `Handler` or `Manage*`.

## Contract

- `class PublishArticle extends \CitOmni\Kernel\Operation\BaseOperation`.
- Constructor contract: `new PublishArticle(App $app)`, inherited. Adapters (Controllers, Commands, job handlers) instantiate it explicitly. Never registered in a service map.
- Exactly one public entry method: `execute(...)` with domain parameters. Never `run()`, `handle()` or `__invoke()`.
- Optional `preflight(...)`: takes the domain inputs, has no side effects, and returns a domain-shaped report of what `execute()` would do or why it would refuse. Use it when callers need a read-only check first, for example before a job is enqueued or to show a confirmation step.
- `execute()` never relies on `preflight()` having run; it re-validates everything it depends on. It may take execution context that `preflight()` does not need, such as a task-execution observer, secrets or a confirmation token returned by `preflight()`.
- No other public methods. Another action is another Operation; reusable capabilities and reads go to Support, Services or Repositories.

## Boundaries

- No request parsing, CLI arguments, templates, headers, Response objects, `response->*` calls or terminal output.
- Registered Services through `$this->app->serviceId` when their contract is known. Not the adapter services: request, response, flash, tplEngine.
- `log` only for explicit domain or workflow events. Never write log files directly.
- `txt` only for domain text that is independent of transport, with the fallback text passed at the call site.

## Persistence and transactions

- SQL-free. Every database read and write goes through a Repository, instantiated inside the Operation.
- When several Repository calls must succeed or fail together, the Operation owns the transaction, whether the calls hit one Repository or several. Db is then only the transaction mechanism: `$this->app->db->transaction(callable, ?TransactionIsolation)`. It commits on success, rolls back on any exception and rethrows it.
- Isolation is chosen per transaction with `\CitOmni\Infrastructure\Enum\TransactionIsolation` (`ReadUncommitted`, `ReadCommitted`, `RepeatableRead`, `Serializable`); omit it for the session default.
- Db does not nest transactions. When a Repository or Service method owns a transaction itself, respect that and do not wrap it in another.
- Retry only when the task requires it and it is safe: on `DbQueryException::isDeadlock()`, rerun the whole transaction from the start. Never retry a single Repository call.
- Mail, outbound HTTP, notifications and best-effort logging run outside the transaction, unless the task defines another ordering.

## Results

- Return stable, documented, domain-shaped arrays, with explicit status constants for bounded result states.
- Return `void` instead when success has nothing to report and every failure throws, as in a logout. Never invent a status array that carries nothing.
- Return `never` when `execute()` cannot return normally, such as a supervisor loop that runs until the process stops.
- Return a `\Generator` only when the caller must consume results one at a time, because it may stop early, for example at a response budget, or the results do not fit in memory together. Each yielded value is a documented, domain-shaped array. Nothing in the body runs before the first iteration, so validation, `@throws` and side effects happen inside the caller's loop. Keep state changes out of a generator unless every yield leaves consistent state.
- A value returned by an adapter's callback, such as the response of a protocol library that must run inside the Operation's transaction, may pass through in a result array. The Operation never reads or changes it.
- A bounded input vocabulary that adapters and the Operation share, such as action names, is a backed enum in `src/Enum/`. Convert external strings with `tryFrom()`; job payloads and result arrays carry the backing value.
- Normalize scalar values before returning them.

## Failures

- `\InvalidArgumentException` for invalid input, checked early and cheaply.
- Let Repository, Service and Db exceptions bubble unless the task defines a recoverable fallback.
- A custom exception only when the public contract needs a domain failure that differs from the lower-layer one.

## Example

[`PublishArticle.php`](PublishArticle.php) is called by both `ArticleController::publish()` and `PublishArticleCommand`:

- `execute()` returns the same shape for every outcome: `status`, `article_id` and `reasons`.
- Expected outcomes are statuses. Invalid input throws, and Repository and Db failures bubble.
- `PublishingPolicy` makes the decision. The guarded write in `ArticleRepository::markPublished()` turns a concurrent change into the reason `state_changed`.

Tests: [`tests/articles/database.php`](../../tests/articles/database.php) runs every outcome against a real database, including `state_changed` through a competing write.

[`SendContactMessage.php`](SendContactMessage.php) shows `preflight()`:

- `ContactController::send()` calls `preflight()` before the captcha, so a refused field does not use up the captcha challenge, and `execute()` after it.
- `execute()` checks the rate limit and the fields again, because the rate limit can block the sender in between. It stores the message through `ContactMessageRepository` and only then counts it with the bruteForce service.
- Both return the same shape: `status`, `message_id`, `retry_after_seconds` and `problems`.

Tests: [`tests/contact/run.php`](../../tests/contact/run.php) runs the refusals with a `BruteForceDouble` and no db service, and [`tests/contact/database.php`](../../tests/contact/database.php) stores a message against a real database.

## Transaction example

The example writes one row and needs no transaction. If publishing also stored a revision through a `RevisionRepository`, which this app does not have, the Operation would wrap both writes:

```php
		$articles = new ArticleRepository($this->app);
		$revisions = new RevisionRepository($this->app);

		// Both writes commit together or not at all; mail goes out after the commit.
		$revisionId = $this->app->db->transaction(function () use ($articles, $revisions, $articleId): ?int {
			if (!$articles->markPublished($articleId)) {
				return null; // The state changed since the read; nothing was written.
			}

			return $revisions->insertSnapshot($articleId);
		});
```
