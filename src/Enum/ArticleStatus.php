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

namespace App\Enum;


/**
 * ArticleStatus: Enum of the lifecycle states of an article.
 *
 * The backing values are stored and compared as they are, so they are part of the data
 * contract, not only of the code.
 *
 * Rules:
 * - Never rename, renumber or reinterpret a backing value; add a case instead.
 */
enum ArticleStatus: string {

	/** Written, but not visible to readers. */
	case Draft = 'draft';

	/** Visible to readers since its published_at time. */
	case Published = 'published';

}
