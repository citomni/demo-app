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

use App\Repository\ArticleRepository;
use CitOmni\Kernel\Command\BaseCommand;


/**
 * CreateArticleCommand: CLI adapter that creates a draft article.
 *
 * Gives editors a way to create drafts from the terminal. It decides how a result is
 * reported, never which values are valid; value rules belong to ArticleRepository.
 *
 * Rules:
 * - Write only through ArticleRepository.
 */
class CreateArticleCommand extends BaseCommand {

	/**
	 * Declare the arguments and options of the command.
	 *
	 * @return array<string,array<string,mixed>>
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [
			'arguments' => [
				'title' => [
					'description' => 'Article title, 1 to 200 characters',
					'required'    => true,
				],
			],
			'options' => [
				'body' => [
					'short'       => 'b',
					'type'        => 'string',
					'description' => 'Plain-text body',
					'default'     => '',
				],
			],
		];
	}


	/**
	 * Insert the draft and report its id.
	 *
	 * Creates an unpublished draft from the title argument and the --body option.
	 *
	 * Behavior:
	 * - SUCCESS with the new id on stdout.
	 * - FAILURE with the reason on stderr when ArticleRepository refuses a value.
	 *
	 * @return int  Exit code.
	 * @see \App\Repository\ArticleRepository::insertDraft()  The rules for the values.
	 */
	protected function execute(): int {
		// Creating a draft has no workflow to coordinate, so the Repository is called directly.
		try {
			$articleId = (new ArticleRepository($this->app))->insertDraft($this->argString('title'), $this->getString('body'));
		} catch (\InvalidArgumentException $e) {
			$this->error($e->getMessage());

			return self::FAILURE;
		}

		$this->success('Draft ' . $articleId . ' created.');

		return self::SUCCESS;
	}


}
