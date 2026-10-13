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

use App\Repository\ContactMessageRepository;
use App\Util\ContactMessage;
use CitOmni\Kernel\Operation\BaseOperation;


/**
 * SendContactMessage: Operation that stores one message from a visitor to the site owner.
 *
 * Owns the decision whether a contact message is stored, so that every adapter that takes
 * contact messages refuses the same ones. The field rules belong to ContactMessage and the
 * counting to the bruteForce service; checks that only one transport has, such as a CSRF token
 * or a captcha, belong to its adapter.
 *
 * Rules:
 * - Report expected outcomes as RESULT_* statuses, never as exceptions.
 * - Return every outcome in the one shape that result() builds.
 * - Count a message against the rate limit only once it is stored.
 */
class SendContactMessage extends BaseOperation {

	/** preflight() only: execute() would store the message. */
	public const RESULT_OK = 'ok';

	/** execute() only: This call stored the message. */
	public const RESULT_SENT = 'sent';

	/** Nothing was stored: The rate limit blocks the sender; retry_after_seconds says for how long. */
	public const RESULT_RATE_LIMITED = 'rate_limited';

	/** Nothing was stored: A field breaks a rule of ContactMessage; problems names each one. */
	public const RESULT_INVALID = 'invalid';

	/** The bruteForce context that limits how many messages a sender may store. */
	private const RATE_LIMIT_CONTEXT = 'contact';


	/**
	 * Report whether execute() would store the message, without storing or counting it.
	 *
	 * Lets an adapter refuse a message before it spends a check of its own that cannot be
	 * repeated, such as a captcha challenge.
	 *
	 * Behavior:
	 * - Reports the first refusal, in this order:
	 *     1) RESULT_RATE_LIMITED while the rate limit blocks the e-mail address or the IP.
	 *     2) RESULT_INVALID when a trimmed field breaks a rule of ContactMessage.
	 * - RESULT_OK otherwise.
	 * - message_id is always null. retry_after_seconds is 0 unless the status is
	 *   RESULT_RATE_LIMITED, and problems is empty unless it is RESULT_INVALID.
	 *
	 * Notes:
	 * - RESULT_OK is no promise: The rate limit can block the sender before execute() runs.
	 *
	 * Typical usage:
	 *   $check = (new SendContactMessage($this->app))->preflight($name, $email, $message, $ip);
	 *
	 * @param  string  $name  Sender name as entered.
	 * @param  string  $email  Sender e-mail address as entered. It identifies the sender for the rate limit.
	 * @param  string  $message  Message text as entered.
	 * @param  ?string  $ip  Client IP address as BruteForce::status() takes it, or null when the transport has none.
	 * @return array{status:string,message_id:?int,retry_after_seconds:int,problems:array<string,string>}
	 * @throws \CitOmni\Infrastructure\Exception\BruteForceConfigException  When cfg security.bruteforce has no valid RATE_LIMIT_CONTEXT entry.
	 * @throws \InvalidArgumentException  When the bruteForce service rejects the IP address.
	 * @see \App\Util\ContactMessage::problems()  The reasons in problems.
	 * @see \CitOmni\Infrastructure\Service\BruteForce::status()  Which IP values count, and which stand for a missing address.
	 */
	public function preflight(string $name, string $email, string $message, ?string $ip): array {
		return $this->refusal(\trim($name), \trim($email), \trim($message), $ip) ?? $this->result(self::RESULT_OK);
	}


