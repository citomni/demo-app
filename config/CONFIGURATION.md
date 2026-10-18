# Configuration Directory

## Purpose

The `/config` directory is the canonical application-owned control surface for CitOmni bootstrap behavior. It is intentionally small, explicit, and deterministic. Its role is not to mirror framework baselines, but to supply the application's own providers, service overrides, shared configuration, mode-specific overlays, and environment-specific overlays.

In other words: the directory should contain only the files that the application meaningfully owns. A quiet config directory is a good config directory.

## Design Principles

CitOmni treats configuration as a layered composition rather than a monolithic document. The effective runtime state is assembled from the selected mode package baseline, optional provider registries, app-owned files, and, where relevant, environment overlays.

For configuration arrays, the merge rule is recursive associative merge with last-wins semantics. Later configuration layers override earlier layers.

For service maps, the rule is PHP array union with left-wins semantics. The service map is therefore documented as a precedence order: highest authority first.

This design has five practical consequences:

1. App-owned base config belongs in `citomni_cfg.php`.
2. HTTP and CLI config files should contain mode-specific deviations only.
3. Environment overlays should express deliberate deviation, not duplication.
4. Service overrides should be placed as close to their intended scope as possible.
5. Missing config and service files are tolerated by runtime for backwards compatibility.

## Recommended Directory Shape

A full application using both HTTP and CLI may have a configuration directory shaped approximately as follows:

```text
/config
	.htaccess
	citomni_cfg.php
	citomni_cfg.dev.php
	citomni_cfg.stage.php
	citomni_cfg.prod.php

	services.php
	services_http.php
	services_cli.php

	providers.php

	citomni_http_cfg.php
	citomni_http_cfg.dev.php
	citomni_http_cfg.stage.php
	citomni_http_cfg.prod.php
	citomni_http_routes.php
	citomni_http_routes.dev.php
	citomni_http_routes.stage.php
	citomni_http_routes.prod.php

	citomni_cli_cfg.php
	citomni_cli_cfg.dev.php
	citomni_cli_cfg.stage.php
	citomni_cli_cfg.prod.php
	citomni_cli_commands.php
	citomni_cli_commands.dev.php
	citomni_cli_commands.stage.php
	citomni_cli_commands.prod.php

	CONFIGURATION.md
```

Not every application needs every file. The official app skeleton expects the common files, while mode packages add mode-specific files when HTTP or CLI support is installed.

The practical bootstrap minimum for a new neutral app is:

```text
/config
	citomni_cfg.php
	services.php
	providers.php
```

A physical app-skeleton config directory may also contain support files such as `.htaccess` and `CONFIGURATION.md`. They protect and document the directory, but they do not participate in the bootstrap merge pipeline.

## File Taxonomy

### `.htaccess`

This is a web-server guard file for deployments where the config directory might otherwise be reachable through the public document root. It is not read by CitOmni bootstrap.

**Status:** Standard support file in official skeletons where relevant.

**Role:**
- Reduce the risk of direct web access to config files.
- Keep operational hardening close to the files it protects.
- Stay outside the runtime merge pipeline.

### `CONFIGURATION.md`

This document explains the config directory contract. It is documentation only and is not read during bootstrap.

**Status:** Standard documentation file in official skeletons.

**Role:**
- Document which files exist and why.
- Explain merge order and ownership.
- Make future archaeology less depressing.

### `citomni_cfg.php`

This is the app-owned common configuration file. It is shared by HTTP and CLI and is loaded after the selected mode package baseline and all provider configuration layers, but before mode-specific app configuration and environment overlays.

**Status:** Standard in official skeletons.

**Role:**
- Define durable app-owned defaults.
- Keep values shared by HTTP and CLI in one place.
- Override selected vendor or provider cfg keys.
- Avoid duplicating identical settings in HTTP and CLI config files.

**What belongs here:**
- App identity values.
- Locale defaults.
- Shared infrastructure settings.
- Cross-mode package configuration.
- Any other setting that should apply to both HTTP and CLI unless explicitly overridden later.

