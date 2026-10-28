<?php
/**
 * Contact form AJAX handler — Group6 portal ticket, or email.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_g6_contact_submit', 'g6_handle_contact_submit' );

/**
 * One log for contact-form failures, whichever destination the form was
 * pointed at, readable in Settings → Plugin without server log access.
 * The option key still says zendesk on purpose: renaming it would orphan
 * every entry already written on every site.
 */
function g6_log_contact_failure( string $client_name, string $reason, int $code, string $response_body ): void {
	$log = get_option( 'g6_zendesk_failure_log', [] );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	array_unshift( $log, [
		'time'   => current_time( 'mysql' ),
		'client' => $client_name,
		'reason' => $reason,
		'code'   => $code,
		'body'   => mb_substr( $response_body, 0, 500 ),
	] );
	update_option( 'g6_zendesk_failure_log', array_slice( $log, 0, 30 ), false );
}

function g6_handle_contact_submit(): void {
	check_ajax_referer( 'g6_contact_nonce' );

	$cfg     = g6_get_client_config();
	$user    = wp_get_current_user();
	// Unslashed first, or a message saying We're arrives as We\'re.
	$subject  = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
	$message  = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
	// Only the portal form sends this, and it is a slug the portal itself
	// handed us — validated again on the way in, so a forged one is
	// refused rather than filed somewhere arbitrary.
	$category = sanitize_key( $_POST['category'] ?? '' );

	if ( empty( $subject ) || empty( $message ) ) {
		wp_send_json_error( [ 'message' => 'Please fill in all fields.' ] );
	}

	// ── The Group6 portal ──────────────────────────────────────────────
	// Succeed and stop, or fall through to the email below. That safety
	// net is the reason this handler is shaped the way it is.
	if ( 'portal' === g6_tickets_destination( $cfg ) ) {
		$portal_token = g6_portal_token( $cfg );

		if ( $portal_token ) {
			// The category is a slug the portal gave this form in the
			// first place, so it round-trips. Empty when the categories
			// could not be fetched — the portal then applies its own
			// default rather than this plugin guessing at one.
			$result = g6_api_submit_ticket( $portal_token, [
				'subject'  => $subject,
				'body'     => $message,
				'category' => $category,
				'site_url' => home_url(),
			] );

			if ( ! is_wp_error( $result ) ) {
				wp_send_json_success( 'Ticket opened in the Group6 portal.' );
			}

			error_log( sprintf(
				'[G6 Dashboard] Portal ticket failed for %s: %s',
				esc_html( $cfg['client_name'] ),
				$result->get_error_message()
			) );
			g6_log_contact_failure( $cfg['client_name'], 'Portal: ' . $result->get_error_message(), 0, '' );

			// The portal refused this particular submission and said why,
			// in a sentence meant for the person reading it. Show that
			// rather than burying it in a log and answering "could not
			// send message", which tells them nothing they can act on.
			//
			// No email fallback here on purpose: the fallback is for when
			// we cannot reach the portal, and emailing a message the
			// portal has already told us is misaddressed just moves the
			// confusion into somebody's inbox.
			if ( 'g6_api_rejected' === $result->get_error_code() ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
			}
		} else {
			g6_log_contact_failure( $cfg['client_name'], 'Portal: no site token configured', 0, '' );
		}
	}

	// ── Email ──────────────────────────────────────────────────────────
	// The destination itself for a site set to email, and the fallback
	// for a portal that could not be reached.
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
		// Everything has failed, including this site's own mail. Say that
		// plainly — "could not send message" reads like the form is
		// broken, when the useful thing to know is that nothing got
		// through and the address to use instead.
		wp_send_json_error( [
			'message' => 'We could not get your message through just now. Please email '
				. $cfg['agency_rep_email'] . ' directly and we will pick it up there.',
		] );
	}
}
