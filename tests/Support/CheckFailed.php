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

namespace App\Tests\Support;

/**
 * CheckFailed: Exception for a check that a test case failed.
 *
 * Separates a failed expectation from an unexpected error, so that a report can show the
 * check's message alone and keep class, file and line for everything else.
 */
class CheckFailed extends \RuntimeException {
}