**What does not belong here:**
- HTTP-only settings.
- CLI-only settings.
- Environment-specific values.
- Full copies of framework baseline config.

### `citomni_cfg.<env>.php`

These are common environment overlays, typically `dev`, `stage`, and `prod`. They load only when `CITOMNI_ENVIRONMENT` matches the suffix.

**Status:** Optional.

**Role:**
- Express environment-specific deviations that apply to both HTTP and CLI.
- Keep shared production posture in one place.
- Prevent duplicated dev, stage, or prod overrides across modes.

Use these files only when a setting is both environment-specific and cross-mode. If the setting is HTTP-only or CLI-only, use the mode-specific overlay instead.

### `providers.php`

This file declares which provider registries participate in application bootstrap. The kernel loads it from `/config/providers.php`; if the file is absent, the provider list defaults to an empty array.

From a purely technical perspective the file is tolerated as missing for backwards compatibility. From an architectural perspective it is foundational because it defines which package registries may contribute configuration, routes, commands, and services.

**Status:** Standard in official skeletons.

**Role:**
- Whitelist package registries.
- Establish the provider layer in the merge pipeline.
- Keep package participation explicit rather than magical.

### `services.php`

This file is the application's common service map override layer. It applies to both HTTP and CLI boot.

**Status:** Standard in official skeletons.

**Role:**
- Add application-specific common services.
- Override provider or vendor service definitions across modes.
- Keep genuinely shared service ownership centralized.

**Important subtlety:** Because `services.php` is shared across modes, any definition placed here affects both HTTP and CLI unless a mode-specific service file overrides it with higher precedence.

### `services_http.php`

This file is the application's HTTP-specific service map override layer. It has higher precedence than `services.php` during HTTP boot.

**Status:** Optional.

**Role:**
- Add HTTP-only services.
- Override a common service only for HTTP.
- Keep transport-specific service wiring out of the common map.

### `services_cli.php`

This file is the application's CLI-specific service map override layer. It has higher precedence than `services.php` during CLI boot.

**Status:** Optional.

**Role:**
- Add CLI-only services.
- Override a common service only for CLI.
- Keep terminal-specific service wiring out of the common map.

## Configuration Merge Order

Configuration uses recursive associative merge. The order below is chronological: later entries override earlier entries.

### HTTP cfg order

```text
vendor CFG_HTTP
provider #1 CFG_COMMON
provider #1 CFG_HTTP
provider #2 CFG_COMMON
provider #2 CFG_HTTP
...
app citomni_cfg.php
app citomni_http_cfg.php
app citomni_cfg.<env>.php
app citomni_http_cfg.<env>.php
```

### CLI cfg order

```text
vendor CFG_CLI
provider #1 CFG_COMMON
provider #1 CFG_CLI
provider #2 CFG_COMMON
provider #2 CFG_CLI
...
app citomni_cfg.php
app citomni_cli_cfg.php
app citomni_cfg.<env>.php
app citomni_cli_cfg.<env>.php
```

Provider order follows the order in `providers.php`.

There is no vendor `CFG_COMMON`. The vendor baseline is supplied by the selected mode package, such as `citomni/http` or `citomni/cli`. Shared package defaults belong in provider `CFG_COMMON`, for example in `citomni/infrastructure` or another provider package.

Existing cfg files must return values accepted by `Arr::normalizeConfig()`. Official scaffolded cfg files return arrays. Scalar cfg values fail fast.

## Service Map Precedence

Service maps use PHP array union semantics. The first matching service id wins, so the order below is a precedence order: highest authority first.

### HTTP service map precedence

```text
app services_http.php
app services.php
provider #N MAP_HTTP
provider #N MAP_COMMON
...
provider #2 MAP_HTTP
provider #2 MAP_COMMON
provider #1 MAP_HTTP
provider #1 MAP_COMMON
vendor MAP_HTTP
```

### CLI service map precedence

```text
app services_cli.php
app services.php
provider #N MAP_CLI
provider #N MAP_COMMON
...
provider #2 MAP_CLI
provider #2 MAP_COMMON
provider #1 MAP_CLI
provider #1 MAP_COMMON
vendor MAP_CLI
```

