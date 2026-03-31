<?php
/**
 * Plugin Name:  Group6 Client Dashboard
 * Plugin URI:   https://github.com/Group6-Inc/g6-client-dashboard
 * Description:  Replaces the default WordPress dashboard with a branded Group6 client portal — SEO metrics, reviews, service CTAs, and how-to guides.
 * Version:      0.1.8
 * Author:       Group6
 * Author URI:   https://group6inc.com
 * License:      Proprietary
 * Requires PHP: 8.0
 * Requires WP:  6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'G6_DASHBOARD_VERSION',   '0.1.8' );
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
define( 'G6_DASHBOARD_MANIFEST_URL', 'https://gist.githubusercontent.com/g6-gabriel/8d8b3d50ba384da12359e34c57efe39a/raw/g6-client-dashboard.json' );

/**
 * Support ticket destination.
 * Swap this constant (or replace the handler in includes/ajax.php)
 * to switch away from Zendesk to another tool or plain email.
 */
define( 'G6_ZENDESK_SUBDOMAIN', 'group61347' );

require_once G6_DASHBOARD_DIR . 'includes/class-updater.php';
require_once G6_DASHBOARD_DIR . 'includes/config.php';
require_once G6_DASHBOARD_DIR . 'includes/icons.php';
require_once G6_DASHBOARD_DIR . 'includes/dashboard.php';
require_once G6_DASHBOARD_DIR . 'includes/ajax.php';
require_once G6_DASHBOARD_DIR . 'includes/settings.php';

// Boot the updater.
new G6\Dashboard\Updater( G6_DASHBOARD_VERSION );
