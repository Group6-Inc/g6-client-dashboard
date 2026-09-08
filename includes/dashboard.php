<?php
/**
 * Dashboard widget registration, styles, and render.
 *
 * @package G6\Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Video embed helper ────────────────────────────────────────────────────────

function g6_get_video_embed_url( string $url ): string {
	if ( preg_match( '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
		return 'https://www.youtube.com/embed/' . $m[1];
	}
	if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m ) ) {
		return 'https://player.vimeo.com/video/' . $m[1];
	}
	return '';
}

// ── Register & clean up dashboard widgets ─────────────────────────────────────

add_action( 'wp_dashboard_setup', 'g6_dashboard_setup', 1 );

function g6_dashboard_setup(): void {
	remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
	remove_meta_box( 'dashboard_primary',     'dashboard', 'side' );
	remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
	remove_meta_box( 'dashboard_right_now',   'dashboard', 'normal' );
	remove_meta_box( 'dashboard_activity',    'dashboard', 'normal' );

	// Remove default welcome panel content here, after WP has registered it.
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}

// ── Hijack the welcome panel slot (admins) ───────────────────────────────────

add_action( 'welcome_panel', 'g6_render_dashboard' );

// Force the welcome panel to always be visible (never let it be dismissed).
add_action( 'current_screen', function( \WP_Screen $screen ): void {
	if ( 'dashboard' === $screen->id ) {
		update_user_meta( get_current_user_id(), 'show_welcome_panel', 1 );
	}
} );

// ── Editors: render above widget columns via admin_notices ───────────────────
// Welcome panel is gated to manage_options; editors use this fallback instead.

add_action( 'admin_notices', function(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	g6_render_dashboard();
} );

// ── Styles ────────────────────────────────────────────────────────────────────

add_action( 'admin_enqueue_scripts', 'g6_dashboard_styles' );

function g6_dashboard_styles( string $hook ): void {
	if ( 'index.php' !== $hook ) {
		return;
	}
	wp_add_inline_style( 'wp-admin', g6_get_dashboard_css() );
}

/**
 * The dashboard CSS.
 *
 * Returned as a SINGLE-QUOTED string, so an apostrophe anywhere in here
 * — including in a comment — ends the string and breaks the file. Write
 * "the header" rather than "the header's". This has bitten once.
 */
