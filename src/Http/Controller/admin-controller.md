# Admin Controller

A Controller in the admin section. `README.md` applies except where this file says otherwise.

## Contract

- `final class XController extends \CitOmni\Admin\Controller\AdminBaseController` (citomni/admin), not `BaseController`. Concrete admin Controllers are final, as the base class documents: `init()` and `hasAdminAccess()` stay overridable for the Controller's own setup and access rule, so a final Controller keeps a subclass from weakening the admin guard.
- `AdminBaseController::init()` is the admin guard: it requires `$this->app->auth->checkStrict()`, then `hasAdminAccess()`, redirects to the login path when either fails, and finally sends the admin response headers. Never bypass, duplicate or weaken it. Its source is authoritative for the current behavior.

## init() and hasAdminAccess()

- Do not declare `init()` unless the Controller needs its own setup. When it does:
  1) Initialize state that `hasAdminAccess()` depends on, such as a Policy instance.
  2) Call `parent::init()` exactly once.
  3) Initialize state that assumes an authenticated, authorized admin.
- Override `hasAdminAccess()` when the admin area needs another access rule than the default `$this->app->role->min('operator')`. Base it on a known Policy, Auth or Role contract. Authentication and the admin headers stay enforced by `init()`.

## Rules

- Mutations require CSRF protection to be enabled and refuse to run without it; then they verify the token. On rejection: a flash error and a 303 redirect back.
- Successful form posts: a flash message and an explicit 303 redirect (Post/Redirect/Get). JSON only for routes that are explicitly AJAX or API endpoints.
- Every admin-facing text, flash messages included, comes from `txt` with the layer and an English default. New texts go into the app's language files; see [`language/`](../../../language/README.md). A package's keys and layers are read from its language files, never assumed.
- Authorization rules belong in Policies; do not invent authentication, authorization or reauthentication rules in the Controller.

## Templates

- Admin pages extend `admin/admin_layout.html@citomni/admin`, so every admin page shares the admin frame. They fill its blocks `meta_title`, `page_css`, `page_content` and `page_scripts`; `admin/basic-admin-page-template.html@citomni/admin` is the starting point.
- Before writing form or list markup, look for a partial in `templates/admin/form/` and `templates/admin/list/` of citomni/admin.
- `AdminBaseController` has no render helper. An action that renders an admin page pulls the flash messages once and passes them under the names that the layout displays:

```php
$flash = $this->app->flash->pullAll();

$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [
	'articles'      => $articles,
	'flash_success' => $flash['msg']['success'] ?? [],
	'flash_info'    => $flash['msg']['info'] ?? [],
	'flash_warning' => $flash['msg']['warning'] ?? [],
	'errors'        => $flash['msg']['error'] ?? [],
]);
```

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace App\Http\Controller;

use App\Operation\PublishArticle;
use CitOmni\Admin\Controller\AdminBaseController;


/**
 * AdminArticleController: HTTP adapter for article administration.
 *
 * Gives admins the article actions that other clients may not take. Access belongs to the
 * admin base class, and domain decisions to Operations.
 *
 * Rules:
 * - Refuse every mutation unless CSRF protection is enabled for its request method.
 * - Change state only through Operations.
 */
final class AdminArticleController extends AdminBaseController {

	/**
	 * Publish one article and report the outcome through a flash message and a redirect.
	 *
	 * The decision belongs to PublishArticle. This action checks the request and translates
	 * the Operation's status for the browser, using Post/Redirect/Get so that a reload does
	 * not post again.
	 *
	 * Behavior:
	 * - Checks CSRF protection before anything else; see @throws.
	 * - 404 through httpError() when the id is below 1.
	 * - Every other path ends in a 303 redirect to /admin/articles.html with one flash message:
	 *     - Error when the CSRF token is rejected. Nothing is published.
	 *     - Success when the article was published.
	 *     - Warning for every other status.
	 *
	 * @param  string  $id  Article id as captured by the route.
	 * @return never
	 * @throws \RuntimeException  When CSRF protection is disabled for this request method.
	 * @see \App\Operation\PublishArticle::execute()  The statuses behind the flash messages.
	 */
	public function publish(string $id): never {
		if (!$this->app->csrf->isEnabled() || !$this->app->csrf->isProtectedMethod()) {
			throw new \RuntimeException('Administrative mutation requires enabled CSRF protection.');
		}

		$articleId = (int)$id;

		if ($articleId < 1) {
			$this->app->errorHandler->httpError(404, ['reason' => 'article_not_found']);
		}

		if (!$this->app->csrf->verify()) {
			$this->app->flash->error($this->app->txt->get('csrf_failed', 'admin_articles', 'app', 'The form expired. Try again.'));
			$this->app->response->redirect('/admin/articles.html', 303);
		}

		$result = (new PublishArticle($this->app))->execute($articleId);

		if ($result['status'] === PublishArticle::RESULT_PUBLISHED) {
			$this->app->flash->success($this->app->txt->get('article_published', 'admin_articles', 'app', 'Article published.'));
		} else {
			$this->app->flash->warning($this->app->txt->get('article_not_published', 'admin_articles', 'app', 'The article was not published.'));
		}

		$this->app->response->redirect('/admin/articles.html', 303);
	}

}
```