There is no vendor `MAP_COMMON`. The selected mode package supplies the vendor service baseline through `MAP_HTTP` or `MAP_CLI`. Shared package service defaults belong in provider `MAP_COMMON`.

Existing service files must return arrays.

## HTTP Configuration Files

### `citomni_http_cfg.php`

This is the app-owned HTTP configuration overlay. It is loaded after `citomni_cfg.php`, and it should contain only values that are specific to HTTP mode.

**Status:** Optional.

**Role:**
- Define HTTP-only app defaults.
- Override common cfg values only for HTTP.
- Keep HTTP transport policy out of common cfg.

**What belongs here:**
- Stable HTTP settings.
- HTTP-facing error-handler policy.
- Cookie and session defaults where they are HTTP-only.
- Any other HTTP-only configuration that should apply across all HTTP environments unless specifically overridden.

**What does not belong here:**
- App identity values that also matter to CLI.
- Locale defaults shared with CLI.
- Full copies of framework baseline config.
- Temporary environment tweaks.

### `citomni_http_cfg.<env>.php`

These are HTTP environment overlays. They load only when `CITOMNI_ENVIRONMENT` matches the suffix, and they override both common environment overlays and the base HTTP overlay.

**Status:** Optional.

**Role:**
- Express environment-specific HTTP deviations.
- Keep production HTTP posture explicit.
- Prevent cross-environment leakage of debug settings.

Typical examples:
- dev-only error rendering and cache behavior.
- stage/prod base URL policy.
- HTTPS-only cookie/session posture.
- HTTP-only webhook or CSRF policy.

### `citomni_http_routes.php`

This is the application-owned HTTP route map. Routes remain mode-specific. There is no `ROUTES_COMMON`.

**Status:** Recommended in HTTP apps that define app-specific routes.

**Role:**
- Declare the app's own HTTP routes.
- Override or refine provider routes where appropriate, or remove one with `'/path' => null`.
- Keep routing decisions out of cfg.

### `citomni_http_routes.<env>.php`

These are optional HTTP route overlays. They load only when `CITOMNI_ENVIRONMENT` matches the suffix. Routes remain dispatch maps, not cfg. The common cfg/service changes do not remove route overlays.

**Status:** Optional.

**Role:**
- Add or override HTTP routes for one environment.
- Keep dev-only diagnostic routes out of stage and prod.
- Keep stage/prod-only operational routes explicit.

Use these files sparingly. Environment-specific routing is sometimes necessary, but excessive use makes the real route contract harder to audit.

## CLI Configuration Files

### `citomni_cli_cfg.php`

This is the app-owned CLI configuration overlay. It is loaded after `citomni_cfg.php`, and it should contain only values that are specific to CLI mode.

**Status:** Optional.

**Role:**
- Define CLI-only app defaults.
- Override common cfg values only for CLI.
- Keep terminal/runtime policy out of common cfg.

### `citomni_cli_cfg.<env>.php`

These are CLI environment overlays. They load only when `CITOMNI_ENVIRONMENT` matches the suffix, and they override both common environment overlays and the base CLI overlay.

**Status:** Optional.

**Role:**
- Express environment-specific CLI deviations.
- Keep CLI diagnostics and operational behavior explicit per environment.

### `citomni_cli_commands.php`

This is the application-owned CLI command map. Commands remain mode-specific. There is no `COMMANDS_COMMON`.

**Status:** Recommended in CLI apps that expose app-owned commands.

**Role:**
- Register application commands.
- Override or augment provider command maps, or remove a command with `'name' => null`.
- Keep command registration out of cfg.

### `citomni_cli_commands.<env>.php`

These are optional CLI command overlays. They load only when `CITOMNI_ENVIRONMENT` matches the suffix. Commands remain dispatch maps, not cfg. There is still no `COMMANDS_COMMON`.

**Status:** Optional.

**Role:**
- Add or override CLI commands for one environment.
- Keep dev-only tooling commands out of stage and prod.
- Keep operational commands explicit per environment.

