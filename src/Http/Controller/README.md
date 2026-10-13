# Http/Controller

A Controller is the HTTP adapter for a group of routes. It owns everything HTTP: request input, CSRF, session, cookie and flash handling, redirects, JSON or HTML output, headers, status codes, downloads, cache and index directives, and template rendering.

A provider package keeps its Controllers in `src/Controller/`, without the `Http/` level, and routes them in its Registry; [citomni/demo-provider](https://github.com/citomni/demo-provider) is the guide for that.

## Variants

- Admin section, extends `AdminBaseController` from citomni/admin: [admin-controller.md](admin-controller.md).
- Member section, extends `MemberBaseController` from citomni/authenticate: [member-controller.md](member-controller.md).

## Contract

- `class ArticleController extends \CitOmni\Kernel\Controller\BaseController`. The name ends with `Controller`.
- No constructor. The Router creates the Controller with `$this->app` and `$this->routeConfig`. `routeConfig` holds only `template_file` and `template_layer` from the route, null when the route sets none.
- A Controller that extends `BaseController` directly declares `protected function init(): void`, empty when nothing applies. It runs on every dispatch, so it holds only setup or guards that every action needs. Admin and member Controllers follow their variant instead: there, `init()` is the access guard.

## Routes

- App routes: `config/citomni_http_routes.php`; dev-only routes: `config/citomni_http_routes.dev.php`. Provider routes: Registry `ROUTES_HTTP`.
- The app's route files merge after the providers' routes, key by key. An entry with only `template_file` and `template_layer` gives a provider route another template and keeps its controller, as `'/notices/manage.html'` in `config/citomni_http_routes.php` does. An entry set to `null` removes a provider route, like `'/notices.json' => null`; a placeholder route is removed the same way under `'regex'`.
- Exact routes are keyed by path. Placeholder routes go under `'regex'`, like `'/article/{id}.html'`. Built-in placeholders: `{id}` (digits), `{slug}`, `{code}`, `{email}`; any other name matches one path segment.
- The extension states what a route returns: `.html` for HTML, `.json` for JSON, `.txt` for plain text, and the file's own extension for a download, like `'/download-example.pdf'`. The root `/` and routes that only redirect, such as a form POST like `'/article/{id}/publish'`, have none.
- Captured values arrive as positional action arguments, always as strings. Declare those parameters `string` and convert explicitly: the Router dispatches under strict types, so an `int` parameter throws a `\TypeError`. `{id}` matches digits only, 0 included, so `(int)$id` needs a range check before it reaches code that requires an id of at least 1.
- The route's `'methods'` decides the allowed verbs. The Router answers 405, adds HEAD for GET and handles OPTIONS, so actions do not check the method again.

## Delegation

- No SQL and no Db access.
- Trivial route-specific CRUD: `new ArticleRepository($this->app)`.
- A workflow (shared with CLI, several Repositories, side effects, state transitions): `(new PublishArticle($this->app))->execute(...)`, then translate the result to HTTP.
- Registered Services through `$this->app->serviceId`.
- Never push transport concerns down into Operations, Repositories, Services, Support, Policy, State or Utils.

## Output

- Input from `$this->app->request` (`post()`, `get()`, `input()`, `json()`, ...). Output through `$this->app->response`.
- HTML views: `$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [...])`. CitOmni has its own template language, not Twig: see [`templates/`](../../../templates/README.md).
- `redirect()`, `json()`, `jsonStatus()`, `jsonProblem()`, `text()`, `html()` and `download()` end the request (`never`), and so does `$this->app->errorHandler->httpError()`. `render()` returns.
- An action that completes through `render()` returns `void`. Declare `never` only when every path ends in one of the terminating calls.
- `jsonStatus()` instead of `json()` when the status must differ from 200; `jsonProblem()` for problem responses.
- A JSON answer picks the fields it publishes by name. Never pass Repository rows through unchanged: a column added for an HTML page would reach the wire.
- Error pages: `$this->app->errorHandler->httpError(404, [...])` logs, renders the page and ends the request. It is typed `never`, so no `return;` follows it.
- A `.json` action answers JSON also when it fails: `jsonProblem()`, not `httpError()`, which gives a browser, curl and a plain `fetch()` an HTML page. A `.txt` action fails with `text(..., $status)`.
- A page that posts with `fetch()` sends its form as `FormData`, so the fields of `$csrfField()` and `$captchaField()` reach `csrf->verify()` and `captcha->verify()` unchanged, and its action answers JSON.
- Files that the app serves itself lie outside `public/` and go out through `download()`. A file in `public/` is served by the web server, past every Controller.
- Response metadata (`setStatus()`, `setHeader()`, `noCache()`, `noIndex()`) goes before the call that completes the response.
- Unsafe actions verify CSRF when the app enables it: `$this->app->csrf->verify()` returns false on rejection, `requireValid()` throws instead.
- A form that posts without a script uses Post/Redirect/Get: messages and old input go to `$this->app->flash`, followed by an explicit 303 redirect.
- Translated text through `$this->app->txt->get($key, $file, $layer, $default)`, see [`language/`](../../../language/README.md).

## Failures

- Catch only known, recoverable failures that become a deliberate HTTP result. No `\Throwable` catches.

## Example

[`ArticleController.php`](ArticleController.php), routed in `config/citomni_http_routes.php` and, for its dev-only actions, in `config/citomni_http_routes.dev.php`:

- `index()` and `show()` render templates and read through the Repository directly. `show()` range-checks the captured id; `findPublishedById()` returns null for drafts, so anything but a published article answers 404.
- `indexJson()` and `showJson()` serve the same data with `json()`, each with the fields it publishes picked by name. `showJson()` answers a missing article with a 404 `jsonProblem()`.
- `showText()` sends `noIndex()` and then the article with `text()`; a missing article gets `text()` with status 404.
- `drafts()` asks `PublishingPolicy` why each draft cannot be published, and pulls the flash messages for the template.
- `publish()` verifies CSRF, delegates to the `PublishArticle` Operation, and turns its status into a flash message and a 303 redirect.
- Its templates are in `templates/public/`, its texts in `language/<language>/articles.php`.

[`DownloadController.php`](DownloadController.php): `example()` streams a PDF from `var/storage/` with `download()` and a suggested file name. The file lies outside `public/`, so the route is the only way to it, and the action is where access checks belong.

[`ContactController.php`](ContactController.php): `form()` renders the contact page. `send()` answers its `fetch()` in JSON: it verifies CSRF, asks `SendContactMessage::preflight()` about the rate limit and the fields before it checks the captcha, and stores with `execute()`. A rate limit refusal gets `Retry-After` with a 429; every refused field is listed in the `invalid_params` of a 422 `jsonProblem()`. A wrong captcha uses up its challenge, so that problem carries a new one. A stored message gets `jsonStatus()` with 201.

No example uses `html()`; its rules are under Output.
