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
 * ContactMessage: Util with the field rules of a contact form message.
 *
 * Keeps the rules in one place, so that every layer that shows, checks or stores a message
 * applies the same limits. It checks values as given and leaves trimming to its callers.
 *
 * Rules:
 * - Keep MAX_LENGTHS within the column sizes of demo_contact_messages, so a message that
 *   passes always fits.
 * - Treat REASON_* values as a stable public vocabulary: Never rename or reuse one; add a new
 *   value instead.
 */
class ContactMessage {

	/** Maximum length in characters per field. */
	public const MAX_LENGTHS = [
		'name'    => 100,
		'email'   => 254,
		'message' => 5000,
	];

	/** The field is empty. */
	public const REASON_REQUIRED = 'required';

	/** The field has more characters than its MAX_LENGTHS entry. */
	public const REASON_TOO_LONG = 'too_long';

	/** The field is not valid UTF-8, or the e-mail address has an invalid format. */
	public const REASON_INVALID = 'invalid';


	/**
	 * Check a message and return the problem of each refused field.
	 *
	 * Behavior:
	 * - An empty array means the message can be stored.
	 * - Each field gets at most one reason, from the first check that fails:
	 *     1) REASON_INVALID when the value is not valid UTF-8.
	 *     2) REASON_REQUIRED when it is empty.
	 *     3) REASON_TOO_LONG when it has more characters than MAX_LENGTHS allows.
	 *     4) REASON_INVALID when it is the e-mail address and FILTER_VALIDATE_EMAIL rejects it.
	 * - Lengths count characters, not bytes.
	 *
	 * @param  string  $name  Sender name, trimmed.
	 * @param  string  $email  Sender e-mail address, trimmed.
	 * @param  string  $message  Message text, trimmed.
	 * @return array<string,string>  REASON_* per field name, in the order name, email, message.
	 */
	public static function problems(string $name, string $email, string $message): array {
		$problems = [];

		foreach (['name' => $name, 'email' => $email, 'message' => $message] as $field => $value) {
			if (!\mb_check_encoding($value, 'UTF-8')) {
				$problems[$field] = self::REASON_INVALID;
			} elseif ($value === '') {
				$problems[$field] = self::REASON_REQUIRED;
			} elseif (\mb_strlen($value, 'UTF-8') > self::MAX_LENGTHS[$field]) {
				$problems[$field] = self::REASON_TOO_LONG;
			} elseif ($field === 'email' && \filter_var($value, \FILTER_VALIDATE_EMAIL) === false) {
				$problems[$field] = self::REASON_INVALID;
			}
		}

		return $problems;
	}


}