function g6_get_dashboard_css(): string {
	return '
	/* ── Reset welcome panel chrome (admins) ── */
	#welcome-panel { background: transparent !important; border: none !important; padding: 0 !important; box-shadow: none !important; }
	#welcome-panel .welcome-panel-close { display: none !important; }
	#welcome-panel .welcome-panel-content { padding: 0 !important; }

	/* ── Spacing for editors (admin_notices context) ── */
	#wpbody-content > .g6-dashboard { margin: 50px 20px 0 2px; }

	/* ── Neutralise WordPress welcome-panel typography overrides ── */
	#welcome-panel h2 { font-size: inherit !important; font-weight: inherit !important; line-height: inherit !important; margin: inherit !important; }
	#welcome-panel h3 { font-size: inherit !important; font-weight: inherit !important; line-height: inherit !important; margin: inherit !important; }
	#welcome-panel p  { font-size: inherit !important; line-height: inherit !important; margin: inherit !important; }

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
	.g6-dashboard__header-left { display: flex; flex-direction: column; gap: 16px; flex: 1; min-width: 0; }
	.g6-dashboard__logo-link { display: inline-flex; align-items: center; text-decoration: none; opacity: 0.9; transition: opacity 0.2s ease; }
	.g6-dashboard__logo-link:hover { opacity: 1; }
	.g6-dashboard__welcome { font-family: var(--g6-font-heading); font-size: 28px; font-weight: 600; color: #fff; margin: 0 0 6px; line-height: 1.2; }
	.g6-dashboard__subtitle { font-size: 15px; color: rgba(255,255,255,0.75); margin: 0; }
	.g6-dashboard__header-meta { display: flex; align-items: center; gap: 16px; flex-shrink: 1; min-width: 0; }
	.g6-dashboard__rep-card {
		background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);
		border-radius: var(--g6-radius); padding: 14px 20px;
		display: flex; align-items: center; gap: 14px; color: #fff; backdrop-filter: blur(10px);
		min-width: 0; overflow: hidden;
	}
	.g6-dashboard__rep-card > div:last-child { min-width: 0; overflow: hidden; }
	.g6-dashboard__rep-avatar {
		width: 44px; height: 44px; border-radius: 50%; background: var(--g6-primary);
		display: flex; align-items: center; justify-content: center;
		font-family: var(--g6-font-heading); font-size: 16px; font-weight: 600; color: #fff;
		flex-shrink: 0; overflow: hidden;
	}
	.g6-dashboard__rep-avatar img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
	.g6-dashboard__rep-name { font-family: var(--g6-font-heading); font-size: 14px; font-weight: 600; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.g6-dashboard__rep-role { font-size: 12px; opacity: 0.7; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.g6-dashboard__rep-contact { font-size: 12px; opacity: 0.85; margin: 2px 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.g6-dashboard__rep-contact a { color: var(--g6-accent-blue); text-decoration: none; }
	.g6-dashboard__rep-contact a:hover { text-decoration: underline; }

	/* ── Section titles ── */
	.g6-dashboard__section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
	/* Project status. Rows rather than a grid: one project is the normal
	   case and two is the most anybody has, so a grid would leave a hole. */
	/* Spacing between projects, never above the first one. The obvious
	   :first-of-type does not work here: the section header is a div
	   too, so it IS the first div and the first project never matched —
	   which left 18px of padding stacked under the 16px margin below the
	   header, and a gap that looked like a mistake, because it was. */
	.g6-project { padding-bottom: 18px; border-bottom: 1px solid var(--g6-neutral-200); }
	.g6-project + .g6-project { padding-top: 18px; }
	.g6-project:last-child { border-bottom: 0; padding-bottom: 0; }
	.g6-project__name { font-family: var(--g6-font-heading); font-size: 16px; font-weight: 600; color: var(--g6-neutral-900); margin: 0 0 3px; line-height: 1.3; }
	.g6-project__stage { font-size: 13px; color: var(--g6-neutral-500); margin: 0; line-height: 1.45; }
	.g6-project__steps { display: flex; align-items: center; gap: 9px; margin-top: 14px; flex-wrap: wrap; }
	.g6-project__dots { display: flex; gap: 3px; }
	/* Segments rather than dots: they read as a bar at a glance and stay
	   legible at seven or eight steps, where circles start to look like
	   a colon. */
	.g6-project__dot { width: 16px; height: 4px; border-radius: 2px; background: var(--g6-neutral-200); }
	.g6-project__dot.is-done { background: var(--g6-primary); }
	.g6-project__count { font-size: 12.5px; color: var(--g6-neutral-500); }
	/* A quote, not a slab. The filled panel was the loudest thing in the
	   card and the card is now one column wide, where a block of tint
	   fills most of it. */
	.g6-project__next { margin-top: 16px; padding: 12px 0 12px 12px; border-left: 2px solid var(--g6-primary); }
	.g6-project__next-label { font-size: 10px; letter-spacing: 0.09em; text-transform: uppercase; color: var(--g6-primary-dark); margin: 0 0 5px; line-height: 1; font-weight: 600; }
	.g6-project__next-text { font-size: 13.5px; line-height: 1.5; color: var(--g6-neutral-900); margin: 0; }
	/* The age is the honesty of the card — see the note where it is rendered. */
	/* Its own line, well clear of whatever came before it. It is a
	   footnote about the card, not part of the last thing in it. */
	.g6-project__updated { font-size: 11.5px; color: #9CA3AF; margin: 20px 0 0; padding-top: 12px; border-top: 1px solid var(--g6-neutral-200); }

	/* The steps themselves. A fraction says how far along; this says
	   what is actually happening, which is what the portal shows and
	   what a client asks about. */
	.g6-project__list { list-style: none; margin: 14px 0 0; padding: 0; }
	.g6-project__step { display: flex; align-items: center; gap: 9px; padding: 7px 0; border-bottom: 1px solid #F3F4F6; font-size: 13.5px; }
	.g6-project__step:last-child { border-bottom: 0; }
	.g6-project__tick { flex: 0 0 15px; height: 15px; border-radius: 4px; border: 1px solid #D1D5DB; font-size: 9px; line-height: 15px; text-align: center; color: #fff; }
	.g6-project__step.is-done .g6-project__tick { background: var(--g6-neutral-900); border-color: var(--g6-neutral-900); }
	.g6-project__step.is-current .g6-project__tick { border-color: var(--g6-primary); }
	.g6-project__step-name { flex: 1 1 auto; color: var(--g6-neutral-900); }
	.g6-project__step.is-done .g6-project__step-name { color: var(--g6-neutral-500); text-decoration: line-through; }
	.g6-project__step.is-current .g6-project__step-name { font-weight: 600; }
	.g6-project__step-when { flex: 0 0 auto; font-size: 11.5px; color: #9CA3AF; }
	.g6-project__step.is-current .g6-project__step-when { color: var(--g6-primary); text-transform: uppercase; letter-spacing: .06em; font-size: 10px; }

	/* Pager. Only rendered with more than one project, so it never sits
	   there greyed out on the common case of exactly one. */
	.g6-project__pager { display: flex; align-items: center; gap: 6px; }
	.g6-project__pos { font-size: 11.5px; color: var(--g6-neutral-500); min-width: 34px; text-align: center; }
	.g6-project__arrow { border: 1px solid var(--g6-neutral-200); background: #fff; border-radius: 6px; width: 26px; height: 26px; line-height: 1; font-size: 15px; color: var(--g6-neutral-500); cursor: pointer; padding: 0; }
	.g6-project__arrow:hover { border-color: #D1D5DB; color: var(--g6-neutral-900); }

	.g6-dashboard__section-title { font-family: var(--g6-font-heading); font-size: 18px; font-weight: 600; color: var(--g6-neutral-900); margin: 0; display: flex; align-items: center; gap: 8px; }
	.g6-dashboard__section-title svg { color: var(--g6-primary); }
	.g6-dashboard__section-badge { font-size: 11px; font-weight: 600; background: var(--g6-primary-light); color: var(--g6-primary); padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
	.g6-dashboard__updated { font-size: 12px; color: var(--g6-neutral-500); }

	/* ── Grid layout ── */
	.g6-dashboard__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(500px, 100%), 1fr)); gap: 24px; margin-bottom: 24px; }
	.g6-card--full { grid-column: 1 / -1; }

	/* ── Card base ── */
	.g6-card { background: #fff; border: 1px solid var(--g6-neutral-200); border-radius: var(--g6-radius-lg); padding: 28px; box-shadow: var(--g6-shadow); transition: box-shadow 0.2s ease; }
	.g6-card:hover { box-shadow: var(--g6-shadow-lg); }
	@media (max-width: 600px) {
		.g6-dashboard__header { padding-left: 24px; padding-right: 24px; }
		.g6-card { padding-left: 24px; padding-right: 24px; }
	}

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
	.g6-reviews__location-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--g6-neutral-500); margin: 20px 0 10px; padding-top: 16px; border-top: 1px solid var(--g6-neutral-200); }
	.g6-dashboard__section-header + .g6-reviews__location-label { margin-top: 0; padding-top: 0; border-top: none; }
	.g6-reviews__summary { display: flex; gap: 24px; margin-bottom: 0; padding-bottom: 0; }
	.g6-reviews__stat { text-align: center; }
	.g6-reviews__stat-value { font-family: var(--g6-font-heading); font-size: 32px; font-weight: 700; color: var(--g6-secondary); line-height: 1; }
	.g6-reviews__stat-label { font-size: 12px; color: var(--g6-neutral-500); margin-top: 4px; }
	.g6-reviews__stars { color: var(--g6-accent-yellow); font-size: 14px; letter-spacing: 2px; }
	.g6-reviews__compare-box { background: var(--g6-neutral-50); border: 1px solid var(--g6-neutral-200); border-radius: var(--g6-radius); padding: 14px 16px; margin: 20px 0; }
	.g6-reviews__comparison-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--g6-neutral-500); margin: 0 0 10px; }
	.g6-reviews__comp-row { display: grid; grid-template-columns: 1fr auto auto auto; gap: 8px 12px; align-items: center; padding: 7px 0; border-bottom: 1px solid var(--g6-neutral-200); }
	.g6-reviews__comp-row:last-child { border-bottom: none; padding-bottom: 0; }
	.g6-reviews__comp-row--client .g6-reviews__comp-name { font-weight: 600; }
	.g6-reviews__comp-name { font-size: 13px; color: var(--g6-neutral-800); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; }
	a.g6-reviews__comp-name:hover { text-decoration: underline; color: var(--g6-secondary); }
	.g6-reviews__comp-rating { font-size: 13px; color: var(--g6-neutral-700); white-space: nowrap; }
	.g6-reviews__comp-count { font-size: 13px; color: var(--g6-neutral-500); white-space: nowrap; }
	.g6-reviews__comp-gap { font-size: 12px; font-weight: 600; white-space: nowrap; min-width: 70px; text-align: right; }
	.g6-reviews__comp-gap--behind { color: #d63638; }
	.g6-reviews__comp-gap--ahead  { color: #00a32a; }
	.g6-reviews__recent-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--g6-neutral-500); margin: 20px 0 4px; padding-top: 16px; border-top: 1px solid var(--g6-neutral-100); }
	.g6-reviews__compare-box + .g6-reviews__recent-title { border-top: none; padding-top: 0; margin-top: 4px; }
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
	.g6-dashboard__footer-logo { display: inline-flex; margin-bottom: 6px; }
	.g6-dashboard__footer-logo path:not([fill="#FF6E61"]) { fill: #1D1D1B; }

	/* ── Body layout (sidebar + main) ── */
	.g6-dashboard__body { display: grid; grid-template-columns: 268px 1fr; gap: 24px; align-items: start; margin-bottom: 24px; }
	.g6-dashboard__body--no-sidebar { grid-template-columns: 1fr; }
	.g6-dashboard__main .g6-dashboard__grid { margin-bottom: 0; }
	@media (max-width: 1100px) {
		.g6-dashboard__body { grid-template-columns: 1fr; }
		.g6-dashboard__sidebar { order: 2; }
		.g6-dashboard__main   { order: 1; }
	}

	/* ── Sidebar shell ── */
	.g6-sidebar {
		background: var(--g6-secondary);
		border-radius: var(--g6-radius-lg);
		padding: 22px;
	}
	.g6-sidebar__section {
		padding-bottom: 24px;
		margin-bottom: 24px;
		border-bottom: 1px solid rgba(255,255,255,0.07);
	}
	.g6-sidebar__section:last-child { padding-bottom: 0; margin-bottom: 0; border-bottom: none; }
	.g6-sidebar__section-title {
		font-family: var(--g6-font-heading);
		font-size: 9.5px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 1.3px;
		color: rgba(255,255,255,0.5);
	}
	#welcome-panel .g6-sidebar__section-title { margin: 0 0 14px !important; }

	/* ── Tracking pills ── */
	.g6-sidebar__tags { display: flex; flex-direction: column; gap: 7px; }
	.g6-sidebar__tag {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 8px 10px;
		background: rgba(255,255,255,0.05);
		border: 1px solid rgba(255,255,255,0.07);
		border-radius: 8px;
		transition: background 0.15s ease;
	}
	.g6-sidebar__tag:hover { background: rgba(255,255,255,0.08); }
	.g6-sidebar__tag-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 34px;
		height: 20px;
		padding: 0 6px;
		border-radius: 4px;
		font-size: 9px;
		font-weight: 700;
		letter-spacing: 0.5px;
		flex-shrink: 0;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}
	.g6-sidebar__tag-label {
		font-size: 12px;
		color: rgba(255,255,255,0.75);
		flex: 1;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.g6-sidebar__tag-dot {
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: #10b981;
		box-shadow: 0 0 0 2px rgba(16,185,129,0.22);
		flex-shrink: 0;
	}
	/* ── Tracking ID tooltip ── */
	.g6-sidebar__tag[data-g6-tip] { position: relative; }
	.g6-sidebar__tag[data-g6-tip]::before {
		content: attr(data-g6-tip);
		position: absolute;
		bottom: calc(100% + 7px);
		left: 50%;
		transform: translateX(-50%);
		background: #0d1117;
		color: #c9d1d9;
		font-size: 11px;
		font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
		padding: 5px 10px;
		border-radius: 5px;
		white-space: nowrap;
		pointer-events: none;
		opacity: 0;
		transition: opacity 0.15s ease;
		z-index: 200;
		border: 1px solid rgba(255,255,255,0.1);
		box-shadow: 0 4px 12px rgba(0,0,0,0.4);
	}
	.g6-sidebar__tag[data-g6-tip]:hover::before { opacity: 1; }

	.g6-sidebar__empty {
		font-size: 12px;
		color: rgba(255,255,255,0.25);
		font-style: italic;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}

	/* ── Support Hours meter ── */
	/* No percentage bar: "Total Hours Purchased" is a cumulative lifetime
	   counter in Airtable that never resets on repurchase, so balance/total
	   is not a meaningful "% remaining" — see the PHP-side comment in
	   g6_render_dashboard() for the full explanation. */
	.g6-hours-meter__value {
		font-size: 12.5px;
		color: rgba(255,255,255,0.65);
		margin: 12px 0 0;
		line-height: 1.5;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}
	.g6-hours-meter__value--first { margin-top: 6px; }
	.g6-hours-meter__highlight {
		font-family: var(--g6-font-heading);
		font-weight: 700;
		font-size: 15px;
		color: #fff;
	}
	.g6-hours-meter__highlight--warning  { color: var(--g6-warning); }
	.g6-hours-meter__highlight--critical { color: var(--g6-error); }
	.g6-hours-meter__cta {
		margin-top: 14px;
		padding-top: 12px;
		border-top: 1px solid rgba(255,255,255,0.08);
	}
	.g6-hours-meter__cta-text {
		font-size: 12px;
		color: rgba(255,255,255,0.55);
		margin: 0 0 4px;
		line-height: 1.45;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
	}
	.g6-hours-meter__cta-link {
		font-family: var(--g6-font-heading);
		font-size: 12.5px;
		font-weight: 600;
		color: var(--g6-accent-blue);
		text-decoration: none;
	}
	.g6-hours-meter__cta-link:hover { text-decoration: underline; }
	';
}

// ── Render ────────────────────────────────────────────────────────────────────

function g6_render_dashboard(): void {
	$cfg   = g6_get_client_config();
	$user  = wp_get_current_user();
	$first = $user->first_name ?: $user->display_name;

	$tracking        = $cfg['tracking'] ?? [];
	$tracking_pills  = [];
	if ( ! empty( $tracking['gtm_id'] ) )             $tracking_pills[] = [ 'badge' => 'GTM',  'label' => 'Google Tag Manager',  'bg' => '#1a73e8', 'color' => '#fff',     'id' => $tracking['gtm_id'] ];
	if ( ! empty( $tracking['ga_measurement_id'] ) )  $tracking_pills[] = [ 'badge' => 'GA4',  'label' => 'Google Analytics 4',   'bg' => '#e37400', 'color' => '#fff',     'id' => $tracking['ga_measurement_id'] ];
	if ( ! empty( $tracking['google_ads_id'] ) )      $tracking_pills[] = [ 'badge' => 'ADS',  'label' => 'Google Ads',           'bg' => '#fbbc04', 'color' => '#1a1a1a', 'id' => $tracking['google_ads_id'] ];
	if ( ! empty( $tracking['facebook_pixel_id'] ) )  $tracking_pills[] = [ 'badge' => 'META', 'label' => 'Meta Pixel',            'bg' => '#1877f2', 'color' => '#fff',     'id' => $tracking['facebook_pixel_id'] ];
	if ( ! empty( $tracking['x_pixel_id'] ) )         $tracking_pills[] = [ 'badge' => 'X',    'label' => 'X Pixel',               'bg' => '#14171a', 'color' => '#fff',     'id' => $tracking['x_pixel_id'] ];
	if ( ! empty( $tracking['clarity_project_id'] ) ) $tracking_pills[] = [ 'badge' => 'CLA',  'label' => 'Microsoft Clarity',     'bg' => '#4f46e5', 'color' => '#fff',     'id' => $tracking['clarity_project_id'] ];

	// ── Support Hours ──
	// Two sources, one widget. Both fetchers return the same array —
	// [ active, balance_hours, total_hours, fetched_at ] — so everything
	// below this block is the same code it always was.
	$_sh_enabled = ! empty( $cfg['support_hours_enabled'] );
	$_sh_source  = g6_support_hours_source( $cfg );
	$_sh_data    = false;

	if ( $_sh_enabled && 'portal' === $_sh_source ) {
		$_sh_data = g6_api_get_support_hours( $cfg['portal_token'] ?? '' );
	} elseif ( $_sh_enabled ) {
		$_sh_record_id = $cfg['support_hours_record_id'] ?? '';
		$_sh_api_key   = $cfg['support_hours_api_key'] ?? '';
		$_sh_data      = ( $_sh_record_id && $_sh_api_key )
			? g6_airtable_get_data( $_sh_record_id, $_sh_api_key )
			: false;
	}

	$_show_sh = $_sh_enabled && is_array( $_sh_data );

	if ( $_show_sh && ! empty( $_sh_data['active'] ) ) {
		$_sh_balance = (float) $_sh_data['balance_hours'];

		// Color threshold is based on absolute hours remaining, not a
		// percentage of "Total Hours Purchased" — that field is a cumulative
		// lifetime counter in Airtable that never resets when a client buys
		// a new bucket after exhausting a previous one, so balance/total is
		// not a meaningful ratio (e.g. a fresh 5h bucket right after using
		// up a prior 5h one would read as "50%", not "100%").
		if ( $_sh_balance <= 2 )      $_sh_level = 'critical';
		elseif ( $_sh_balance <= 3 )  $_sh_level = 'warning';
		else                          $_sh_level = 'good';

		$_sh_over       = $_sh_balance < 0;
		$_sh_abs_hours  = abs( $_sh_balance );
		$_sh_hours_text = rtrim( rtrim( number_format( $_sh_abs_hours, 2 ), '0' ), '.' );
		if ( '' === $_sh_hours_text ) $_sh_hours_text = '0';
		$_sh_plural    = abs( $_sh_abs_hours - 1.0 ) > 0.001;
		$_sh_highlight = $_sh_hours_text . ' hour' . ( $_sh_plural ? 's' : '' );
		$_sh_prefix    = $_sh_over ? "You're " : 'You have ';
		$_sh_suffix    = $_sh_over ? ' over' : ' left';

		// CTA: surface once hours are low (warning or critical), via email for now.
		// TODO: swap to a self-serve purchase link once one exists.
		$_sh_show_cta = in_array( $_sh_level, [ 'warning', 'critical' ], true );
		if ( $_sh_show_cta ) {
			$_sh_cta_text = $_sh_over
				? "You've used all your support hours."
				: "You're running out of support hours.";
			$_sh_cta_url = 'mailto:' . $cfg['agency_rep_email'] . '?subject=' . rawurlencode( 'Purchase More Support Hours - ' . $cfg['client_name'] );
		}
	}

	$_show_sidebar = ! empty( $tracking_pills ) || $_show_sh;
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
					<p class="g6-dashboard__subtitle">Your <?php echo esc_html( $cfg['client_name'] ); ?> dashboard</p>
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

		<div class="g6-dashboard__body<?php echo empty( $_show_sidebar ) ? ' g6-dashboard__body--no-sidebar' : ''; ?>">

		<!-- Sidebar -->
		<?php if ( $_show_sidebar ) : ?>
		<aside class="g6-dashboard__sidebar">
			<div class="g6-sidebar">
				<?php if ( $_show_sh ) : ?>
				<div class="g6-sidebar__section">
					<p class="g6-sidebar__section-title">Support Hours</p>
					<?php if ( empty( $_sh_data['active'] ) ) : ?>
						<div class="g6-sidebar__empty">No active support plan</div>
					<?php else : ?>
						<div class="g6-hours-meter__value g6-hours-meter__value--first">
							<?php echo esc_html( $_sh_prefix ); ?><strong class="g6-hours-meter__highlight g6-hours-meter__highlight--<?php echo esc_attr( $_sh_level ); ?>"><?php echo esc_html( $_sh_highlight ); ?></strong><?php echo esc_html( $_sh_suffix ); ?>
						</div>
						<?php if ( $_sh_show_cta ) : ?>
						<div class="g6-hours-meter__cta">
							<div class="g6-hours-meter__cta-text"><?php echo esc_html( $_sh_cta_text ); ?></div>
							<a href="<?php echo esc_url( $_sh_cta_url ); ?>" class="g6-hours-meter__cta-link">Purchase more by contacting Group6</a>
						</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $tracking_pills ) ) : ?>
				<div class="g6-sidebar__section">
					<p class="g6-sidebar__section-title">Active Tracking</p>
					<div class="g6-sidebar__tags">
						<?php foreach ( $tracking_pills as $pill ) : ?>
						<div class="g6-sidebar__tag" data-g6-tip="<?php echo esc_attr( $pill['id'] ); ?>">
							<span class="g6-sidebar__tag-badge" style="background:<?php echo esc_attr( $pill['bg'] ); ?>;color:<?php echo esc_attr( $pill['color'] ); ?>;"><?php echo esc_html( $pill['badge'] ); ?></span>
							<span class="g6-sidebar__tag-label"><?php echo esc_html( $pill['label'] ); ?></span>
							<span class="g6-sidebar__tag-dot"></span>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</aside>
		<?php endif; ?>

		<!-- Main -->
		<div class="g6-dashboard__main">
		<div class="g6-dashboard__grid">

		<?php
		// The widgets, in the order this site has put them in. Each one
		// lives in includes/widgets/<key>.php and decides for itself
		// whether it has anything to show — this loop only decides WHEN.
		foreach ( g6_widget_order( $cfg ) as $g6_widget ) {
			$g6_file = G6_DASHBOARD_DIR . 'includes/widgets/' . $g6_widget . '.php';

			if ( is_readable( $g6_file ) ) {
				include $g6_file;
			}
		}
		?>

		</div><!-- /.g6-dashboard__grid -->
		</div><!-- /.g6-dashboard__main -->

		</div><!-- /.g6-dashboard__body -->

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
	// Project pager: one project on screen, arrows to move between them.
	// The cards are all rendered and hidden rather than fetched on
	// demand — there are two or three of them, and a dashboard widget
	// that goes to the network to show something already downloaded is
	// slower for no reason.
	document.addEventListener('DOMContentLoaded', function() {
		var wrap = document.getElementById('g6-projects');
		if (!wrap) return;

		var cards = [...wrap.querySelectorAll('.g6-project')];
		var pos   = document.getElementById('g6-project-pos');
		var at    = 0;

		if (cards.length < 2) return;

		wrap.querySelectorAll('.g6-project__arrow').forEach(function(btn) {
			btn.addEventListener('click', function() {
				// Wraps, so neither arrow is ever a dead button.
				at = (at + Number(btn.dataset.step) + cards.length) % cards.length;
				cards.forEach(function(card, i) { card.hidden = i !== at; });
				if (pos) pos.textContent = String(at + 1);
			});
		});
	});

	function g6SubmitContact() {
		var subject   = document.getElementById('g6-subject').value.trim();
		var message   = document.getElementById('g6-message').value;
		var categoryEl = document.getElementById('g6-category');
		var category  = categoryEl ? categoryEl.value : '';
		var errorEl   = document.getElementById('g6-contact-error');
		var successEl = document.getElementById('g6-contact-success');

		errorEl.style.display   = 'none';
		successEl.style.display = 'none';

		// The subject is required either way — the portal's API requires
		// it, and on the Zendesk form the dropdown IS the subject.
		//
		// Which wording to use follows the form's mode, not whether a
		// topic dropdown happens to be present: the portal form renders
		// without one when the topic list could not be fetched, and
		// "please SELECT a subject" beside a text box is nonsense.
		var portalForm = document.getElementById('g6-contact-form').dataset.mode === 'portal';

		if ( categoryEl && ! category ) {
			errorEl.textContent   = 'Please choose a topic.';
			errorEl.style.display = 'block';
			return;
		}

		if ( ! subject || ! message ) {
			errorEl.textContent    = portalForm
				? 'Please add a subject and a message.'
				: 'Please select a subject and enter a message.';
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
		if ( category ) { data.append('category', category); }
		data.append('_wpnonce',  '<?php echo esc_js( wp_create_nonce( 'g6_contact_nonce' ) ); ?>');

		fetch(ajaxurl, { method: 'POST', body: data })
			.then(function(r) { return r.json(); })
			.then(function(result) {
				if ( result.success ) {
					successEl.style.display = 'flex';
					document.getElementById('g6-subject').value = '';
					document.getElementById('g6-message').value = '';
					if ( categoryEl ) { categoryEl.value = ''; }
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
