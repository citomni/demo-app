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

namespace App\Cli\Command;

use App\Operation\PublishArticle;
use CitOmni\Kernel\Command\BaseCommand;


/**
 * PublishArticleCommand: CLI adapter that publishes one draft article.
 *
 * Publishes through the PublishArticle Operation, so the terminal applies the publishing rules
 * that the Operation owns. It decides only how the result is shown: As text for people, or
 * with --json for scripts.
 *
 * Rules:
 * - Change state only through Operations.
 * - With --json, write exactly one JSON document to stdout for every SUCCESS or FAILURE, and
 *   nothing else.
 */
class PublishArticleCommand extends BaseCommand {

	/**
	 * Declare the arguments and options of the command.
	 *
	 * @return array{arguments?: array<string,array<string,mixed>>, options?: array<string,array<string,mixed>>}
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [
			'arguments' => [
				'id' => [
					'description' => 'Article id',
					'required'    => true,
					'type'        => 'int',
				],
			],
			'options' => [
				'json' => [
					'short'       => 'j',
					'type'        => 'bool',
					'description' => 'Emit the result as JSON',
				],
			],
		];
	}


	/**
	 * Publish one draft and report the outcome as text or JSON.
	 *
	 * The decision belongs to PublishArticle. This method translates its status into output and
	 * an exit code.
	 *
	 * Behavior:
	 * - USAGE when the id is below 1. Nothing is published.
	 * - SUCCESS when the draft was published, FAILURE for every other status.
	 * - With --json: One JSON line on stdout with ok, which is true only when the draft was
	 *   published, and result, which holds the result of PublishArticle. A usage error writes
	 *   no JSON.
	 * - Without --json: A success line on stdout, or an error line on stderr with the status and
	 *   the reasons.
	 *
	 * @return int  Exit code.
	 * @see \App\Operation\PublishArticle::execute()  The statuses and the result shape.
	 */
	protected function execute(): int {
		$articleId = $this->argInt('id');

		if ($articleId < 1) {
			$this->error('Article id must be >= 1.');

			return self::USAGE;
		}

		$result = (new PublishArticle($this->app))->execute($articleId);
		$published = $result['status'] === PublishArticle::RESULT_PUBLISHED;

		if ($this->getBool('json')) {
			$this->stdout(\json_encode([
				'ok'     => $published,
				'result' => $result,
			], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

			return $published ? self::SUCCESS : self::FAILURE;
		}

		if (!$published) {
			$reasons = $result['reasons'] === [] ? '' : ' (' . \implode(', ', $result['reasons']) . ')';
			$this->error('Article ' . $articleId . ' was not published: ' . $result['status'] . $reasons . '.');

			return self::FAILURE;
		}

		$this->success('Article ' . $articleId . ' published.');

		return self::SUCCESS;
	}


}
