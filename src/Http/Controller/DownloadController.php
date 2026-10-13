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

use CitOmni\Kernel\Controller\BaseController;


/**
 * DownloadController: HTTP adapter for files that the app sends itself.
 *
 * Lets the app decide who gets a file. A file outside public/ can only be reached through a
 * route, so its action can check access, log or name the file before the file is sent. This
 * assumes that the web server serves public/ without any Controller.
 *
 * Rules:
 * - Send only files that lie outside public/.
 */
class DownloadController extends BaseController {

	/** Path of the example file below the app root. */
	private const EXAMPLE_FILE = '/var/storage/citomni_demo_app-http_file_download_example.pdf';


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
	 * Send the example PDF as an attachment.
	 *
	 * @return never
	 * @throws \RuntimeException  When the example file is missing or unreadable.
	 * @see \CitOmni\Http\Service\Response::download()  The headers and the encoding of the file name.
	 */
	public function example(): never {
		// A name with spaces shows the encoded file name next to its ASCII fallback.
		$this->app->response->download(\CITOMNI_APP_PATH . self::EXAMPLE_FILE, 'CitOmni download example.pdf');
	}


}
