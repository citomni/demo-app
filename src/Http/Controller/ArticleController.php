<?php
declare(strict_types=1);
/*
 * This file is part of the CitOmni framework.
 * Low overhead, high performance, ready for anything.
 *
 * For more information, visit https://github.com/citomni
 *
 * Copyright (c) 2012-present Lars Grove Mortensen
 * SPDX-License-Identifier: MIT
 *
 * For full copyright, trademark, and license information,
 * please see the LICENSE file distributed with this source code.
 */

namespace App\Http\Controller;

use App\Operation\PublishArticle;
use App\Policy\PublishingPolicy;
use App\Repository\ArticleRepository;
use App\Util\Words;
use CitOmni\Kernel\Controller\BaseController;


/**
 * ArticleController: HTTP adapter for articles.
 *
 * Gives clients and editors HTTP access to articles. It decides how a result is answered,
 * never whether an action may happen; domain decisions belong to Policies and Operations.
 *
 * Rules:
 * - Read through ArticleRepository; change state only through Operations.
 * - Never let a draft reach a client that may not see drafts. Answer as for a missing article.
 */
class ArticleController extends BaseController {

	/** Maximum number of articles in each list. */
	private const LIST_LIMIT = 20;


	/**
	 * Run setup and guards that every action needs.
	 *
	 * Notes:
	 * - Holds no access guard, so each action is exactly as exposed as its route. A guard added
	 *   here applies to every action.
	 *
	 * @return void
	 * @see \CitOmni\Kernel\Controller\BaseController::__construct()  Calls it before every action.
	 */
	protected function init(): void {
	}


