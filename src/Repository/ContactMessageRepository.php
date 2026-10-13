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

use CitOmni\Kernel\Repository\BaseRepository;


/**
 * ContactMessageRepository: Repository for contact form messages in demo_contact_messages.
 *
 * Owns every query on demo_contact_messages. Whether a message is acceptable is decided by its
 * callers; this class only makes sure that what it stores fits the table unchanged.
 *
 * Rules:
 * - Store only values that their columns hold unchanged, whatever the server's sql_mode.
 * - Take timestamps from the database clock, never from PHP.
 */
class ContactMessageRepository extends BaseRepository {

	/** Maximum name length in characters. demo_contact_messages.name must hold at least this many. */
	private const NAME_MAX_LENGTH = 100;

	/** Maximum e-mail address length in characters. demo_contact_messages.email must hold at least this many. */
	private const EMAIL_MAX_LENGTH = 254;

	/** Maximum message length in bytes. demo_contact_messages.message must hold at least this many. */
	private const MESSAGE_MAX_BYTES = 65535;


	/**
	 * Store one message and return its id.
	 *
	 * Trims the values itself, so the stored row is the same whether or not the caller trimmed
	 * them, and checks the trimmed values against the columns.
	 *
	 * @param  string  $name  Sender name; valid UTF-8 of at most 100 characters after trimming.
	 * @param  string  $email  Sender e-mail address; valid UTF-8 of at most 254 characters after trimming.
	 * @param  string  $message  Message text; valid UTF-8 of at most 65535 bytes after trimming.
	 * @return int  Id of the new message.
	 * @throws \InvalidArgumentException  When a trimmed value is not valid UTF-8 or is longer than its column allows.
	 */
	public function insert(string $name, string $email, string $message): int {
		$row = [
			'name'    => \trim($name),
			'email'   => \trim($email),
			'message' => \trim($message),
		];

		// Refused here rather than left to the server, which outside strict mode would replace
		// invalid bytes and cut a long value short instead of failing.
		foreach ($row as $column => $value) {
			if (!\mb_check_encoding($value, 'UTF-8')) {
				throw new \InvalidArgumentException('Contact message ' . $column . ' is not valid UTF-8.');
			}
		}

		if (\mb_strlen($row['name'], 'UTF-8') > self::NAME_MAX_LENGTH) {
			throw new \InvalidArgumentException('Contact message name cannot be longer than ' . self::NAME_MAX_LENGTH . ' characters.');
		}

		if (\mb_strlen($row['email'], 'UTF-8') > self::EMAIL_MAX_LENGTH) {
			throw new \InvalidArgumentException('Contact message email cannot be longer than ' . self::EMAIL_MAX_LENGTH . ' characters.');
		}

		if (\strlen($row['message']) > self::MESSAGE_MAX_BYTES) {
			throw new \InvalidArgumentException('Contact message text cannot be longer than ' . self::MESSAGE_MAX_BYTES . ' bytes.');
		}

		return $this->app->db->insert('demo_contact_messages', $row);
	}


}