Use these files sparingly. Environment-specific command registration should be deliberate, not a confetti cannon with a shell prompt.

## Backwards Compatibility and Fail Fast Rules

Runtime tolerates missing config and service files for backwards compatibility. This makes older apps easier to migrate and keeps optional overlays optional.

New official skeletons still install the standard files:

- `config/citomni_cfg.php`
- `config/services.php`
- `config/providers.php`

Rules:

- Missing optional cfg, service, route, and command files are ignored.
- Missing standard cfg/service/provider files are tolerated by runtime for backwards compatibility.
- Existing service files must return arrays.
- Existing cfg files must return values accepted by `Arr::normalizeConfig()`.
- Official scaffolded cfg files return arrays.
- Scalar cfg values fail fast.

## Ownership

The official skeleton and mode packages provide scaffold files through `citomni/installer`, but once a file has been installed into an application, the application owns it.

That means the installer may create missing files, but it must not silently repair or overwrite real app configuration without explicit force behavior. User-owned config is not a punching bag.

Owning a create-only file includes deleting it. The installer cannot tell a deleted file from one that a package has just added, so `install` and `sync` create it again unless `config/citomni_installer.php` lists it under `omit`. This app omits `src/Http/Controller/AppController.php`, because it keeps only its example Controllers. The citomni/installer README has the rules.

Typical scaffold ownership:

### `citomni/kernel`

```text
citomni_cfg.php
citomni_cfg.dev.php
citomni_cfg.stage.php
citomni_cfg.prod.php
services.php
providers.php
```

### `citomni/http`

```text
citomni_http_cfg.php
citomni_http_cfg.dev.php
citomni_http_cfg.stage.php
citomni_http_cfg.prod.php
services_http.php
citomni_http_routes.php
citomni_http_routes.dev.php
citomni_http_routes.stage.php
citomni_http_routes.prod.php
```

### `citomni/cli`

```text
citomni_cli_cfg.php
citomni_cli_cfg.dev.php
citomni_cli_cfg.stage.php
citomni_cli_cfg.prod.php
services_cli.php
citomni_cli_commands.php
citomni_cli_commands.dev.php
citomni_cli_commands.stage.php
citomni_cli_commands.prod.php
```

Routes and commands are supplied by mode packages where relevant. They are separate dispatch maps, not shared cfg or service layers, and their environment overlays remain valid.

## Which Files Are Actually Necessary?

The answer depends on whether one is asking a technical or architectural question.

### Minimal technical minimum

A CitOmni app can technically boot with very few files because missing files are ignored where backwards compatibility requires it.

### Official skeleton minimum

A new neutral app should have:

```text
/config
	citomni_cfg.php
	services.php
	providers.php
```

### HTTP app additions

An HTTP app commonly adds:

```text
/config
	citomni_http_cfg.php
	citomni_http_cfg.dev.php
	citomni_http_cfg.stage.php
	citomni_http_cfg.prod.php
	services_http.php
	citomni_http_routes.php
	citomni_http_routes.dev.php
	citomni_http_routes.stage.php
	citomni_http_routes.prod.php
```

### CLI app additions

A CLI app commonly adds:

```text
/config
	citomni_cli_cfg.php
	citomni_cli_cfg.dev.php
	citomni_cli_cfg.stage.php
	citomni_cli_cfg.prod.php
	services_cli.php
	citomni_cli_commands.php
	citomni_cli_commands.dev.php
	citomni_cli_commands.stage.php
	citomni_cli_commands.prod.php
```

Environment overlays, route overlays, and command overlays should exist only when there is an actual environment-specific deviation to express.

## Operational Guidance

### 1. Put shared defaults in `citomni_cfg.php`

If a value should apply to both HTTP and CLI, put it in the common cfg file. Do not duplicate it in both mode files.

### 2. Keep mode files narrow

`citomni_http_cfg.php` and `citomni_cli_cfg.php` should describe mode-specific differences, not re-state the app's entire identity.

### 3. Keep overlays narrower still

Environment overlays should contain the smallest possible deviation from the base layer below them. The same restraint applies to route and command overlays.

