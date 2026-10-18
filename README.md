# CitOmni Demo App

A runnable reference app for the CitOmni PHP framework, and the guide tree that developers and AI assistants read before they write a CitOmni file.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](./LICENSE)

- Framework: [CitOmni](https://github.com/citomni)
- Kernel: [citomni/kernel](https://github.com/citomni/kernel)

---

## What this repository is

- **A guide tree.** Every folder that holds a kind of file has a `README.md` with the rules for that kind of file. The tree mirrors a CitOmni app, so the path of the file you are about to write is also the path of its guide.
- **A reference app.** Everything under `src/` is written the way production code should be written, and is meant to be copied. A small article feature runs through Controller, Command, Operation, Policy, Repository, Enum and Util, with its templates, texts, schema and tests. The other guides and the variant files still show patterns with placeholder names.

Code that several apps install with Composer is a provider package, with its own guide tree in [citomni/demo-provider](https://github.com/citomni/demo-provider). This app installs that package and shows the app's side: enabling it, overriding its cfg and routes, and using its templates.

## Guides

| Writing | Read |
|---|---|
| Any PHP file in `src/` | [`src/README.md`](src/README.md), then the guide of its folder |
| Controller | [`src/Http/Controller/`](src/Http/Controller/README.md), with admin and member variants |
| CLI command | [`src/Cli/Command/`](src/Cli/Command/README.md), with a test command variant |
| Operation | [`src/Operation/`](src/Operation/README.md) |
| Repository | [`src/Repository/`](src/Repository/README.md) |
| Service | [`src/Service/`](src/Service/README.md) |
| Support, Policy, State | [`src/Support/`](src/Support/README.md), [`src/Policy/`](src/Policy/README.md), [`src/State/`](src/State/README.md) |
| Util, Enum, Exception | [`src/Util/`](src/Util/README.md), [`src/Enum/`](src/Enum/README.md), [`src/Exception/`](src/Exception/README.md) |
| Constant, Contract | [`src/Constant/`](src/Constant/README.md), [`src/Contract/`](src/Contract/README.md) |
| Job handler | [`src/JobRunner/`](src/JobRunner/README.md), with token/push and trusted/pull jobs |
| Template | [`templates/`](templates/README.md) |
| Language file | [`language/`](language/README.md) |
| Database schema | [`sql/`](sql/README.md) |
| Test suite | [`tests/`](tests/README.md) |
| Config files | [`config/CONFIGURATION.md`](config/CONFIGURATION.md) |

### Looking up a guide

1. Take the path of the file you will write, relative to its repository root.
2. Read the `README.md` of every folder from the top folder down to the file's folder. For `src/Service/Mailer.php` that is `src/README.md` and `src/Service/README.md`. A folder without a README adds no rules.
3. Variants are separate files, linked from the folder README.
4. When a guide links example files of this app, read them too. They are the pattern for a new file of that kind.
5. A file in a provider package follows [citomni/demo-provider](https://github.com/citomni/demo-provider) instead. Its tree differs where packages differ: Controllers in `src/Controller/`, Commands in `src/Command/`, and wiring in the Registry instead of `config/`.

### Precedence

- The guides are binding for new code.
- When you change an existing file, keep that file's style unless the task is to align it.
- When a guide and the framework source disagree, the source shows what happens today. Report the conflict so one of them gets fixed.

## For AI assistants

Give the assistant read access to this repository and add this to its instructions:

```text
Before writing or changing a CitOmni file, read the README.md guides for its path in citomni/demo-app, from the top folder down to the file's folder, in one batch (for src/Service/Mailer.php: src/README.md and src/Service/README.md). Config files: config/CONFIGURATION.md. A file in a provider package follows citomni/demo-provider instead. A missing README adds no rules. When a guide links example files, read them too. The guides are binding for new code. Never assume an existing contract, such as an API, a service, a cfg key, a route, a table or the template syntax: read it from its source, and stop and ask when it cannot be read. When changing an existing file, keep its style. Report a conflict between a guide and the source instead of silently picking one.
```

## The article example

Articles are created as drafts from the CLI and published from the CLI or from a draft page that exists only in dev. Visitors see the published articles.

| Part | Files |
|---|---|
| HTTP | `src/Http/Controller/ArticleController.php`, routed in `config/citomni_http_routes.php` and `config/citomni_http_routes.dev.php` |
| CLI | `src/Cli/Command/CreateArticleCommand.php` and `PublishArticleCommand.php`, registered in `config/citomni_cli_commands.php` |
| Workflow | `src/Operation/PublishArticle.php`, `src/Policy/PublishingPolicy.php` |
| Persistence | `src/Repository/ArticleRepository.php`, `sql/citomni_demo-app.sql` |
| Values and helpers | `src/Enum/ArticleStatus.php`, `src/Util/Words.php` |
| Presentation | `templates/public/`, `language/da/articles.php`, `language/en/articles.php` |
| Config | `articles` in `config/citomni_cfg.php`; `db` from the baseline of citomni/infrastructure, which `config/providers.php` loads, or from `config/citomni_cfg.{ENV}.php` |
| Tests | `tests/words/`, `tests/articles/` |

Publishing shows the layers working together. `PublishArticle` loads the article through `ArticleRepository`, asks `PublishingPolicy` for refusal reasons and publishes with one guarded `UPDATE`. Both adapters call it and turn its result into their own output: a flash message and a redirect, or a line of text, JSON and an exit code.

## The contact form

Visitors send a message from `/contact.html`. A script on the page posts the form fields to `/contact.json` and shows the JSON answer, so the CSRF and captcha fields of the template helpers travel unchanged.

| Part | Files |
|---|---|
| HTTP | `src/Http/Controller/ContactController.php`, routed in `config/citomni_http_routes.php` together with the captcha image of citomni/http |
| Workflow | `src/Operation/SendContactMessage.php` |
| Rules | `src/Util/ContactMessage.php` |
| Persistence | `src/Repository/ContactMessageRepository.php`, `sql/citomni_demo-app.sql` |
| Presentation | `templates/public/contact.html`, `language/da/contact.php`, `language/en/contact.php` |
| Config | `security.bruteforce.contact` in `config/citomni_cfg.php`, citomni/image in `config/providers.php` |
| Tests | `tests/contact/`, `tests/Support/BruteForceDouble.php` |

`SendContactMessage` decides whether a message is stored: the rate limit of the bruteForce service first, then the fields. `ContactController::send()` checks the CSRF token, asks `preflight()`, checks the captcha and stores with `execute()`, in that order, so a refused field never uses up the captcha challenge. It answers each refusal with a problem document: 403, 429 with `Retry-After`, or 422 with the fields in `invalid_params`. A stored message gets 201.

## Site notices from a provider package

citomni/demo-provider adds site notices: short messages above the page header, scheduled from the CLI or from a management page. The package owns the feature; this app enables and adapts it without code of its own.

| Part | Files |
|---|---|
| Install | `citomni/demo-provider` in `composer.json`, its Registry in `config/providers.php`, its schema from `vendor/citomni/demo-provider/sql/` |
| Notices on every page | `templates/public/layout.html` includes `partials/notices.html@citomni/demo-provider` and styles its classes |
| Cfg override | `demo_provider.notices.max_visible` in `config/citomni_cfg.php` |
| Management page | Enabled in dev by `demo_provider.notices.management_enabled` in `config/citomni_http_cfg.dev.php`; shown in this app's layout by the route override of `/notices/manage.html` in `config/citomni_http_routes.php` and `templates/public/notices.html` |

The package ships a baseline for every cfg key it reads, so the app sets only the keys that differ. Its routes merge with the app's, so the route override names only the new template and keeps the package's controller. The package's texts, commands and `/notices.json` are used as they are.

## Requirements

- PHP, its extensions and the packages, with their versions: `require` in `composer.json`.
- MySQL 8.0.16+ or MariaDB 10.6+

## Run the demo

Install the dependencies and the app scaffold, which adds the front controller, `bin/citomni` and the secrets files:

```bash
composer install
composer citomni:install -- --environment=dev
```

Create the database and its user, then import the schema of the app, the counter table of the bruteForce service, which rate-limits the contact form, and the table of citomni/demo-provider. The names are the `db` defaults of citomni/infrastructure, so the app's config does not set them:

```sql
CREATE DATABASE citomni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'citomni'@'localhost' IDENTIFIED BY 'choose-a-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON citomni.* TO 'citomni'@'localhost';
```

```bash
mysql -u root -p citomni < sql/citomni_demo-app.sql
mysql -u root -p citomni < vendor/citomni/infrastructure/sql/citomni_bruteforce.sql
mysql -u root -p citomni < vendor/citomni/demo-provider/sql/citomni_demo-provider.sql
```

Put the password in `var/secrets/app.secret.dev.php`. The installer created that file, and Git ignores it:

```php
return [
	'db.password' => 'choose-a-password',
];
```

The connection settings belong to the environment, because each environment usually has its own database. When one differs from the baseline above, set `host`, `user` or `name` under `db` in `config/citomni_cfg.dev.php`, `.stage.php` or `.prod.php`; each file has a commented example. The password always stays in `var/secrets/app.secret.{ENV}.php`.

The app sends no mail, but its config shows where the mailer of citomni/infrastructure is set up: the sender in `config/citomni_cfg.php`, the transport of each environment in `config/citomni_cfg.dev.php`, `.stage.php` and `.prod.php`, and the SMTP password as the secret `mail.smtp.password`. The dev example points at a local mail catcher, so that no message reaches a real inbox.

Create a draft and publish it. A draft needs at least 20 words (`articles.min_words_to_publish`):

```bash
php bin/citomni article:create "Hello CitOmni" --body="CitOmni keeps every layer explicit. This article was created from the command line and stays a draft until the publish command or the draft page publishes it."
php bin/citomni article:publish 1
```

Schedule a site notice, which shows above the header of every page until it ends:

```bash
php bin/citomni notice:schedule "Planned maintenance tonight 22-23." --level=warning
php bin/citomni notice:list
```

Without `--ends`, the notice shows until it is ended. `--ends` sets an end, as a local time in the form `YYYY-MM-DD HH:MM`.

Serve the app with a web server whose document root is `public/` and that sends every path that is not a file to `public/index.php`. PHP's built-in server (`php -S`) is deliberately not supported.

The front page lists the published articles. Each is also served as JSON and plain text, like `/article/1.json` and `/article/1.txt`, and the list as `/articles.json`. `/download-example.pdf` streams a PDF that lies outside `public/`, and `/contact.html` sends a message. `/notices.json` lists the current site notices. In dev, `/drafts.html` lists the drafts and publishes them from the browser, and `/notices/manage.html` schedules and ends site notices.

## Run the tests

The suites follow the CitOmni testing guide; [`tests/README.md`](tests/README.md) has the rules. The database suite connects as `root` to `127.0.0.1:3306` over TCP with the password in `CITOMNI_TEST_PASSWORD`, and creates and drops its own database, so use a local development server. It also needs `vendor/` from `composer install`.

```bash
composer test
```

Multi-process checks are skipped unless `CITOMNI_TEST_PARALLEL` is `1`.

---

## Copyright

© 2026-present Lars Grove Mortensen.
This repository contains application code built on top of the CitOmni Framework.

The underlying **CitOmni Framework** is © 2012-present **Lars Grove Mortensen**, licensed under the MIT License.
Your use of CitOmni is governed by its own open-source license, independent of this app's code and ownership.

---

## Trademark notice

See [`TRADEMARKS.md`](./TRADEMARKS.md) for the CitOmni trademark policy applicable to this project.

---

## License

This project is licensed under the MIT License. See [`LICENSE`](./LICENSE) and [`NOTICE`](./NOTICE).
