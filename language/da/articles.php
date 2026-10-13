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
	'nav_articles' => 'Artikler',
	'nav_drafts'   => 'Kladder',

	// Article pages
	'articles_title'   => 'Artikler',
	'articles_empty'   => 'Der er ikke udgivet nogen artikler endnu.',
	'back_to_articles' => 'Alle artikler',
	'date_pattern'     => 'd. MMMM y',
	'reading_time'     => '%MINUTES% min. læsning',

	// Plain-text article
	'article_not_found' => 'Artiklen findes ikke.',

	// Draft list
	'drafts_title'          => 'Kladder',
	'drafts_empty'          => 'Der er ingen kladder. Opret en med php bin/citomni article:create.',
	'publish'               => 'Udgiv',
	'reason_not_draft'      => 'Ikke en kladde.',
	'reason_body_too_short' => 'Teksten er for kort til at blive udgivet.',

	// Flash messages
	'flash_published'    => 'Artiklen blev udgivet.',
	'flash_refused'      => 'Artiklen blev ikke udgivet.',
	'flash_not_found'    => 'Artiklen findes ikke.',
	'flash_form_expired' => 'Formularen er udløbet. Prøv igen.',

];
