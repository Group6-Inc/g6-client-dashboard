<?php
/**
 * Contact form AJAX handler — Zendesk ticket or email fallback.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_g6_contact_submit', 'g6_handle_contact_submit' );

function g6_handle_contact_submit(): void {
	check_ajax_referer( 'g6_contact_nonce' );

	$cfg     = g6_get_client_config();
	$user    = wp_get_current_user();
	$subject = sanitize_text_field( $_POST['subject'] ?? '' );
	$message = sanitize_textarea_field( $_POST['message'] ?? '' );

	if ( empty( $subject ) || empty( $message ) ) {
		wp_send_json_error( [ 'message' => 'Please fill in all fields.' ] );
	}

	// ── Try Zendesk first ──────────────────────────────────────────────
	// Subdomain is hardcoded via G6_ZENDESK_SUBDOMAIN in the main plugin file.
	// To switch tools, update that constant or replace this block.
	if ( defined( 'G6_ZENDESK_SUBDOMAIN' ) && G6_ZENDESK_SUBDOMAIN ) {
		$zendesk_url = sprintf( 'https://%s.zendesk.com/api/v2/requests.json', G6_ZENDESK_SUBDOMAIN );

		$body = wp_json_encode( [
			'request' => [
				'requester' => [
					'name'  => $user->display_name,
					'email' => $user->user_email,
				],
				'subject' => sprintf( '[%s] %s', $cfg['client_name'], $subject ),
				'comment' => [
					'body' => sprintf(
						"Client: %s\nUser: %s (%s)\nSubject: %s\n\n%s",
						$cfg['client_name'],
						$user->display_name,
						$user->user_email,
						$subject,
						$message
					),
				],
			],
		] );

		$response = wp_remote_post( $zendesk_url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				wp_send_json_success( 'Zendesk ticket created.' );
			}
		}
		// Fall through to email if Zendesk fails.
	}

	// ── Email fallback ─────────────────────────────────────────────────
	$headers = [
		'Content-Type: text/html; charset=UTF-8',
		'Reply-To: ' . $user->user_email,
	];

	$email_body = sprintf(
		'<h2>Dashboard Message from %s</h2>
		<p><strong>Client:</strong> %s</p>
		<p><strong>User:</strong> %s (%s)</p>
		<p><strong>Subject:</strong> %s</p>
		<hr>
		<p>%s</p>',
		esc_html( $cfg['client_name'] ),
		esc_html( $cfg['client_name'] ),
		esc_html( $user->display_name ),
		esc_html( $user->user_email ),
		esc_html( $subject ),
		nl2br( esc_html( $message ) )
	);

	$sent = wp_mail(
		$cfg['agency_rep_email'],
		sprintf( '[%s] %s', $cfg['client_name'], $subject ),
		$email_body,
		$headers
	);

	if ( $sent ) {
		wp_send_json_success( 'Email sent.' );
	} else {
		wp_send_json_error( [
			'message' => 'Could not send message. Please email ' . $cfg['agency_rep_email'] . ' directly.',
		] );
	}
}
