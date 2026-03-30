<?php
/**
 * Dashboard widget registration, styles, and render.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Register & clean up dashboard widgets ─────────────────────────────────────

add_action( 'wp_dashboard_setup', 'g6_dashboard_setup', 1 );

function g6_dashboard_setup(): void {
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_primary',     'dashboard', 'side' );
	remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_right_now',   'dashboard', 'normal' );
	remove_meta_box( 'dashboard_activity',    'dashboard', 'normal' );

	add_meta_box(
		'g6_client_dashboard',
		'G6 Client Dashboard',
		'g6_render_dashboard',
		'dashboard',
		'normal',
		'high'
	);
}

// ── Styles ────────────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'g6_dashboard_styles' );

function g6_dashboard_styles( string $hook ): void {
	if ( 'index.php' !== $hook ) {
		return;
	}
	wp_add_inline_style( 'wp-admin', g6_get_dashboard_css() );
}

function g6_get_dashboard_css(): string {
	return '
	/* ── Reset the widget chrome ── */
	#g6_client_dashboard {
		border: none !important;
		box-shadow: none !important;
		background: transparent !important;
		margin: 0 !important;
		padding: 0 !important;
	}
	#g6_client_dashboard .postbox-header { display: none !important; }
	#g6_client_dashboard .inside { padding: 0 !important; margin: 0 !important; }
	#dashboard-widgets .postbox-container { width: 100% !important; }
	#dashboard-widgets-wrap #dashboard-widgets #postbox-container-1 { width: 100% !important; }

	/* ── Design tokens ── */
	:root {
		--g6-primary: #FF6E61;
		--g6-primary-light: #FFF0EE;
		--g6-primary-dark: #E8554E;
		--g6-secondary: #1E3A3F;
		--g6-secondary-light: #2A5058;
		--g6-accent-blue: #B6EAF2;
		--g6-accent-yellow: #F3DE58;
		--g6-accent-purple: #B7B6F2;
		--g6-neutral-50: #F9FAFB;
		--g6-neutral-100: #F3F4F6;
		--g6-neutral-200: #E5E7EB;
		--g6-neutral-300: #D1D5DB;
		--g6-neutral-500: #6B7280;
		--g6-neutral-900: #111827;
		--g6-success: #10B981;
		--g6-error: #EF4444;
		--g6-warning: #F59E0B;
		--g6-radius: 8px;
		--g6-radius-lg: 16px;
		--g6-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
		--g6-shadow-lg: 0 4px 12px rgba(0,0,0,0.1);
		--g6-font-heading: "Lexend", -apple-system, sans-serif;
		--g6-font-body: "Open Sans", -apple-system, sans-serif;
	}

	/* ── Dashboard shell ── */
	.g6-dashboard { font-family: var(--g6-font-body); color: var(--g6-neutral-900); max-width: 100%; }
	.g6-dashboard svg { flex-shrink: 0; }

	/* ── Header ── */
	.g6-dashboard__header {
		background: linear-gradient(135deg, var(--g6-secondary) 0%, var(--g6-secondary-light) 100%);
		border-radius: var(--g6-radius-lg);
		padding: 32px 40px;
		margin-bottom: 24px;
		display: flex; align-items: center; justify-content: space-between;
		gap: 24px; flex-wrap: wrap;
	}
	.g6-dashboard__header-left { display: flex; flex-direction: column; gap: 16px; flex: 1; min-width: 280px; }
	.g6-dashboard__logo-link { display: inline-flex; align-items: center; text-decoration: none; opacity: 0.9; transition: opacity 0.2s ease; }
	.g6-dashboard__logo-link:hover { opacity: 1; }
	.g6-dashboard__welcome { font-family: var(--g6-font-heading); font-size: 28px; font-weight: 600; color: #fff; margin: 0 0 6px; line-height: 1.2; }
	.g6-dashboard__subtitle { font-size: 15px; color: rgba(255,255,255,0.75); margin: 0; }
	.g6-dashboard__header-meta { display: flex; align-items: center; gap: 16px; flex-shrink: 0; }
	.g6-dashboard__rep-card {
		background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);
		border-radius: var(--g6-radius); padding: 14px 20px;
		display: flex; align-items: center; gap: 14px; color: #fff; backdrop-filter: blur(10px);
	}
	.g6-dashboard__rep-avatar {
		width: 44px; height: 44px; border-radius: 50%; background: var(--g6-primary);
		display: flex; align-items: center; justify-content: center;
		font-family: var(--g6-font-heading); font-size: 16px; font-weight: 600; color: #fff;
		flex-shrink: 0; overflow: hidden;
	}
	.g6-dashboard__rep-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
	.g6-dashboard__rep-name { font-family: var(--g6-font-heading); font-size: 14px; font-weight: 600; margin: 0 0 2px; }
	.g6-dashboard__rep-role { font-size: 12px; opacity: 0.7; margin: 0; }
	.g6-dashboard__rep-contact { font-size: 12px; opacity: 0.85; margin: 2px 0 0; }
	.g6-dashboard__rep-contact a { color: var(--g6-accent-blue); text-decoration: none; }
	.g6-dashboard__rep-contact a:hover { text-decoration: underline; }

	/* ── Section titles ── */
	.g6-dashboard__section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
	.g6-dashboard__section-title { font-family: var(--g6-font-heading); font-size: 18px; font-weight: 600; color: var(--g6-neutral-900); margin: 0; display: flex; align-items: center; gap: 8px; }
	.g6-dashboard__section-title svg { color: var(--g6-primary); }
	.g6-dashboard__section-badge { font-size: 11px; font-weight: 600; background: var(--g6-primary-light); color: var(--g6-primary); padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
	.g6-dashboard__updated { font-size: 12px; color: var(--g6-neutral-500); }

	/* ── Grid layout ── */
	.g6-dashboard__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
	.g6-dashboard__full-width { grid-column: 1 / -1; }
	@media (max-width: 860px) { .g6-dashboard__grid { grid-template-columns: 1fr; } }

	/* ── Card base ── */
	.g6-card { background: #fff; border: 1px solid var(--g6-neutral-200); border-radius: var(--g6-radius-lg); padding: 28px; box-shadow: var(--g6-shadow); transition: box-shadow 0.2s ease; }
	.g6-card:hover { box-shadow: var(--g6-shadow-lg); }

	/* ── Guides ── */
	.g6-guides { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
	@media (max-width: 1200px) { .g6-guides { grid-template-columns: repeat(2, 1fr); } }
	@media (max-width: 680px) { .g6-guides { grid-template-columns: 1fr; } }
	.g6-guide { display: flex; align-items: flex-start; gap: 14px; padding: 18px; background: var(--g6-neutral-50); border: 1px solid var(--g6-neutral-200); border-radius: var(--g6-radius); text-decoration: none; color: inherit; transition: all 0.2s ease; }
	.g6-guide:hover { border-color: var(--g6-primary); background: var(--g6-primary-light); transform: translateY(-1px); }
	.g6-guide:focus { outline: 2px solid var(--g6-primary); outline-offset: 2px; }
	.g6-guide__icon { width: 40px; height: 40px; background: #fff; border: 1px solid var(--g6-neutral-200); border-radius: var(--g6-radius); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--g6-secondary); }
	.g6-guide:hover .g6-guide__icon { border-color: var(--g6-primary); color: var(--g6-primary); }
	.g6-guide__title { font-family: var(--g6-font-heading); font-size: 14px; font-weight: 600; margin: 0 0 4px; color: var(--g6-neutral-900); }
	.g6-guide__desc { font-size: 13px; color: var(--g6-neutral-500); margin: 0; line-height: 1.45; }

	/* ── Keywords table ── */
	.g6-keywords-table { width: 100%; border-collapse: collapse; font-size: 14px; }
	.g6-keywords-table th { font-family: var(--g6-font-heading); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--g6-neutral-500); padding: 0 12px 12px; text-align: left; border-bottom: 2px solid var(--g6-neutral-200); }
	.g6-keywords-table th:last-child, .g6-keywords-table td:last-child { text-align: right; }
	.g6-keywords-table td { padding: 14px 12px; border-bottom: 1px solid var(--g6-neutral-100); vertical-align: middle; }
	.g6-keywords-table tr:last-child td { border-bottom: none; }
	.g6-keywords-table__term { font-weight: 600; color: var(--g6-neutral-900); }
	.g6-keywords-table__position { font-family: var(--g6-font-heading); font-size: 18px; font-weight: 700; min-width: 32px; display: inline-block; }
	.g6-keywords-table__position--top3 { color: var(--g6-success); }
	.g6-keywords-table__position--top10 { color: var(--g6-secondary); }
	.g6-keywords-table__position--top20 { color: var(--g6-warning); }
	.g6-keywords-table__position--below { color: var(--g6-neutral-500); }
	.g6-keywords-table__change { font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 3px; }
	.g6-keywords-table__change--up { color: var(--g6-success); }
	.g6-keywords-table__change--down { color: var(--g6-error); }
	.g6-keywords-table__change--flat { color: var(--g6-neutral-500); }
	.g6-keywords-table__volume { color: var(--g6-neutral-500); font-size: 13px; }

	/* ── Reviews ── */
	.g6-reviews__summary { display: flex; gap: 24px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--g6-neutral-100); }
	.g6-reviews__stat { text-align: center; }
	.g6-reviews__stat-value { font-family: var(--g6-font-heading); font-size: 32px; font-weight: 700; color: var(--g6-secondary); line-height: 1; }
	.g6-reviews__stat-label { font-size: 12px; color: var(--g6-neutral-500); margin-top: 4px; }
	.g6-reviews__stars { color: var(--g6-accent-yellow); font-size: 14px; letter-spacing: 2px; }
	.g6-review { padding: 14px 0; border-bottom: 1px solid var(--g6-neutral-100); }
	.g6-review:last-child { border-bottom: none; padding-bottom: 0; }
	.g6-review__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
	.g6-review__author { font-weight: 600; font-size: 14px; }
	.g6-review__meta { font-size: 12px; color: var(--g6-neutral-500); }
	.g6-review__text { font-size: 13px; color: var(--g6-neutral-500); line-height: 1.5; margin: 0; }
	.g6-review__stars { color: var(--g6-accent-yellow); font-size: 12px; letter-spacing: 1px; margin-right: 6px; }

	/* ── Services ── */
	.g6-services { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
	@media (max-width: 680px) { .g6-services { grid-template-columns: 1fr; } }
	.g6-service { padding: 22px; background: var(--g6-neutral-50); border: 1px solid var(--g6-neutral-200); border-radius: var(--g6-radius); transition: all 0.2s ease; position: relative; }
	.g6-service--highlight { border-color: var(--g6-primary); background: var(--g6-primary-light); }
	.g6-service--highlight::after { content: "Popular"; position: absolute; top: 10px; right: 10px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background: var(--g6-primary); color: #fff; padding: 2px 8px; border-radius: 20px; }
	.g6-service__icon { width: 40px; height: 40px; background: #fff; border: 1px solid var(--g6-neutral-200); border-radius: var(--g6-radius); display: flex; align-items: center; justify-content: center; color: var(--g6-primary); margin-bottom: 12px; }
	.g6-service__name { font-family: var(--g6-font-heading); font-size: 15px; font-weight: 600; margin: 0 0 6px; }
	.g6-service__desc { font-size: 13px; color: var(--g6-neutral-500); margin: 0 0 14px; line-height: 1.5; }
	.g6-service__cta { display: inline-flex; align-items: center; gap: 6px; font-family: var(--g6-font-heading); font-size: 13px; font-weight: 600; color: var(--g6-primary); text-decoration: none; transition: gap 0.2s ease; }
	.g6-service__cta:hover { gap: 10px; color: var(--g6-primary-dark); }

	/* ── Contact form ── */
	.g6-contact-form { display: flex; flex-direction: column; gap: 14px; }
	.g6-contact-form__field { display: flex; flex-direction: column; gap: 5px; }
	.g6-contact-form__label { font-family: var(--g6-font-heading); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--g6-neutral-500); }
	.g6-contact-form__input,
	.g6-contact-form__select,
	.g6-contact-form__textarea { padding: 10px 14px; border: 1px solid var(--g6-neutral-300); border-radius: var(--g6-radius); font-family: var(--g6-font-body); font-size: 14px; color: var(--g6-neutral-900); background: #fff; transition: border-color 0.2s ease; width: 100%; box-sizing: border-box; }
	.g6-contact-form__input:focus,
	.g6-contact-form__select:focus,
	.g6-contact-form__textarea:focus { outline: none; border-color: var(--g6-primary); box-shadow: 0 0 0 3px rgba(255,110,97,0.12); }
	.g6-contact-form__textarea { min-height: 100px; resize: vertical; }
	.g6-contact-form__submit { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 28px; background: var(--g6-primary); color: #fff; border: none; border-radius: var(--g6-radius); font-family: var(--g6-font-heading); font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s ease; align-self: flex-start; }
	.g6-contact-form__submit:hover { background: var(--g6-primary-dark); }
	.g6-contact-form__submit:disabled { opacity: 0.7; cursor: not-allowed; }
	.g6-contact-form__success { display: none; padding: 14px 18px; background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: var(--g6-radius); color: #065F46; font-size: 14px; font-weight: 500; }
	.g6-contact-form__error { display: none; padding: 14px 18px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: var(--g6-radius); color: #991B1B; font-size: 14px; font-weight: 500; }

	/* ── Card CTA footer ── */
	.g6-card__cta-footer { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--g6-neutral-100); }
	.g6-card__cta-text { font-size: 13px; color: var(--g6-neutral-500); margin: 0 0 4px; }
	.g6-card__cta-link { font-family: var(--g6-font-heading); font-size: 13px; font-weight: 600; color: var(--g6-primary); text-decoration: none; }
	.g6-card__cta-link:hover { color: var(--g6-primary-dark); }

	/* ── Footer ── */
	.g6-dashboard__footer { text-align: center; padding: 24px 0 8px; font-size: 12px; color: var(--g6-neutral-500); }
	.g6-dashboard__footer a { color: var(--g6-primary); text-decoration: none; font-weight: 600; }
	.g6-dashboard__footer a:hover { text-decoration: underline; }
	.g6-dashboard__footer-logo { display: inline-flex; margin-bottom: 6px; opacity: 0.5; }
	';
}

// ── Render ────────────────────────────────────────────────────────────────────

function g6_render_dashboard(): void {
	$cfg   = g6_get_client_config();
	$user  = wp_get_current_user();
	$first = $user->first_name ?: $user->display_name;
	?>
	<div class="g6-dashboard">

		<!-- Header -->
		<div class="g6-dashboard__header">
			<div class="g6-dashboard__header-left">
				<a href="https://group6inc.com/" target="_blank" rel="noopener" class="g6-dashboard__logo-link" title="Visit Group6">
					<?php echo g6_logo_white( 100 ); ?>
				</a>
				<div>
					<h1 class="g6-dashboard__welcome">Welcome back, <?php echo esc_html( $first ); ?></h1>
					<p class="g6-dashboard__subtitle">Your <?php echo esc_html( $cfg['client_name'] ); ?> marketing dashboard</p>
				</div>
			</div>
			<div class="g6-dashboard__header-meta">
				<div class="g6-dashboard__rep-card">
					<div class="g6-dashboard__rep-avatar">
						<?php if ( ! empty( $cfg['agency_rep_photo'] ) ) : ?>
							<img src="<?php echo esc_url( $cfg['agency_rep_photo'] ); ?>" alt="<?php echo esc_attr( $cfg['agency_rep_name'] ); ?>">
						<?php else : ?>
							<?php echo esc_html( strtoupper( substr( $cfg['agency_rep_name'], 0, 1 ) ) ); ?>
						<?php endif; ?>
					</div>
					<div>
						<p class="g6-dashboard__rep-name"><?php echo esc_html( $cfg['agency_rep_name'] ); ?></p>
						<p class="g6-dashboard__rep-role">Your Account Manager</p>
						<p class="g6-dashboard__rep-contact">
							<a href="mailto:<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>"><?php echo esc_html( $cfg['agency_rep_email'] ); ?></a>
							&middot; <?php echo esc_html( $cfg['agency_rep_phone'] ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<!-- How-to Guides -->
		<div class="g6-card g6-dashboard__full-width" style="margin-bottom:24px;">
			<div class="g6-dashboard__section-header">
				<h2 class="g6-dashboard__section-title">
					<?php echo g6_icon( 'book-open', 20 ); ?>
					How-To Guides &amp; Resources
				</h2>
			</div>
			<div class="g6-guides">
				<?php foreach ( $cfg['guides'] as $guide ) : ?>
					<a href="<?php echo esc_url( $guide['url'] ); ?>" class="g6-guide">
						<div class="g6-guide__icon"><?php echo g6_icon( $guide['icon'] ); ?></div>
						<div>
							<p class="g6-guide__title"><?php echo esc_html( $guide['title'] ); ?></p>
							<p class="g6-guide__desc"><?php echo esc_html( $guide['description'] ); ?></p>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Keywords + Reviews -->
		<div class="g6-dashboard__grid">

			<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'search', 20 ); ?>
						Keyword Rankings
					</h2>
					<span class="g6-dashboard__updated">
						Updated <?php echo esc_html( date( 'M j, Y', strtotime( $cfg['last_updated'] ) ) ); ?>
					</span>
				</div>
				<table class="g6-keywords-table">
					<thead>
						<tr>
							<th>Keyword</th>
							<th>Position</th>
							<th>Change</th>
							<th>Volume</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cfg['keywords'] as $kw ) :
							if ( $kw['position'] <= 3 )       $pos_class = 'g6-keywords-table__position--top3';
							elseif ( $kw['position'] <= 10 )  $pos_class = 'g6-keywords-table__position--top10';
							elseif ( $kw['position'] <= 20 )  $pos_class = 'g6-keywords-table__position--top20';
							else                              $pos_class = 'g6-keywords-table__position--below';

							if ( $kw['change'] > 0 )      { $change_class = 'g6-keywords-table__change--up';   $change_text = '↑ ' . $kw['change']; }
							elseif ( $kw['change'] < 0 )  { $change_class = 'g6-keywords-table__change--down'; $change_text = '↓ ' . abs( $kw['change'] ); }
							else                          { $change_class = 'g6-keywords-table__change--flat'; $change_text = '—'; }
						?>
						<tr>
							<td class="g6-keywords-table__term"><?php echo esc_html( $kw['term'] ); ?></td>
							<td><span class="g6-keywords-table__position <?php echo esc_attr( $pos_class ); ?>"><?php echo (int) $kw['position']; ?></span></td>
							<td><span class="g6-keywords-table__change <?php echo esc_attr( $change_class ); ?>"><?php echo esc_html( $change_text ); ?></span></td>
							<td class="g6-keywords-table__volume"><?php echo number_format( $kw['volume'] ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="g6-card__cta-footer">
					<p class="g6-card__cta-text">Want to improve these rankings?</p>
					<a href="mailto:<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>?subject=SEO%20Inquiry%20-%20<?php echo rawurlencode( $cfg['client_name'] ); ?>" class="g6-card__cta-link">
						Talk to us about Local SEO &rarr;
					</a>
				</div>
			</div>

			<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'star', 20 ); ?>
						Reputation Snapshot
					</h2>
				</div>
				<div class="g6-reviews__summary">
					<div class="g6-reviews__stat">
						<div class="g6-reviews__stat-value"><?php echo esc_html( $cfg['reviews']['google_rating'] ); ?></div>
						<div class="g6-reviews__stars"><?php echo str_repeat( '★', (int) round( $cfg['reviews']['google_rating'] ) ); ?></div>
						<div class="g6-reviews__stat-label">Google Rating</div>
					</div>
					<div class="g6-reviews__stat">
						<div class="g6-reviews__stat-value"><?php echo (int) $cfg['reviews']['google_count']; ?></div>
						<div class="g6-reviews__stat-label">Google Reviews</div>
					</div>
					<div class="g6-reviews__stat">
						<div class="g6-reviews__stat-value"><?php echo (int) $cfg['reviews']['total_reviews']; ?></div>
						<div class="g6-reviews__stat-label">Total Reviews</div>
					</div>
				</div>
				<?php foreach ( $cfg['reviews']['recent'] as $review ) : ?>
					<div class="g6-review">
						<div class="g6-review__header">
							<span>
								<span class="g6-review__stars"><?php echo str_repeat( '★', (int) $review['rating'] ); ?></span>
								<span class="g6-review__author"><?php echo esc_html( $review['author'] ); ?></span>
							</span>
							<span class="g6-review__meta"><?php echo esc_html( $review['source'] ); ?> &middot; <?php echo esc_html( $review['date'] ); ?></span>
						</div>
						<p class="g6-review__text"><?php echo esc_html( $review['text'] ); ?></p>
					</div>
				<?php endforeach; ?>
				<div class="g6-card__cta-footer">
					<p class="g6-card__cta-text">Want more reviews and better reputation management?</p>
					<a href="mailto:<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>?subject=Reputation%20Management%20-%20<?php echo rawurlencode( $cfg['client_name'] ); ?>" class="g6-card__cta-link">
						Ask about Reputation Management &rarr;
					</a>
				</div>
			</div>

		</div>

		<!-- Services + Contact -->
		<div class="g6-dashboard__grid">

			<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'zap', 20 ); ?>
						Grow Your Business
					</h2>
					<span class="g6-dashboard__section-badge">Add-On Services</span>
				</div>
				<div class="g6-services">
					<?php foreach ( $cfg['services'] as $svc ) : ?>
						<div class="g6-service<?php echo $svc['highlight'] ? ' g6-service--highlight' : ''; ?>">
							<div class="g6-service__icon"><?php echo g6_icon( $svc['icon'] ); ?></div>
							<h3 class="g6-service__name"><?php echo esc_html( $svc['name'] ); ?></h3>
							<p class="g6-service__desc"><?php echo esc_html( $svc['description'] ); ?></p>
							<a href="<?php echo esc_url( $svc['cta_url'] ); ?>" class="g6-service__cta">
								<?php echo esc_html( $svc['cta_label'] ); ?> &rarr;
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="g6-card">
				<div class="g6-dashboard__section-header">
					<h2 class="g6-dashboard__section-title">
						<?php echo g6_icon( 'message-circle', 20 ); ?>
						Get in Touch
					</h2>
				</div>
				<p style="font-size:14px; color:var(--g6-neutral-500); margin:0 0 18px; line-height:1.5;">
					Have a question or need help? Submit a request and your account manager will follow up.
				</p>
				<div class="g6-contact-form" id="g6-contact-form">
					<div class="g6-contact-form__field">
						<label class="g6-contact-form__label" for="g6-subject">Subject</label>
						<select class="g6-contact-form__select" id="g6-subject" name="subject">
							<option value="">Choose a topic&hellip;</option>
							<option value="Website Update Request">Website Update Request</option>
							<option value="SEO Question">SEO Question</option>
							<option value="New Service Inquiry">New Service Inquiry</option>
							<option value="Bug Report">Bug Report</option>
							<option value="General Question">General Question</option>
						</select>
					</div>
					<div class="g6-contact-form__field">
						<label class="g6-contact-form__label" for="g6-message">Message</label>
						<textarea class="g6-contact-form__textarea" id="g6-message" name="message" placeholder="Tell us what you need&hellip;"></textarea>
					</div>
					<button type="button" class="g6-contact-form__submit" id="g6-submit-btn" onclick="g6SubmitContact()">
						<?php echo g6_icon( 'send', 16 ); ?>
						Send Message
					</button>
					<div class="g6-contact-form__success" id="g6-contact-success">
						<?php echo g6_icon( 'check-circle', 16 ); ?>
						&nbsp; Request submitted! Your account manager will follow up shortly.
					</div>
					<div class="g6-contact-form__error" id="g6-contact-error"></div>
				</div>
			</div>

		</div>

		<!-- Footer -->
		<div class="g6-dashboard__footer">
			<div class="g6-dashboard__footer-logo">
				<a href="https://group6inc.com/" target="_blank" rel="noopener">
					<?php echo g6_logo_white( 70 ); ?>
				</a>
			</div>
			<p>
				Your website &amp; marketing partner &middot;
				<a href="mailto:<?php echo esc_attr( $cfg['agency_rep_email'] ); ?>">Contact Us</a>
				&middot;
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $cfg['agency_rep_phone'] ) ); ?>"><?php echo esc_html( $cfg['agency_rep_phone'] ); ?></a>
			</p>
			<p style="margin-top:6px; font-size:11px; opacity:0.6;">
				Dashboard v<?php echo esc_html( G6_DASHBOARD_VERSION ); ?>
			</p>
		</div>

	</div>

	<script>
	function g6SubmitContact() {
		var subject   = document.getElementById('g6-subject').value;
		var message   = document.getElementById('g6-message').value;
		var errorEl   = document.getElementById('g6-contact-error');
		var successEl = document.getElementById('g6-contact-success');

		errorEl.style.display   = 'none';
		successEl.style.display = 'none';

		if ( ! subject || ! message ) {
			errorEl.textContent    = 'Please select a subject and enter a message.';
			errorEl.style.display  = 'block';
			return;
		}

		var btn = document.getElementById('g6-submit-btn');
		btn.disabled    = true;
		btn.textContent = 'Sending\u2026';

		var data = new FormData();
		data.append('action',    'g6_contact_submit');
		data.append('subject',   subject);
		data.append('message',   message);
		data.append('_wpnonce',  '<?php echo wp_create_nonce( 'g6_contact_nonce' ); ?>');

		fetch(ajaxurl, { method: 'POST', body: data })
			.then(function(r) { return r.json(); })
			.then(function(result) {
				if ( result.success ) {
					successEl.style.display = 'flex';
					document.getElementById('g6-subject').value = '';
					document.getElementById('g6-message').value = '';
					btn.textContent = 'Sent \u2713';
					setTimeout(function() {
						successEl.style.display = 'none';
						btn.disabled = false;
						btn.innerHTML = '<?php echo g6_icon( 'send', 16 ); ?> Send Message';
					}, 5000);
				} else {
					var msg = (result.data && result.data.message) ? result.data.message : 'Something went wrong. Please email us directly.';
					errorEl.textContent   = msg;
					errorEl.style.display = 'block';
					btn.disabled          = false;
					btn.innerHTML         = '<?php echo g6_icon( 'send', 16 ); ?> Send Message';
				}
			})
			.catch(function() {
				errorEl.textContent   = 'Network error. Please email us directly.';
				errorEl.style.display = 'block';
				btn.disabled          = false;
				btn.innerHTML         = '<?php echo g6_icon( 'send', 16 ); ?> Send Message';
			});
	}
	</script>
	<?php
}
