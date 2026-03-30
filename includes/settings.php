<?php
/**
 * Admin settings page — visible only to @group6inc.com users.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Access helper ─────────────────────────────────────────────────────────────

function g6_is_group6_user(): bool {
	$user = wp_get_current_user();
	return $user->ID > 0 && str_ends_with( $user->user_email, '@group6inc.com' );
}

// ── Register menu ─────────────────────────────────────────────────────────────

add_action( 'admin_menu', 'g6_add_settings_page' );

function g6_add_settings_page(): void {
	if ( ! g6_is_group6_user() ) {
		return;
	}
	add_submenu_page(
		'index.php',
		'Group6 Dashboard Settings',
		'G6 Dashboard',
		'manage_options',
		'g6-dashboard-settings',
		'g6_settings_page_render'
	);
}

// ── Render settings page ──────────────────────────────────────────────────────

function g6_settings_page_render(): void {
	if ( ! current_user_can( 'manage_options' ) || ! g6_is_group6_user() ) {
		wp_die( 'You do not have permission to access this page.' );
	}

	// Handle save.
	if ( isset( $_POST['g6_save_settings'] ) && check_admin_referer( 'g6_settings_nonce' ) ) {
		$config = get_option( 'g6_client_config', [] );
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		$config['agency_rep_name']   = sanitize_text_field( $_POST['rep_name'] ?? '' );
		$config['agency_rep_email']  = sanitize_email( $_POST['rep_email'] ?? '' );
		$config['agency_rep_phone']  = sanitize_text_field( $_POST['rep_phone'] ?? '' );
		$config['agency_rep_photo']  = esc_url_raw( $_POST['rep_photo'] ?? '' );
		$config['last_updated'] = current_time( 'mysql' );

		// Parse keywords (one per line: term | position | change | volume).
		$keywords_raw = trim( $_POST['keywords'] ?? '' );
		if ( $keywords_raw ) {
			$keywords = [];
			foreach ( explode( "\n", $keywords_raw ) as $line ) {
				$parts = array_map( 'trim', explode( '|', $line ) );
				if ( count( $parts ) >= 4 ) {
					$keywords[] = [
						'term'     => sanitize_text_field( $parts[0] ),
						'position' => (int) $parts[1],
						'change'   => (int) $parts[2],
						'volume'   => (int) $parts[3],
					];
				}
			}
			if ( ! empty( $keywords ) ) {
				$config['keywords'] = $keywords;
			}
		}

		update_option( 'g6_client_config', $config );
		echo '<div class="updated"><p>Settings saved.</p></div>';
	}

	$cfg = g6_get_client_config();

	// Build keyword textarea content.
	$kw_lines = implode( "\n", array_map( function( $kw ) {
		return sprintf( '%s | %d | %d | %d', $kw['term'], $kw['position'], $kw['change'], $kw['volume'] );
	}, $cfg['keywords'] ) );

	?>
	<div class="wrap">
		<h1>Group6 Dashboard Settings</h1>
		<p>Configure the client-facing dashboard that appears on the WordPress home screen.</p>

		<form method="post">
			<?php wp_nonce_field( 'g6_settings_nonce' ); ?>

			<h2 class="title">Account Manager</h2>
			<table class="form-table">
				<tr>
					<th scope="row">Name</th>
					<td><input type="text" name="rep_name" value="<?php echo esc_attr( $cfg['agency_rep_name'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row">Email</th>
					<td><input type="email" name="rep_email" value="<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row">Phone</th>
					<td><input type="text" name="rep_phone" value="<?php echo esc_attr( $cfg['agency_rep_phone'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row">Photo URL</th>
					<td>
						<input type="url" name="rep_photo" value="<?php echo esc_attr( $cfg['agency_rep_photo'] ); ?>" class="regular-text" placeholder="https://example.com/photo.jpg">
						<p class="description">Direct URL to a headshot image. Leave blank to show initials.</p>
						<?php if ( ! empty( $cfg['agency_rep_photo'] ) ) : ?>
							<p style="margin-top:8px;">
								<img src="<?php echo esc_url( $cfg['agency_rep_photo'] ); ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #ddd;">
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h2 class="title">SEO Keywords</h2>
			<table class="form-table">
				<tr>
					<th scope="row">Keywords</th>
					<td>
						<textarea name="keywords" rows="10" cols="70" class="large-text code"><?php echo esc_textarea( $kw_lines ); ?></textarea>
						<p class="description">One keyword per line: <code>keyword term | position | change | monthly volume</code></p>
					</td>
				</tr>
			</table>

			<h2 class="title">Plugin Info</h2>
			<table class="form-table">
				<tr>
					<th scope="row">Current Version</th>
					<td>
						<code><?php echo esc_html( G6_DASHBOARD_VERSION ); ?></code>
						<p class="description">
							Updates are delivered automatically from the Group6 GitHub repo.
							To trigger an update check now, visit
							<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>">Dashboard &rarr; Updates</a>
							and click <strong>Check Again</strong>.
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="g6_save_settings" class="button-primary" value="Save Settings">
			</p>
		</form>
	</div>
	<?php
}
