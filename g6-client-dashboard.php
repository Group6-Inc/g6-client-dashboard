<?php
/**
 * Plugin Name:  Group6 Client Dashboard
 * Plugin URI:   https://github.com/Group6-Inc/g6-client-dashboard
 * Description:  Replaces the default WordPress dashboard with a branded Group6 client portal — SEO metrics, reviews, service CTAs, and how-to guides.
 * Version:      0.3.9.4
 * Author:       Group6
 * Author URI:   https://group6inc.com
 * License:      Proprietary
 * Requires PHP: 8.0
 * Requires WP:  6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'G6_DASHBOARD_VERSION',   '0.3.9.4' );
define( 'G6_DASHBOARD_FILE',      __FILE__ );
define( 'G6_DASHBOARD_DIR',       plugin_dir_path( __FILE__ ) );
define( 'G6_DASHBOARD_SLUG',      'g6-client-dashboard' );

/**
 * GitHub repo details — update these to match your repo.
 * MANIFEST_URL points to the JSON file you host (on your site,
 * GitHub Pages, or a raw Gist). See plugin-manifest.json in this repo.
 */
define( 'G6_DASHBOARD_GITHUB_ORG',   'Group6-Inc' );
define( 'G6_DASHBOARD_GITHUB_REPO',  'g6-client-dashboard' );
define( 'G6_DASHBOARD_MANIFEST_URL',      'https://gist.githubusercontent.com/g6-gabriel/8d8b3d50ba384da12359e34c57efe39a/raw/g6-client-dashboard.json' );
define( 'G6_DASHBOARD_MANIFEST_URL_BETA', 'https://gist.githubusercontent.com/g6-gabriel/8d8b3d50ba384da12359e34c57efe39a/raw/g6-client-dashboard-beta.json' );

/**
 * Support ticket destination.
 * Swap this constant (or replace the handler in includes/ajax.php)
 * to switch away from Zendesk to another tool or plain email.
 */
define( 'G6_ZENDESK_SUBDOMAIN', 'group61347' );

require_once G6_DASHBOARD_DIR . 'includes/class-updater.php';
require_once G6_DASHBOARD_DIR . 'includes/config.php';
require_once G6_DASHBOARD_DIR . 'includes/icons.php';
require_once G6_DASHBOARD_DIR . 'includes/gmb.php';
require_once G6_DASHBOARD_DIR . 'includes/dashboard.php';
require_once G6_DASHBOARD_DIR . 'includes/ajax.php';
require_once G6_DASHBOARD_DIR . 'includes/settings.php';
require_once G6_DASHBOARD_DIR . 'includes/tracking.php';

// Boot the updater.
new G6\Dashboard\Updater( G6_DASHBOARD_VERSION );

// Conditionally boot the Asset Manager based on settings.
add_action( 'plugins_loaded', function() {
	$cfg = get_option( 'g6_client_config', [] );
	if ( ! empty( $cfg['asset_manager_enabled'] ) ) {
		require_once G6_DASHBOARD_DIR . 'includes/asset-manager.php';
		new G6_Asset_Manager();
	}
} );

// Boot the Login Screen Customizer.
// Enabled by default on new installs; existing installs without the key also get the default (true).
add_action( 'plugins_loaded', function() {
	$defaults  = g6_default_config()['login'];
	$saved     = get_option( 'g6_client_config', [] );
	$login_cfg = array_merge( $defaults, $saved['login'] ?? [] );
	if ( ! empty( $login_cfg['enabled'] ) ) {
		require_once G6_DASHBOARD_DIR . 'includes/login-customizer.php';
		new G6_Login_Customizer( $login_cfg );
	}
} );

// Disable attachment pages (Developer Tools setting).
// Functions use unique g6_ prefix — safe alongside WPCodeBox's wpse237762_* functions.
if ( ! function_exists( 'g6_attachment_redirect_404' ) ) {
	function g6_attachment_redirect_404(): void {
		if ( is_attachment() ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
		}
	}
}
if ( ! function_exists( 'g6_unique_attachment_slug' ) ) {
	function g6_unique_attachment_slug( string $slug, int $_post_id, string $_post_status, string $post_type, int $_post_parent, string $_original_slug ): string {
		if ( $post_type === 'attachment' ) {
			return str_replace( '-', '', wp_generate_uuid4() );
		}
		return $slug;
	}
}
add_action( 'plugins_loaded', function() {
	$cfg = get_option( 'g6_client_config', [] );
	if ( ! empty( $cfg['disable_attachment_slugs'] ) ) {
		add_action( 'template_redirect',   'g6_attachment_redirect_404' );
		add_filter( 'redirect_canonical',  'g6_attachment_redirect_404', 0 );
		add_filter( 'wp_unique_post_slug', 'g6_unique_attachment_slug', 10, 6 );
	}
} );
