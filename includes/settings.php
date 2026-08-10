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
	$hook = add_submenu_page(
		'index.php',
		'Group6 Dashboard Settings',
		'G6 Dashboard',
		'manage_options',
		'g6-dashboard-settings',
		'g6_settings_page_render'
	);
	add_action( 'admin_enqueue_scripts', function( string $current_hook ) use ( $hook ): void {
		if ( $current_hook === $hook ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	} );
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
	$is_save             = isset( $_POST['g6_save_settings'] );
	$is_refresh          = isset( $_POST['g6_gmb_refresh'] );
	$is_airtable_refresh = isset( $_POST['g6_airtable_refresh'] );

	if ( ( ! $is_save && ! $is_refresh && ! $is_airtable_refresh ) || ! check_admin_referer( 'g6_settings_nonce' ) ) {
		return;
	}

	if ( $is_refresh ) {
		g6_gmb_clear_all_caches( $config['reviews_locations']   ?? [] );
		g6_gmb_clear_all_caches( $config['reviews_competitors'] ?? [] );
		return;
	}

	if ( $is_airtable_refresh ) {
		g6_airtable_clear_cache( $config['support_hours_record_id'] ?? '' );
		return;
	}

	// ── Dashboard tab ────────────────────────────────────────────────────────
	$config['agency_rep_name']  = sanitize_text_field( $_POST['rep_name']   ?? '' );
	$config['agency_rep_email'] = sanitize_email( $_POST['rep_email']       ?? '' );
	$config['agency_rep_phone'] = sanitize_text_field( $_POST['rep_phone']  ?? '' );
	$config['agency_rep_photo'] = esc_url_raw( $_POST['rep_photo']          ?? '' );

	// Airtable Support Hours.
	$old_record_id = $config['support_hours_record_id'] ?? '';
	$new_record_id = g6_airtable_parse_record_id( $_POST['support_hours_record_id'] ?? '' );

	$config['support_hours_enabled']   = isset( $_POST['support_hours_enabled'] );
	$config['support_hours_api_key']   = sanitize_text_field( $_POST['support_hours_api_key'] ?? '' );
	$config['support_hours_record_id'] = $new_record_id;

	if ( $old_record_id && $old_record_id !== $new_record_id ) {
		g6_airtable_clear_cache( $old_record_id );
	}

	$config['widgets'] = [
		'guides'   => isset( $_POST['widget_guides'] ),
		'keywords' => isset( $_POST['widget_keywords'] ),
		'reviews'  => isset( $_POST['widget_reviews'] ),
		'services' => isset( $_POST['widget_services'] ),
		'contact'  => isset( $_POST['widget_contact'] ),
		'video'    => isset( $_POST['widget_video'] ),
	];

	// ── GMB integration ─────────────────────────────────────────────────────
	$old_locations    = $config['reviews_locations']   ?? [];
	$old_competitors  = $config['reviews_competitors'] ?? [];

	// Helper: parse a place_id[]/label[] pair from POST into a locations array.
	$parse_gmb_rows = function( string $pid_key, string $label_key ): array {
		$pids   = $_POST[ $pid_key ]   ?? [];
		$labels = $_POST[ $label_key ] ?? [];
		$out    = [];
		foreach ( $pids as $i => $pid ) {
			$pid = sanitize_text_field( $pid );
			if ( $pid ) {
				$out[] = [ 'place_id' => $pid, 'label' => sanitize_text_field( $labels[ $i ] ?? '' ) ];
			}
		}
		return $out;
	};

	$new_locations   = $parse_gmb_rows( 'gmb_place_id', 'gmb_label' );
	$new_competitors = $parse_gmb_rows( 'gmb_comp_place_id', 'gmb_comp_label' );

	$config['reviews_locations']    = $new_locations;
	$config['reviews_competitors']  = $new_competitors;
	$config['reviews_display_mode'] = in_array( $_POST['reviews_display_mode'] ?? '', [ 'combined', 'separate' ], true )
		? $_POST['reviews_display_mode'] : 'combined';
	$config['reviews_cta_text']     = sanitize_text_field( $_POST['reviews_cta_text'] ?? '' );
	$config['reviews_api_key']      = sanitize_text_field( $_POST['reviews_api_key']  ?? '' );

	// Clear cache for any place IDs that were removed.
	$new_loc_ids  = array_column( $new_locations,   'place_id' );
	$new_comp_ids = array_column( $new_competitors, 'place_id' );
	foreach ( $old_locations as $loc ) {
		if ( ! in_array( $loc['place_id'], $new_loc_ids, true ) ) g6_gmb_clear_cache( $loc['place_id'] );
	}
	foreach ( $old_competitors as $loc ) {
		if ( ! in_array( $loc['place_id'], $new_comp_ids, true ) ) g6_gmb_clear_cache( $loc['place_id'] );
	}

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
	foreach ( $svc_names as $i => $name ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			continue;
		}
		$icon       = sanitize_key( $svc_icons[ $i ] ?? 'zap' );
		$services[] = [
			'name'        => $name,
			'description' => sanitize_text_field( $svc_descs[ $i ] ?? '' ),
			'cta_url'     => esc_url_raw( $svc_urls[ $i ] ?? '' ),
			'cta_label'   => sanitize_text_field( $svc_cta_labels[ $i ] ?? 'Learn More' ),
			'icon'        => in_array( $icon, $allowed_icons, true ) ? $icon : 'zap',
			'highlight'   => ( $svc_highlights[ $i ] ?? '0' ) === '1',
		];
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
		'ga_measurement_id' => sanitize_text_field( $_POST['ga_measurement_id'] ?? '' ),
		'google_ads_id'     => sanitize_text_field( $_POST['google_ads_id']     ?? '' ),
		'facebook_pixel_id' => sanitize_text_field( $_POST['facebook_pixel_id'] ?? '' ),
		'x_pixel_id'        => sanitize_text_field( $_POST['x_pixel_id']        ?? '' ),
		'clarity_project_id'=> sanitize_text_field( $_POST['clarity_project_id'] ?? '' ),
	];

	// ── Login tab ─────────────────────────────────────────────────────────────
	$_ld = g6_default_config()['login'];
	$config['login'] = [
		'enabled'        => isset( $_POST['login_enabled'] ),
		'layout'         => sanitize_text_field( $_POST['login_layout']        ?? $_ld['layout'] ),
		'logo_url'       => esc_url_raw( $_POST['login_logo_url']              ?? '' ),
		'logo_height'    => absint( $_POST['login_logo_height']                ?? $_ld['logo_height'] ),
		'bg_color'       => sanitize_text_field( $_POST['login_bg_color']      ?? '' ) ?: $_ld['bg_color'],
		'hero_image_url' => esc_url_raw( $_POST['login_hero_image_url']        ?? '' ),
		'accent_color'        => sanitize_text_field( $_POST['login_accent_color']      ?? '' ) ?: $_ld['accent_color'],
		'link_color'          => sanitize_text_field( $_POST['login_link_color']        ?? '' ) ?: $_ld['link_color'],
		'login_error_message' => sanitize_text_field( $_POST['login_error_message']     ?? '' ),
	];

	// ── Developer Tools tab ───────────────────────────────────────────────────
	$config['asset_manager_enabled']    = isset( $_POST['asset_manager_enabled'] );
	$config['disable_attachment_slugs'] = isset( $_POST['disable_attachment_slugs'] );

	// ── Plugin tab ────────────────────────────────────────────────────────────
	$config['beta_updates_enabled'] = isset( $_POST['beta_updates_enabled'] );

	$config['last_updated'] = current_time( 'mysql' );

	update_option( 'g6_client_config', $config );
}

// ── Render settings page ──────────────────────────────────────────────────────