	/**
	 * Store one message and count it against the sender's rate limit.
	 *
	 * Checks everything that preflight() checks again, so it never relies on a preflight() call
	 * or on its answer.
	 *
	 * Behavior:
	 * - RESULT_SENT with message_id when this call stored the message, trimmed. The message then
	 *   counts against the rate limit of the e-mail address and the IP.
	 * - RESULT_RATE_LIMITED or RESULT_INVALID, in the order that preflight() checks, when
	 *   nothing was stored or counted.
	 * - message_id is null unless the status is RESULT_SENT. retry_after_seconds is 0 unless it
	 *   is RESULT_RATE_LIMITED, and problems is empty unless it is RESULT_INVALID.
	 *
	 * Notes:
	 * - The check and the count are separate steps. Concurrent calls for one sender can each pass
	 *   the check, so the rate limit can let a few more messages through than it allows.
	 *
	 * Typical usage:
	 *   $result = (new SendContactMessage($this->app))->execute($name, $email, $message, $ip);
	 *
	 * @param  string  $name  Sender name as entered.
	 * @param  string  $email  Sender e-mail address as entered. It identifies the sender for the rate limit.
	 * @param  string  $message  Message text as entered.
	 * @param  ?string  $ip  Client IP address as BruteForce::status() takes it, or null when the transport has none.
	 * @return array{status:string,message_id:?int,retry_after_seconds:int,problems:array<string,string>}
	 * @throws \CitOmni\Infrastructure\Exception\BruteForceConfigException  When cfg security.bruteforce has no valid RATE_LIMIT_CONTEXT entry.
	 * @throws \InvalidArgumentException  When the bruteForce service rejects the IP address.
	 * @see \App\Operation\SendContactMessage::preflight()  The order of the refusals.
	 */
	public function execute(string $name, string $email, string $message, ?string $ip): array {
		$name = \trim($name);
		$email = \trim($email);
		$message = \trim($message);

		$refusal = $this->refusal($name, $email, $message, $ip);

		if ($refusal !== null) {
			return $refusal;
		}

		// ContactMessage keeps its limits within the columns, so insert() takes every message
		// that passed the check.
		$messageId = (new ContactMessageRepository($this->app))->insert($name, $email, $message);
		$this->app->bruteForce->record(self::RATE_LIMIT_CONTEXT, $email, $ip);

		return $this->result(self::RESULT_SENT, $messageId);
	}


	/**
	 * Return the first refusal of a trimmed message, or null when nothing refuses it.
	 *
	 * @param  string  $name  Trimmed.
	 * @param  string  $email  Trimmed.
	 * @param  string  $message  Trimmed.
	 * @param  ?string  $ip
	 * @return array{status:string,message_id:?int,retry_after_seconds:int,problems:array<string,string>}|null
	 */
	private function refusal(string $name, string $email, string $message, ?string $ip): ?array {
		// An empty address identifies no sender; ContactMessage refuses it below.
		$identifier = $email === '' ? null : $email;

		// The rate limit comes first: Fixing a field would not help a blocked sender. Without an
		// address and an IP there is no sender to look up, and status() refuses such a call.
		if ($identifier !== null || $ip !== null) {
			$limit = $this->app->bruteForce->status(self::RATE_LIMIT_CONTEXT, $identifier, $ip);

			if ($limit['blocked']) {
				return $this->result(self::RESULT_RATE_LIMITED, retryAfterSeconds: (int)$limit['retry_after_seconds']);
			}
		}

		$problems = ContactMessage::problems($name, $email, $message);

		return $problems === [] ? null : $this->result(self::RESULT_INVALID, problems: $problems);
	}


	/**
	 * Build a result in the one shape that preflight() and execute() return.
	 *
	 * @param  string  $status  RESULT_* value.
	 * @param  ?int  $messageId  Id of the stored message; null unless the status is RESULT_SENT.
	 * @param  int  $retryAfterSeconds  Seconds the rate limit still blocks the sender; 0 unless the status is RESULT_RATE_LIMITED.
	 * @param  array<string,string>  $problems  ContactMessage::REASON_* per refused field; empty unless the status is RESULT_INVALID.
	 * @return array{status:string,message_id:?int,retry_after_seconds:int,problems:array<string,string>}
	 */
	private function result(string $status, ?int $messageId = null, int $retryAfterSeconds = 0, array $problems = []): array {
		return [
			'status'              => $status,
			'message_id'          => $messageId,
			'retry_after_seconds' => $retryAfterSeconds,
			'problems'            => $problems,
		];
	}


}
