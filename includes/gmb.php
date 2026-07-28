<?php
/**
 * Google My Business / Places API integration.
 * Fetches rating, review count, and up to 3 recent reviews via the Places API (New).
 * Data is cached in a WordPress transient (24 h). API errors are cached briefly (5 min)
 * to avoid hammering the API on repeated page loads.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function g6_gmb_transient_key( string $place_id ): string {
	return 'g6_gmb_' . md5( $place_id );
}

/**
 * Hit the Places API (New) and return normalised review data.
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
				'X-Goog-FieldMask' => 'rating,userRatingCount,reviews',
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
			'author' => sanitize_text_field( $r['authorAttribution']['displayName'] ?? 'Anonymous' ),
			'rating' => (int) ( $r['rating'] ?? 5 ),
			'text'   => sanitize_text_field( $r['text']['text'] ?? '' ),
			'source' => 'Google',
			'date'   => sanitize_text_field( $r['relativePublishTimeDescription'] ?? '' ),
		];
	}

	return [
		'google_rating' => (float) ( $body['rating']          ?? 0 ),
		'google_count'  => (int)   ( $body['userRatingCount'] ?? 0 ),
		'total_reviews' => (int)   ( $body['userRatingCount'] ?? 0 ),
		'recent'        => $recent,
		'fetched_at'    => current_time( 'mysql' ),
	];
}

/**
 * Return cached GMB data, fetching fresh if the transient has expired.
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
		// Cache the error briefly so we don't hammer the API on every page load.
		set_transient( $key, [ 'error' => $data->get_error_message() ], 5 * MINUTE_IN_SECONDS );
		return false;
	}

	set_transient( $key, $data, DAY_IN_SECONDS );
	return $data;
}

/** Delete the cached data so the next page load fetches fresh results. */
function g6_gmb_clear_cache( string $place_id ): void {
	delete_transient( g6_gmb_transient_key( $place_id ) );
}

/** Return the last API error message stored in the error-backoff transient, or ''. */
function g6_gmb_get_last_error( string $place_id ): string {
	$cached = get_transient( g6_gmb_transient_key( $place_id ) );
	return ( is_array( $cached ) && isset( $cached['error'] ) ) ? $cached['error'] : '';
}
