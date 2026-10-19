# templates/

HTML templates for the `tplEngine` service of citomni/http. A Controller prepares the data and renders a template; the template only presents that data.

## Layout

- `templates/<area>/<name>.html`. The scaffold has `public/`, `member/` and `admin/`; the article example is in `public/`.
- A template is referenced as `<path below the layer root>@<layer>`, like `public/article.html@app`. The `app` layer is this folder. Packages register their own layers under their package slug in `view.template_layers`, like `citomni/http` and `citomni/demo-provider`.
- A route names its template in `template_file` and `template_layer`. The Controller renders `template_file . '@' . template_layer` instead of hard-coding the reference.

## Syntax

CitOmni has its own template language. Expressions are native PHP expressions: there are no filters and no Twig syntax.

| Syntax | Meaning |
|---|---|
| `{{ expr }}` | Escaped output (`htmlspecialchars` with `ENT_QUOTES` and the runtime charset). Null prints nothing. |
| `{{{ expr }}}` | Raw output, for trusted markup only. |
| `{% set $name = expr %}` | Local variable. The `$` is required. |
| `{% if expr %}`, `{% elseif expr %}`, `{% else %}`, `{% endif %}` | Conditions. |
| `{% foreach ($items as $item) %}`, `{% endforeach %}` | Loops. The parentheses are required. |
| `{% continue %}`, `{% break %}` | Loop control, with an optional level. |
| `{% extends "file@layer" %}` | Inherit a layout. |
| `{% block name %}`, `{% endblock %}` | Content for the layout's `{% yield name %}`. |
| `{% include "file@layer" %}` | Insert another template at compile time. It shares the variables of the including template. |
| `{% include expr %}` | Include the template whose `file@layer` reference `expr` yields when the directive runs, such as one partial per content block. It is compiled on its own and shares the variables of the including template. An `expr` that starts with a string literal goes in parentheses. |
| `{# ... #}` | Template comment, removed at compile time. |
| `{?= expr ?}`, `{? code ?}` | Inline PHP, when `view.allow_php_tags` is on (the default). |

## Inheritance

- A page starts with `{% extends "public/layout.html@app" %}` and consists of blocks. Markup outside the blocks is discarded.
- The yields of a layout are a contract. A child block without a matching yield, a yield that the child does not fill, and a duplicate block name all fail at compile time.
- A page can extend a layout in another layer, like an app admin page that extends `admin/admin_layout.html@citomni/admin`. A layout can itself extend another layout; the engine follows the chain and fails on a cycle.

## Variables

A template sees three sources. The first one wins on a name conflict:

1. The data the Controller passes to `render()`.
2. Path-scoped values from `view.vars`, which provider Registries and the app's cfg declare, like `$site_notices` of citomni/demo-provider.
3. Globals and helpers.

Controller data shadows a global or helper with the same name, so never pass keys like `url`, `txt` or `charset`.

Globals: `$app_name`, `$language`, `$charset`, `$base_url`, `$public_root_url`, `$env['name']`, `$env['dev']`, `$marketing_scripts`, and the flags `$csrf_protection`, `$captcha_protection`, `$honeypot_protection` and `$form_action_switching`.

| Helper | Use |
|---|---|
| `$url($path, $query = [])` | Absolute URL of a path in this app. |
| `$asset($path, $version = null)` | Absolute URL of a file in `public/`, with the `view.asset_version` cache buster. |
| `$txt($key, $file, $layer, $default, $vars)` | Translated text, see [`language/`](../language/README.md). |
| `$dt($when, $pattern)` | A date formatted with an ICU pattern in the app's timezone and locale. `$dtNow()`, `$dtMonth()` and `$dtWeekday()` cover the rest. |
| `$csrfField()` | Hidden CSRF input for a form. Raw output. |
| `$captchaField()`, `$captchaUrl()` | Captcha challenge. Empty when captcha is off. |
| `$currentPath()` | Path of the current request from the app root, for active menu items. |
| `$icon($id, $file, $layer)`, `$hasIcon(...)` | Inline SVG icons. Raw output. |
| `$hasService($id)`, `$hasPackage($slug)` | Show parts that need an optional service or package. |
| `$auth(...)`, `$role(...)` | Identity and role checks, with citomni/authenticate. |

## Templates from a provider package

- A provider partial is included with its layer: `{% include "partials/notices.html@citomni/demo-provider" %}`. It reads the variables its package supplies and uses the CSS classes that the package documents, which the app's layout styles.
- To show a provider page in the app's layout, the app overrides the template of the provider's route (see [`src/Http/Controller/`](../src/Http/Controller/README.md)) with an app page that includes the provider's content partial.
- How a package writes its templates: [citomni/demo-provider](https://github.com/citomni/demo-provider).

## Rules

- Templates present; they do not decide. Data, access checks and business rules come from the Controller. No queries and no domain side effects, like writes or mail. The form helpers `$csrfField()` and `$captchaField()` keep their state in the session; that is expected.
- Escape by default. `{{{ }}}` only for markup that the app or a helper built, like `$csrfField()` and `$icon()`. Never for user content, and never for a text whose placeholders carry user content.
- A runtime include takes its reference from code or cfg, never from request data or other untrusted input: the template it names runs as PHP.
- Every visible text comes from `$txt()` with the layer and an English default: `$txt('publish', 'articles', 'app', 'Publish')`. Without the layer argument, `$txt()` reads the `app` layer; pass it anyway, so every call names where its text lives.
- Links through `$url()`, files in `public/` through `$asset()`. No hard-coded hosts or base paths.
- Every POST form contains `{{{ $csrfField() }}}`, and its action verifies the token.
- Dates through `$dt()`, with the pattern from the language file, so each language orders the parts its own way.
- Hiding a link is not access control. A link shown only in dev (`{% if ($env['dev']) %}`) points to a route that exists only in dev, or to one that checks access itself.

## Notes

- Templates compile to PHP files in `var/cache`. A changed source file is detected on the next render.

## Example

- [`public/layout.html`](public/layout.html): the layout. It yields `meta_title` and `content`, includes the site notices of citomni/demo-provider above the header, styles the classes of that package, and shows the Drafts and Site notices links only in dev.
- [`public/articles.html`](public/articles.html) and [`public/article.html`](public/article.html): escaped output, links through `$url()`, `$dt()` with a translated pattern, and `$txt()` with a placeholder.
- [`public/drafts.html`](public/drafts.html): flash messages, a POST form with `$csrfField()`, and Policy reason codes turned into text through `$txt('reason_' . $reason, ...)`.
- [`public/notices.html`](public/notices.html): the management page of citomni/demo-provider in this app's layout. `config/citomni_http_routes.php` gives the package's route this template.
- [`public/contact.html`](public/contact.html): a form with `$csrfField()` and the captcha helpers, which a small script posts with `fetch()` as `FormData`. It shows the translated texts of the JSON answer and swaps in the new captcha that a wrong answer carries.
