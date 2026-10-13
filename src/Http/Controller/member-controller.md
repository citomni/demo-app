# Member Controller

A Controller in the authenticated member section. `README.md` applies except where this file says otherwise.

## Contract

- `final class XController extends \CitOmni\Authenticate\Controller\MemberBaseController` (citomni/authenticate), not `BaseController`. Concrete member Controllers are final, as in the base class's usage example.
- Extend it only when every action of the Controller requires a signed-in member. A Controller that mixes public and member-only actions keeps explicit guards per action instead.
- Declare no `init()`: `MemberBaseController::init()` is final. It resolves the identity through `auth`, remembers the intended URL for an unauthenticated GET, redirects to the login path, assigns `$this->identity` and sends the member response headers. Its source is authoritative for the current behavior.

## Rules

- `$this->identity` holds the authenticated identity; prefer `$this->identityId()` when only the id is needed. Never call `auth` just to find the same identity again; other Auth APIs only when the workflow needs more than the inherited identity.
- Render route-configured pages with `$this->render([...])`. It renders `template_file@template_layer` and adds the pulled flash data as the `flash` variable. Do not call `tplEngine` directly for that path, and do not pull flash before `render()`.
- Session only for explicit member workflow state, never for authentication state that citomni/authenticate owns. Cookies only when the task needs them.
- Unsafe actions verify CSRF; `verify()` takes optional log context such as a stable action id.
- Form posts use Post/Redirect/Get with flash messages, field errors and old input, and an explicit 303 redirect.
- Every member-facing text, flash messages included, comes from `txt` with the layer and an English default. New texts go into the app's language files; see [`language/`](../../../language/README.md).
- A sensitive action that needs a recent credential check, such as changing an e-mail address, uses the reauthentication of citomni/authenticate: `$this->app->auth->requireReauth($maxAgeSeconds)` throws `ReauthRequiredException`, which the action turns into a redirect to the reauthentication page.
- Authentication, authorization and reauthentication rules, session keys and cookie names belong to citomni/authenticate or to existing app code. Read them from the source; never invent them in a Controller.
- A form action may be declared `void` even when its branches end in redirects; `never` when every path ends in a terminating Response call.

## Templates

- Member pages extend the app's own layout, so the member section looks like the rest of the app. The member pages of citomni/authenticate extend `member/member_layout.html@citomni/authenticate`; an app that wants that look may extend it too.

## Pattern

Placeholder names; replace them with the real ones. `NoteRepository` is not part of this app.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace App\Http\Controller;

use App\Repository\NoteRepository;
use CitOmni\Authenticate\Controller\MemberBaseController;


/**
 * MemberNoteController: HTTP adapter for a member's own notes.
 *
 * Gives a signed-in member HTTP access to the notes that the member owns. Authentication
 * belongs to the member base class.
 *
 * Rules:
 * - Read and change only notes owned by $this->identityId().
 */
final class MemberNoteController extends MemberBaseController {

	/**
	 * Render the member's notes.
	 *
	 * @return void
	 */
	public function index(): void {
		$notes = (new NoteRepository($this->app))->listByOwner($this->identityId());

		$this->render([
			'notes' => $notes,
		]);
	}


	/**
	 * Delete one of the member's notes and report the outcome through a flash message and a redirect.
	 *
	 * Uses Post/Redirect/Get, so a reload does not post again. A note that the member does not
	 * own counts as missing.
	 *
	 * Behavior:
	 * - Every path ends in a 303 redirect to /member/notes.html with one flash message:
	 *     - Error when the CSRF token is rejected. Nothing is deleted.
	 *     - Success when the note was deleted.
	 *     - Warning when the id is below 1 or the member has no note with it.
	 *
	 * @param  string  $id  Note id as captured by the route.
	 * @return void
	 */
	public function delete(string $id): void {
		if (!$this->app->csrf->verify(['action' => 'member_note_delete'])) {
			$this->app->flash->error($this->app->txt->get('flash_form_expired', 'member_notes', 'app', 'The form expired. Try again.'));
			$this->app->response->redirect('/member/notes.html', 303);
		}

		$deleted = (int)$id > 0
			&& (new NoteRepository($this->app))->deleteForOwner((int)$id, $this->identityId());

		if ($deleted) {
			$this->app->flash->success($this->app->txt->get('flash_deleted', 'member_notes', 'app', 'Note deleted.'));
		} else {
			$this->app->flash->warning($this->app->txt->get('flash_not_deleted', 'member_notes', 'app', 'The note was not deleted.'));
		}

		$this->app->response->redirect('/member/notes.html', 303);
	}

}
```
