<?php
/**
 * GitHub Auto-Updater
 *
 * Hooks into WordPress's native update system to pull new versions
 * directly from GitHub Releases via a hosted manifest JSON file.
 *
 * @package G6\Dashboard
 */

namespace G6\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Updater {

	private string $version;
	private string $plugin_slug;
	private string $plugin_file;
	private string $cache_key;
	private bool   $cache_allowed;

	public function __construct( string $version ) {
		$this->version       = $version;
		$this->plugin_slug   = G6_DASHBOARD_SLUG;
		$this->plugin_file   = $this->plugin_slug . '/' . $this->plugin_slug . '.php';
		$this->cache_key     = 'g6_dashboard_update_manifest';
		$this->cache_allowed = true;

		add_filter( 'plugins_api',                   [ $this, 'plugin_info' ],    20, 3 );
		add_filter( 'site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_action( 'upgrader_process_complete',     [ $this, 'purge_cache' ],    10, 2 );
	}

	// ── Fetch & cache the remote manifest ──────────────────────────────

	private function fetch_manifest(): object|false {
		$cached = get_transient( $this->cache_key );

		if ( false === $cached || ! $this->cache_allowed ) {
			$args = [
				'timeout' => 10,
				'headers' => [ 'Accept' => 'application/json' ],
			];

			$response = wp_remote_get( G6_DASHBOARD_MANIFEST_URL, $args );

			if (
				is_wp_error( $response ) ||
				200 !== wp_remote_retrieve_response_code( $response ) ||
				empty( wp_remote_retrieve_body( $response ) )
			) {
				return false;
			}

			// Cache for 12 hours to reduce API calls.
			set_transient( $this->cache_key, $response, 12 * HOUR_IN_SECONDS );
			$cached = $response;
		}

		$manifest = json_decode( wp_remote_retrieve_body( $cached ) );

		return is_object( $manifest ) ? $manifest : false;
	}

	// ── Populate the plugin details modal ──────────────────────────────

	public function plugin_info( mixed $response, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action ) {
			return $response;
		}

		if ( empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $response;
		}

		$manifest = $this->fetch_manifest();
		if ( ! $manifest ) {
			return $response;
		}

		$info                 = new \stdClass();
		$info->name           = $manifest->name           ?? '';
		$info->slug           = $manifest->slug           ?? $this->plugin_slug;
		$info->version        = $manifest->version        ?? '';
		$info->author         = $manifest->author         ?? '';
		$info->author_profile = $manifest->author_profile ?? '';
		$info->homepage       = $manifest->homepage       ?? '';
		$info->requires       = $manifest->requires       ?? '';
		$info->tested         = $manifest->tested         ?? '';
		$info->requires_php   = $manifest->requires_php   ?? '';
		$info->last_updated   = $manifest->last_updated   ?? '';
		$info->download_link  = $manifest->download_url   ?? '';
		$info->trunk          = $manifest->download_url   ?? '';
		$info->sections       = isset( $manifest->sections )
			? (array) $manifest->sections
			: [];

		if ( ! empty( $manifest->banners ) ) {
			$info->banners = (array) $manifest->banners;
		}

		return $info;
	}

	// ── Flag the update in the plugins transient ───────────────────────

	public function check_for_update( mixed $transient ): mixed {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$manifest = $this->fetch_manifest();
		if ( ! $manifest ) {
			return $transient;
		}

		if (
			version_compare( $this->version, $manifest->version, '<' ) &&
			version_compare( $manifest->requires ?? '1.0', get_bloginfo( 'version' ), '<=' ) &&
			version_compare( $manifest->requires_php ?? '7.4', PHP_VERSION, '<=' )
		) {
			$update              = new \stdClass();
			$update->slug        = $this->plugin_slug;
			$update->plugin      = $this->plugin_file;
			$update->new_version = $manifest->version;
			$update->tested      = $manifest->tested ?? '';
			$update->package     = $manifest->download_url;
			$update->url         = $manifest->homepage ?? '';

			$transient->response[ $this->plugin_file ] = $update;
		}

		return $transient;
	}

	// ── Clear cached manifest after an update completes ───────────────

	public function purge_cache( \WP_Upgrader $upgrader, array $options ): void {
		if (
			$this->cache_allowed &&
			'update' === $options['action'] &&
			'plugin' === $options['type']
		) {
			delete_transient( $this->cache_key );
			delete_site_transient( 'update_plugins' );
		}
	}


}
