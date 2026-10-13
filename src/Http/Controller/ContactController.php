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

namespace App\Http\Controller;

use App\Operation\SendContactMessage;
use App\Util\ContactMessage;
use CitOmni\Kernel\Controller\BaseController;


/**
 * ContactController: HTTP adapter for the contact form.
 *
 * Lets visitors send a message to the site owner. It owns the checks that only HTTP has, the
 * CSRF token and the captcha, and decides how every refusal is answered. Whether a message may
 * be stored belongs to SendContactMessage.
 *
 * Rules:
 * - Store a message only through SendContactMessage, and only after the CSRF token and the
 *   captcha have passed.
 */
class ContactController extends BaseController {

	/**
	 * Run setup and guards that every action needs.
	 *
	 * Notes:
	 * - Holds no access guard, so each action is exactly as exposed as its route. A guard added
	 *   here applies to every action.
	 *
	 * @return void
	 * @see \CitOmni\Kernel\Controller\BaseController::__construct()  Calls it before every action.
	 */
	protected function init(): void {
	}


	/**
	 * Render the contact form with the field limits.
	 *
	 * @return void
	 * @see \App\Util\ContactMessage::MAX_LENGTHS  The values in max_lengths.
	 */
	public function form(): void {
		$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [
			'max_lengths' => ContactMessage::MAX_LENGTHS,
		]);
	}


	/**
	 * Store one message from the contact form and answer in JSON.
	 *
	 * Answers every outcome in JSON, a refusal included, so that a page can post the form with
	 * a script and show the answer without a reload. SendContactMessage decides whether the
	 * message is stored; this action adds the CSRF token and the captcha, and translates the
	 * Operation's statuses.
	 *
	 * Behavior:
	 * - A refusal ends the request with a problem document. The checks run in this order:
	 *     1) 403 when the CSRF token is rejected.
	 *     2) 429 with Retry-After while the rate limit blocks the e-mail address or the client IP.
	 *     3) 422 with invalid_params, one entry per refused field.
	 *     4) 422 with invalid_params and a new captcha challenge when the captcha answer is wrong.
	 * - A refused field never uses up the captcha challenge.
	 * - 201 with {"ok": true, "result": {"message": ...}} when the message is stored.
	 * - Problem titles are fixed English. The detail and the field messages are translated.
	 *
	 * Notes:
	 * - Reads ordinary form fields. A script must post the form as form data, with the fields of
	 *   $csrfField() and $captchaField().
	 * - The IP limit counts only public client addresses, so on a local machine only the e-mail
	 *   address limits.
	 * - The rate limit is checked again when the message is stored, so a sender that is blocked
	 *   in between gets the 429 after a right captcha answer.
	 *
	 * @return never
	 * @see \App\Operation\SendContactMessage::execute()  What is stored, and what counts against the rate limit.
	 * @see \CitOmni\Http\Service\Request::ip()  Which client addresses are public.
	 * @see \CitOmni\Infrastructure\Service\BruteForce::status()  How an address that is not public is counted.
	 */
	public function send(): never {
		$response = $this->app->response;

		// -- 1. CSRF token ----------------------------------------------------------
		if (!$this->app->csrf->verify(['action' => 'contact_send'])) {
			$response->jsonProblem('Forbidden', 403, $this->app->txt->get('error_form_expired', 'contact', 'app', 'The form expired. Reload the page and try again.'));
		}

		// -- 2. Rate limit and fields -----------------------------------------------
		// Checked before the captcha, so that a refused field does not use up the captcha challenge.
		$fields = [];

		foreach (['name', 'email', 'message'] as $field) {
			$value = $this->app->request->post($field);
			$fields[$field] = \is_string($value) ? $value : '';
		}

		$ip = $this->app->request->ip();
		$sending = new SendContactMessage($this->app);
		$check = $sending->preflight($fields['name'], $fields['email'], $fields['message'], $ip);

		if ($check['status'] !== SendContactMessage::RESULT_OK) {
			$this->answerRefusal($check);
		}

		// -- 3. Captcha -------------------------------------------------------------
		$captcha = $this->app->captcha;

		if (!$captcha->verify()) {
			$response->jsonProblem('Unprocessable Content', 422, $this->app->txt->get('error_invalid', 'contact', 'app', 'Check the marked fields.'), 'about:blank', [
				'invalid_params' => [[
					'name'    => 'captcha',
					'reason'  => 'captcha_mismatch',
					'message' => $this->app->txt->get('reason_captcha_mismatch', 'contact', 'app', 'The characters do not match the image.'),
				]],
				// A wrong answer uses up its challenge, so the page needs a new one.
				'captcha' => [
					'field' => $captcha->htmlField(),
					'url'   => \rtrim((string)\CITOMNI_PUBLIC_ROOT_URL, '/') . $captcha->imagePath(),
				],
			]);
		}

		// -- 4. Store ---------------------------------------------------------------
		$result = $sending->execute($fields['name'], $fields['email'], $fields['message'], $ip);

		if ($result['status'] !== SendContactMessage::RESULT_SENT) {
			// The rate limit can block the sender between preflight() and execute().
			$this->answerRefusal($result);
		}

		$response->jsonStatus([
			'ok'     => true,
			'result' => ['message' => $this->app->txt->get('sent', 'contact', 'app', 'Thank you. Your message was sent.')],
		], 201);
	}


	/**
	 * Answer a refusal of SendContactMessage with a problem document.
	 *
	 * Behavior:
	 * - 429 with Retry-After for RESULT_RATE_LIMITED.
	 * - 422 with invalid_params, one entry per refused field, for RESULT_INVALID.
	 *
	 * @param  array{status:string,message_id:?int,retry_after_seconds:int,problems:array<string,string>}  $result  A refusal from preflight() or execute().
	 * @return never
	 */
	private function answerRefusal(array $result): never {
		$response = $this->app->response;

		if ($result['status'] === SendContactMessage::RESULT_RATE_LIMITED) {
			$response->setHeader('Retry-After', (string)$result['retry_after_seconds']);
			$response->jsonProblem('Too Many Requests', 429, $this->app->txt->get('error_rate_limited', 'contact', 'app', 'You have sent too many messages. Try again later.'));
		}

		// RESULT_INVALID is the only refusal left; a new status needs its own branch.
		$invalid = [];

		foreach ($result['problems'] as $field => $reason) {
			$invalid[] = [
				'name'    => $field,
				'reason'  => $reason,
				'message' => $this->app->txt->get('reason_' . $reason, 'contact', 'app', $reason, ['max' => ContactMessage::MAX_LENGTHS[$field]]),
			];
		}

		$response->jsonProblem('Unprocessable Content', 422, $this->app->txt->get('error_invalid', 'contact', 'app', 'Check the marked fields.'), 'about:blank', [
			'invalid_params' => $invalid,
		]);
	}


}
