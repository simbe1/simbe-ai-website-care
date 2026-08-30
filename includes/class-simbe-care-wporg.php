<?php
/**
 * WordPress.org plugin and theme API client with a per-item cached lookup.
 *
 * The WordPress.org plugins API returns `{"error":"closed"}` for plugins that
 * were closed/removed from the directory and `{"error":"notfound"}` for
 * plugins that do not exist. Both responses are HTTP 404, so we parse the
 * body. The themes API similarly returns an error payload for themes that are
 * no longer in the directory.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Simbe_Care_WPorg
 */
class Simbe_Care_WPorg {

	/**
	 * Transient key holding the aggregate per-item cache.
	 *
	 * @var string
	 */
	const CACHE_TRANSIENT = 'simbe_care_wporg_cache';

	/**
	 * Base URL for the WordPress.org plugin info endpoint.
	 *
	 * @var string
	 */
	const PLUGIN_API = 'https://api.wordpress.org/plugins/info/1.0/';

	/**
	 * URL template for the WordPress.org theme info endpoint.
	 *
	 * @var string
	 */
	const THEME_API = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request[slug]=';

	/**
	 * Returns cached or freshly-fetched info for a single plugin slug.
	 *
	 * @param string $slug Plugin slug.
	 * @param int    $ttl  Freshness window in seconds.
	 * @return array Normalized entry: status, info, message.
	 */
	public static function get_plugin_info( $slug, $ttl = 0 ) {
		return self::get_item_info( 'plugins', $slug, self::PLUGIN_API, $ttl );
	}

	/**
	 * Returns cached or freshly-fetched info for a single theme slug.
	 *
	 * @param string $slug Theme slug.
	 * @param int    $ttl  Freshness window in seconds.
	 * @return array Normalized entry: status, info, message.
	 */
	public static function get_theme_info( $slug, $ttl = 0 ) {
		return self::get_item_info( 'themes', $slug, self::THEME_API, $ttl );
	}

	/**
	 * Shared lookup for a plugin or theme slug.
	 *
	 * @param string $kind     'plugins' or 'themes'.
	 * @param string $slug     Item slug.
	 * @param string $base_url Endpoint URL prefix.
	 * @param int    $ttl      Freshness window in seconds.
	 * @return array Normalized entry.
	 */
	private static function get_item_info( $kind, $slug, $base_url, $ttl = 0 ) {
		$kind = ( 'themes' === $kind ) ? 'themes' : 'plugins';
		$slug = sanitize_title( (string) $slug );
		$ttl  = max( HOUR_IN_SECONDS, absint( $ttl ) );
		$cache = self::get_cache();

		if ( isset( $cache[ $kind ][ $slug ] ) ) {
			$entry = $cache[ $kind ][ $slug ];
			if ( time() - (int) $entry['time'] < $ttl ) {
				return $entry['data'];
			}
		}

		$data                  = self::fetch( $kind, $slug );
		$cache[ $kind ][ $slug ] = array(
			'time' => time(),
			'data' => $data,
		);
		set_transient( self::CACHE_TRANSIENT, $cache, 30 * DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Reads the aggregate cache.
	 *
	 * @return array
	 */
	private static function get_cache() {
		$cache = get_transient( self::CACHE_TRANSIENT );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}
		if ( ! isset( $cache['plugins'] ) || ! is_array( $cache['plugins'] ) ) {
			$cache['plugins'] = array();
		}
		if ( ! isset( $cache['themes'] ) || ! is_array( $cache['themes'] ) ) {
			$cache['themes'] = array();
		}
		return $cache;
	}

	/**
	 * Fetches plugin or theme info from WordPress.org.
	 *
	 * @param string $kind 'plugins' or 'themes'.
	 * @param string $slug Item slug.
	 * @return array Normalized entry: status, info, message.
	 */
	public static function fetch( $kind, $slug ) {
		if ( 'themes' === $kind ) {
			$url = self::THEME_API . rawurlencode( $slug );
			$is_theme = true;
		} else {
			$url = add_query_arg(
				array(
					'fields' => 'active_installs,downloaded,last_updated,tested,requires,requires_php,homepage,author,version,rating,num_ratings',
				),
				self::PLUGIN_API . rawurlencode( $slug ) . '.json'
			);
			$is_theme = false;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'Simbe-AI-Website-Care/' . SIMBE_CARE_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'error',
				'info'    => null,
				'message' => $response->get_error_message(),
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $data ) ) {
			return array(
				'status'  => 'error',
				'info'    => null,
				'message' => __( 'Unexpected response from WordPress.org.', 'simbe-ai-website-care' ),
			);
		}

		if ( isset( $data->error ) ) {
			$error = (string) $data->error;

			if ( $is_theme ) {
				// The themes API does not distinguish "closed" items the same
				// way the plugins API does.
				if ( false !== strpos( $error, 'closed' ) ) {
					return array(
						'status'  => 'closed',
						'info'    => null,
						'message' => __( 'This theme was closed and removed from the WordPress.org directory.', 'simbe-ai-website-care' ),
					);
				}
				return array(
					'status'  => 'notfound',
					'info'    => null,
					'message' => __( 'This theme is not listed in the WordPress.org directory. It may be a premium or custom theme that cannot be checked automatically.', 'simbe-ai-website-care' ),
				);
			}

			if ( 'closed' === $error ) {
				return array(
					'status'  => 'closed',
					'info'    => null,
					'message' => __( 'This plugin was closed and removed from the WordPress.org directory. Closed plugins are often removed for security or guideline reasons.', 'simbe-ai-website-care' ),
				);
			}

			return array(
				'status'  => 'notfound',
				'info'    => null,
				'message' => __( 'This plugin is not listed in the WordPress.org directory. It may be a premium or custom plugin that cannot be checked automatically.', 'simbe-ai-website-care' ),
			);
		}

		return array(
			'status'  => 'ok',
			'info'    => $data,
			'message' => '',
		);
	}

	/**
	 * Clears the entire WordPress.org lookup cache.
	 */
	public static function flush() {
		delete_transient( self::CACHE_TRANSIENT );
	}
}