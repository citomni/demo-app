# State

A State class owns one piece of runtime state outside the database: its storage keys, TTL, shape and transitions, in the session, in files or similar. No other class reads or writes that storage directly. Callers instantiate it explicitly.

## Rules

- `class NameState` in `src/State/`, constructed with the App: `new MfaPendingState($this->app)`. Never registered in a service map.
- Owns the keys, the TTL, the stored shape and the allowed transitions. The lifecycle, for example `set -> advance -> cleared`, is shown in the Typical usage of the class PHPDoc.
- Methods name the transitions (`set()`, `advance()`, `clear()`, `consume()`); `get()` returns the current state or null.
- `get()` treats expired or malformed stored data as absent: it clears it and returns null, so callers need no separate expiry check.
- Nothing is cached on the instance unless the class documents why; instantiating it ad hoc in any caller is then safe.
- No SQL; database-backed state belongs in a Repository. No workflow coordination, no transport output, no audit events; callers own those.
- Validate stored references and payloads strictly; never trust what comes back from storage.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace App\State;

use CitOmni\Kernel\App;


/**
 * PublishConfirmationState: State of a pending publish confirmation in the session.
 *
 * Owns the session key, the TTL and the stored shape of the confirmation, so that no other
 * class reads or writes that key.
 *
 * Rules:
 * - Store only the article id and the creation time under KEY.
 *
 * Typical usage:
 *   (new PublishConfirmationState($this->app))->set($articleId);
 *
 *   // A later request, within TTL_SECONDS:
 *   $state = new PublishConfirmationState($this->app);
 *   if ($state->get() === $articleId) {
 *       $state->clear();
 *   }
 */
class PublishConfirmationState {

	/** Session key of the confirmation. */
	private const string KEY = 'demo.publish_confirmation';

	/** Seconds that a confirmation stays valid after set(). */
	private const int TTL_SECONDS = 300;


	/**
	 * Create the state for the app whose session holds it.
	 *
	 * @param  App  $app
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * Store a confirmation for one article.
	 *
	 * Replaces any earlier confirmation, so only the newest article can be confirmed.
	 *
	 * @param  int  $articleId  At least 1.
	 * @return void
	 * @throws \InvalidArgumentException  When the id is below 1.
	 */
	public function set(int $articleId): void {
		if ($articleId < 1) {
			throw new \InvalidArgumentException('Article id must be >= 1.');
		}

		$this->app->session->set(self::KEY, ['article_id' => $articleId, 'created_at' => \time()]);
	}


	/**
	 * Return the pending article id.
	 *
	 * Clears expired or malformed state as it reads it, so callers need no separate expiry check.
	 *
	 * @return int|null  Null when there is no valid confirmation.
	 */
	public function get(): ?int {
		$state = $this->app->session->get(self::KEY);

		if ($state === null) {
			return null;
		}

		$valid = \is_array($state)
			&& \is_int($state['article_id'] ?? null)
			&& \is_int($state['created_at'] ?? null)
			&& \time() - $state['created_at'] <= self::TTL_SECONDS;

		if (!$valid) {
			$this->clear();
			return null;
		}

		return $state['article_id'];
	}


	/**
	 * Remove the confirmation.
	 *
	 * Ends its lifecycle; call it once the confirmation has been used.
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->app->session->remove(self::KEY);
	}


}
```
