<?php
/**
 * Group6 App integration — replaces the Airtable Support Hours layer.
 *
 * Deliberately returns the same normalised array shape that
 * g6_airtable_get_data() did, so dashboard.php's Support Hours meter and
 * its thresholds need no changes:
 *
 *     [ 'active' => bool, 'balance_hours' => float,
 *       'total_hours' => float, 'fetched_at' => string ]
 *
 * Differences from the Airtable version:
 *   - one site token replaces the PAT + record ID pair; the token itself
 *     identifies which client this site belongs to
 *   - 'total_hours' is now genuinely "hours ever purchased", not
 *     Airtable's cumulative counter
 *   - project status and ticket counts are available too
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Where the portal lives when nothing says otherwise. */
const G6_API_DEFAULT_BASE = 'https://portal.group6inc.com/api/v1';

/**
 * The API root this site talks to.
 *
 * A G6_API_BASE constant in wp-config.php wins, so a staging site can be
 * pointed elsewhere without anyone editing settings on a live site by
 * mistake. Otherwise the settings field, and otherwise production.
 */
function g6_api_base( ?array $cfg = null ): string {
	if ( defined( 'G6_API_BASE' ) && G6_API_BASE ) {
		return rtrim( G6_API_BASE, '/' );
	}

	$cfg = $cfg ?? ( function_exists( 'g6_get_config' ) ? g6_get_config() : [] );
	$url = trim( $cfg['portal_url'] ?? '' );

	return $url ? rtrim( $url, '/' ) : G6_API_DEFAULT_BASE;
}

/** Cache lifetime: 30 minutes (spec §11 asks for 15–60). */
const G6_API_CACHE_TTL = 30 * MINUTE_IN_SECONDS;

/** Failures back off briefly so a slow app doesn't hammer the site. */
const G6_API_ERROR_TTL = 5 * MINUTE_IN_SECONDS;

/**
 * The base URL is part of the key. Point a site at staging and back and
 * the balance you are shown must come from the portal you are actually
 * pointed at, not from whichever one answered first.
 */
function g6_api_transient_key( string $endpoint, string $token, ?array $cfg = null ): string {
	return 'g6_api_' . md5( g6_api_base( $cfg ) . '|' . $endpoint . '|' . $token );
}

/**
 * GET one endpoint and return the decoded body.
 *
 * @return array|WP_Error
 */
function g6_api_fetch( string $endpoint, string $token ): array|WP_Error {
	$response = wp_remote_get(
		g6_api_base() . '/' . ltrim( $endpoint, '/' ),
		[
			'timeout' => 10,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			],
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 401 === $code ) {
		return new WP_Error( 'g6_api_auth', 'This site\'s API token was rejected. Ask Group6 for a new one.' );
	}

	if ( 403 === $code ) {
		return new WP_Error( 'g6_api_scope', 'This site\'s token is not permitted to read that data.' );
	}

	if ( 429 === $code ) {
		return new WP_Error( 'g6_api_rate', 'Too many requests — try again shortly.' );
	}

	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
		return new WP_Error( 'g6_api_http', sprintf( 'Group6 app returned HTTP %d.', $code ) );
	}

	return $body;
}

/**
 * Cached GET. Returns false when unconfigured or on error.
 *
 * @return array|false
 */
function g6_api_get( string $endpoint, string $token ): array|false {
	if ( ! $token ) {
		return false;
	}

	$key    = g6_api_transient_key( $endpoint, $token );
	$cached = get_transient( $key );

	if ( false !== $cached ) {
		return isset( $cached['error'] ) ? false : $cached;
	}

	$data = g6_api_fetch( $endpoint, $token );

	if ( is_wp_error( $data ) ) {
		set_transient( $key, [ 'error' => $data->get_error_message() ], G6_API_ERROR_TTL );
		return false;
	}

	set_transient( $key, $data, G6_API_CACHE_TTL );
	return $data;
}

/** This site's portal credential, wherever it is being used. */
function g6_portal_token( ?array $cfg = null ): string {
	$cfg = $cfg ?? ( function_exists( 'g6_get_config' ) ? g6_get_config() : [] );

	return trim( $cfg['portal_token'] ?? '' );
}

/**
 * Where the Get in Touch form files a request.
 *
 * Anything that is not exactly 'portal' means Zendesk, for the same
 * reason the support-hours source works that way: a site updating to
 * this version has nothing saved, and must carry on doing what it did.
 */
function g6_tickets_destination( ?array $cfg = null ): string {
	$cfg = $cfg ?? ( function_exists( 'g6_get_config' ) ? g6_get_config() : [] );

	return ( ( $cfg['tickets_destination'] ?? 'zendesk' ) === 'portal' ) ? 'portal' : 'zendesk';
}

