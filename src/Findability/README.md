# Findability

A findability provider shows citomni/findability the app's own content: its pages in the sitemaps, its pages as targets in the menu editor, its tagged content as cards on the tag pages, and its content as documents in the site search. The packages that own content ship their own providers; the app writes providers only for the content that it owns itself. The app keeps its data, URLs and publishing rules; findability keeps only what its own features need.

## Setup

This app does not use citomni/findability yet. Before its first provider: `composer require citomni/findability`, add `\CitOmni\Findability\Boot\Registry::class` to `config/providers.php` as its README describes, and import `vendor/citomni/findability/sql/citomni_findability.sql`.

## Contract

| Interface in `CitOmni\Findability\Contract` | Registered in | Keyed by | Findability asks it for |
|---|---|---|---|
| `SitemapSource` | `findability.sitemaps` in `config/citomni_http_cfg.php` | Source name | The public URLs, counted and in windows |
| `MenuTargetSource` | `findability.menu_targets` in `config/citomni_http_cfg.php` | Source type | The pages that an editor searches for in the menu editor |
| `CardSource` | `findability.cards` in `config/citomni_http_cfg.php` | Source type | The cards of tagged content on the tag pages |
| `SearchSource` | `findability.search_sources` in `config/citomni_cfg.php` | Source type | The documents of the site search |

- `class PageSearch implements \CitOmni\Findability\Contract\SearchSource` in `src/Findability/`. The name is the content and the role: `PageSitemap`, `PageTargets`, `PageCards`, `PageSearch`.
- The constructor takes the App: Findability creates a new instance with `new $class($app)` every time it uses the provider. Keep no state between calls.
- A source type names the content, such as `app.page`: words of lowercase letters, digits and underscores, each starting with a letter, joined by dots, at most 64 bytes. It starts with `app`, so it never collides with a package's. Ids are ints of at least 1. A source name of a sitemap is a lowercase letter followed by lowercase letters and digits.
- Each source type gets a label in `findability.types` of `config/citomni_http_cfg.php`, which names the content on findability's pages.
- The PHPDoc of each interface in citomni/findability is the contract: the shapes, the limits and the rules for paths. Read it before writing the provider.

## Rules

- Read only: no writes, no output and no locking reads. Findability calls a provider inside its own requests and inside the transactions that save content.
- Show only what every visitor may see now, by the same rules as the content's own page. The provider decides on every call; findability never decides access.
- Thin: The SQL stays in the app's Repository. The provider picks the visible rows and shapes them for findability.
- Leave out content that is missing or hidden. An exception aborts the request, or the save that indexes the content, so throw only for a real failure.

## Keep findability's data up to date

Two features store data about the content: the tags of a resource and its document in the search index. The app updates both from the Operation that saves or deletes the content, inside the same transaction, so findability's data commits or rolls back with it:

```php
$this->app->db->transaction(function () use ($pageId, $values, $termIds): void {
	$pages = new PageRepository($this->app);
	$pages->update($pageId, $values);  // Locks the page's row.

	$this->app->tags->set('app.page', $pageId, $termIds);
	$this->app->searchIndex->changed('app.page', [$pageId]);
}, \CitOmni\Infrastructure\Enum\TransactionIsolation::ReadCommitted);
```

- Run the transaction at READ COMMITTED, and lock the content's own row before the calls, such as by the UPDATE that saves it: Then findability reads what other transactions committed, also those it waited for, and two saves of the content do not interleave. Under REPEATABLE READ, findability reads the snapshot of the transaction's first read and can miss what a rebuild of the search index stored meanwhile; the README of citomni/findability has the details.
- A deletion calls `$this->app->tags->forget()` and `$this->app->searchIndex->removed()` in the transaction that deletes the content.

## Registration

```php
// config/citomni_cfg.php
'findability' => [
	'search_sources' => [
		'app.page' => \App\Findability\PageSearch::class,  // The app's published pages, in the site search.
	],
],
```

```php
// config/citomni_http_cfg.php
'findability' => [
	'sitemaps' => [
		'pages' => \App\Findability\PageSitemap::class,  // The app's published pages.
	],
	'types' => [
		'app.page' => [
			'label' => 'Pages',  // Name of the type on findability's pages.
		],
	],
],
```

The search sources go in `config/citomni_cfg.php`, because content is also saved and indexed in CLI, such as by an import. `null` removes a package's provider, such as `'commerce.product' => null`.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace App\Findability;

use App\Repository\PageRepository;
use CitOmni\Findability\Contract\SearchSource;
use CitOmni\Kernel\App;


/**
 * PageSearch: Search source of the app's pages, for citomni/findability.
 *
 * Turns the published pages into the documents of the site search. Which pages are published
 * belongs to PageRepository; how the index stores and matches the documents belongs to
 * citomni/findability.
 *
 * Rules:
 * - Return a document only for a page that its own route shows.
 */
class PageSearch implements SearchSource {

	/**
	 * Keep the app.
	 *
	 * @param  App  $app
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * Return the documents of the published pages among the ids.
	 *
	 * @param  list<int>  $ids
	 * @return array<int,array{path:string,title:string,keywords:string,text:string,publish_at:int|null,expire_at:int|null}>  By id.
	 * @see \CitOmni\Findability\Contract\SearchSource::documents()  The contract.
	 */
	public function documents(array $ids): array {
		return self::documentsOf((new PageRepository($this->app))->listByIds($ids));
	}


	/**
	 * Return the documents of the published pages among the next pages above an id.
	 *
	 * @param  int  $after
	 * @param  int  $limit
	 * @return array{documents:array<int,array{path:string,title:string,keywords:string,text:string,publish_at:int|null,expire_at:int|null}>,last:int|null}
	 * @see \CitOmni\Findability\Contract\SearchSource::documentsAfter()  The contract.
	 */
	public function documentsAfter(int $after, int $limit): array {
		// Published or not, so the answer covers every id that it read.
		$rows = (new PageRepository($this->app))->listAfter($after, $limit);

		return [
			'documents' => self::documentsOf($rows),
			'last'      => \count($rows) === $limit ? (int)\end($rows)['id'] : null,
		];
	}


	/**
	 * Build the documents of the published pages among some rows.
	 *
	 * @param  list<array{id:int,slug:string,title:string,body:string,status:string}>  $rows
	 * @return array<int,array{path:string,title:string,keywords:string,text:string,publish_at:int|null,expire_at:int|null}>  By id.
	 */
	private static function documentsOf(array $rows): array {
		$documents = [];

		foreach ($rows as $page) {
			if ($page['status'] !== 'published') {
				continue;
			}

			$documents[$page['id']] = [
				'path'       => '/' . $page['slug'] . '.html',
				'title'      => $page['title'],
				'keywords'   => '',
				'text'       => $page['body'],
				'publish_at' => null,
				'expire_at'  => null,
			];
		}

		return $documents;
	}

}
```

## Example

This app does not use citomni/findability. The README of [citomni/findability](https://github.com/citomni/findability) shows a provider for each interface.
