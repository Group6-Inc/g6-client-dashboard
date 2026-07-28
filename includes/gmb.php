<?php
/**
 * Google My Business / Places API integration.
 * Fetches rating, review count, business name, and up to 3 recent reviews
 * via the Places API (New). Data is cached in a WordPress transient (48 h).
 * API errors are cached briefly (5 min) to avoid hammering the API on failures.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cache lifetime: 48 hours. Keeps API calls low across many client sites. */
const G6_GMB_CACHE_TTL = 2 * DAY_IN_SECONDS;

function g6_gmb_transient_key( string $place_id ): string {
	return 'g6_gmb_' . md5( $place_id );
}

/**
 * Hit the Places API (New) and return normalised data.
 *
 * @return array|WP_Error Normalised data array, or WP_Error on failure.
 */
function g6_gmb_fetch( string $place_id, string $api_key ): array|WP_Error {
	$response = wp_remote_get(
		'https://places.googleapis.com/v1/places/' . rawurlencode( $place_id ),
		[
			'timeout' => 10,
			'headers' => [
				'X-Goog-Api-Key'   => $api_key,
				'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,reviews',
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) || isset( $body['error'] ) ) {
		$msg = $body['error']['message'] ?? 'Unknown Places API error';
		return new WP_Error( 'gmb_api', $msg );
	}

	// Sort by publishTime descending so we always get the 3 most recent.
	$raw = $body['reviews'] ?? [];
	usort( $raw, fn( $a, $b ) => strcmp( $b['publishTime'] ?? '', $a['publishTime'] ?? '' ) );

	$recent = [];
	foreach ( array_slice( $raw, 0, 3 ) as $r ) {
		$recent[] = [
			'author'       => sanitize_text_field( $r['authorAttribution']['displayName'] ?? 'Anonymous' ),
			'rating'       => (int) ( $r['rating'] ?? 5 ),
			'text'         => sanitize_text_field( $r['text']['text'] ?? '' ),
			'source'       => 'Google',
			'date'         => sanitize_text_field( $r['relativePublishTimeDescription'] ?? '' ),
			'publish_time' => sanitize_text_field( $r['publishTime'] ?? '' ),
		];
	}

	return [
		'display_name'  => sanitize_text_field( $body['displayName']['text'] ?? '' ),
		'google_rating' => (float) ( $body['rating']          ?? 0 ),
		'google_count'  => (int)   ( $body['userRatingCount'] ?? 0 ),
		'recent'        => $recent,
		'fetched_at'    => current_time( 'mysql' ),
	];
}

/**
 * Return cached GMB data for a single place, fetching fresh if the transient has expired.
 *
 * @return array|false Normalised data, or false if not configured / on error.
 */
function g6_gmb_get_data( string $place_id, string $api_key ): array|false {
	if ( ! $place_id || ! $api_key ) {
		return false;
	}

	$key    = g6_gmb_transient_key( $place_id );
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return isset( $cached['error'] ) ? false : $cached;
	}

	$data = g6_gmb_fetch( $place_id, $api_key );

	if ( is_wp_error( $data ) ) {
		set_transient( $key, [ 'error' => $data->get_error_message() ], 5 * MINUTE_IN_SECONDS );
		return false;
	}

	set_transient( $key, $data, G6_GMB_CACHE_TTL );
	return $data;
}

/**
 * Fetch data for an array of locations/competitors.
 * Each returned entry gets a 'label' key (custom label or Google display_name fallback).
 *
 * @param array  $locations Array of ['place_id' => '', 'label' => ''] entries.
 * @return array Only entries that returned valid data.
 */
function g6_gmb_get_all_data( array $locations, string $api_key ): array {
	$results = [];
	foreach ( $locations as $loc ) {
		$place_id = $loc['place_id'] ?? '';
		if ( ! $place_id ) continue;
		$data = g6_gmb_get_data( $place_id, $api_key );
		if ( $data ) {
			// Prefer the user-supplied label; fall back to the name Google returns.
			$data['label']    = sanitize_text_field( $loc['label'] ?? '' ) ?: ( $data['display_name'] ?? '' );
			$data['place_id'] = $place_id;
			$results[]        = $data;
		}
	}
	return $results;
}

/**
 * Merge multiple location datasets into a single combined result.
 * Rating is weighted by review count. Reviews are merged and sorted newest-first.
 *
 * @return array|false Combined data, or false if $all_data is empty.
 */
function g6_gmb_combine( array $all_data ): array|false {
	if ( empty( $all_data ) ) {
		return false;
	}

	$total_count  = 0;
	$weighted_sum = 0.0;
	$all_reviews  = [];
	$fetched_at   = '';

	foreach ( $all_data as $data ) {
		$count         = (int)   ( $data['google_count']  ?? 0 );
		$rating        = (float) ( $data['google_rating'] ?? 0 );
		$total_count  += $count;
		$weighted_sum += $rating * $count;
		foreach ( $data['recent'] as $r ) {
			$all_reviews[] = $r;
		}
		if ( ( $data['fetched_at'] ?? '' ) > $fetched_at ) {
			$fetched_at = $data['fetched_at'];
		}
	}

	usort( $all_reviews, fn( $a, $b ) => strcmp( $b['publish_time'] ?? '', $a['publish_time'] ?? '' ) );

	return [
		'google_rating' => $total_count > 0 ? round( $weighted_sum / $total_count, 1 ) : 0.0,
		'google_count'  => $total_count,
		'recent'        => array_slice( $all_reviews, 0, 3 ),
		'fetched_at'    => $fetched_at,
	];
}

/**
 * Build the dynamic CTA text based on client vs competitor review counts.
 * Returns empty string if no competitors are configured.
 */
function g6_gmb_dynamic_cta( int $client_count, array $competitors_data ): string {
	if ( empty( $competitors_data ) ) {
		return '';
	}

	$max_gap        = 0;
	$top_threat     = '';
	$anyone_ahead   = false;

	foreach ( $competitors_data as $comp ) {
		$gap = (int) ( $comp['google_count'] ?? 0 ) - $client_count;
		if ( $gap > 0 ) {
			$anyone_ahead = true;
			if ( $gap > $max_gap ) {
				$max_gap    = $gap;
				$top_threat = $comp['label'] ?: $comp['display_name'] ?? '';
			}
		}
	}

	if ( $anyone_ahead && $top_threat ) {
		return $top_threat . ' has ' . number_format( $max_gap ) . ' more review' . ( $max_gap !== 1 ? 's' : '' ) . ' than you — let\'s close that gap.';
	}

	return 'You\'re ahead of the competition — let\'s keep it that way.';
}

/** Delete the cached data for a single place. */
function g6_gmb_clear_cache( string $place_id ): void {
	if ( $place_id ) {
		delete_transient( g6_gmb_transient_key( $place_id ) );
	}
}

/** Delete cached data for all entries in a locations/competitors array. */
function g6_gmb_clear_all_caches( array $locations ): void {
	foreach ( $locations as $loc ) {
		g6_gmb_clear_cache( $loc['place_id'] ?? '' );
	}
}

/** Return the last API error message stored in the error-backoff transient, or ''. */
function g6_gmb_get_last_error( string $place_id ): string {
	$cached = get_transient( g6_gmb_transient_key( $place_id ) );
	return ( is_array( $cached ) && isset( $cached['error'] ) ) ? $cached['error'] : '';
}
