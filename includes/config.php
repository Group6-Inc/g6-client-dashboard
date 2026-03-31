<?php
/**
 * Client configuration — default values and option retrieval.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function g6_get_client_config(): array {
	$stored = get_option( 'g6_client_config' );
	if ( $stored && is_array( $stored ) ) {
		return wp_parse_args( $stored, g6_default_config() );
	}
	return g6_default_config();
}

function g6_default_config(): array {
	return [
		'client_name'      => get_bloginfo( 'name' ),
		'agency_rep_name'  => 'Nate Fineberg',
		'agency_rep_email' => 'nate@group6inc.com',
		'agency_rep_phone' => '(802) 342-4656',
		'agency_rep_photo' => '',

		// SEO Keywords
		'keywords' => [
			[
				'term'     => 'financial advisor near me',
				'position' => 3,
				'change'   => 2,
				'volume'   => 2400,
			],
			[
				'term'     => 'wealth management [city]',
				'position' => 7,
				'change'   => -1,
				'volume'   => 1300,
			],
			[
				'term'     => 'retirement planning services',
				'position' => 12,
				'change'   => 5,
				'volume'   => 3100,
			],
			[
				'term'     => 'fiduciary financial planner',
				'position' => 18,
				'change'   => 0,
				'volume'   => 880,
			],
			[
				'term'     => '401k rollover advisor',
				'position' => 24,
				'change'   => 3,
				'volume'   => 1600,
			],
		],

		// Reviews snapshot
		'reviews' => [
			'google_rating' => 4.8,
			'google_count'  => 127,
			'avg_rating'    => 4.7,
			'total_reviews' => 189,
			'recent'        => [
				[
					'author' => 'Michael R.',
					'rating' => 5,
					'text'   => 'Outstanding service from start to finish. They really understand our business goals.',
					'source' => 'Google',
					'date'   => '2 weeks ago',
				],
				[
					'author' => 'Sarah T.',
					'rating' => 5,
					'text'   => 'Professional, responsive, and results-driven. Highly recommend.',
					'source' => 'Google',
					'date'   => '1 month ago',
				],
				[
					'author' => 'James K.',
					'rating' => 4,
					'text'   => 'Great team. Helped us improve our online presence significantly.',
					'source' => 'Yelp',
					'date'   => '1 month ago',
				],
			],
		],

		// Services available for upsell
		'services' => [
			[
				'name'        => 'Local SEO',
				'description' => 'Rank higher in local search results and Google Maps for your target areas.',
				'icon'        => 'map-pin',
				'cta_label'   => 'Learn More',
				'cta_url'     => 'https://group6inc.com/service/local-seo/',
				'highlight'   => true,
			],
			[
				'name'        => 'Reputation Management',
				'description' => 'Monitor, respond to, and grow your online reviews across all platforms.',
				'icon'        => 'star',
				'cta_label'   => 'Learn More',
				'cta_url'     => 'https://group6inc.com/service/online-reputation-management/',
				'highlight'   => false,
			],
			[
				'name'        => 'Brand Identity Design',
				'description' => 'Distinctive, stand-out logos and brand identities crafted from scratch by expert designers.',
				'icon'        => 'zap',
				'cta_label'   => 'Learn More',
				'cta_url'     => 'https://group6inc.com/expertise/brand-identity-design/',
				'highlight'   => false,
			],
		],

		// How-to guides
		'guides' => [
			[
				'title'       => 'How to Update a Page in WordPress',
				'description' => 'Step-by-step instructions for editing pages using Bricks Builder.',
				'icon'        => 'edit',
				'url'         => '#',
			],
			[
				'title'       => 'Adding a New Blog Post',
				'description' => 'Create and publish blog content with proper formatting and SEO.',
				'icon'        => 'plus-circle',
				'url'         => '#',
			],
			[
				'title'       => 'Updating Your Contact Info',
				'description' => 'Change phone numbers, addresses, and hours across your site.',
				'icon'        => 'phone',
				'url'         => '#',
			],
			[
				'title'       => 'Understanding Your SEO Report',
				'description' => 'What the keyword rankings below mean and how to read them.',
				'icon'        => 'bar-chart',
				'url'         => '#',
			],
			[
				'title'       => 'Managing Form Submissions',
				'description' => 'View, export, and respond to leads from your website forms.',
				'icon'        => 'inbox',
				'url'         => '#',
			],
			[
				'title'       => 'Requesting Changes from Group6',
				'description' => 'How to submit a support ticket for design or content updates.',
				'icon'        => 'message-circle',
				'url'         => '#',
			],
		],

		// Widget visibility
		'widgets' => [
			'guides'   => true,
			'keywords' => true,
			'reviews'  => true,
			'services' => true,
			'contact'  => true,
			'video'    => false,
		],

		// Featured video
		'video_url'   => '',
		'video_title' => 'How to Use Your WordPress Site',

		'use_search_console' => false,
		'use_gbp_api'        => false,
		'last_updated'       => current_time( 'mysql' ),
	];
}
