# language/

Translations for the `txt` service of citomni/infrastructure. PHP code reads them through `$this->app->txt->get()`, templates through `$txt()`.

## Layout

- `language/<language>/<file>.php`. `<language>` is `locale.language` from the config, like `da`. `<file>` is the name that callers pass, without `.php`; it may contain subfolders, like `admin/users`.
- One file per feature or area, shared by its Controllers, Commands and templates. The article example uses `articles.php`.
- Every language folder holds the same files with the same keys.
- Package texts stay in the package, in `vendor/<vendor>/<package>/language/`, and are read with the package slug as layer: `$txt('<key>', '<file>', 'vendor/package', '<default>')`. The app's language files cannot override them; for other wording, the app renders its own template with its own texts.

## File format

- The PHP file header, then `return [ ... ];` with a flat map of keys to strings. Comments group the keys.
- Keys in snake_case, prefixed by purpose where that helps: `nav_`, `flash_`, `reason_`.
- Placeholders are `%UPPERCASE%` tokens, filled from the `$vars` argument. The keys of `$vars` are uppercased first, so `['minutes' => 3]` fills `%MINUTES%`.

## Lookup

- `get(string $key, string $file, string $layer = 'app', string $default = '', array $vars = []): string`.
- A missing file, a missing key, an empty string or a non-scalar value returns `$default`, with the placeholders applied. Each such lookup writes a `txt.missing_key` or `txt.non_scalar_value` line to `txt.jsonl` in the `log.path` directory (`var/logs/`), and a missing file adds one `txt.missing_file` line per request.
- A malformed file name or layer throws `\InvalidArgumentException`. That is an error in the code, not missing content. A file name consists of letters, digits, `/`, `_` and `-`, and starts and ends with a letter or digit.
- Each file is loaded once per request and kept in memory.

## Rules

- Pass the layer and an English default at every call: `$this->app->txt->get('flash_published', 'articles', 'app', 'The article was published.')`. The default keeps the page readable when a key is missing, and the log line names the key.
- Results carry codes, not translated text. Operations and Policies return codes, and the adapter or template translates them by key, like `'reason_' . $reason` for the reason codes of `PublishingPolicy`. Text that the domain produces itself, like the body of a mail, may use `txt`; see [`src/Operation/`](../src/Operation/README.md).
- A date pattern is a translation (`date_pattern`), because languages order the parts of a date differently. Templates pass it to `$dt()`.
- Texts are plain text and are rendered escaped. A text that must carry markup is rendered raw with `{{{ }}}`, and then its placeholders must never carry user input.

## Example

- [`en/articles.php`](en/articles.php) and [`da/articles.php`](da/articles.php): the texts of the article example. The app runs in Danish (`locale` in `config/citomni_cfg.php`). For English, set `language` to `en` and `icu_locale` to `en_GB`, which formats the dates.
