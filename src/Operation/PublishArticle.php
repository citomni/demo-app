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

namespace App\Operation;

use App\Policy\PublishingPolicy;
use App\Repository\ArticleRepository;
use CitOmni\Kernel\Operation\BaseOperation;


/**
 * PublishArticle: Operation that publishes one draft article.
 *
 * Owns the decision whether a draft becomes published, so that every adapter publishes by the
 * same rules. The rules themselves belong to PublishingPolicy; turning the result into a
 * response belongs to the adapter.
 *
 * Rules:
 * - Report expected outcomes as RESULT_* statuses, never as exceptions.
 * - Return every outcome in the one shape that result() builds.
 */
class PublishArticle extends BaseOperation {

	/** This call published the draft. */
	public const RESULT_PUBLISHED = 'published';

	/** No article has the id. */
	public const RESULT_NOT_FOUND = 'not_found';

	/** The article was not published; the reasons say why. */
	public const RESULT_REFUSED = 'refused';

	/** Refusal reason: The article stopped being a draft after PublishingPolicy allowed it. */
	public const REASON_STATE_CHANGED = 'state_changed';


	/**
	 * Publish one draft when PublishingPolicy allows it.
	 *
	 * Reads the article in any status, so that an article that exists but is not a draft is
	 * refused, never reported as missing.
	 *
	 * Behavior:
	 * - RESULT_PUBLISHED when this call published the draft. Of concurrent calls for one draft,
	 *   at most one publishes it.
	 * - RESULT_NOT_FOUND when no article has the id.
	 * - RESULT_REFUSED when nothing was written:
	 *     - with the reasons of PublishingPolicy when it refuses,
	 *     - with REASON_STATE_CHANGED when the article stopped being a draft after the check.
	 * - reasons is empty unless the status is RESULT_REFUSED.
	 *
	 * Typical usage:
	 *   $result = (new PublishArticle($this->app))->execute($articleId);
	 *
	 * @param  int  $articleId  At least 1.
	 * @return array{status:string,article_id:int,reasons:list<string>}
	 * @throws \InvalidArgumentException  When the article id is below 1.
	 * @see \App\Policy\PublishingPolicy::refusalReasons()  The reasons of a policy refusal.
	 */
	public function execute(int $articleId): array {
		if ($articleId < 1) {
			throw new \InvalidArgumentException('Article id must be >= 1.');
		}

		$articles = new ArticleRepository($this->app);
		$article = $articles->findById($articleId);

		if ($article === null) {
			return $this->result(self::RESULT_NOT_FOUND, $articleId);
		}

		$reasons = (new PublishingPolicy($this->app))->refusalReasons($article);

		if ($reasons !== []) {
			return $this->result(self::RESULT_REFUSED, $articleId, $reasons);
		}

		if (!$articles->markPublished($articleId)) {
			return $this->result(self::RESULT_REFUSED, $articleId, [self::REASON_STATE_CHANGED]);
		}

		return $this->result(self::RESULT_PUBLISHED, $articleId);
	}


	/**
	 * Build a result in the one shape that execute() returns.
	 *
	 * @param  string  $status  RESULT_* value.
	 * @param  int  $articleId
	 * @param  list<string>  $reasons  Refusal reasons; empty unless the status is RESULT_REFUSED.
	 * @return array{status:string,article_id:int,reasons:list<string>}
	 */
	private function result(string $status, int $articleId, array $reasons = []): array {
		return [
			'status'     => $status,
			'article_id' => $articleId,
			'reasons'    => $reasons,
		];
	}


}
