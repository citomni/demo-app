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

namespace App\Tests;

use App\Operation\PublishArticle;
use App\Policy\PublishingPolicy;
use App\Repository\ArticleRepository;
use App\Tests\Support\Kernel\App;

/*
 * Isolated suite for the article example: the decisions of PublishingPolicy, and
 * the argument guards of ArticleRepository and PublishArticle.
 *
 * The app double registers no services. Code that reached the database would
 * throw the double's \RuntimeException instead of the expected exception, so the
 * guard cases also prove that invalid input never reaches persistence.
 *
 * Usage:
 *   php tests/articles/run.php
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


/**
 * Create an App double with only the word minimum in cfg and no services.
 *
 * @param  int  $minWords  Value of cfg articles.min_words_to_publish.
 * @return App
 */
function appWithMinimum(int $minWords): App {
	return new App(['articles' => ['min_words_to_publish' => $minWords]]);
}


/**
 * Build the article fields that PublishingPolicy::refusalReasons() takes.
 *
 * @param  string  $status  Stored status.
 * @param  int  $words  Number of words in the body.
 * @return array{status:string,body:string}
 */
function article(string $status, int $words): array {
	return ['status' => $status, 'body' => \trim(\str_repeat('word ', $words))];
}


// -- 1. PublishingPolicy -------------------------------------------------------
// The reason codes are compared as strings on purpose: They are a public vocabulary,
// so renaming one is a breaking change.

test('PublishingPolicy allows a draft that reaches the word minimum', function (): void {
	$policy = new PublishingPolicy(appWithMinimum(5));

	check($policy->refusalReasons(article('draft', 5)) === [], 'A draft with exactly the minimum was refused');
	check($policy->refusalReasons(article('draft', 6)) === [], 'A draft above the minimum was refused');
});

test('PublishingPolicy refuses a draft below the word minimum with body_too_short', function (): void {
	$policy = new PublishingPolicy(appWithMinimum(5));

	check($policy->refusalReasons(article('draft', 4)) === ['body_too_short'], 'A draft one word short was not refused');
	check($policy->refusalReasons(article('draft', 0)) === ['body_too_short'], 'An empty draft was not refused');
});

test('PublishingPolicy refuses every status but draft with not_draft', function (): void {
	$policy = new PublishingPolicy(appWithMinimum(5));

	check($policy->refusalReasons(article('published', 5)) === ['not_draft'], 'A published article was not refused');
	check($policy->refusalReasons(article('archived', 5)) === ['not_draft'], 'An unknown status was not refused');
});

test('PublishingPolicy lists every reason, in check order', function (): void {
	$reasons = (new PublishingPolicy(appWithMinimum(5)))->refusalReasons(article('published', 1));

	check($reasons === ['not_draft', 'body_too_short'], 'Unexpected reasons: ' . \implode(', ', $reasons));
});

test('PublishingPolicy reads the word minimum from cfg articles.min_words_to_publish', function (): void {
	check((new PublishingPolicy(appWithMinimum(3)))->refusalReasons(article('draft', 3)) === [], 'Minimum 3 refused 3 words');
	check((new PublishingPolicy(appWithMinimum(4)))->refusalReasons(article('draft', 3)) === ['body_too_short'], 'Minimum 4 allowed 3 words');
});


// -- 2. Argument guards --------------------------------------------------------

test('ArticleRepository rejects ids and limits below 1 before it reaches the database', function (): void {
	$articles = new ArticleRepository(new App());

	throws(\InvalidArgumentException::class, fn() => $articles->findById(0));
	throws(\InvalidArgumentException::class, fn() => $articles->findPublishedById(0));
	throws(\InvalidArgumentException::class, fn() => $articles->markPublished(-1));
	throws(\InvalidArgumentException::class, fn() => $articles->listPublished(0));
	throws(\InvalidArgumentException::class, fn() => $articles->listDrafts(0));
});

test('ArticleRepository rejects an empty or too long title before it reaches the database', function (): void {
	$articles = new ArticleRepository(new App());

	throws(\InvalidArgumentException::class, fn() => $articles->insertDraft('', 'Body'));
	throws(\InvalidArgumentException::class, fn() => $articles->insertDraft(" \t ", 'Body'));

	// 201 two-byte characters: The limit counts characters, not bytes.
	throws(\InvalidArgumentException::class, fn() => $articles->insertDraft(\str_repeat('é', 201), 'Body'));
});

test('PublishArticle rejects an article id below 1 with its own guard', function (): void {
	$publish = new PublishArticle(new App());

	foreach ([0, -1] as $articleId) {
		$e = throws(\InvalidArgumentException::class, fn() => $publish->execute($articleId));

		// ArticleRepository::findById() rejects the same id with the same exception,
		// so only the throw site shows that the Operation checked it first.
		check(\basename($e->getFile()) === 'PublishArticle.php', 'Thrown by ' . \basename($e->getFile()) . ', not by PublishArticle');
	}
});

done();
