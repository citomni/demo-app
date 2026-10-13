# Service

A Service is one small, reusable, App-aware capability that lives as a lazy singleton in the service map.

## Use another layer when

- It runs SQL or touches Db: Repository. A Service that needs persistence delegates to a Repository.
- It parses requests, renders templates or writes terminal output: Controller or Command.
- It coordinates a workflow: Operation.
- It is App-aware but should not be a singleton: Support, Policy or State.
- It is pure: Util.

## Contract

- `class ServiceName extends \CitOmni\Kernel\Service\BaseService`.
- Constructor contract: `new ServiceName(App $app, array $options = [])`. Declare no constructor of your own. BaseService stores `$this->app` and `$this->options`, then calls `init()` when it exists.
- One instance per request (HTTP) or process (CLI), created on first access through `$this->app->serviceId`. Never instantiate a registered Service with `new`.
- Service ids are lowerCamelCase, like `tplEngine` and `valueToSql`.

## init()

- Only cheap, deterministic setup derived from options and cfg. Leave `init()` out when there is nothing to derive.
- No IO, runtime discovery, directory scans, reflection or large object graphs. Expensive work happens in explicit public methods.

## Options and cfg

- Options (`$this->options`) are construction-time wiring from the service map entry.
- Cfg is runtime policy. Cfg rules, including package baselines: `src/README.md`.

## Rules

- One responsibility. A narrow public API that is predictable, deterministic and boring.
- Filesystem IO is fine when it is the Service's own job: log files, storage, caches, produced files.
- No transport concerns and no workflow coordination.
- Public methods validate their input and document return shapes and exceptions.
- Failure rules: `src/README.md`. A `\Throwable` catch is allowed only around one narrowly scoped, documented best-effort call whose failure must not mask an in-flight exception or break a documented no-throw contract, such as optional logging during cleanup.
- Exception classes for the Service's public contract: `src/Exception/README.md`.

## Registration

- App: `config/services.php` for both modes, `config/services_http.php` for HTTP only, `config/services_cli.php` for CLI only. The class stays in `src/Service/` whatever its mode.
- Provider package: Registry `MAP_COMMON`, or `MAP_HTTP` / `MAP_CLI` for a Service that only works in one mode. See [`src/Boot/README.md`](https://github.com/citomni/demo-provider/blob/main/src/Boot/README.md) in citomni/demo-provider. The app's service maps win over the providers' for the same id.
- An entry is `'serviceId' => Fqcn::class`, or with options:

```php
// config/services.php
return [
	'serviceId' => [
		'class'   => \App\Service\ServiceName::class,
		'options' => [
			'prefix' => 'demo-',
		],
	],
];
```

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace App\Service;

use CitOmni\Kernel\Service\BaseService;


/**
 * ServiceName: Service that prefixes labels.
 *
 * Gives every caller in a request or process the same prefix, chosen once from the service
 * options or cfg. What a label means is left to its callers.
 *
 * Rules:
 * - Derive the prefix only in init(); public methods never change it.
 */
class ServiceName extends BaseService {

	/** Prefix of every label. */
	private string $prefix;


	/**
	 * Derive immutable state from options and cfg.
	 *
	 * Behavior:
	 * - The prefix option wins over cfg service_name.prefix.
	 *
	 * @return void
	 * @see \CitOmni\Kernel\Service\BaseService::__construct()  Calls it once per instance.
	 */
	protected function init(): void {
		$this->prefix = (string)($this->options['prefix'] ?? $this->app->cfg->service_name->prefix);
	}


	/**
	 * Prefix one non-empty value.
	 *
	 * Typical usage:
	 *   $label = $this->app->serviceId->label('value');
	 *
	 * @param  string  $value  Trimmed before use.
	 * @return string
	 * @throws \InvalidArgumentException  When the value is empty after trimming.
	 */
	public function label(string $value): string {
		$value = \trim($value);

		if ($value === '') {
			throw new \InvalidArgumentException('Value cannot be empty.');
		}

		return $this->prefix . $value;
	}


}
```
