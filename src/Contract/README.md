# Contract

A Contract is a stable public extension interface: a seam where other code implements the interface or types against it as documented API.

An app rarely has one, because no code outside the app implements its interfaces. The app implements the Contracts of its packages instead, in the folder of the implementing role, like a job handler in [`src/JobRunner/`](../JobRunner/README.md). Packages define their Contracts; [citomni/demo-provider](https://github.com/citomni/demo-provider) is the guide for that.

## When a Contract earns its existence

- The app has a documented seam where cfg chooses among several implementations, such as storage backends.

A Contract exists because other code needs a stable seam, not because an interface looks architectural.

## Not a Contract

- Internal convenience interfaces, and interfaces created only for tests.
- An interface that mirrors one concrete class without a real extension point.
- DTOs, runtime contexts, traits, helpers and implementations.
- An interface that belongs to one layer's own pattern stays in that layer's folder.

## Rules

- `interface Name` in `src/Contract/`, named after the role that an implementation plays. No base class; a Contract is never instantiated.
- Small: only the methods the app calls. No implementation.
- The PHPDoc is the contract, because implementations are written without reading the calling code. It states:
  - What the caller passes in and what it expects back, with array shapes.
  - When it is called, and whether one instance is reused across calls.
  - The failure contract: What a thrown exception aborts.
  - What an implementation must not do, such as writing, sending transport output or keeping per-call state.
- How an implementation is plugged in is part of the contract: usually its FQCN in cfg, which the caller instantiates explicitly. Document the constructor it expects, such as no arguments or the App.
- Parameters and return values are scalars, documented arrays and enums.
- Stable: a changed signature breaks every implementation; prefer a new Contract.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace App\Contract;


/**
 * FeedSource: Contract for a source of feed entries.
 *
 * Lets cfg choose where the entries of a feed come from. An implementation is listed by
 * FQCN in cfg feeds.sources, created with new $class($app) for each refresh, and asked for
 * its entries once.
 *
 * Rules:
 * - Take the App as the only constructor argument.
 * - Read only: No writes and no output.
 */
interface FeedSource {

	/**
	 * Return the newest entries of the feed.
	 *
	 * @param  int  $limit  Maximum number of entries, at least 1.
	 * @return list<array{id:string,title:string,published_at:string}>  At most $limit entries, newest first.
	 * @throws \RuntimeException  When the entries cannot be read. Aborts the refresh of this feed; other feeds still refresh.
	 */
	public function entries(int $limit): array;

}
```

## Example

This app has no Contract. It would implement `JobHandlerInterface` of citomni/jobrunner for each job type it runs; see [`src/JobRunner/`](../JobRunner/README.md).