	/**
	 * Render the newest published articles.
	 *
	 * @return void
	 */
	public function index(): void {
		$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [
			'articles' => (new ArticleRepository($this->app))->listPublished(self::LIST_LIMIT),
		]);
	}


	/**
	 * Answer the newest published articles as JSON.
	 *
	 * Behavior:
	 * - 200 with {"ok": true, "result": [{id, title, published_at}, ...]}.
	 *
	 * @return never
	 * @see \App\Repository\ArticleRepository::listPublished()  The order of the articles.
	 */
	public function indexJson(): never {
		$articles = [];

		// Fields picked by name, so a new Repository column does not reach the wire.
		foreach ((new ArticleRepository($this->app))->listPublished(self::LIST_LIMIT) as $article) {
			$articles[] = [
				'id'           => $article['id'],
				'title'        => $article['title'],
				'published_at' => $article['published_at'],
			];
		}

		$this->app->response->json([
			'ok'     => true,
			'result' => $articles,
		]);
	}


	/**
	 * Render one published article with its reading time.
	 *
	 * Behavior:
	 * - 404 through httpError() when no published article has the id.
	 *
	 * @param  string  $id  Article id as captured by the route.
	 * @return void
	 * @throws \InvalidArgumentException  When Words::readingMinutes() rejects articles.words_per_minute.
	 * @see \App\Util\Words::readingMinutes()  How reading_minutes is computed.
	 */
	public function show(string $id): void {
		$articleId = (int)$id;
		$article = $articleId > 0 ? (new ArticleRepository($this->app))->findPublishedById($articleId) : null;

		if ($article === null) {
			$this->app->errorHandler->httpError(404, ['reason' => 'article_not_found']);
		}

		$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [
			'article'         => $article,
			'reading_minutes' => Words::readingMinutes($article['body'], (int)$this->app->cfg->articles->words_per_minute),
		]);
	}


	/**
	 * Answer one published article as JSON.
	 *
	 * Behavior:
	 * - 200 with {"ok": true, "result": {id, title, body, published_at}}.
	 * - 404 problem document when no published article has the id.
	 *
	 * @param  string  $id  Article id as captured by the route.
	 * @return never
	 */
	public function showJson(string $id): never {
		$articleId = (int)$id;
		$article = $articleId > 0 ? (new ArticleRepository($this->app))->findPublishedById($articleId) : null;

		if ($article === null) {
			// Not httpError(): It negotiates HTML or JSON, and this URL promises JSON.
			$this->app->response->jsonProblem('Not Found', 404, 'No published article has this id.');
		}

		// Fields picked by name, so a new Repository column does not reach the wire.
		$this->app->response->json([
			'ok'     => true,
			'result' => [
				'id'           => $article['id'],
				'title'        => $article['title'],
				'body'         => $article['body'],
				'published_at' => $article['published_at'],
			],
		]);
	}


	/**
	 * Answer one published article as plain text.
	 *
	 * Behavior:
	 * - 200 with the title, a blank line and the body, marked noindex.
	 * - 404 with the translated article_not_found text when no published article has the id.
	 *
	 * @param  string  $id  Article id as captured by the route.
	 * @return never
	 */
	public function showText(string $id): never {
		$articleId = (int)$id;
		$article = $articleId > 0 ? (new ArticleRepository($this->app))->findPublishedById($articleId) : null;

		if ($article === null) {
			$this->app->response->text($this->app->txt->get('article_not_found', 'articles', 'app', 'The article does not exist.'), 404);
		}

		// The HTML page is the copy that search engines should list.
		$this->app->response->noIndex();
		$this->app->response->text($article['title'] . "\n\n" . $article['body'] . "\n");
	}


	/**
	 * Render the newest drafts with the reasons that block publishing them.
	 *
	 * Gives editors the publishing verdict before they try: Each draft carries the reasons
	 * PublishingPolicy would refuse it for, in refusal_reasons.
	 *
	 * Behavior:
	 * - Pulls the flash messages for the template.
	 *
	 * Notes:
	 * - Shows drafts without access control. Route it only where every client may see them.
	 *
	 * @return void
	 * @see \App\Policy\PublishingPolicy::refusalReasons()  The values in refusal_reasons.
	 */
	public function drafts(): void {
		$policy = new PublishingPolicy($this->app);
		$drafts = [];

		foreach ((new ArticleRepository($this->app))->listDrafts(self::LIST_LIMIT) as $draft) {
			$draft['refusal_reasons'] = $policy->refusalReasons($draft);
			$drafts[] = $draft;
		}

		$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [
			'drafts' => $drafts,
			'flash'  => $this->app->flash->pullAll(),
		]);
	}


	/**
	 * Publish one draft and report the outcome through a flash message and a redirect.
	 *
	 * The decision belongs to PublishArticle. This action validates the request and translates
	 * the Operation's status for the browser, using Post/Redirect/Get so that a reload does not
	 * post again.
	 *
	 * Behavior:
	 * - 404 through httpError() when the id is below 1.
	 * - Every other path ends in a 303 redirect to /drafts.html with exactly one flash message:
	 *     - Error when the CSRF token is rejected. Nothing is published.
	 *     - Success when the draft was published.
	 *     - Error when the article does not exist.
	 *     - Warning when PublishArticle refuses. The message does not name the reasons.
	 *
	 * Notes:
	 * - Publishes without access control. Route it only where every client may publish.
	 *
	 * @param  string  $id  Article id as captured by the route.
	 * @return never
	 * @see \App\Operation\PublishArticle::execute()  The statuses behind the flash messages.
	 */
	public function publish(string $id): never {
		$articleId = (int)$id;

		if ($articleId < 1) {
			$this->app->errorHandler->httpError(404, ['reason' => 'article_not_found']);
		}

		if (!$this->app->csrf->verify()) {

			// The form posts without a script, so a rejected token goes back to the list with a
			// message rather than to a 403 page.
			$this->app->flash->error($this->app->txt->get('flash_form_expired', 'articles', 'app', 'The form expired. Try again.'));
			$this->app->response->redirect('/drafts.html', 303);
		}

		$result = (new PublishArticle($this->app))->execute($articleId);

		if ($result['status'] === PublishArticle::RESULT_PUBLISHED) {
			$this->app->flash->success($this->app->txt->get('flash_published', 'articles', 'app', 'The article was published.'));

		} elseif ($result['status'] === PublishArticle::RESULT_NOT_FOUND) {
			$this->app->flash->error($this->app->txt->get('flash_not_found', 'articles', 'app', 'The article does not exist.'));

		} else {
			// RESULT_REFUSED is the only status left; a new status needs its own branch.
			$this->app->flash->warning($this->app->txt->get('flash_refused', 'articles', 'app', 'The article was not published.'));
		}

		$this->app->response->redirect('/drafts.html', 303);
	}


}
