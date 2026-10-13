# Constant

A Constant class holds code-level invariants that several parts of the app must agree on: values fixed in code, not set per environment, and read by more than one class, the config files or the templates. No App, no IO, no logic.

## Use another place when

- The value differs per environment or is a setting: cfg.
- The values are a closed set that code compares, stores or transmits: Enum.
- One class owns the value and the others read it from there: a class constant on the owner, like `ContactMessage::MAX_LENGTHS`, which the Util that checks the fields owns.
- The value decides something: Policy.

## Rules

- `class Name` in `src/Constant/`, named after what its values describe, like `Route`. No `Constant` suffix; the namespace says it.
- Typed public constants only: `public const string PREFIX = '/feeds';`. No properties, no methods, no constructor. It is never instantiated.
- Constant expressions only: literals, other constants, and `\CITOMNI_APP_PATH` for paths inside the app. Never a value that differs per environment.
- A PHPDoc line on every constant: what the value means, not where it is used. The class PHPDoc states that the values must be read from here, not copied.
- Config files may use the constants, such as route keys in `config/citomni_http_routes.php`.

## Pattern

Placeholder names; replace them with the real ones. Route paths are the typical case: the route files route them, and Controllers and templates link and redirect to them.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace App\Constant;


/**
 * Route: Constants with the URL paths of the feed routes, relative to the public root.
 *
 * Gives every place that routes, links or redirects to a feed path one source for it, so
 * routes and links cannot drift apart. Those places must read the paths from here, never
 * copy them.
 *
 * Rules:
 * - Build every feed path from PREFIX.
 */
class Route {

	/** Common prefix of the feed paths. */
	public const string PREFIX = '/feeds';

	/** Path of the feed list page. */
	public const string LIST_PAGE = self::PREFIX . '/list.html';

	/** Path that refreshes one feed. {id} is the feed id. */
	public const string REFRESH = self::PREFIX . '/{id}/refresh';

}
```

```php
// config/citomni_http_routes.php
return [
	\App\Constant\Route::LIST_PAGE => [
		'controller' => \App\Http\Controller\FeedController::class,
		'action'     => 'list',
		'methods'    => ['GET'],
	],
	'regex' => [
		\App\Constant\Route::REFRESH => [
			'controller' => \App\Http\Controller\FeedController::class,
			'action'     => 'refresh',
			'methods'    => ['POST'],
		],
	],
];
```

## Example

This app has no Constant class.
