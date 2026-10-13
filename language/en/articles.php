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

return [

	// Navigation
	'nav_articles' => 'Articles',
	'nav_drafts'   => 'Drafts',

	// Article pages
	'articles_title'   => 'Articles',
	'articles_empty'   => 'No articles have been published yet.',
	'back_to_articles' => 'All articles',
	'date_pattern'     => 'd MMMM y',
	'reading_time'     => '%MINUTES% min read',

	// Plain-text article
	'article_not_found' => 'The article does not exist.',

	// Draft list
	'drafts_title'          => 'Drafts',
	'drafts_empty'          => 'There are no drafts. Create one with php bin/citomni article:create.',
	'publish'               => 'Publish',
	'reason_not_draft'      => 'Not a draft.',
	'reason_body_too_short' => 'The text is too short to publish.',

	// Flash messages
	'flash_published'    => 'The article was published.',
	'flash_refused'      => 'The article was not published.',
	'flash_not_found'    => 'The article does not exist.',
	'flash_form_expired' => 'The form expired. Try again.',

];
