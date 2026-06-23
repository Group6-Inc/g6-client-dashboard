<?php
/**
 * Admin settings page — visible only to @group6inc.com / @group6interactive.com users.
 *
 * Tabs: Dashboard | Content | Tracking | Developer Tools | Plugin
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Access helper ─────────────────────────────────────────────────────────────

function g6_is_group6_user(): bool {
	$user = wp_get_current_user();
	return $user->ID > 0 && (
		str_ends_with( $user->user_email, '@group6inc.com' ) ||
		str_ends_with( $user->user_email, '@group6interactive.com' )
	);
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

// ── Icon list (shared by guides + services repeaters) ─────────────────────────

function g6_settings_icon_options(): array {
	return [
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
	];
}

// ── Save handler ──────────────────────────────────────────────────────────────

function g6_settings_handle_save( array &$config ): void {
	if ( ! isset( $_POST['g6_save_settings'] ) || ! check_admin_referer( 'g6_settings_nonce' ) ) {
		return;
	}

	// ── Dashboard tab ────────────────────────────────────────────────────────
	$config['agency_rep_name']  = sanitize_text_field( $_POST['rep_name']   ?? '' );
	$config['agency_rep_email'] = sanitize_email( $_POST['rep_email']       ?? '' );
	$config['agency_rep_phone'] = sanitize_text_field( $_POST['rep_phone']  ?? '' );
	$config['agency_rep_photo'] = esc_url_raw( $_POST['rep_photo']          ?? '' );

	$config['widgets'] = [
		'guides'   => isset( $_POST['widget_guides'] ),
		'keywords' => isset( $_POST['widget_keywords'] ),
		'reviews'  => isset( $_POST['widget_reviews'] ),
		'services' => isset( $_POST['widget_services'] ),
		'contact'  => isset( $_POST['widget_contact'] ),
		'video'    => isset( $_POST['widget_video'] ),
	];

	// ── Content tab ──────────────────────────────────────────────────────────
	$config['video_url']   = esc_url_raw( $_POST['video_url']    ?? '' );
	$config['video_title'] = sanitize_text_field( $_POST['video_title'] ?? '' );

	$allowed_icons  = array_keys( g6_settings_icon_options() );
	$guide_titles   = $_POST['guide_title'] ?? [];
	$guide_descs    = $_POST['guide_desc']  ?? [];
	$guide_urls     = $_POST['guide_url']   ?? [];
	$guide_icons    = $_POST['guide_icon']  ?? [];
	$guides = [];
	foreach ( $guide_titles as $i => $title ) {
		$title = sanitize_text_field( $title );
		if ( '' === $title ) {
			continue;
		}
		$icon     = sanitize_key( $guide_icons[ $i ] ?? 'book-open' );
		$guides[] = [
			'title'       => $title,
			'description' => sanitize_text_field( $guide_descs[ $i ] ?? '' ),
			'url'         => sanitize_text_field( $guide_urls[ $i ] ?? '' ),
			'icon'        => in_array( $icon, $allowed_icons, true ) ? $icon : 'book-open',
		];
	}
	if ( ! empty( $guides ) ) {
		$config['guides'] = $guides;
	}

	$svc_names      = $_POST['svc_name']      ?? [];
	$svc_descs      = $_POST['svc_desc']      ?? [];
	$svc_urls       = $_POST['svc_url']       ?? [];
	$svc_icons      = $_POST['svc_icon']      ?? [];
	$svc_cta_labels = $_POST['svc_cta_label'] ?? [];
	$svc_highlights = $_POST['svc_highlight'] ?? [];
	$services       = [];
	$svc_index      = 0;
	foreach ( $svc_names as $i => $name ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			$svc_index++;
			continue;
		}
		$icon       = sanitize_key( $svc_icons[ $i ] ?? 'zap' );
		$services[] = [
			'name'        => $name,
			'description' => sanitize_text_field( $svc_descs[ $i ] ?? '' ),
			'cta_url'     => esc_url_raw( $svc_urls[ $i ] ?? '' ),
			'cta_label'   => sanitize_text_field( $svc_cta_labels[ $i ] ?? 'Learn More' ),
			'icon'        => in_array( $icon, $allowed_icons, true ) ? $icon : 'zap',
			'highlight'   => isset( $svc_highlights[ $svc_index ] ),
		];
		$svc_index++;
	}
	if ( ! empty( $services ) ) {
		$config['services'] = $services;
	}

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

	// ── Tracking tab ─────────────────────────────────────────────────────────
	$config['tracking'] = [
		'gtm_id'            => sanitize_text_field( $_POST['gtm_id']            ?? '' ),
		'google_ads_id'     => sanitize_text_field( $_POST['google_ads_id']     ?? '' ),
		'facebook_pixel_id' => sanitize_text_field( $_POST['facebook_pixel_id'] ?? '' ),
		'x_pixel_id'        => sanitize_text_field( $_POST['x_pixel_id']        ?? '' ),
		'clarity_project_id'=> sanitize_text_field( $_POST['clarity_project_id'] ?? '' ),
	];

	// ── Developer Tools tab ───────────────────────────────────────────────────
	$config['asset_manager_enabled'] = isset( $_POST['asset_manager_enabled'] );

	$config['last_updated'] = current_time( 'mysql' );

	update_option( 'g6_client_config', $config );
}

// ── Render settings page ──────────────────────────────────────────────────────

function g6_settings_page_render(): void {
	if ( ! current_user_can( 'manage_options' ) || ! g6_is_group6_user() ) {
		wp_die( 'You do not have permission to access this page.' );
	}

	$config = get_option( 'g6_client_config', [] );
	if ( ! is_array( $config ) ) {
		$config = [];
	}

	g6_settings_handle_save( $config );

	$cfg        = g6_get_client_config();
	$active_tab = sanitize_key( $_POST['g6_active_tab'] ?? $_GET['g6_tab'] ?? 'dashboard' );
	$icon_opts  = g6_settings_icon_options();

	$kw_lines = implode( "\n", array_map( function( $kw ) {
		return sprintf( '%s | %d | %d | %d', $kw['term'], $kw['position'], $kw['change'], $kw['volume'] );
	}, $cfg['keywords'] ) );

	$tabs = [
		'dashboard' => 'Dashboard',
		'content'   => 'Content',
		'tracking'  => 'Tracking',
		'developer' => 'Developer Tools',
		'plugin'    => 'Plugin',
	];
	?>
	<div class="wrap">
		<h1>Group6 Dashboard Settings</h1>

		<?php if ( isset( $_POST['g6_save_settings'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper" id="g6-tab-nav">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a href="#" class="nav-tab<?php echo $key === $active_tab ? ' nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<form method="post" id="g6-settings-form">
			<?php wp_nonce_field( 'g6_settings_nonce' ); ?>
			<input type="hidden" id="g6_active_tab" name="g6_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: DASHBOARD                                              -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-dashboard">

				<h2 class="title">Widget Visibility</h2>
				<p style="margin:-4px 0 12px; color:#646970;">Control which sections appear on the client-facing dashboard.</p>
				<table class="form-table">
					<tr>
						<th scope="row">Visible Widgets</th>
						<td>
							<?php
							$widgets     = $cfg['widgets'];
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
										<?php checked( $widgets[ $key ] ?? false ); ?>>
									<?php echo $label; ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

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

			</div><!-- /tab: dashboard -->

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: CONTENT                                                -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-content" style="display:none">

				<div id="g6-widget-settings-video"<?php echo empty( $cfg['widgets']['video'] ) ? ' style="display:none"' : ''; ?>>
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
				</div><!-- /widget-settings-video -->

				<div id="g6-widget-settings-guides"<?php echo empty( $cfg['widgets']['guides'] ) ? ' style="display:none"' : ''; ?>>
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
										<input type="text" name="guide_title[]" value="<?php echo esc_attr( $guide['title'] ); ?>"       placeholder="Title"       class="regular-text" style="width:100%;">
										<select           name="guide_icon[]"  style="width:100%;">
											<?php foreach ( $icon_opts as $icon_key => $icon_label ) : ?>
												<option value="<?php echo esc_attr( $icon_key ); ?>" <?php selected( $guide['icon'], $icon_key ); ?>><?php echo esc_html( $icon_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<input type="text" name="guide_desc[]" value="<?php echo esc_attr( $guide['description'] ); ?>" placeholder="Short description (optional)" class="regular-text" style="width:100%;">
										<input type="text" name="guide_url[]"  value="<?php echo esc_attr( $guide['url'] ); ?>"         placeholder="https://… or #"   class="regular-text" style="width:100%;">
									</div>
									<button type="button" onclick="g6RemoveGuide(this)" class="g6-remove-btn" title="Remove">&times;</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" onclick="g6AddGuide()" class="button" style="margin-top:6px;">+ Add Guide</button>
						</td>
					</tr>
				</table>
				</div><!-- /widget-settings-guides -->

				<div id="g6-widget-settings-services"<?php echo empty( $cfg['widgets']['services'] ) ? ' style="display:none"' : ''; ?>>
				<h2 class="title">Add-On Services</h2>
				<p style="margin:-4px 0 12px; color:#646970;">Services shown in the "Grow Your Business" widget. Check "Popular" to highlight a card.</p>
				<table class="form-table">
					<tr>
						<th scope="row">Services</th>
						<td>
							<div id="g6-services-repeater">
								<?php foreach ( $cfg['services'] as $i => $svc ) : ?>
								<div class="g6-svc-row" style="display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;">
									<div style="flex:1; display:grid; grid-template-columns:1fr 1fr; gap:6px;">
										<input type="text" name="svc_name[]"      value="<?php echo esc_attr( $svc['name'] ); ?>"        placeholder="Service name"   class="regular-text" style="width:100%;">
										<select           name="svc_icon[]"      style="width:100%;">
											<?php foreach ( $icon_opts as $icon_key => $icon_label ) : ?>
												<option value="<?php echo esc_attr( $icon_key ); ?>" <?php selected( $svc['icon'], $icon_key ); ?>><?php echo esc_html( $icon_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<input type="text" name="svc_desc[]"      value="<?php echo esc_attr( $svc['description'] ); ?>" placeholder="Short description" class="regular-text" style="width:100%; grid-column:1/-1;">
										<input type="url"  name="svc_url[]"       value="<?php echo esc_attr( $svc['cta_url'] ); ?>"    placeholder="https://…"       class="regular-text" style="width:100%;">
										<input type="text" name="svc_cta_label[]" value="<?php echo esc_attr( $svc['cta_label'] ); ?>"  placeholder="CTA label (e.g. Learn More)" class="regular-text" style="width:100%;">
										<label style="display:flex; align-items:center; gap:6px; grid-column:1/-1;">
											<input type="checkbox" name="svc_highlight[<?php echo $i; ?>]" <?php checked( $svc['highlight'] ); ?>>
											Mark as Popular
										</label>
									</div>
									<button type="button" onclick="g6RemoveService(this)" class="g6-remove-btn" title="Remove">&times;</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" onclick="g6AddService()" class="button" style="margin-top:6px;">+ Add Service</button>
						</td>
					</tr>
				</table>
				</div><!-- /widget-settings-services -->

				<div id="g6-widget-settings-keywords"<?php echo empty( $cfg['widgets']['keywords'] ) ? ' style="display:none"' : ''; ?>>
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
				</div><!-- /widget-settings-keywords -->

			</div><!-- /tab: content -->

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: TRACKING                                               -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-tracking" style="display:none">

				<p style="margin:16px 0; color:#646970;">Enter tracking IDs below. Scripts are injected automatically into the site's <code>&lt;head&gt;</code>. Leave a field blank to disable that platform.</p>

				<?php $tracking = $cfg['tracking'] ?? []; ?>

				<h2 class="title">Google Tag Manager</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gtm_id">Container ID</label></th>
						<td>
							<input type="text" id="gtm_id" name="gtm_id" value="<?php echo esc_attr( $tracking['gtm_id'] ?? '' ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
							<p class="description">Injects the GTM snippet into <code>&lt;head&gt;</code> and the noscript fallback after <code>&lt;body&gt;</code>.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Google Ads</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="google_ads_id">Conversion ID</label></th>
						<td>
							<input type="text" id="google_ads_id" name="google_ads_id" value="<?php echo esc_attr( $tracking['google_ads_id'] ?? '' ); ?>" class="regular-text" placeholder="AW-XXXXXXXXXX">
							<p class="description">Injects the global site tag (gtag.js) for Google Ads remarketing and conversion tracking.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Meta (Facebook) Pixel</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="facebook_pixel_id">Pixel ID</label></th>
						<td>
							<input type="text" id="facebook_pixel_id" name="facebook_pixel_id" value="<?php echo esc_attr( $tracking['facebook_pixel_id'] ?? '' ); ?>" class="regular-text" placeholder="123456789012345">
							<p class="description">Injects the Meta Pixel base code and fires a PageView event on every page.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">X (Twitter) Pixel</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="x_pixel_id">Pixel ID</label></th>
						<td>
							<input type="text" id="x_pixel_id" name="x_pixel_id" value="<?php echo esc_attr( $tracking['x_pixel_id'] ?? '' ); ?>" class="regular-text" placeholder="oabcd">
							<p class="description">Injects the X universal website tag.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Microsoft Clarity</h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="clarity_project_id">Project ID</label></th>
						<td>
							<input type="text" id="clarity_project_id" name="clarity_project_id" value="<?php echo esc_attr( $tracking['clarity_project_id'] ?? '' ); ?>" class="regular-text" placeholder="abc123xyz0">
							<p class="description">Injects the Clarity session recording and heatmap script.</p>
						</td>
					</tr>
				</table>

			</div><!-- /tab: tracking -->

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: DEVELOPER TOOLS                                        -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-developer" style="display:none">

				<p style="margin:16px 0; color:#646970;">Internal agency utilities. These features are not visible to clients.</p>

				<h2 class="title">Asset Manager</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Enable Asset Manager</th>
						<td>
							<label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
								<input type="checkbox" name="asset_manager_enabled" value="1" <?php checked( ! empty( $cfg['asset_manager_enabled'] ) ); ?>>
								Enable the G6 Asset Manager (Appearance &rarr; G6 Asset Manager)
							</label>
							<p class="description">Provides an interface to upload and manage theme-specific assets (icons, logos, images, JS, CSS) directly in the theme folder, separate from the WordPress Media Library.</p>
							<?php if ( ! empty( $cfg['asset_manager_enabled'] ) ) : ?>
								<p style="margin-top:8px;">
									<a href="<?php echo esc_url( admin_url( 'themes.php?page=upload-theme-assets' ) ); ?>" class="button button-secondary">Open Asset Manager &rarr;</a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

			</div><!-- /tab: developer -->

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: PLUGIN                                                 -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-plugin" style="display:none">

				<h2 class="title">Plugin Info</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Current Version</th>
						<td>
							<code><?php echo esc_html( G6_DASHBOARD_VERSION ); ?></code>
							<p class="description">
								Updates are delivered automatically from the Group6 GitHub repo.
								To trigger an update check, visit
								<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>">Dashboard &rarr; Updates</a>
								and click <strong>Check Again</strong>.
							</p>
							<p class="description" style="margin-top:6px;">
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'g6-refresh-update', '1' ), 'g6_refresh_update' ) ); ?>" class="button button-secondary">
									Force Refresh Update Cache
								</a>
								<span style="margin-left:6px; color:#646970;">Clears the cached manifest and reloads update info immediately.</span>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Last Saved</th>
						<td><code><?php echo esc_html( $cfg['last_updated'] ); ?></code></td>
					</tr>
				</table>

			</div><!-- /tab: plugin -->

			<p class="submit" style="padding-top:0;">
				<input type="submit" name="g6_save_settings" class="button-primary" value="Save Settings">
			</p>

		</form>
	</div><!-- .wrap -->

	<style>
		#g6-tab-nav { margin-top: 16px; margin-bottom: 0; }
		#g6-settings-form { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 0 24px 8px; margin-top: 0; }
		#g6-settings-form .g6-tab-panel { padding-top: 8px; }
		.g6-remove-btn {
			flex-shrink: 0;
			background: none;
			border: 1px solid #ccc;
			border-radius: 4px;
			cursor: pointer;
			padding: 4px 8px;
			color: #b32d2e;
			font-size: 18px;
			line-height: 1;
		}
		.g6-remove-btn:hover { background: #fbeaea; border-color: #b32d2e; }
	</style>

	<script>
	(function() {
		var STORAGE_KEY = 'g6_settings_active_tab';

		function switchTab(tabName) {
			document.querySelectorAll('.g6-tab-panel').forEach(function(el) {
				el.style.display = 'none';
			});
			document.querySelectorAll('#g6-tab-nav .nav-tab').forEach(function(el) {
				el.classList.remove('nav-tab-active');
			});
			var panel = document.getElementById('g6-tab-' + tabName);
			var tab   = document.querySelector('#g6-tab-nav [data-tab="' + tabName + '"]');
			if (panel) panel.style.display = '';
			if (tab)   tab.classList.add('nav-tab-active');
			document.getElementById('g6_active_tab').value = tabName;
			try { localStorage.setItem(STORAGE_KEY, tabName); } catch(e) {}
		}

		document.addEventListener('DOMContentLoaded', function() {
			// After a save, PHP echoes the active tab via the hidden field.
			// On a fresh page load, fall back to localStorage.
			var fromServer  = document.getElementById('g6_active_tab').value;
			var fromStorage = '';
			try { fromStorage = localStorage.getItem(STORAGE_KEY) || ''; } catch(e) {}
			var initial = fromServer || fromStorage || 'dashboard';
			switchTab(initial);

			document.querySelectorAll('#g6-tab-nav [data-tab]').forEach(function(el) {
				el.addEventListener('click', function(e) {
					e.preventDefault();
					switchTab(this.dataset.tab);
				});
			});

			// Cross-tab validation guard: if a field on a hidden tab is invalid,
			// switch to that tab and show a clear error instead of silently blocking.
			document.getElementById('g6-settings-form').addEventListener('submit', function(e) {
				var firstOffTabInvalid = null;
				var errorTabLabels    = [];

				this.querySelectorAll('input, textarea, select').forEach(function(field) {
					if (!field.checkValidity()) {
						var panel = field.closest('.g6-tab-panel');
						if (panel && panel.style.display === 'none') {
							if (!firstOffTabInvalid) firstOffTabInvalid = panel;
							var tabId  = panel.id.replace('g6-tab-', '');
							var tabEl  = document.querySelector('#g6-tab-nav [data-tab="' + tabId + '"]');
							var label  = tabEl ? tabEl.textContent.trim() : tabId;
							if (errorTabLabels.indexOf(label) === -1) errorTabLabels.push(label);
						}
					}
				});

				if (firstOffTabInvalid) {
					e.preventDefault();
					switchTab(firstOffTabInvalid.id.replace('g6-tab-', ''));
					var notice = document.getElementById('g6-cross-tab-error');
					if (!notice) {
						notice = document.createElement('div');
						notice.id        = 'g6-cross-tab-error';
						notice.className = 'notice notice-error';
						notice.innerHTML = '<p></p>';
						var form = document.getElementById('g6-settings-form');
						form.insertBefore(notice, form.firstChild);
					}
					notice.querySelector('p').textContent =
						'Please fix the required fields on the following tab' +
						(errorTabLabels.length > 1 ? 's' : '') + ': ' + errorTabLabels.join(', ');
					notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			});
		});
	})();

	// ── Guides repeater ───────────────────────────────────────────────────────
	var g6IconOptions = <?php echo wp_json_encode( g6_settings_icon_options() ); ?>;

	function g6BuildIconSelect(name, selectedKey) {
		selectedKey = selectedKey || '';
		return '<select name="' + name + '" style="width:100%;">' +
			Object.entries(g6IconOptions).map(function(e) {
				return '<option value="' + e[0] + '"' + (e[0] === selectedKey ? ' selected' : '') + '>' + e[1] + '</option>';
			}).join('') +
		'</select>';
	}

	function g6AddGuide() {
		var row = document.createElement('div');
		row.className = 'g6-guide-row';
		row.style.cssText = 'display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;';
		row.innerHTML =
			'<div style="flex:1; display:grid; grid-template-columns:1fr 1fr; gap:6px;">' +
				'<input type="text" name="guide_title[]" placeholder="Title" class="regular-text" style="width:100%;">' +
				g6BuildIconSelect('guide_icon[]') +
				'<input type="text" name="guide_desc[]" placeholder="Short description (optional)" class="regular-text" style="width:100%;">' +
				'<input type="text" name="guide_url[]"  placeholder="https://… or #" class="regular-text" style="width:100%;">' +
			'</div>' +
			'<button type="button" onclick="g6RemoveGuide(this)" class="g6-remove-btn" title="Remove">&times;</button>';
		document.getElementById('g6-guides-repeater').appendChild(row);
	}

	function g6RemoveGuide(btn) { btn.closest('.g6-guide-row').remove(); }

	// ── Services repeater ─────────────────────────────────────────────────────
	function g6AddService() {
		var idx = document.querySelectorAll('.g6-svc-row').length;
		var row = document.createElement('div');
		row.className = 'g6-svc-row';
		row.style.cssText = 'display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;';
		row.innerHTML =
			'<div style="flex:1; display:grid; grid-template-columns:1fr 1fr; gap:6px;">' +
				'<input type="text" name="svc_name[]"      placeholder="Service name"   class="regular-text" style="width:100%;">' +
				g6BuildIconSelect('svc_icon[]') +
				'<input type="text" name="svc_desc[]"      placeholder="Short description" class="regular-text" style="width:100%; grid-column:1/-1;">' +
				'<input type="url"  name="svc_url[]"       placeholder="https://…" class="regular-text" style="width:100%;">' +
				'<input type="text" name="svc_cta_label[]" placeholder="Learn More"     class="regular-text" style="width:100%;">' +
				'<label style="display:flex; align-items:center; gap:6px; grid-column:1/-1;">' +
					'<input type="checkbox" name="svc_highlight[' + idx + ']"> Mark as Popular' +
				'</label>' +
			'</div>' +
			'<button type="button" onclick="g6RemoveService(this)" class="g6-remove-btn" title="Remove">&times;</button>';
		document.getElementById('g6-services-repeater').appendChild(row);
	}

	function g6RemoveService(btn) { btn.closest('.g6-svc-row').remove(); }

	// ── Widget visibility → Content tab settings ──────────────────────────────
	// Keys that have a corresponding settings section on the Content tab.
	var g6WidgetSettingsKeys = ['video', 'guides', 'services', 'keywords'];

	function g6SyncWidgetSettings(key, enabled) {
		var el = document.getElementById('g6-widget-settings-' + key);
		if (el) el.style.display = enabled ? '' : 'none';
	}

	document.addEventListener('DOMContentLoaded', function() {
		g6WidgetSettingsKeys.forEach(function(key) {
			var cb = document.querySelector('input[name="widget_' + key + '"]');
			if (!cb) return;
			cb.addEventListener('change', function() {
				g6SyncWidgetSettings(key, this.checked);
			});
		});
	});
	</script>
	<?php
}
