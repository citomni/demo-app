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

namespace App\Repository;

use App\Enum\ArticleStatus;
use CitOmni\Kernel\Repository\BaseRepository;


/**
 * ArticleRepository: Repository for articles in demo_articles.
 *
 * Owns every query on demo_articles. Whether an article may change state is decided by its
 * callers; this class only makes sure that a state change still matches the state the caller
 * checked.
 *
 * Rules:
 * - Return rows with cast values, and datetimes as 'Y-m-d H:i:s' strings in the app timezone.
 * - Take timestamps from the database clock, never from PHP.
 * - Pass statuses as ArticleStatus values in SQL parameters, never as literals.
 */
class ArticleRepository extends BaseRepository {

	/** Maximum title length in characters. demo_articles.title must hold at least this many. */
	private const TITLE_MAX_LENGTH = 200;


	/**
	 * Find one article by id, in any status.
	 *
	 * @param  int  $articleId  At least 1.
	 * @return array{id:int,title:string,body:string,status:string,created_at:string,published_at:?string}|null  Null when no article has the id.
	 * @throws \InvalidArgumentException  When the id is below 1.
	 */
	public function findById(int $articleId): ?array {
		if ($articleId < 1) {
			throw new \InvalidArgumentException('Article id must be >= 1.');
		}

		$row = $this->app->db->fetchRow(
			'SELECT id, title, body, status, created_at, published_at
			 FROM demo_articles
			 WHERE id = ?
			 LIMIT 1',
			[$articleId]
		);

		if ($row === null) {
			return null;
		}

		return [
			'id'           => (int)$row['id'],
			'title'        => (string)$row['title'],
			'body'         => (string)$row['body'],
			'status'       => (string)$row['status'],
			'created_at'   => (string)$row['created_at'],
			'published_at' => $row['published_at'] === null ? null : (string)$row['published_at'],
		];
	}


	/**
	 * Find one published article by id.
	 *
	 * Behavior:
	 * - A draft returns null, like a missing article, so public callers cannot tell them apart.
	 *
	 * @param  int  $articleId  At least 1.
	 * @return array{id:int,title:string,body:string,published_at:string}|null  Null when no published article has the id.
	 * @throws \InvalidArgumentException  When the id is below 1.
	 */
	public function findPublishedById(int $articleId): ?array {
		if ($articleId < 1) {
			throw new \InvalidArgumentException('Article id must be >= 1.');
		}

		$row = $this->app->db->fetchRow(
			'SELECT id, title, body, published_at
			 FROM demo_articles
			 WHERE id = ? AND status = ?
			 LIMIT 1',
			[$articleId, ArticleStatus::Published->value]
		);

		if ($row === null) {
			return null;
		}

		return [
			'id'           => (int)$row['id'],
			'title'        => (string)$row['title'],
			'body'         => (string)$row['body'],
			'published_at' => (string)$row['published_at'],
		];
	}


	/**
	 * List the newest published articles.
	 *
	 * Behavior:
	 * - Newest published_at first; for the same time, the higher id first.
	 *
	 * @param  int  $limit  Maximum number of articles, at least 1.
	 * @return list<array{id:int,title:string,published_at:string}>
	 * @throws \InvalidArgumentException  When the limit is below 1.
	 */
	public function listPublished(int $limit): array {
		if ($limit < 1) {
			throw new \InvalidArgumentException('Limit must be >= 1.');
		}

		$rows = $this->app->db->fetchAll(
			'SELECT id, title, published_at
			 FROM demo_articles
			 WHERE status = ?
			 ORDER BY published_at DESC, id DESC
			 LIMIT ?',
			[ArticleStatus::Published->value, $limit]
		);

		$articles = [];

		foreach ($rows as $row) {
			$articles[] = [
				'id'           => (int)$row['id'],
				'title'        => (string)$row['title'],
				'published_at' => (string)$row['published_at'],
			];
		}

		return $articles;
	}


	/**
	 * List the newest drafts, with their body and status.
	 *
	 * Behavior:
	 * - Newest created_at first; for the same time, the higher id first.
	 *
	 * @param  int  $limit  Maximum number of drafts, at least 1.
	 * @return list<array{id:int,title:string,body:string,status:string}>
	 * @throws \InvalidArgumentException  When the limit is below 1.
	 */
	public function listDrafts(int $limit): array {
		if ($limit < 1) {
			throw new \InvalidArgumentException('Limit must be >= 1.');
		}

		$rows = $this->app->db->fetchAll(
			'SELECT id, title, body, status
			 FROM demo_articles
			 WHERE status = ?
			 ORDER BY created_at DESC, id DESC
			 LIMIT ?',
			[ArticleStatus::Draft->value, $limit]
		);

		$drafts = [];

		foreach ($rows as $row) {
			$drafts[] = [
				'id'     => (int)$row['id'],
				'title'  => (string)$row['title'],
				'body'   => (string)$row['body'],
				'status' => (string)$row['status'],
			];
		}

		return $drafts;
	}


	/**
	 * Insert one draft and return its id.
	 *
	 * Every article starts as a draft; publishing it is a separate, guarded state change.
	 *
	 * Behavior:
	 * - Stores the title trimmed and the body as given.
	 *
	 * @param  string  $title  1 to 200 characters after trimming.
	 * @param  string  $body  Plain text; may be empty.
	 * @return int  Id of the new article.
	 * @throws \InvalidArgumentException  When the trimmed title is empty or longer than 200 characters.
	 */
	public function insertDraft(string $title, string $body): int {
		$title = \trim($title);

		if ($title === '') {
			throw new \InvalidArgumentException('Title cannot be empty.');
		}

		if (\mb_strlen($title) > self::TITLE_MAX_LENGTH) {
			throw new \InvalidArgumentException('Title cannot be longer than ' . self::TITLE_MAX_LENGTH . ' characters.');
		}

		return $this->app->db->insert('demo_articles', [
			'title'  => $title,
			'body'   => $body,
			'status' => ArticleStatus::Draft->value,
		]);
	}


	/**
	 * Publish one draft and stamp published_at.
	 *
	 * Safe to call after a status check that may be stale: The write itself re-checks that the
	 * article is a draft, so the article is published once.
	 *
	 * Behavior:
	 * - Of concurrent calls for the same draft, exactly one returns true.
	 * - Leaves a missing article and an article that is no longer a draft unchanged.
	 *
	 * @param  int  $articleId  At least 1.
	 * @return bool  True when this call published the article.
	 * @throws \InvalidArgumentException  When the id is below 1.
	 */
	public function markPublished(int $articleId): bool {
		if ($articleId < 1) {
			throw new \InvalidArgumentException('Article id must be >= 1.');
		}

		$updated = $this->app->db->execute(
			'UPDATE demo_articles
			 SET status = ?, published_at = NOW()
			 WHERE id = ? AND status = ?',
			[ArticleStatus::Published->value, $articleId, ArticleStatus::Draft->value]
		);

		return $updated === 1;
	}


}
