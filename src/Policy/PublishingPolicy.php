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

namespace App\Policy;

use App\Enum\ArticleStatus;
use App\Util\Words;
use CitOmni\Kernel\App;


/**
 * PublishingPolicy: Policy that decides whether an article may be published.
 *
 * Keeps the publishing rules in one place, so that publishing an article and explaining why it
 * cannot be published apply the same rules. It only decides; acting on the answer is left to
 * its callers.
 *
 * Rules:
 * - Take rule values such as limits from cfg articles, never from callers.
 * - Treat REASON_* values as a stable public vocabulary: Never rename or reuse one; add a new
 *   value instead.
 */
class PublishingPolicy {

	/** The article is not a draft, and only drafts can be published. */
	public const REASON_NOT_DRAFT = 'not_draft';

	/** The body has fewer words than cfg articles.min_words_to_publish. */
	public const REASON_BODY_TOO_SHORT = 'body_too_short';


	/**
	 * Create the policy for the app whose cfg holds the rule values.
	 *
	 * @param  App  $app
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * List the reasons that block publishing an article.
	 *
	 * Behavior:
	 * - An empty list means the article may be published.
	 * - Lists every reason that applies, not only the first: REASON_NOT_DRAFT before
	 *   REASON_BODY_TOO_SHORT.
	 *
	 * @param  array{status:string,body:string}  $article  Article row; other keys are ignored.
	 * @return list<string>  REASON_* values.
	 * @throws \InvalidArgumentException  When the body is not valid UTF-8.
	 */
	public function refusalReasons(array $article): array {
		$reasons = [];

		if ($article['status'] !== ArticleStatus::Draft->value) {
			$reasons[] = self::REASON_NOT_DRAFT;
		}

		if (Words::count($article['body']) < (int)$this->app->cfg->articles->min_words_to_publish) {
			$reasons[] = self::REASON_BODY_TOO_SHORT;
		}

		return $reasons;
	}


}