function g6_settings_page_render(): void {
	if ( ! current_user_can( 'manage_options' ) || ! g6_is_group6_user() ) {
		wp_die( 'You do not have permission to access this page.' );
	}
	wp_enqueue_media();

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
		'content'   => 'Widgets',
		'tracking'  => 'Tracking',
		'login'     => 'Login',
		'developer' => 'Developer Tools',
		'plugin'    => 'Plugin',
	];
	?>
	<div class="wrap">
		<h1>Group6 Dashboard Settings</h1>

		<?php if ( isset( $_POST['g6_save_settings'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
			<?php
			$_sh_raw_input = trim( $_POST['support_hours_record_id'] ?? '' );
			if ( $_sh_raw_input && empty( $cfg['support_hours_record_id'] ) ) :
			?>
			<div class="notice notice-warning is-dismissible"><p>Couldn't find a valid Airtable record ID in what you pasted for Support Hours — paste a record URL (it should contain <code>rec…</code>) or the bare record ID directly.</p></div>
			<?php endif; ?>
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

				<div class="g6t-page-header">
					<p class="g6t-page-header__desc">Per-client configuration. More cards will appear here as integrations are added.</p>
				</div>

				<div class="g6s-grid">

					<div class="g6s-card g6s-card--full">
						<div class="g6s-card__header">
							<h3 class="g6s-card__title">Account Manager</h3>
							<p class="g6s-card__desc">Contact displayed to clients on the dashboard.</p>
						</div>
						<div class="g6s-field-row">
							<div class="g6s-field">
								<label class="g6s-field__label">Name</label>
								<input type="text" name="rep_name" value="<?php echo esc_attr( $cfg['agency_rep_name'] ); ?>" class="g6s-field__input">
							</div>
							<div class="g6s-field">
								<label class="g6s-field__label">Email</label>
								<input type="email" name="rep_email" value="<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>" class="g6s-field__input">
							</div>
							<div class="g6s-field">
								<label class="g6s-field__label">Phone</label>
								<input type="text" name="rep_phone" value="<?php echo esc_attr( $cfg['agency_rep_phone'] ); ?>" class="g6s-field__input">
							</div>
							<div class="g6s-field">
								<label class="g6s-field__label">Photo URL</label>
								<input type="url" name="rep_photo" value="<?php echo esc_attr( $cfg['agency_rep_photo'] ); ?>" class="g6s-field__input" placeholder="https://…">
							</div>
						</div>
						<?php if ( ! empty( $cfg['agency_rep_photo'] ) ) : ?>
						<div style="display:flex; align-items:center; gap:10px; padding-top:12px; border-top:1px solid #f3f4f6;">
							<img src="<?php echo esc_url( $cfg['agency_rep_photo'] ); ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;" alt="">
							<span style="font-size:12px; color:#6b7280;"><?php echo esc_html( $cfg['agency_rep_name'] ); ?></span>
						</div>
						<?php endif; ?>
					</div>

					<!-- Support Hours (Airtable) -->
					<?php
					$_sh_record_id    = $cfg['support_hours_record_id'] ?? '';
					$_sh_api_key      = $cfg['support_hours_api_key']   ?? '';
					$_sh_has_config   = $_sh_record_id && $_sh_api_key;
					$_sh_error        = $_sh_record_id ? g6_airtable_get_last_error( $_sh_record_id ) : '';
					$_sh_cached       = $_sh_record_id ? get_transient( g6_airtable_transient_key( $_sh_record_id ) ) : false;
					$_sh_last_fetched = ( is_array( $_sh_cached ) && isset( $_sh_cached['fetched_at'] ) ) ? $_sh_cached['fetched_at'] : '';
					?>
					<div class="g6s-card g6s-card--full">
						<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
							<div class="g6s-card__header">
								<h3 class="g6s-card__title">Support Hours</h3>
								<p class="g6s-card__desc">Shows the client's remaining support-hour balance from Airtable in the dashboard sidebar.</p>
							</div>
							<label class="g6w-toggle" style="flex-shrink:0; margin-top:2px;">
								<input type="checkbox" name="support_hours_enabled" <?php checked( ! empty( $cfg['support_hours_enabled'] ) ); ?>>
								<span class="g6w-toggle__track"></span>
							</label>
						</div>
						<div class="g6s-field-row">
							<div class="g6s-field">
								<label class="g6s-field__label" for="support_hours_api_key">Airtable Personal Access Token</label>
								<input class="g6s-field__input" type="password" id="support_hours_api_key" name="support_hours_api_key"
									value="<?php echo esc_attr( $_sh_api_key ); ?>" placeholder="pat…">
							</div>
							<div class="g6s-field">
								<label class="g6s-field__label" for="support_hours_record_id">Airtable Record URL or ID</label>
								<input class="g6s-field__input" type="text" id="support_hours_record_id" name="support_hours_record_id"
									value="<?php echo esc_attr( $_sh_record_id ); ?>" placeholder="Paste the record URL, or recXXXXXXXXXXXXXX">
							</div>
						</div>
						<p class="description" style="margin-top:6px;">
							Create a token at <a href="https://airtable.com/create/tokens" target="_blank">airtable.com/create/tokens</a> with <code>data.records:read</code> scope, granted access to the Client Support Hours base. The same token works across every client site — only the record (which row = this client) changes per site.
						</p>

						<?php if ( $_sh_has_config ) : ?>
						<div style="display:flex; align-items:center; gap:12px; margin-top:14px; flex-wrap:wrap;">
							<button type="submit" name="g6_airtable_refresh" value="1" class="button">
								<?php echo g6_icon( 'refresh-cw', 13 ); ?> Refresh Data
							</button>
							<?php if ( $_sh_error ) : ?>
								<span style="color:#d63638; font-size:13px;">API error: <?php echo esc_html( $_sh_error ); ?></span>
							<?php elseif ( $_sh_last_fetched ) : ?>
								<span class="description">Last fetched: <?php echo esc_html( $_sh_last_fetched ); ?> · refreshes every 6 h</span>
							<?php else : ?>
								<span class="description">Not yet fetched — save to pull data.</span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>

				</div>

			</div><!-- /tab: dashboard -->

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: WIDGETS                                                -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-content" style="display:none">

				<div class="g6t-page-header">
					<p class="g6t-page-header__desc">Enable or disable widgets shown on the client dashboard. Expand each card to configure its content.</p>
				</div>

				<?php
				$widget_nav_items = [
					'guides'   => [ 'label' => 'How-To Guides & Resources', 'icon' => 'book-open' ],
					'services' => [ 'label' => 'Grow Your Business',        'icon' => 'zap' ],
					'keywords' => [ 'label' => 'Keyword Rankings',          'icon' => 'search' ],
					'video'    => [ 'label' => 'Featured Video',            'icon' => 'play-circle' ],
					'reviews'  => [ 'label' => 'Reputation Snapshot',       'icon' => 'star' ],
					'contact'  => [ 'label' => 'Get in Touch',              'icon' => 'message-circle' ],
				];
				?>

				<div class="g6w-layout">

					<aside class="g6w-nav">
						<?php foreach ( $widget_nav_items as $w_key => $w_item ) : ?>
						<div class="g6w-nav__item" data-scroll="<?php echo esc_attr( $w_key ); ?>">
							<span class="g6w-nav__icon"><?php echo g6_icon( $w_item['icon'], 16 ); ?></span>
							<span class="g6w-nav__label"><?php echo esc_html( $w_item['label'] ); ?></span>
							<label class="g6w-toggle">
								<input type="checkbox" name="widget_<?php echo esc_attr( $w_key ); ?>" <?php checked( $cfg['widgets'][ $w_key ] ?? false ); ?>>
								<span class="g6w-toggle__track"></span>
							</label>
						</div>
						<?php endforeach; ?>
					</aside>

					<div class="g6w-main">

					<!-- Guides -->
					<div class="g6w-card<?php echo empty( $cfg['widgets']['guides'] ) ? ' g6w-card--disabled' : ''; ?>" data-widget="guides">
						<div class="g6w-card__header">
							<div class="g6w-card__meta">
								<div class="g6w-card__icon"><?php echo g6_icon( 'book-open', 20 ); ?></div>
								<div>
									<h3 class="g6w-card__title">How-To Guides &amp; Resources</h3>
									<p class="g6w-card__desc">Guide cards linking to Loom videos, Google Docs, or any URL.</p>
								</div>
							</div>
						</div>
						<div id="g6-widget-settings-guides" class="g6w-card__settings"<?php echo empty( $cfg['widgets']['guides'] ) ? ' style="display:none"' : ''; ?>>
							<div id="g6-guides-repeater">
								<?php foreach ( $cfg['guides'] as $i => $guide ) : ?>
								<div class="g6-repeater-row g6-guide-row" style="display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;">
									<span class="g6-drag-handle" title="Drag to reorder"><?php echo g6_icon( 'grip-vertical', 16 ); ?></span>
									<div style="flex:1; display:grid; grid-template-columns:1fr 1fr; gap:6px;">
										<input type="text" name="guide_title[]" value="<?php echo esc_attr( $guide['title'] ); ?>"       placeholder="Title"       class="regular-text" style="width:100%;">
										<select           name="guide_icon[]"  style="width:100%;">
											<?php foreach ( $icon_opts as $icon_key => $icon_label ) : ?>
												<option value="<?php echo esc_attr( $icon_key ); ?>" <?php selected( $guide['icon'], $icon_key ); ?>><?php echo esc_html( $icon_label ); ?></option>
											<?php endforeach; ?>
										</select>
										<input type="text" name="guide_desc[]" value="<?php echo esc_attr( $guide['description'] ); ?>" placeholder="Short description (optional)" class="regular-text" style="width:100%;">
										<input type="text" name="guide_url[]"  value="<?php echo esc_attr( $guide['url'] ); ?>"         placeholder="https://… or #" class="regular-text" style="width:100%;">
									</div>
									<button type="button" onclick="g6RemoveGuide(this)" class="g6-remove-btn" title="Remove">&times;</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" onclick="g6AddGuide()" class="button" style="margin-top:6px;">+ Add Guide</button>
						</div>
					</div>

					<!-- Services -->
					<div class="g6w-card<?php echo empty( $cfg['widgets']['services'] ) ? ' g6w-card--disabled' : ''; ?>" data-widget="services">
						<div class="g6w-card__header">
							<div class="g6w-card__meta">
								<div class="g6w-card__icon"><?php echo g6_icon( 'zap', 20 ); ?></div>
								<div>
									<h3 class="g6w-card__title">Grow Your Business</h3>
									<p class="g6w-card__desc">Add-on service cards with CTAs. Check "Popular" to highlight a card.</p>
								</div>
							</div>
						</div>
						<div id="g6-widget-settings-services" class="g6w-card__settings"<?php echo empty( $cfg['widgets']['services'] ) ? ' style="display:none"' : ''; ?>>
							<div id="g6-services-repeater">
								<?php foreach ( $cfg['services'] as $i => $svc ) : ?>
								<div class="g6-repeater-row g6-svc-row" style="display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;">
									<span class="g6-drag-handle" title="Drag to reorder"><?php echo g6_icon( 'grip-vertical', 16 ); ?></span>
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
											<input type="hidden" name="svc_highlight[]" class="g6-svc-highlight-hidden" value="<?php echo $svc['highlight'] ? '1' : '0'; ?>">
											<input type="checkbox" class="g6-svc-highlight-checkbox" <?php checked( $svc['highlight'] ); ?>>
											Mark as Popular
										</label>
									</div>
									<button type="button" onclick="g6RemoveService(this)" class="g6-remove-btn" title="Remove">&times;</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" onclick="g6AddService()" class="button" style="margin-top:6px;">+ Add Service</button>
						</div>
					</div>

					<!-- Keywords -->
					<div class="g6w-card<?php echo empty( $cfg['widgets']['keywords'] ) ? ' g6w-card--disabled' : ''; ?>" data-widget="keywords">
						<div class="g6w-card__header">
							<div class="g6w-card__meta">
								<div class="g6w-card__icon"><?php echo g6_icon( 'search', 20 ); ?></div>
								<div>
									<h3 class="g6w-card__title">Keyword Rankings</h3>
									<p class="g6w-card__desc">Keyword position table with change and monthly volume.</p>
								</div>
							</div>
						</div>
						<div id="g6-widget-settings-keywords" class="g6w-card__settings"<?php echo empty( $cfg['widgets']['keywords'] ) ? ' style="display:none"' : ''; ?>>
							<textarea name="keywords" rows="8" class="large-text code" style="width:100%; font-size:12.5px;"><?php echo esc_textarea( $kw_lines ); ?></textarea>
							<p class="description" style="margin-top:6px;">One per line: <code>keyword term | position | change | monthly volume</code></p>
						</div>
					</div>

					<!-- Video -->
					<div class="g6w-card<?php echo empty( $cfg['widgets']['video'] ) ? ' g6w-card--disabled' : ''; ?>" data-widget="video">
						<div class="g6w-card__header">
							<div class="g6w-card__meta">
								<div class="g6w-card__icon"><?php echo g6_icon( 'play-circle', 20 ); ?></div>
								<div>
									<h3 class="g6w-card__title">Featured Video</h3>
									<p class="g6w-card__desc">YouTube or Vimeo embed with a custom title.</p>
								</div>
							</div>
						</div>
						<div id="g6-widget-settings-video" class="g6w-card__settings"<?php echo empty( $cfg['widgets']['video'] ) ? ' style="display:none"' : ''; ?>>
							<div style="display:flex; flex-direction:column; gap:10px;">
								<div class="g6s-field">
									<label class="g6s-field__label">Video URL</label>
									<input type="url" name="video_url" value="<?php echo esc_attr( $cfg['video_url'] ?? '' ); ?>" class="g6s-field__input" placeholder="https://www.youtube.com/watch?v=…">
								</div>
								<div class="g6s-field">
									<label class="g6s-field__label">Video Title</label>
									<input type="text" name="video_title" value="<?php echo esc_attr( $cfg['video_title'] ?? '' ); ?>" class="g6s-field__input" placeholder="How to Use Your WordPress Site">
								</div>
							</div>
						</div>
					</div>

					<!-- Reviews -->
					<?php
					$_locations    = $cfg['reviews_locations']   ?? [];
					$_competitors  = $cfg['reviews_competitors']  ?? [];
					// Migrate legacy single place_id.
					if ( empty( $_locations ) && ! empty( $cfg['reviews_place_id'] ) ) {
						$_locations = [ [ 'place_id' => $cfg['reviews_place_id'], 'label' => '' ] ];
					}
					$_api_key      = $cfg['reviews_api_key']     ?? '';
					$_display_mode = $cfg['reviews_display_mode'] ?? 'combined';
					$_cta_text     = $cfg['reviews_cta_text']    ?? '';
					$_has_config   = ! empty( $_locations ) && $_api_key;
					// Cache status across all locations + competitors.
					$_any_error    = '';
					$_last_fetched = '';
					foreach ( array_merge( $_locations, $_competitors ) as $_loc ) {
						$_pid = $_loc['place_id'] ?? '';
						$_err = $_pid ? g6_gmb_get_last_error( $_pid ) : '';
						if ( $_err ) { $_any_error = $_err; break; }
						$_c = $_pid ? get_transient( g6_gmb_transient_key( $_pid ) ) : false;
						if ( is_array( $_c ) && isset( $_c['fetched_at'] ) && $_c['fetched_at'] > $_last_fetched ) {
							$_last_fetched = $_c['fetched_at'];
						}
					}
					?>
					<div class="g6w-card<?php echo empty( $cfg['widgets']['reviews'] ) ? ' g6w-card--disabled' : ''; ?>" data-widget="reviews">
						<div class="g6w-card__header">
							<div class="g6w-card__meta">
								<div class="g6w-card__icon"><?php echo g6_icon( 'star', 20 ); ?></div>
								<div>
									<h3 class="g6w-card__title">Reputation Snapshot</h3>
									<p class="g6w-card__desc">Google rating summary, competitor comparison, and recent reviews.</p>
								</div>
							</div>
						</div>
						<div id="g6-widget-settings-reviews" class="g6w-card__settings"<?php echo empty( $cfg['widgets']['reviews'] ) ? ' style="display:none"' : ''; ?>>

							<!-- Your Locations repeater -->
							<p class="g6s-field__label" style="margin:0 0 8px;">Your Locations</p>
							<div id="g6-gmb-locations">
								<?php foreach ( $_locations as $_loc ) : ?>
								<div class="g6-repeater-row g6-gmb-row">
									<span class="g6-drag-handle" title="Drag to reorder"><?php echo g6_icon( 'grip-vertical', 16 ); ?></span>
									<input class="g6s-field__input" type="text" name="gmb_place_id[]"
										value="<?php echo esc_attr( $_loc['place_id'] ?? '' ); ?>"
										placeholder="Place ID" style="flex:2;">
									<input class="g6s-field__input" type="text" name="gmb_label[]"
										value="<?php echo esc_attr( $_loc['label'] ?? '' ); ?>"
										placeholder="Label (optional)" style="flex:1;">
									<button type="button" class="g6-remove-btn g6-gmb-remove" title="Remove">&times;</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button g6-gmb-add" data-target="g6-gmb-locations" data-pid-name="gmb_place_id[]" data-label-name="gmb_label[]" style="margin-top:8px;">+ Add Location</button>

							<!-- Competitors repeater -->
							<p class="g6s-field__label" style="margin:16px 0 8px;">Competitors</p>
							<div id="g6-gmb-competitors">
								<?php foreach ( $_competitors as $_comp ) : ?>
								<div class="g6-repeater-row g6-gmb-row">
									<span class="g6-drag-handle" title="Drag to reorder"><?php echo g6_icon( 'grip-vertical', 16 ); ?></span>
									<input class="g6s-field__input" type="text" name="gmb_comp_place_id[]"
										value="<?php echo esc_attr( $_comp['place_id'] ?? '' ); ?>"
										placeholder="Place ID" style="flex:2;">
									<input class="g6s-field__input" type="text" name="gmb_comp_label[]"
										value="<?php echo esc_attr( $_comp['label'] ?? '' ); ?>"
										placeholder="Label (optional — uses Google name if empty)" style="flex:1;">
									<button type="button" class="g6-remove-btn g6-gmb-remove" title="Remove">&times;</button>
								</div>
								<?php endforeach; ?>
							</div>
							<button type="button" class="button g6-gmb-add" data-target="g6-gmb-competitors" data-pid-name="gmb_comp_place_id[]" data-label-name="gmb_comp_label[]" style="margin-top:8px;">+ Add Competitor</button>
							<p class="description" style="margin-top:6px;">Find Place IDs at <a href="https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder" target="_blank">Google's Place ID finder</a>.</p>

							<!-- Display mode -->
							<div class="g6s-field" style="margin-top:16px;">
								<p class="g6s-field__label" style="margin:0 0 8px;">Display mode</p>
								<label style="display:flex; align-items:center; gap:6px; margin-bottom:6px;">
									<input type="radio" name="reviews_display_mode" value="combined" <?php checked( $_display_mode, 'combined' ); ?>>
									<span><strong>Combined</strong> — weighted average rating, total count, newest reviews across all locations</span>
								</label>
								<label style="display:flex; align-items:center; gap:6px;">
									<input type="radio" name="reviews_display_mode" value="separate" <?php checked( $_display_mode, 'separate' ); ?>>
									<span><strong>Separate</strong> — each location shown independently with its own stats and reviews</span>
								</label>
							</div>

							<!-- CTA override -->
							<div class="g6s-field" style="margin-top:16px;">
								<label class="g6s-field__label" for="reviews_cta_text">CTA Text Override</label>
								<input class="g6s-field__input" type="text" id="reviews_cta_text" name="reviews_cta_text"
									value="<?php echo esc_attr( $_cta_text ); ?>"
									placeholder="Leave blank to use auto-generated text based on competitor comparison">
							</div>

							<!-- API key -->
							<div class="g6s-field" style="margin-top:16px;">
								<label class="g6s-field__label" for="reviews_api_key">Google Places API Key</label>
								<input class="g6s-field__input" type="password" id="reviews_api_key" name="reviews_api_key"
									value="<?php echo esc_attr( $_api_key ); ?>" placeholder="AIza…">
								<p class="description" style="margin-top:6px;">Restrict the key to the <strong>Places API (New)</strong> in <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>. One key works for all locations, competitors, and all client sites.</p>
							</div>

							<!-- Cache status + refresh -->
							<?php if ( $_has_config ) : ?>
							<div style="display:flex; align-items:center; gap:12px; margin-top:14px; flex-wrap:wrap;">
								<button type="submit" name="g6_gmb_refresh" value="1" class="button">
									<?php echo g6_icon( 'refresh-cw', 13 ); ?> Refresh Data
								</button>
								<?php if ( $_any_error ) : ?>
									<span style="color:#d63638; font-size:13px;">API error: <?php echo esc_html( $_any_error ); ?></span>
								<?php elseif ( $_last_fetched ) : ?>
									<span class="description">Last fetched: <?php echo esc_html( $_last_fetched ); ?> · refreshes every 48 h</span>
								<?php else : ?>
									<span class="description">Not yet fetched — save to pull data.</span>
								<?php endif; ?>
							</div>
							<?php endif; ?>

						</div>
					</div>

					<!-- Contact -->
					<div class="g6w-card<?php echo empty( $cfg['widgets']['contact'] ) ? ' g6w-card--disabled' : ''; ?>" data-widget="contact">
						<div class="g6w-card__header">
							<div class="g6w-card__meta">
								<div class="g6w-card__icon"><?php echo g6_icon( 'message-circle', 20 ); ?></div>
								<div>
									<h3 class="g6w-card__title">Get in Touch</h3>
									<p class="g6w-card__desc">Support request form linked to Zendesk.</p>
								</div>
							</div>
						</div>
					</div>

					</div><!-- /.g6w-main -->

				</div><!-- /.g6w-layout -->

			</div><!-- /tab: content -->

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: TRACKING                                               -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-tracking" style="display:none">

				<?php
				$tracking = $cfg['tracking'] ?? [];

				// Helper: render one tracking card.
				// $args keys: id, name, label, placeholder, badge_text, badge_bg, badge_color, title, desc
				function g6_tracking_card( array $args, array $tracking ): void {
					$value  = trim( $tracking[ $args['name'] ] ?? '' );
					$active = $value !== '';
					$extra  = isset( $args['full'] ) ? ' g6t-card--full' : '';
					$extra .= $active ? ' g6t-card--active' : '';
					?>
					<div class="g6t-card<?php echo $extra; ?>" data-card="<?php echo esc_attr( $args['name'] ); ?>">
						<div class="g6t-card__header">
							<span class="g6t-badge" style="background:<?php echo esc_attr( $args['badge_bg'] ); ?>;color:<?php echo esc_attr( $args['badge_color'] ); ?>;">
								<?php echo esc_html( $args['badge_text'] ); ?>
							</span>
							<div>
								<h3 class="g6t-card__title"><?php echo esc_html( $args['title'] ); ?></h3>
								<p class="g6t-card__desc"><?php echo esc_html( $args['desc'] ); ?></p>
							</div>
						</div>
						<div class="g6t-card__body">
							<label class="g6t-card__label" for="<?php echo esc_attr( $args['id'] ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
							<input
								type="text"
								id="<?php echo esc_attr( $args['id'] ); ?>"
								name="<?php echo esc_attr( $args['name'] ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								class="g6t-card__input"
								placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
							>
						</div>
						<div class="g6t-status <?php echo $active ? 'g6t-status--active' : 'g6t-status--inactive'; ?>">
							<span class="g6t-status__dot"></span>
							<?php echo $active ? 'Active' : 'Not configured'; ?>
						</div>
					</div>
					<?php
				}
				?>

				<div class="g6t-page-header">
					<p class="g6t-page-header__desc">Scripts inject automatically into <code>&lt;head&gt;</code> on the frontend. Leave a field blank to disable that platform.</p>
				</div>

				<div class="g6t-grid">

					<?php g6_tracking_card( [
						'id'          => 'gtm_id',
						'name'        => 'gtm_id',
						'label'       => 'Container ID',
						'placeholder' => 'GTM-XXXXXXX',
						'badge_text'  => 'GTM',
						'badge_bg'    => '#e8f0fe',
						'badge_color' => '#1a73e8',
						'title'       => 'Google Tag Manager',
						'desc'        => 'Injects the GTM snippet into <head> and a noscript fallback after <body>. Use GTM to manage all other tags from one place.',
						'full'        => true,
					], $tracking ); ?>

					<?php g6_tracking_card( [
						'id'          => 'ga_measurement_id',
						'name'        => 'ga_measurement_id',
						'label'       => 'Measurement ID',
						'placeholder' => 'G-XXXXXXXXXX',
						'badge_text'  => 'GA4',
						'badge_bg'    => '#fef3e2',
						'badge_color' => '#e37400',
						'title'       => 'Google Analytics 4',
						'desc'        => 'Injects the GA4 measurement script for traffic and behaviour analytics.',
					], $tracking ); ?>

					<?php g6_tracking_card( [
						'id'          => 'google_ads_id',
						'name'        => 'google_ads_id',
						'label'       => 'Conversion ID',
						'placeholder' => 'AW-XXXXXXXXXX',
						'badge_text'  => 'ADS',
						'badge_bg'    => '#fef9e7',
						'badge_color' => '#d97706',
						'title'       => 'Google Ads',
						'desc'        => 'Injects the global site tag (gtag.js) for remarketing and conversion tracking.',
					], $tracking ); ?>

					<?php g6_tracking_card( [
						'id'          => 'facebook_pixel_id',
						'name'        => 'facebook_pixel_id',
						'label'       => 'Pixel ID',
						'placeholder' => '123456789012345',
						'badge_text'  => 'META',
						'badge_bg'    => '#eff6ff',
						'badge_color' => '#2563eb',
						'title'       => 'Meta Pixel',
						'desc'        => 'Injects the Meta Pixel base code and fires a PageView event on every page load.',
					], $tracking ); ?>

					<?php g6_tracking_card( [
						'id'          => 'x_pixel_id',
						'name'        => 'x_pixel_id',
						'label'       => 'Pixel ID',
						'placeholder' => 'oabcd',
						'badge_text'  => 'X',
						'badge_bg'    => '#f3f4f6',
						'badge_color' => '#111827',
						'title'       => 'X (Twitter) Pixel',
						'desc'        => 'Injects the X universal website tag for audience targeting and conversion tracking.',
					], $tracking ); ?>

					<?php g6_tracking_card( [
						'id'          => 'clarity_project_id',
						'name'        => 'clarity_project_id',
						'label'       => 'Project ID',
						'placeholder' => 'abc123xyz0',
						'badge_text'  => 'CLA',
						'badge_bg'    => '#f0f4ff',
						'badge_color' => '#4f46e5',
						'title'       => 'Microsoft Clarity',
						'desc'        => 'Injects the Clarity session recording and heatmap script.',
					], $tracking ); ?>

				</div><!-- /.g6t-grid -->

			</div><!-- /tab: tracking -->

			<!-- ═══════════════════════════════════════════════════════════ -->
			<!-- TAB: LOGIN                                                   -->
			<!-- ═══════════════════════════════════════════════════════════ -->
			<div class="g6-tab-panel" id="g6-tab-login" style="display:none">

				<?php
				$_ld2      = g6_default_config()['login'];
				$login_cfg = array_merge( $_ld2, $cfg['login'] ?? [] );
				$login_on  = ! empty( $login_cfg['enabled'] );
				?>

				<!-- Enable toggle (always visible) -->
				<div class="g6s-grid">
					<div class="g6s-card g6s-card--full">
						<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px;">
							<div class="g6s-card__header">
								<h3 class="g6s-card__title">Login Screen Customizer</h3>
								<p class="g6s-card__desc">Replaces the default WordPress login with a branded split-screen layout. If you have existing login CSS in WPCodeBox, disable or remove it to avoid duplicate styles.</p>
							</div>
							<label class="g6w-toggle" style="flex-shrink:0; margin-top:2px;">
								<input type="checkbox" name="login_enabled" id="login_enabled" value="1" <?php checked( $login_on ); ?>>
								<span class="g6w-toggle__track"></span>
							</label>
						</div>
					</div>
				</div>

				<!-- Settings fields (hidden when customizer is disabled) -->
				<div id="g6-login-fields" <?php if ( ! $login_on ) echo 'style="display:none"'; ?>>
				<div class="g6s-grid" style="padding-top:0;">

					<!-- Layout -->
					<div class="g6s-card g6s-card--full">
						<div>
							<p class="g6s-field__label" style="margin:0 0 12px;">Layout</p>
							<div class="g6l-layout-picker">
								<label class="g6l-layout-card">
									<input type="radio" name="login_layout" value="split-screen" <?php checked( $login_cfg['layout'] ?? 'split-screen', 'split-screen' ); ?>>
									<div class="g6l-layout-card__inner">
										<div class="g6l-layout-card__screen">
											<div class="g6l-mock-left">
												<div class="g6l-mock-logo"></div>
												<div class="g6l-mock-field"></div>
												<div class="g6l-mock-field g6l-mock-field--short"></div>
												<div class="g6l-mock-btn"></div>
											</div>
											<div class="g6l-mock-right"></div>
										</div>
										<div class="g6l-layout-card__name">Split Screen</div>
									</div>
								</label>
							</div>
						</div>
					</div>

					<!-- Logo -->
					<div class="g6s-card">
						<div class="g6s-card__header">
							<h3 class="g6s-card__title">Logo</h3>
						</div>
						<div class="g6s-field">
							<label class="g6s-field__label" for="login_logo_url">Logo URL</label>
							<div class="g6l-url-field">
								<input class="g6s-field__input" type="text" id="login_logo_url" name="login_logo_url" value="<?php echo esc_attr( $login_cfg['logo_url'] ?? '' ); ?>" placeholder="/wp-content/uploads/logo.svg">
								<button type="button" class="button g6l-media-btn" data-target="login_logo_url" data-type="image">Select</button>
							</div>
						</div>
						<div class="g6s-field" style="max-width:140px;">
							<label class="g6s-field__label" for="login_logo_height">Logo Height (px)</label>
							<input class="g6s-field__input" type="number" id="login_logo_height" name="login_logo_height" value="<?php echo absint( $login_cfg['logo_height'] ?? 65 ); ?>" min="20" max="300">
						</div>
					</div>

					<!-- Colors -->
					<div class="g6s-card">
						<div class="g6s-card__header">
							<h3 class="g6s-card__title">Colors</h3>
						</div>
						<div class="g6l-colors">
							<div class="g6s-field">
								<label class="g6s-field__label" for="login_bg_color_hex">Background</label>
								<div class="g6l-color-input">
									<input type="color" id="login_bg_color_swatch" class="g6l-swatch" value="<?php echo esc_attr( $login_cfg['bg_color'] ?? '#111111' ); ?>">
									<input type="text" id="login_bg_color_hex" name="login_bg_color" class="g6l-hex" value="<?php echo esc_attr( $login_cfg['bg_color'] ?? '#111111' ); ?>" data-swatch="login_bg_color_swatch">
								</div>
							</div>
							<div class="g6s-field">
								<label class="g6s-field__label" for="login_accent_color_hex">Button</label>
								<div class="g6l-color-input">
									<input type="color" id="login_accent_color_swatch" class="g6l-swatch" value="<?php echo esc_attr( $login_cfg['accent_color'] ?? '#ff6e61' ); ?>">
									<input type="text" id="login_accent_color_hex" name="login_accent_color" class="g6l-hex" value="<?php echo esc_attr( $login_cfg['accent_color'] ?? '#ff6e61' ); ?>" data-swatch="login_accent_color_swatch">
								</div>
							</div>
							<div class="g6s-field">
								<label class="g6s-field__label" for="login_link_color_hex">Links</label>
								<div class="g6l-color-input">
									<input type="color" id="login_link_color_swatch" class="g6l-swatch" value="<?php echo esc_attr( $login_cfg['link_color'] ?? '#ffffff' ); ?>">
									<input type="text" id="login_link_color_hex" name="login_link_color" class="g6l-hex" value="<?php echo esc_attr( $login_cfg['link_color'] ?? '#ffffff' ); ?>" data-swatch="login_link_color_swatch">
								</div>
							</div>
						</div>
					</div>

					<!-- Hero image -->
					<div class="g6s-card g6s-card--full">
						<div class="g6s-card__header">
							<h3 class="g6s-card__title">Hero Image</h3>
							<p class="g6s-card__desc">Displayed on the right panel of the Split Screen layout.</p>
						</div>
						<div class="g6s-field">
							<label class="g6s-field__label" for="login_hero_image_url">Image URL</label>
							<div class="g6l-url-field">
								<input class="g6s-field__input" type="text" id="login_hero_image_url" name="login_hero_image_url" value="<?php echo esc_attr( $login_cfg['hero_image_url'] ?? '' ); ?>" placeholder="https://example.com/wp-content/uploads/hero.jpg">
								<button type="button" class="button g6l-media-btn" data-target="login_hero_image_url" data-type="image">Select</button>
							</div>
						</div>
					</div>

					<!-- Error message -->
					<div class="g6s-card g6s-card--full">
						<div class="g6s-card__header">
							<h3 class="g6s-card__title">Login Error Message</h3>
							<p class="g6s-card__desc">Replaces WP's default error message, which reveals whether a username exists. Leave blank to keep the default.</p>
						</div>
						<div class="g6s-field">
							<label class="g6s-field__label" for="login_error_message">Error Text</label>
							<input class="g6s-field__input" type="text" id="login_error_message" name="login_error_message" value="<?php echo esc_attr( $login_cfg['login_error_message'] ?? '' ); ?>" placeholder="Those credentials don't look right.">
						</div>
					</div>

				</div><!-- /.g6s-grid -->
				</div><!-- /#g6-login-fields -->

			</div><!-- /tab: login -->

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

				<h2 class="title">Attachment Pages</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Disable Attachment Pages</th>
						<td>
							<label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
								<input type="checkbox" name="disable_attachment_slugs" value="1" <?php checked( ! empty( $cfg['disable_attachment_slugs'] ) ); ?>>
								Redirect attachment URLs to 404 and randomize attachment slugs
							</label>
							<p class="description">Prevents media attachment pages from reserving post slugs and returns 404 for any attachment URL. Equivalent to the <code>wpse237762</code> snippet — disable that snippet in WPCodeBox before enabling this.</p>
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

				<h2 class="title" style="margin-top:28px;">Update Channel</h2>
				<table class="form-table">
					<tr>
						<th scope="row">Beta Updates</th>
						<td>
							<label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
								<input type="checkbox" name="beta_updates_enabled" value="1" <?php checked( ! empty( $cfg['beta_updates_enabled'] ) ); ?>>
								Enable beta versions
							</label>
							<div style="margin-top:10px; padding:10px 14px; background:#fffbeb; border:1px solid #fbbf24; border-radius:6px; color:#92400e; font-size:12.5px; line-height:1.5;">
								<strong>Beta channel:</strong> Pre-release builds for internal testing. May contain bugs or incomplete features. Only enable on test/staging sites — not on client production sites.
							</div>
							<p class="description" style="margin-top:8px;">
								When enabled, the auto-updater checks the <code>beta</code> branch builds instead of stable releases.
								<?php if ( ! empty( $cfg['beta_updates_enabled'] ) ) : ?>
									<strong style="color:#b45309;">Beta channel is currently active.</strong>
								<?php endif; ?>
							</p>
						</td>
					</tr>
				</table>

			</div><!-- /tab: plugin -->

			<p class="submit" style="padding-top:0;">
				<input type="submit" name="g6_save_settings" class="button-primary" value="Save Settings">
			</p>

		</form>
	</div><!-- .wrap -->

	<style>
		/* ── Tab chrome ────────────────────────────────────────────── */
		#g6-tab-nav { margin-top: 16px; margin-bottom: 0; }
		#g6-settings-form { background: #fff; border: 1px solid #c3c4c7; border-top: none; padding: 0 24px 8px; margin-top: 0; }
		#g6-settings-form .g6-tab-panel { padding-top: 8px; }

		/* ── Repeaters: drag-to-reorder (guides, services, GMB rows) ── */
		.g6-drag-handle { display: flex; align-items: center; color: #9ca3af; cursor: grab; flex-shrink: 0; padding: 4px 2px; touch-action: none; }
		.g6-drag-handle:hover { color: #6b7280; }
		.g6-repeater-row.ui-sortable-helper { box-shadow: 0 6px 16px rgba(0,0,0,0.15); cursor: grabbing; }
		.g6-repeater-placeholder-row { border: 2px dashed #d1d5db; border-radius: 4px; margin-bottom: 10px; background: #f3f4f6; }

		/* ── Repeater remove button ────────────────────────────────── */
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

		/* ── Tracking tab ──────────────────────────────────────────── */
		.g6t-page-header { padding: 20px 0 4px; }
		.g6t-page-header__desc { margin: 0; font-size: 13px; color: #6b7280; }
		.g6t-page-header__desc code { background: #f3f4f6; padding: 1px 5px; border-radius: 4px; font-size: 12px; }

		.g6t-grid {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 14px;
			padding: 16px 0 24px;
		}
		@media (max-width: 1100px) { .g6t-grid { grid-template-columns: 1fr; } }

		.g6t-card {
			background: #fff;
			border: 1.5px solid #e5e7eb;
			border-radius: 10px;
			padding: 20px;
			display: flex;
			flex-direction: column;
			gap: 14px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
			transition: box-shadow 0.15s ease, border-color 0.15s ease;
		}
		.g6t-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.08); border-color: #d1d5db; }
		.g6t-card--full  { grid-column: 1 / -1; }
		.g6t-card--active { border-color: #6ee7b7; background: #f0fdf4; }
		.g6t-card--active:hover { border-color: #34d399; }

		.g6t-card__header { display: flex; align-items: flex-start; gap: 12px; }

		.g6t-badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 42px;
			height: 42px;
			padding: 0 8px;
			border-radius: 8px;
			font-size: 10.5px;
			font-weight: 700;
			letter-spacing: 0.4px;
			flex-shrink: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}

		.g6t-card__title {
			font-size: 14.5px;
			font-weight: 600;
			color: #111827;
			margin: 0 0 4px;
			line-height: 1.3;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}
		.g6t-card__desc { font-size: 12px; color: #6b7280; margin: 0; line-height: 1.55; }

		.g6t-card__body { display: flex; flex-direction: column; gap: 6px; }

		.g6t-card__label {
			display: block;
			font-size: 10.5px;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: 0.7px;
			color: #6b7280;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		}

		.g6t-card__input {
			width: 100%;
			padding: 9px 12px;
			border: 1.5px solid #d1d5db;
			border-radius: 6px;
			font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
			font-size: 13px;
			color: #111827;
			background: #f9fafb;
			box-sizing: border-box;
			transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
		}
		.g6t-card__input:focus {
			outline: none;
			border-color: #6366f1;
			background: #fff;
			box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
		}
		.g6t-card--active .g6t-card__input { background: #fff; border-color: #a7f3d0; }
		.g6t-card--active .g6t-card__input:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
		.g6t-card__input::placeholder { color: #9ca3af; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 12.5px; }

		.g6t-status {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 11.5px;
			font-weight: 500;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			margin-top: 2px;
		}
		.g6t-status__dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
		.g6t-status--active  { color: #059669; }
		.g6t-status--active  .g6t-status__dot { background: #10b981; box-shadow: 0 0 0 2px #a7f3d0; }
		.g6t-status--inactive { color: #9ca3af; }
		.g6t-status--inactive .g6t-status__dot { background: #d1d5db; }

		/* ── Settings cards (Dashboard tab) ── */
		.g6s-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; padding: 16px 0 24px; }
		@media (max-width: 1100px) { .g6s-grid { grid-template-columns: 1fr; } }
		.g6s-card {
			background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px;
			padding: 20px; display: flex; flex-direction: column; gap: 16px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
		}
		.g6s-card--full { grid-column: 1 / -1; }
		.g6s-card__header { display: flex; flex-direction: column; gap: 3px; }
		.g6s-card__title { font-size: 14.5px; font-weight: 600; color: #111827; margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		.g6s-card__desc { font-size: 12px; color: #6b7280; margin: 0; }
		.g6s-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
		@media (max-width: 900px) { .g6s-field-row { grid-template-columns: 1fr; } }
		.g6s-field { display: flex; flex-direction: column; gap: 5px; }
		.g6s-field__label { display: block; font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px; color: #6b7280; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		.g6s-field__input { width: 100%; padding: 9px 12px; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 13px; color: #111827; background: #f9fafb; box-sizing: border-box; transition: border-color 0.15s ease, background 0.15s ease; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		.g6s-field__input:focus { outline: none; border-color: #6366f1; background: #fff; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }

		/* ── Widgets tab: sidebar + main layout ── */
		.g6w-layout { display: flex; align-items: flex-start; gap: 24px; padding: 16px 0 24px; }
		.g6w-nav {
			flex: 0 0 240px;
			position: sticky;
			top: 40px;
			display: flex;
			flex-direction: column;
			gap: 2px;
			background: #fff;
			border: 1.5px solid #e5e7eb;
			border-radius: 10px;
			padding: 8px;
		}
		.g6w-nav__item { display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 6px; cursor: pointer; transition: background 0.15s ease; }
		.g6w-nav__item:hover { background: #f3f4f6; }
		.g6w-nav__icon { display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; color: #6b7280; flex-shrink: 0; }
		.g6w-nav__label { flex: 1; min-width: 0; font-size: 12.5px; font-weight: 600; color: #111827; line-height: 1.3; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		.g6w-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 14px; }
		@media (max-width: 900px) {
			.g6w-layout { flex-direction: column; }
			.g6w-nav { position: static; width: 100%; flex-direction: row; flex-wrap: wrap; }
			.g6w-nav__item { flex: 1 1 auto; }
		}

		/* ── Widget cards (Widgets tab) ── */
		.g6w-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); scroll-margin-top: 40px; transition: opacity 0.15s ease; }
		.g6w-card--disabled { opacity: 0.5; }
		.g6w-card__header { display: flex; align-items: flex-start; gap: 12px; }
		.g6w-card__meta { display: flex; align-items: flex-start; gap: 12px; flex: 1; }
		.g6w-card__icon { width: 38px; height: 38px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #1E3A3F; }
		.g6w-card__title { font-size: 14px; font-weight: 600; color: #111827; margin: 0 0 3px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		.g6w-card__desc { font-size: 12px; color: #6b7280; margin: 0; line-height: 1.45; }
		.g6w-card__settings { padding-top: 16px; margin-top: 16px; border-top: 1px solid #e5e7eb; }

		/* ── Toggle switch ── */
		.g6w-toggle { position: relative; display: inline-flex; cursor: pointer; flex-shrink: 0; margin-top: 2px; }
		.g6w-nav__item .g6w-toggle { margin-top: 0; }
		.g6w-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
		.g6w-toggle__track { display: block; width: 40px; height: 22px; background: #d1d5db; border-radius: 11px; transition: background 0.2s ease; position: relative; }
		.g6w-toggle__track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; background: #fff; border-radius: 50%; transition: transform 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
		.g6w-toggle input:checked + .g6w-toggle__track { background: #FF6E61; }
		.g6w-toggle input:checked + .g6w-toggle__track::after { transform: translateX(18px); }

		/* ── Login tab ── */
		.g6l-layout-picker { display: flex; gap: 14px; flex-wrap: wrap; }
		.g6l-layout-card { cursor: pointer; }
		.g6l-layout-card input { position: absolute; opacity: 0; pointer-events: none; }
		.g6l-layout-card__inner {
			border: 2px solid #e5e7eb;
			border-radius: 10px;
			overflow: hidden;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
			width: 168px;
		}
		.g6l-layout-card:hover .g6l-layout-card__inner { border-color: #d1d5db; }
		.g6l-layout-card--selected .g6l-layout-card__inner,
		.g6l-layout-card:has(input:checked) .g6l-layout-card__inner {
			border-color: #FF6E61;
			box-shadow: 0 0 0 3px rgba(255,110,97,0.15);
		}
		.g6l-layout-card__screen { height: 100px; display: flex; }
		.g6l-layout-card__name { font-size: 12px; font-weight: 600; color: #374151; padding: 8px 12px; background: #f9fafb; border-top: 1px solid #e5e7eb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		.g6l-mock-left { flex: 0 0 50%; background: #1a1a1a; padding: 14px 12px; display: flex; flex-direction: column; gap: 5px; }
		.g6l-mock-logo { width: 36px; height: 6px; background: rgba(255,255,255,0.35); border-radius: 2px; margin-bottom: 5px; }
		.g6l-mock-field { height: 6px; background: rgba(255,255,255,0.15); border-radius: 2px; }
		.g6l-mock-field--short { width: 60%; }
		.g6l-mock-btn { height: 6px; background: #FF6E61; border-radius: 2px; margin-top: 3px; width: 55%; }
		.g6l-mock-right { flex: 0 0 50%; background: linear-gradient(160deg, #6b7280 0%, #9ca3af 100%); }
		.g6l-colors { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
		@media (max-width: 900px) { .g6l-colors { grid-template-columns: 1fr; } }
		.g6l-color-input {
			display: flex; align-items: center; gap: 8px;
			border: 1.5px solid #d1d5db; border-radius: 6px;
			background: #f9fafb; padding: 0 10px 0 6px; height: 38px;
			transition: border-color 0.15s ease;
		}
		.g6l-color-input:focus-within { border-color: #6366f1; background: #fff; box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
		.g6l-swatch { width: 22px; height: 22px; border: none; border-radius: 4px; cursor: pointer; padding: 0; background: none; flex-shrink: 0; }
		.g6l-hex { border: none; background: transparent; font-size: 13px; font-family: "SFMono-Regular", Consolas, monospace; color: #111827; outline: none; width: 100%; }
		.g6l-url-field { display: flex; gap: 8px; align-items: center; }
		.g6l-url-field .g6s-field__input { flex: 1; }
		.g6-gmb-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
		.g6-gmb-row .g6s-field__input { min-width: 0; }
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

			// This form has multiple submit buttons (Save Settings, plus "Refresh Data"
			// buttons for GMB/Airtable that appear earlier in the DOM). Pressing Enter
			// inside a text field implicitly submits via the FIRST submit button in the
			// form, not necessarily "Save Settings" — so block Enter-to-submit on plain
			// text inputs and require an explicit click on the intended button instead.
			document.getElementById('g6-settings-form').addEventListener('keydown', function(e) {
				if (e.key !== 'Enter') return;
				var el = e.target;
				if (el.tagName !== 'INPUT') return;
				var textLikeTypes = ['text', 'password', 'url', 'email', 'number', 'search', 'tel'];
				if (textLikeTypes.indexOf(el.type) !== -1) {
					e.preventDefault();
				}
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

	var g6DragHandleSvg = '<?php echo g6_icon( 'grip-vertical', 16 ); ?>';

	function g6AddGuide() {
		var row = document.createElement('div');
		row.className = 'g6-repeater-row g6-guide-row';
		row.style.cssText = 'display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;';
		row.innerHTML =
			'<span class="g6-drag-handle" title="Drag to reorder">' + g6DragHandleSvg + '</span>' +
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
	function g6BindHighlightCheckbox(cb) {
		if (!cb) return;
		var hidden = cb.closest('.g6-svc-row').querySelector('.g6-svc-highlight-hidden');
		cb.addEventListener('change', function() {
			hidden.value = this.checked ? '1' : '0';
		});
	}

	function g6AddService() {
		var row = document.createElement('div');
		row.className = 'g6-repeater-row g6-svc-row';
		row.style.cssText = 'display:flex; gap:8px; align-items:flex-start; margin-bottom:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:10px;';
		row.innerHTML =
			'<span class="g6-drag-handle" title="Drag to reorder">' + g6DragHandleSvg + '</span>' +
			'<div style="flex:1; display:grid; grid-template-columns:1fr 1fr; gap:6px;">' +
				'<input type="text" name="svc_name[]"      placeholder="Service name"   class="regular-text" style="width:100%;">' +
				g6BuildIconSelect('svc_icon[]') +
				'<input type="text" name="svc_desc[]"      placeholder="Short description" class="regular-text" style="width:100%; grid-column:1/-1;">' +
				'<input type="url"  name="svc_url[]"       placeholder="https://…" class="regular-text" style="width:100%;">' +
				'<input type="text" name="svc_cta_label[]" placeholder="Learn More"     class="regular-text" style="width:100%;">' +
				'<label style="display:flex; align-items:center; gap:6px; grid-column:1/-1;">' +
					'<input type="hidden" name="svc_highlight[]" class="g6-svc-highlight-hidden" value="0">' +
					'<input type="checkbox" class="g6-svc-highlight-checkbox"> Mark as Popular' +
				'</label>' +
			'</div>' +
			'<button type="button" onclick="g6RemoveService(this)" class="g6-remove-btn" title="Remove">&times;</button>';
		document.getElementById('g6-services-repeater').appendChild(row);
		g6BindHighlightCheckbox(row.querySelector('.g6-svc-highlight-checkbox'));
	}

	function g6RemoveService(btn) { btn.closest('.g6-svc-row').remove(); }

	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.g6-svc-highlight-checkbox').forEach(g6BindHighlightCheckbox);
	});

	// ── Drag-to-reorder for all repeaters (jQuery UI Sortable, bundled with WP) ──
	document.addEventListener('DOMContentLoaded', function() {
		if (window.jQuery && jQuery.fn.sortable) {
			['g6-guides-repeater', 'g6-services-repeater', 'g6-gmb-locations', 'g6-gmb-competitors'].forEach(function(id) {
				var el = document.getElementById(id);
				if (!el) return;
				jQuery(el).sortable({
					handle: '.g6-drag-handle',
					axis: 'y',
					tolerance: 'pointer',
					placeholder: 'g6-repeater-placeholder-row',
					forcePlaceholderSize: true
				});
			});
		}
	});

	// ── Tracking card live status ─────────────────────────────────────────────
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.g6t-card__input').forEach(function(input) {
			input.addEventListener('input', function() {
				var card   = this.closest('.g6t-card');
				var status = card.querySelector('.g6t-status');
				var active = this.value.trim() !== '';
				card.classList.toggle('g6t-card--active', active);
				status.className = 'g6t-status ' + (active ? 'g6t-status--active' : 'g6t-status--inactive');
				status.innerHTML = '<span class="g6t-status__dot"></span>' + (active ? 'Active' : 'Not configured');
			});
		});
	});

	// ── Widget toggles → settings visibility ──────────────────────────────────
	var g6WidgetSettingsKeys = ['guides', 'services', 'keywords', 'video', 'reviews', 'contact'];

	function g6SyncWidgetSettings(key, enabled) {
		var el   = document.getElementById('g6-widget-settings-' + key);
		var card = document.querySelector('.g6w-card[data-widget="' + key + '"]');
		if (el)   el.style.display = enabled ? '' : 'none';
		if (card) card.classList.toggle('g6w-card--disabled', !enabled);
	}

	document.addEventListener('DOMContentLoaded', function() {
		g6WidgetSettingsKeys.forEach(function(key) {
			var cb = document.querySelector('input[name="widget_' + key + '"]');
			if (!cb) return;
			cb.addEventListener('change', function() {
				g6SyncWidgetSettings(key, this.checked);
			});
		});

		// Widget nav: click a row (not the toggle) to scroll to its card.
		document.querySelectorAll('.g6w-nav__item').forEach(function(item) {
			item.addEventListener('click', function(e) {
				if (e.target.closest('.g6w-toggle')) return;
				var card = document.querySelector('.g6w-card[data-widget="' + this.dataset.scroll + '"]');
				if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
			});
		});
	});

	// ── GMB repeaters (locations + competitors share the same logic) ─────
	document.addEventListener('DOMContentLoaded', function() {
		function g6GmbAddRow(containerId, pidName, labelName) {
			var row = document.createElement('div');
			row.className = 'g6-repeater-row g6-gmb-row';
			row.innerHTML =
				'<span class="g6-drag-handle" title="Drag to reorder">' + g6DragHandleSvg + '</span>' +
				'<input class="g6s-field__input" type="text" name="' + pidName + '" value="" placeholder="Place ID" style="flex:2;">' +
				'<input class="g6s-field__input" type="text" name="' + labelName + '" value="" placeholder="Label (optional)" style="flex:1;">' +
				'<button type="button" class="g6-remove-btn g6-gmb-remove" title="Remove">&times;</button>';
			document.getElementById(containerId).appendChild(row);
		}

		document.querySelectorAll('.g6-gmb-add').forEach(function(btn) {
			btn.addEventListener('click', function() {
				g6GmbAddRow(this.dataset.target, this.dataset.pidName, this.dataset.labelName);
			});
		});

		['g6-gmb-locations', 'g6-gmb-competitors'].forEach(function(id) {
			var el = document.getElementById(id);
			if (el) el.addEventListener('click', function(e) {
				if (e.target.classList.contains('g6-gmb-remove')) e.target.closest('.g6-gmb-row').remove();
			});
		});
	});

	// ── Login tab: toggle, layout picker, color swatches, media picker ───
	document.addEventListener('DOMContentLoaded', function() {
		// Enable toggle → show/hide fields
		var loginToggle = document.getElementById('login_enabled');
		var loginFields = document.getElementById('g6-login-fields');
		if (loginToggle && loginFields) {
			loginToggle.addEventListener('change', function() {
				loginFields.style.display = this.checked ? '' : 'none';
			});
		}

		// Layout card visual selection
		document.querySelectorAll('.g6l-layout-card input[type="radio"]').forEach(function(radio) {
			function syncSelected() {
				document.querySelectorAll('.g6l-layout-card').forEach(function(card) {
					card.classList.remove('g6l-layout-card--selected');
				});
				radio.closest('.g6l-layout-card').classList.add('g6l-layout-card--selected');
			}
			if (radio.checked) syncSelected();
			radio.addEventListener('change', syncSelected);
		});

		// Color swatch ↔ text sync.
		// Only push text → swatch when the value is a valid hex (#rrggbb).
		// CSS variables (var(--x)) and other values are passed through to CSS as-is.
		document.querySelectorAll('.g6l-hex').forEach(function(hex) {
			var swatch = document.getElementById(hex.dataset.swatch);
			if (!swatch) return;
			hex.addEventListener('input', function() {
				if (/^#[0-9a-fA-F]{6}$/.test(this.value)) swatch.value = this.value;
			});
			swatch.addEventListener('input', function() { hex.value = this.value; });
		});

		// WordPress media library picker
		document.querySelectorAll('.g6l-media-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var targetId = this.dataset.target;
				var frame    = wp.media({
					title:    'Select Image',
					button:   { text: 'Use this image' },
					multiple: false,
					library:  { type: 'image' }
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					var url        = attachment.url;
					var input      = document.getElementById(targetId);
					if (input) { input.value = url; input.dispatchEvent(new Event('input')); }
				});
				frame.open();
			});
		});
	});
	</script>
	<?php
}
