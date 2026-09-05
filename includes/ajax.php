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

/**
 * Persist a rolling log of Zendesk ticket-creation failures/suspensions so
 * they're readable in Settings → Plugin without needing server log access.
 */
/**
 * One log for contact-form failures, whichever destination the form was
 * pointed at. The option key still says zendesk on purpose: renaming it
 * would orphan every entry already written on every site.
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
	$subject  = sanitize_text_field( $_POST['subject'] ?? '' );
	$message  = sanitize_textarea_field( $_POST['message'] ?? '' );
	// Only the portal form sends this, and it is a slug the portal itself
	// handed us — validated again on the way in, so a forged one is
	// refused rather than filed somewhere arbitrary.
	$category = sanitize_key( $_POST['category'] ?? '' );

	if ( empty( $subject ) || empty( $message ) ) {
		wp_send_json_error( [ 'message' => 'Please fill in all fields.' ] );
	}

	// ── The Group6 portal, when this site is cut over ──────────────────
	// Same contract as the Zendesk block below: succeed and stop, or fall
	// through to the email fallback. That safety net is the reason this
	// handler is shaped the way it is, and a new destination does not get
	// to opt out of it.
	if ( function_exists( 'g6_tickets_destination' ) && 'portal' === g6_tickets_destination( $cfg ) ) {
		$portal_token = g6_portal_token( $cfg );

		if ( $portal_token ) {
			// The category is a slug the portal gave this form in the
			// first place, so it round-trips. Empty when the categories
			// could not be fetched and the form fell back to its Zendesk
			// shape — the portal then applies its own default rather than
			// this plugin guessing at one.
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

		// Deliberately does NOT try Zendesk after the portal fails. A site
		// that has been cut over should not quietly start filing tickets
		// back into the tool it was moved off — the message goes to email,
		// where somebody sees it and acts.
		$skip_zendesk = true;
	}

	// ── Try Zendesk first ──────────────────────────────────────────────
	// Subdomain is hardcoded via G6_ZENDESK_SUBDOMAIN in the main plugin file.
	// To switch tools, update that constant or replace this block.
	if ( empty( $skip_zendesk ) && defined( 'G6_ZENDESK_SUBDOMAIN' ) && G6_ZENDESK_SUBDOMAIN ) {
		$zendesk_url = sprintf( 'https://%s.zendesk.com/api/v2/requests.json', G6_ZENDESK_SUBDOMAIN );

		// Required custom field on this Zendesk instance ("What is your issue?",
		// field ID 38403385125267). Our dashboard's subject dropdown is meant
		// to mirror this field's options 1:1 (see the <select> in
		// includes/dashboard.php) — keep both lists in sync if either changes.
		// Falls back to "customer_other" defensively in case the two ever drift.
		$zendesk_issue_field_map = [
			'I would like to update my website' => 'customer_update',
			'Hosting-related issues' => 'customer_hosting',
			'Design/branding requests or issues' => 'customer_design',
			'New feature request' => 'customer_feature',
			'Billing/account' => 'customer_billing',
			'I need training on a specific topic' => 'customer_training',
			'Other - My issue is not listed' => 'customer_other',
		];
		$zendesk_issue_tag = $zendesk_issue_field_map[ $subject ] ?? 'customer_other';

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
				'fields' => [
					[ 'id' => 38403385125267, 'value' => $zendesk_issue_tag ],
				],
			],
		] );

		$response = wp_remote_post( $zendesk_url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( ! is_wp_error( $response ) ) {
			$code          = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$decoded       = json_decode( $response_body, true );
			// A 2xx response can still mean the ticket was SUSPENDED rather
			// than actually opened — Zendesk does this for requester emails
			// it doesn't already recognise as a verified contact. Suspended
			// tickets sit in a separate queue, invisible in the normal ticket
			// view, so treat this as not-fully-successful and still fall
			// back to email rather than risk the message going unseen.
			$is_suspended = is_array( $decoded ) && isset( $decoded['suspended_ticket'] );

			if ( $code >= 200 && $code < 300 && ! $is_suspended ) {
				wp_send_json_success( 'Zendesk ticket created.' );
			}

			// Log the failure/suspension reason — otherwise a silent fallback
			// to email leaves no trace of why Zendesk didn't produce a normal,
			// visible ticket. Written both to the PHP error log and to a WP
			// option shown in Settings → Plugin, since server log access
			// isn't always practical to reach.
			$reason = $is_suspended ? 'Ticket suspended (unverified requester email)' : 'Ticket creation failed';
			error_log( sprintf( '[G6 Dashboard] Zendesk %s (HTTP %d) for %s: %s', $reason, $code, esc_html( $cfg['client_name'] ), $response_body ) );
			g6_log_contact_failure( $cfg['client_name'], $reason, $code, $response_body );
		} else {
			error_log( sprintf(
				'[G6 Dashboard] Zendesk request errored for %s: %s',
				esc_html( $cfg['client_name'] ),
				$response->get_error_message()
			) );
			g6_log_contact_failure( $cfg['client_name'], 'Request errored', 0, $response->get_error_message() );
		}
		// Fall through to email if Zendesk fails or suspends the ticket.
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
