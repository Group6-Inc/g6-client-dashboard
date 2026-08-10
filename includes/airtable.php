<?php
/**
 * Airtable integration — Support Hours meter.
 * Fetches a single client's support-hour balance from the shared Group6
 * Airtable base and caches it in a WordPress transient (6h). API errors
 * are cached briefly (5 min) to avoid hammering the API on failures.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Shared across every client site — one internal Group6 base/table, only the record ID differs per site. */
define( 'G6_AIRTABLE_BASE_ID', 'appGY3W3gkZC2AROo' );
define( 'G6_AIRTABLE_TABLE_ID', 'tbl5EumRfhbaeSb3N' );

/** Cache lifetime: 6 hours. */
const G6_AIRTABLE_CACHE_TTL = 6 * HOUR_IN_SECONDS;

function g6_airtable_transient_key( string $record_id ): string {
	return 'g6_airtable_' . md5( $record_id );
}

/**
 * Accepts either a bare record ID ("recXXXXXXXXXXXXXX") or a full Airtable
 * record URL (".../recXXXXXXXXXXXXXX") and returns just the record ID.
 */
function g6_airtable_parse_record_id( string $input ): string {
	$input = trim( $input );
	if ( preg_match( '/rec[A-Za-z0-9]{14,}/', $input, $m ) ) {
		return $m[0];
	}
	return '';
}

/**
 * Hit the Airtable REST API and return normalised data.
 *
 * @return array|WP_Error Normalised data array, or WP_Error on failure.
 */
function g6_airtable_fetch( string $record_id, string $token ): array|WP_Error {
	$url = sprintf(
		'https://api.airtable.com/v0/%s/%s/%s',
		G6_AIRTABLE_BASE_ID,
		G6_AIRTABLE_TABLE_ID,
		rawurlencode( $record_id )
	);

	$response = wp_remote_get(
		$url,
		[
			'timeout' => 10,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $body ) || isset( $body['error'] ) ) {
		$msg = is_array( $body['error'] ?? null ) ? ( $body['error']['message'] ?? 'Unknown Airtable error' ) : ( $body['error'] ?? 'Unknown Airtable error' );
		return new WP_Error( 'airtable_api', $msg );
	}

	$fields = $body['fields'] ?? [];

	return [
		'active'        => ! empty( $fields['Active Support Plan?'] ),
		'balance_hours' => (float) ( $fields['Current Support Balance (hrs)'] ?? 0 ),
		'total_hours'   => (float) ( $fields['Total Hours Purchased'] ?? 0 ),
		'fetched_at'    => current_time( 'mysql' ),
	];
}

/**
 * Return cached Support Hours data for a record, fetching fresh if the transient has expired.
 *
 * @return array|false Normalised data, or false if not configured / on error.
 */
function g6_airtable_get_data( string $record_id, string $token ): array|false {
	if ( ! $record_id || ! $token ) {
		return false;
	}

	$key    = g6_airtable_transient_key( $record_id );
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return isset( $cached['error'] ) ? false : $cached;
	}

	$data = g6_airtable_fetch( $record_id, $token );

	if ( is_wp_error( $data ) ) {
		set_transient( $key, [ 'error' => $data->get_error_message() ], 5 * MINUTE_IN_SECONDS );
		return false;
	}

	set_transient( $key, $data, G6_AIRTABLE_CACHE_TTL );
	return $data;
}

/** Delete the cached data for a single record. */
function g6_airtable_clear_cache( string $record_id ): void {
	if ( $record_id ) {
		delete_transient( g6_airtable_transient_key( $record_id ) );
	}
}

/** Return the last API error message stored in the error-backoff transient, or ''. */
function g6_airtable_get_last_error( string $record_id ): string {
	$cached = get_transient( g6_airtable_transient_key( $record_id ) );
	return ( is_array( $cached ) && isset( $cached['error'] ) ) ? $cached['error'] : '';
}
