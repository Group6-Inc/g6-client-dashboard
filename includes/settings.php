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
		$config['video_url']         = esc_url_raw( $_POST['video_url'] ?? '' );
		$config['video_title']       = sanitize_text_field( $_POST['video_title'] ?? '' );
		$config['last_updated']      = current_time( 'mysql' );

		// Widget visibility — unchecked checkboxes don't submit, so default to false.
		$config['widgets'] = [
			'guides'   => isset( $_POST['widget_guides'] ),
			'keywords' => isset( $_POST['widget_keywords'] ),
			'reviews'  => isset( $_POST['widget_reviews'] ),
			'services' => isset( $_POST['widget_services'] ),
			'contact'  => isset( $_POST['widget_contact'] ),
			'video'    => isset( $_POST['widget_video'] ),
		];

		// Parse guides repeater.
		$guide_titles = $_POST['guide_title'] ?? [];
		$guide_descs  = $_POST['guide_desc']  ?? [];
		$guide_urls   = $_POST['guide_url']   ?? [];
		$guide_icons  = $_POST['guide_icon']  ?? [];

		$allowed_icons = [ 'book-open', 'edit', 'plus-circle', 'phone', 'bar-chart',
		                   'inbox', 'message-circle', 'search', 'star', 'zap',
		                   'map-pin', 'file-text', 'trending-up', 'check-circle', 'mail' ];

		$guides = [];
		foreach ( $guide_titles as $i => $title ) {
			$title = sanitize_text_field( $title );
			if ( '' === $title ) {
				continue;
			}
			$icon = sanitize_key( $guide_icons[ $i ] ?? 'book-open' );
			if ( ! in_array( $icon, $allowed_icons, true ) ) {
				$icon = 'book-open';
			}
			$guides[] = [
				'title'       => $title,
				'description' => sanitize_text_field( $guide_descs[ $i ] ?? '' ),
				'url'         => esc_url_raw( $guide_urls[ $i ] ?? '' ),
				'icon'        => $icon,
			];
		}
		if ( ! empty( $guides ) ) {
			$config['guides'] = $guides;
		}

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

			<h2 class="title">Widget Visibility</h2>
			<p style="margin:-4px 0 12px; color:#646970;">Enable or disable sections on the client dashboard. Disabling a widget also hides its settings below.</p>
			<table class="form-table">
				<tr>
					<th scope="row">Visible Widgets</th>
					<td>
						<?php
						$widgets = $cfg['widgets'];
						$widget_list = [
							'guides'   => 'How-To Guides &amp; Resources',
							'keywords' => 'Keyword Rankings',
							'reviews'  => 'Reputation Snapshot',
							'services' => 'Grow Your Business',
							'contact'  => 'Get in Touch',
							'video'    => 'Featured Video',
						];
						foreach ( $widget_list as $key => $label ) :
						?>
							<label style="display:flex; align-items:center; gap:8px; margin-bottom:10px; cursor:pointer;">
								<input type="checkbox"
									name="widget_<?php echo esc_attr( $key ); ?>"
									id="widget_<?php echo esc_attr( $key ); ?>"
									<?php checked( $widgets[ $key ] ?? false ); ?>
									onchange="g6ToggleWidgetSettings('<?php echo esc_js( $key ); ?>', this.checked)">
								<?php echo $label; ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<script>
			function g6ToggleWidgetSettings( key, enabled ) {
				var el = document.getElementById( 'g6-settings-' + key );
				if ( el ) el.style.display = enabled ? '' : 'none';
			}
			// Set initial state on page load.
			document.addEventListener( 'DOMContentLoaded', function() {
				['guides','keywords','reviews','services','contact','video'].forEach( function( key ) {
					var cb = document.getElementById( 'widget_' + key );
					if ( cb ) g6ToggleWidgetSettings( key, cb.checked );
				});
			});
			</script>

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

			<div id="g6-settings-video">
				<h2 class="title">Featured Video</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Video URL</th>
						<td>
							<input type="url" name="video_url" value="<?php echo esc_attr( $cfg['video_url'] ?? '' ); ?>" class="regular-text" placeholder="https://www.youtube.com/watch?v=...">
							<p class="description">Paste a YouTube or Vimeo URL.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Video Title</th>
						<td>
							<input type="text" name="video_title" value="<?php echo esc_attr( $cfg['video_title'] ?? '' ); ?>" class="regular-text" placeholder="How to Use Your WordPress Site">
						</td>
					</tr>
				</table>
			</div>

			<div id="g6-settings-guides">
				<h2 class="title">How-To Guides &amp; Resources</h2>
				<p style="margin:-4px 0 12px; color:#646970;">Add or remove guide cards shown on the client dashboard. Each card links to a Loom, Google Doc, or any URL.</p>
				<table class="form-table">
					<tr>
						<th scope="row">Guides</th>
						<td>
							<div id="g6-guides-repeater">
								<?php foreach ( $cfg['guides'] as $i => $guide ) : ?>
								<div class="g6-guide-row" style="display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;">
									<div style="flex:1; display:grid; grid-template-columns:1fr 1fr; gap:6px;">
										<input type="text"   name="guide_title[]" value="<?php echo esc_attr( $guide['title'] ); ?>"       placeholder="Title"       class="regular-text" style="width:100%;">
										<select              name="guide_icon[]"  style="width:100%;">
											<?php foreach ( [
												'book-open'      => 'Book / Guide',
												'edit'           => 'Edit / Page',
												'plus-circle'    => 'Plus / New',
												'phone'          => 'Phone',
												'bar-chart'      => 'Bar Chart / Report',
												'inbox'          => 'Inbox / Forms',
												'message-circle' => 'Message',
												'search'         => 'Search / SEO',
												'star'           => 'Star / Reviews',
												'zap'            => 'Zap / Services',
												'map-pin'        => 'Map Pin / Local',
												'file-text'      => 'File / Docs',
												'trending-up'    => 'Trending Up',
												'check-circle'   => 'Check / Done',
												'mail'           => 'Mail',
											] as $icon_key => $icon_label ) : ?>
												<option value="<?php echo esc_attr( $icon_key ); ?>" <?php selected( $guide['icon'], $icon_key ); ?>><?php echo esc_html( $icon_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<input type="text"   name="guide_desc[]"  value="<?php echo esc_attr( $guide['description'] ); ?>" placeholder="Short description (optional)" class="regular-text" style="width:100%;">
										<input type="url"    name="guide_url[]"   value="<?php echo esc_attr( $guide['url'] ); ?>"         placeholder="https://…"   class="regular-text" style="width:100%;">
									</div>
									<button type="button" onclick="g6RemoveGuide(this)" style="flex-shrink:0; background:none; border:1px solid #ccc; border-radius:4px; cursor:pointer; padding:4px 8px; color:#b32d2e; font-size:18px; line-height:1;" title="Remove">&times;</button>
								</div>
								<?php endforeach; ?>
							</div>

							<button type="button" onclick="g6AddGuide()" class="button" style="margin-top:6px;">+ Add Guide</button>

							<script>
							var g6GuideIconOptions = <?php echo wp_json_encode( [
								'book-open'      => 'Book / Guide',
								'edit'           => 'Edit / Page',
								'plus-circle'    => 'Plus / New',
								'phone'          => 'Phone',
								'bar-chart'      => 'Bar Chart / Report',
								'inbox'          => 'Inbox / Forms',
								'message-circle' => 'Message',
								'search'         => 'Search / SEO',
								'star'           => 'Star / Reviews',
								'zap'            => 'Zap / Services',
								'map-pin'        => 'Map Pin / Local',
								'file-text'      => 'File / Docs',
								'trending-up'    => 'Trending Up',
								'check-circle'   => 'Check / Done',
								'mail'           => 'Mail',
							] ); ?>;

							function g6AddGuide() {
								var iconOpts = Object.entries(g6GuideIconOptions).map(function([k,v]) {
									return '<option value="' + k + '">' + v + '</option>';
								}).join('');

								var row = document.createElement('div');
								row.className = 'g6-guide-row';
								row.style.cssText = 'display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;';
								row.innerHTML =
									'<div style="flex:1; display:grid; grid-template-columns:1fr 1fr; gap:6px;">' +
										'<input type="text" name="guide_title[]" placeholder="Title" class="regular-text" style="width:100%;">' +
										'<select name="guide_icon[]" style="width:100%;">' + iconOpts + '</select>' +
										'<input type="text" name="guide_desc[]" placeholder="Short description (optional)" class="regular-text" style="width:100%;">' +
										'<input type="url" name="guide_url[]" placeholder="https://\u2026" class="regular-text" style="width:100%;">' +
									'</div>' +
									'<button type="button" onclick="g6RemoveGuide(this)" style="flex-shrink:0; background:none; border:1px solid #ccc; border-radius:4px; cursor:pointer; padding:4px 8px; color:#b32d2e; font-size:18px; line-height:1;" title="Remove">&times;</button>';
								document.getElementById('g6-guides-repeater').appendChild(row);
							}

							function g6RemoveGuide(btn) {
								btn.closest('.g6-guide-row').remove();
							}
							</script>
						</td>
					</tr>
				</table>
			</div>

			<div id="g6-settings-keywords">
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
			</div>

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
