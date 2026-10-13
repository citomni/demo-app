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

namespace App\Util;


/**
 * Words: Util that counts words and times the reading of plain text.
 *
 * Gives the app one definition of a word, so that every word count and every reading time
 * agree on how long a text is.
 *
 * Rules:
 * - Count words only through count(), so every member shares its definition of a word.
 * - Reject text that is not valid UTF-8 instead of counting it.
 */
class Words {

	/**
	 * Count the words in a text.
	 *
	 * Behavior:
	 * - A word is a run of non-whitespace characters.
	 *
	 * @param  string  $text  Plain UTF-8 text.
	 * @return int  0 for an empty or whitespace-only text.
	 * @throws \InvalidArgumentException  When the text is not valid UTF-8.
	 */
	public static function count(string $text): int {
		$words = \preg_match_all('/\S+/u', $text);

		if ($words === false) {
			throw new \InvalidArgumentException('Text must be valid UTF-8.');
		}

		return $words;
	}


	/**
	 * Compute the minutes needed to read a text, rounded up to whole minutes.
	 *
	 * @param  string  $text  Plain UTF-8 text.
	 * @param  int  $wordsPerMinute  Reading speed, at least 1.
	 * @return int  0 for a text without words, otherwise at least 1.
	 * @throws \InvalidArgumentException  When the speed is below 1 or the text is not valid UTF-8.
	 * @see \App\Util\Words::count()  What counts as a word.
	 */
	public static function readingMinutes(string $text, int $wordsPerMinute): int {
		if ($wordsPerMinute < 1) {
			throw new \InvalidArgumentException('Words per minute must be >= 1.');
		}

		return (int)\ceil(self::count($text) / $wordsPerMinute);
	}


}