### 4. Do not mirror vendor baseline config

Duplication creates review noise, obscures ownership, and makes future vendor changes harder to adopt cleanly.

### 5. Prefer explicit production values

Development may auto-detect where the selected mode package allows it, but stage and prod should use explicit operational values. For HTTP, that normally means an explicit absolute base URL or `CITOMNI_PUBLIC_ROOT_URL` at bootstrap.

### 6. Treat `/appinfo.html` as an inspection tool, not a config source of truth

`/appinfo.html` is useful for inspecting merged runtime configuration and copying exact array structure for overrides. The true source of authority remains the bootstrap merge pipeline itself.

### 7. Place service overrides at the right scope

Use `services.php` for cross-mode services. Use `services_http.php` or `services_cli.php` when the service definition is transport-specific.

### 8. Keep database and mail connections in the common files

The `db` and `mail` nodes of `citomni/infrastructure` serve HTTP and CLI alike: a command or a job uses the same database and the same sender as a request. Put what every environment shares, such as the mail sender, in `citomni_cfg.php`, and the connection of each environment in `citomni_cfg.<env>.php`. A node that only an HTTP file sets leaves CLI on the baseline: the Db service connects to its default database, and the mailer throws `MailerConfigException` for want of a sender.

Passwords never go in cfg. The services read them as secrets from `var/secrets/app.secret.<env>.php`: `db.password` and `mail.smtp.password` unless `db.password_secret` or `mail.smtp.password_secret` names another key. This app's `citomni_cfg.php` and `citomni_cfg.<env>.php` show both nodes as commented examples.

## Recommended Defaults by App Type

### Neutral application

```text
/config
	citomni_cfg.php
	services.php
	providers.php
```

### HTTP-only application

```text
/config
	citomni_cfg.php
	citomni_cfg.dev.php
	citomni_cfg.stage.php
	citomni_cfg.prod.php
	services.php
	services_http.php
	providers.php
	citomni_http_cfg.php
	citomni_http_cfg.dev.php
	citomni_http_cfg.stage.php
	citomni_http_cfg.prod.php
	citomni_http_routes.php
	citomni_http_routes.dev.php
	citomni_http_routes.stage.php
	citomni_http_routes.prod.php
```

### CLI-only application

```text
/config
	citomni_cfg.php
	citomni_cfg.dev.php
	citomni_cfg.stage.php
	citomni_cfg.prod.php
	services.php
	services_cli.php
	providers.php
	citomni_cli_cfg.php
	citomni_cli_cfg.dev.php
	citomni_cli_cfg.stage.php
	citomni_cli_cfg.prod.php
	citomni_cli_commands.php
	citomni_cli_commands.dev.php
	citomni_cli_commands.stage.php
	citomni_cli_commands.prod.php
```

### Application using both HTTP and CLI

```text
/config
	citomni_cfg.php
	citomni_cfg.dev.php
	citomni_cfg.stage.php
	citomni_cfg.prod.php

	services.php
	services_http.php
	services_cli.php

	providers.php

	citomni_http_cfg.php
	citomni_http_cfg.dev.php
	citomni_http_cfg.stage.php
	citomni_http_cfg.prod.php
	citomni_http_routes.php
	citomni_http_routes.dev.php
	citomni_http_routes.stage.php
	citomni_http_routes.prod.php

	citomni_cli_cfg.php
	citomni_cli_cfg.dev.php
	citomni_cli_cfg.stage.php
	citomni_cli_cfg.prod.php
	citomni_cli_commands.php
	citomni_cli_commands.dev.php
	citomni_cli_commands.stage.php
	citomni_cli_commands.prod.php
```

## Final Rule of Thumb

A well-kept CitOmni `/config` directory should read less like a dump of available knobs and more like a disciplined statement of application ownership. Each file should answer one question only:

- which providers participate,
- which shared defaults the app owns,
- which services the app owns,
- which HTTP and CLI deviations the app declares,
- which routes and commands are app-owned,
- and which deviations are genuinely environment-specific.

Anything beyond that is usually duplication masquerading as explicitness.
