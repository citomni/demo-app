# Policy

A Policy holds App-aware rules: whether something is allowed, required, valid or withheld. Callers instantiate it explicitly and act on its answer.

## Rules

- `class NamePolicy` in `src/Policy/`, constructed with the App: `new PasswordPolicy($this->app)`. Never registered in a service map.
- It decides or validates; it does not change state and does not coordinate workflows.
- Rule values such as limits and switches come from cfg, with baselines like any package cfg (see `src/README.md`).
- The current time arrives as an argument, so the Policy reads no clock and a test chooses the time.
- Data the rule needs comes from the arguments or from Repositories it instantiates, only when the check that needs them is reached. No SQL in the Policy itself.
- No transport concerns: no request parsing, rendering, redirects or output. Adapters turn a refusal into HTTP or CLI output.
- Public methods return plain values (`bool`, a list of reasons, a domain-shaped array) or throw a documented exception when a rule is violated, such as a `PasswordPolicyException` from `validate()`.
- Run cheap checks before expensive ones.
- Authorization rules for admin Controllers live here; see `src/Http/Controller/admin-controller.md`.

## Example

[`PublishingPolicy.php`](PublishingPolicy.php) returns the reasons a draft cannot be published, with the word limit from cfg `articles.min_words_to_publish`. `PublishArticle` refuses on any reason, and the dev draft list in `ArticleController::drafts()` shows them, so the rule lives in one place.

Tests: [`tests/articles/run.php`](../../tests/articles/run.php), isolated with an app double that carries only the cfg the Policy reads.