/**
 * Which source the Support Hours widget reads from.
 *
 * Anything that is not exactly 'portal' means Airtable. Sites updating
 * from an older version have no such setting saved, and they must carry
 * on reading Airtable rather than silently going blank.
 */
function g6_support_hours_source( ?array $cfg = null ): string {
	$cfg = $cfg ?? ( function_exists( 'g6_get_config' ) ? g6_get_config() : [] );

	return ( ( $cfg['support_hours_source'] ?? 'airtable' ) === 'portal' ) ? 'portal' : 'airtable';
}

/**
 * Support Hours — drop-in replacement for g6_airtable_get_data().
 *
 * @return array|false
 */
function g6_api_get_support_hours( string $token ): array|false {
	return g6_api_get( 'support-hours', $token );
}

/** Project status summary. @return array|false */
function g6_api_get_projects( string $token ): array|false {
	return g6_api_get( 'projects', $token );
}

/**
 * The categories a ticket may be filed under, as [ slug => name ].
 *
 * Fetched rather than hardcoded: staff rename, reorder and hide these in
 * the portal, and a list baked into the plugin would mean a release to
 * every site every time. Returns [] when unconfigured or unreachable,
 * and the form falls back to its own list rather than showing nothing.
 *
 * @return array<string, string>
 */
function g6_api_get_ticket_categories( string $token ): array {
	$data = g6_api_get( 'ticket-categories', $token );

	if ( ! is_array( $data ) || empty( $data['categories'] ) || ! is_array( $data['categories'] ) ) {
		return [];
	}

	$out = [];

	foreach ( $data['categories'] as $row ) {
		if ( ! empty( $row['slug'] ) && ! empty( $row['name'] ) ) {
			$out[ (string) $row['slug'] ] = (string) $row['name'];
		}
	}

	return $out;
}

/** Open ticket count and recent tickets. @return array|false */
function g6_api_get_tickets( string $token ): array|false {
	return g6_api_get( 'tickets', $token );
}

/**
 * Submit a ticket on behalf of the logged-in WordPress user.
 *
 * @return array|WP_Error The created ticket, or an error to show the user.
 */
function g6_api_submit_ticket( string $token, array $fields ): array|WP_Error {
	$user = wp_get_current_user();

	if ( ! $user || ! $user->exists() ) {
		return new WP_Error( 'g6_api_no_user', 'You must be logged in to submit a ticket.' );
	}

	// No category unless the caller has a real slug to send. The draft of
	// this file defaulted to 'general', which happens to be a slug today
	// — but the portal's categories are staff-editable, and hiding or
	// renaming that one would start rejecting every request from every
	// site. Omitting it lets the portal apply its own default, which is
	// the thing that knows what the default currently is.
	$payload = [
		'email'    => $user->user_email,
		'name'     => $user->display_name,
		'subject'  => $fields['subject'] ?? '',
		'body'     => $fields['body'] ?? '',
		'site_url' => $fields['site_url'] ?? home_url(),
	];

	if ( ! empty( $fields['category'] ) ) {
		$payload['category'] = $fields['category'];
	}

	$response = wp_remote_post(
		g6_api_base() . '/tickets',
		[
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			],
			'body'    => $payload,
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( 201 !== $code ) {
		$message = is_array( $body ) && ! empty( $body['message'] )
			? $body['message']
			: 'Could not submit the ticket. Please try again.';
		return new WP_Error( 'g6_api_submit', $message );
	}

	// a new ticket changes the counts the dashboard shows
	g6_api_clear_cache( $token );

	return $body;
}

/**
 * Drop every cached response for this site's token.
 *
 * $cfg exists for the settings screen: when the token or the portal URL
 * has just been changed, the entry to delete is the one under the OLD
 * key, and that key can only be rebuilt from the old settings. Without
 * it a changed token leaves the previous portal's numbers sitting in a
 * transient nothing will ever read or replace.
 */
function g6_api_clear_cache( string $token, ?array $cfg = null ): void {
	if ( ! $token ) {
		return;
	}

	foreach ( [ 'support-hours', 'projects', 'tickets', 'ticket-categories' ] as $endpoint ) {
		delete_transient( g6_api_transient_key( $endpoint, $token, $cfg ) );
	}
}

/** Last error message stored by the backoff transient, or ''. */
function g6_api_get_last_error( string $endpoint, string $token ): string {
	$cached = get_transient( g6_api_transient_key( $endpoint, $token ) );
	return ( is_array( $cached ) && isset( $cached['error'] ) ) ? $cached['error'] : '';
}

/**
 * Verify a token by making one live call. Used by the settings screen's
 * "Test connection" button.
 *
 * @return true|WP_Error
 */
function g6_api_test_connection( string $token ): true|WP_Error {
	$result = g6_api_fetch( 'support-hours', $token );

	return is_wp_error( $result ) ? $result : true;
}
