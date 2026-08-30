<?php
/**
 * Scans installed plugins against WordPress.org data and computes risk flags.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Simbe_Care_Scanner
 */
class Simbe_Care_Scanner {

	/**
	 * Transient key holding the last scan results.
	 *
	 * @var string
	 */
	const RESULTS_TRANSIENT = 'simbe_care_scan_results';

	/**
	 * Returns the cached scan results, optionally running a fresh scan.
	 *
	 * @param bool $force     Force a fresh scan.
	 * @param bool $read_only Return cached results only (no inline scan).
	 * @return array|null
	 */
	public static function get_scan( $force = false, $read_only = false ) {
		$cached = get_transient( self::RESULTS_TRANSIENT );

		if ( is_array( $cached ) && ! empty( $cached['time'] ) && ! $force ) {
			return $cached;
		}

		if ( $read_only ) {
			return null;
		}

		return self::run_scan();
	}

	/**
	 * Runs a full plugin and theme scan.
	 *
	 * @return array Scan results.
	 */
	public static function run_scan() {
		self::load_admin_dependencies();

		$settings = Simbe_Care::get_settings();

		$summary = array(
			'total'    => 0,
			'critical' => 0,
			'warning'  => 0,
			'ok'       => 0,
		);
		$results = array();

		$helpers = self::scan_data();

		foreach ( $helpers['plugins'] as $plugin_file => $plugin_data ) {
			$slug   = self::get_slug( $plugin_file );
			$wporg  = Simbe_Care_WPorg::get_plugin_info( $slug, $settings['wporg_ttl_hours'] * HOUR_IN_SECONDS );
			$update = isset( $helpers['plugin_updates'][ $plugin_file ] ) ? $helpers['plugin_updates'][ $plugin_file ] : null;

			$assessment = self::assess_plugin( $plugin_file, $plugin_data, $wporg, $update, $settings );
			self::tally( $summary, $assessment['risk'] );
			$results[ $plugin_file ] = $assessment;
		}
		$summary['total'] = count( $results );

		$theme_summary = array(
			'total'    => 0,
			'critical' => 0,
			'warning'  => 0,
			'ok'       => 0,
		);
		$theme_results = array();

		foreach ( $helpers['themes'] as $theme_slug => $theme ) {
			$wporg  = Simbe_Care_WPorg::get_theme_info( $theme_slug, $settings['wporg_ttl_hours'] * HOUR_IN_SECONDS );
			$update = null;
			if ( isset( $helpers['theme_updates'][ $theme_slug ]->update ) ) {
				$update = $helpers['theme_updates'][ $theme_slug ]->update;
			}

			$assessment = self::assess_theme( $theme_slug, $theme, $wporg, $update, $settings );
			self::tally( $theme_summary, $assessment['risk'] );
			$theme_results[ $theme_slug ] = $assessment;
		}
		$theme_summary['total'] = count( $theme_results );

		$data = array(
			'time'          => time(),
			'summary'       => $summary,
			'plugins'       => $results,
			'theme_summary' => $theme_summary,
			'themes'        => $theme_results,
		);

		set_transient( self::RESULTS_TRANSIENT, $data, $settings['wporg_ttl_hours'] * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Refreshes WordPress.org data and reruns the scan. Used by cron and the
	 * manual "Scan now" button.
	 */
	public static function refresh_wporg_data() {
		self::load_admin_dependencies();

		// Keep a reference to the previous scan so we can detect new issues.
		$previous = self::get_scan( false, true );

		// Refresh the core update transients first so update checks are current.
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
		if ( function_exists( 'wp_update_themes' ) ) {
			wp_update_themes();
		}

		Simbe_Care_WPorg::flush();
		$scan = self::run_scan();

		$settings               = Simbe_Care::get_settings();
		$settings['last_scan']  = time();
		update_option( Simbe_Care::SETTINGS_KEY, $settings, false );

		Simbe_Care_Notifications::maybe_send( $previous, $scan, $settings );

		return $scan;
	}

	/**
	 * Assesses a single plugin.
	 *
	 * @param string       $plugin_file Plugin file relative path.
	 * @param array        $plugin_data get_plugins() data.
	 * @param array        $wporg       Normalized WordPress.org entry.
	 * @param object|null  $update      Update object from get_plugin_updates().
	 * @param array        $settings    Plugin settings.
	 * @return array
	 */
	private static function assess_plugin( $plugin_file, $plugin_data, $wporg, $update, $settings ) {
		$flags    = array();
		$messages = array();
		$risk     = 'ok';

		$wp_version   = get_bloginfo( 'version' );
		$current_php  = PHP_VERSION;
		$active       = function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file );
		$abandon_days = absint( $settings['abandoned_days'] );

		// Update available?
		if ( $update && isset( $update->new_version ) ) {
			$flags['update_available'] = true;
			$flags['new_version']      = $update->new_version;
			$risk                      = self::raise_risk( $risk, 'warning' );
			$messages[]                = sprintf(
				/* translators: 1: new version number. */
				__( 'A newer version (%1$s) is available. Update to receive the latest fixes.', 'simbe-ai-website-care' ),
				$update->new_version
			);

			if ( isset( $update->requires_php ) && $update->requires_php && version_compare( $current_php, $update->requires_php, '<' ) ) {
				$flags['update_php_requirement'] = true;
				$risk                            = self::raise_risk( $risk, 'critical' );
				$messages[]                      = sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'The available update requires PHP %1$s, but this site runs PHP %2$s.', 'simbe-ai-website-care' ),
					$update->requires_php,
					$current_php
				);
			}
		}

		$status = isset( $wporg['status'] ) ? $wporg['status'] : 'error';

		if ( 'ok' === $status ) {
			$info                      = $wporg['info'];
			$flags['in_directory']     = true;
			$flags['active_installs']  = isset( $info->active_installs ) ? (int) $info->active_installs : 0;
			$flags['downloads']        = isset( $info->downloaded ) ? (int) $info->downloaded : 0;
			$flags['homepage']         = isset( $info->homepage ) ? esc_url_raw( $info->homepage ) : '';
			$flags['author']           = isset( $info->author ) ? wp_strip_all_tags( $info->author ) : '';

			// Last updated / abandoned.
			if ( isset( $info->last_updated ) && $info->last_updated ) {
				$ts = strtotime( $info->last_updated );
				$flags['last_updated'] = ( $ts ) ? gmdate( 'Y-m-d', $ts ) : (string) $info->last_updated;

				if ( $ts && $abandon_days > 0 && ( time() - $ts ) > ( $abandon_days * DAY_IN_SECONDS ) ) {
					$flags['abandoned'] = true;
					$risk               = self::raise_risk( $risk, 'warning' );
					$messages[]         = sprintf(
						/* translators: 1: number of days, 2: last update date. */
						__( 'No update in over %1$d days (last update: %2$s). The plugin may be abandoned.', 'simbe-ai-website-care' ),
						$abandon_days,
						$flags['last_updated']
					);
				}
			}

			// Tested with the current WordPress version?
			if ( isset( $info->tested ) && $info->tested ) {
				$tested = self::normalize_version( $info->tested );
				$flags['tested'] = $tested;
				if ( version_compare( self::normalize_version( $wp_version ), $tested, '>' ) ) {
					$flags['untested'] = true;
					$risk              = self::raise_risk( $risk, 'warning' );
					$messages[]        = sprintf(
						/* translators: 1: tested WordPress version, 2: current WordPress version. */
						__( 'Tested up to WordPress %1$s, but this site runs WordPress %2$s.', 'simbe-ai-website-care' ),
						$tested,
						$wp_version
					);
				}
			}

			// PHP requirement.
			if ( isset( $info->requires_php ) && $info->requires_php && version_compare( $current_php, $info->requires_php, '<' ) ) {
				$flags['php_requirement'] = true;
				$risk                     = self::raise_risk( $risk, 'critical' );
				$messages[]               = sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'Requires PHP %1$s, but this site runs PHP %2$s.', 'simbe-ai-website-care' ),
					$info->requires_php,
					$current_php
				);
			}

			// WordPress requirement.
			if ( isset( $info->requires ) && $info->requires && version_compare( $wp_version, $info->requires, '<' ) ) {
				$flags['wp_requirement'] = true;
				$risk                    = self::raise_risk( $risk, 'critical' );
				$messages[]              = sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version. */
					__( 'Requires WordPress %1$s, but this site runs WordPress %2$s.', 'simbe-ai-website-care' ),
					$info->requires,
					$wp_version
				);
			}
		} elseif ( 'closed' === $status ) {
			$flags['closed'] = true;
			$risk            = self::raise_risk( $risk, 'critical' );
			$messages[]      = $wporg['message'];
		} elseif ( 'notfound' === $status ) {
			$flags['not_found'] = true;
			$risk               = self::raise_risk( $risk, 'warning' );
			$messages[]         = $wporg['message'];
		} else {
			$flags['lookup_error'] = true;
			$messages[]            = __( 'Could not reach WordPress.org to check this plugin. It will be re-checked on the next scan.', 'simbe-ai-website-care' );
		}

		return array(
			'name'     => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : $plugin_file,
			'slug'     => self::get_slug( $plugin_file ),
			'version'  => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '',
			'active'   => $active,
			'risk'     => $risk,
			'flags'    => $flags,
			'messages' => $messages,
		);
	}

	/**
	 * Assesses a single theme.
	 *
	 * @param string       $theme_slug Theme slug (stylesheet directory).
	 * @param WP_Theme     $theme      WP_Theme object.
	 * @param array        $wporg      Normalized WordPress.org entry.
	 * @param object|null  $update     Update object from get_theme_updates().
	 * @param array        $settings   Plugin settings.
	 * @return array
	 */
	private static function assess_theme( $theme_slug, $theme, $wporg, $update, $settings ) {
		$flags    = array();
		$messages = array();
		$risk     = 'ok';

		$wp_version   = get_bloginfo( 'version' );
		$current_php  = PHP_VERSION;
		$active       = ( $theme_slug === get_stylesheet() );
		$abandon_days = absint( $settings['abandoned_days'] );
		$name         = method_exists( $theme, 'get' ) ? $theme->get( 'Name' ) : '';
		$version      = method_exists( $theme, 'get' ) ? $theme->get( 'Version' ) : '';

		// Update available?
		if ( $update && isset( $update->new_version ) ) {
			$flags['update_available'] = true;
			$flags['new_version']      = $update->new_version;
			$risk                      = self::raise_risk( $risk, 'warning' );
			$messages[]                = sprintf(
				/* translators: 1: new version number. */
				__( 'A newer version (%1$s) is available. Update to receive the latest fixes.', 'simbe-ai-website-care' ),
				$update->new_version
			);

			if ( isset( $update->requires_php ) && $update->requires_php && version_compare( $current_php, $update->requires_php, '<' ) ) {
				$flags['update_php_requirement'] = true;
				$risk                            = self::raise_risk( $risk, 'critical' );
				$messages[]                      = sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'The available update requires PHP %1$s, but this site runs PHP %2$s.', 'simbe-ai-website-care' ),
					$update->requires_php,
					$current_php
				);
			}
		}

		$status = isset( $wporg['status'] ) ? $wporg['status'] : 'error';

		if ( 'ok' === $status ) {
			$info                  = $wporg['info'];
			$flags['in_directory'] = true;
			$flags['downloads']    = isset( $info->downloaded ) ? (int) $info->downloaded : 0;
			$flags['homepage']     = isset( $info->homepage ) ? esc_url_raw( $info->homepage ) : '';
			$flags['author']       = self::theme_author( $info );

			// Last updated / abandoned.
			if ( isset( $info->last_updated ) && $info->last_updated ) {
				$ts = strtotime( $info->last_updated );
				$flags['last_updated'] = ( $ts ) ? gmdate( 'Y-m-d', $ts ) : (string) $info->last_updated;

				if ( $ts && $abandon_days > 0 && ( time() - $ts ) > ( $abandon_days * DAY_IN_SECONDS ) ) {
					$flags['abandoned'] = true;
					$risk               = self::raise_risk( $risk, 'warning' );
					$messages[]         = sprintf(
						/* translators: 1: number of days, 2: last update date. */
						__( 'No update in over %1$d days (last update: %2$s). The theme may be abandoned.', 'simbe-ai-website-care' ),
						$abandon_days,
						$flags['last_updated']
					);
				}
			}

			// Tested with the current WordPress version?
			if ( isset( $info->tested ) && $info->tested ) {
				$tested          = self::normalize_version( $info->tested );
				$flags['tested'] = $tested;
				if ( version_compare( self::normalize_version( $wp_version ), $tested, '>' ) ) {
					$flags['untested'] = true;
					$risk              = self::raise_risk( $risk, 'warning' );
					$messages[]        = sprintf(
						/* translators: 1: tested WordPress version, 2: current WordPress version. */
						__( 'Tested up to WordPress %1$s, but this site runs WordPress %2$s.', 'simbe-ai-website-care' ),
						$tested,
						$wp_version
					);
				}
			}

			// PHP requirement.
			if ( isset( $info->requires_php ) && $info->requires_php && version_compare( $current_php, $info->requires_php, '<' ) ) {
				$flags['php_requirement'] = true;
				$risk                     = self::raise_risk( $risk, 'critical' );
				$messages[]               = sprintf(
					/* translators: 1: required PHP version, 2: current PHP version. */
					__( 'Requires PHP %1$s, but this site runs PHP %2$s.', 'simbe-ai-website-care' ),
					$info->requires_php,
					$current_php
				);
			}

			// WordPress requirement.
			if ( isset( $info->requires ) && $info->requires && version_compare( $wp_version, $info->requires, '<' ) ) {
				$flags['wp_requirement'] = true;
				$risk                    = self::raise_risk( $risk, 'critical' );
				$messages[]              = sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version. */
					__( 'Requires WordPress %1$s, but this site runs WordPress %2$s.', 'simbe-ai-website-care' ),
					$info->requires,
					$wp_version
				);
			}
		} elseif ( 'closed' === $status ) {
			$flags['closed'] = true;
			$risk            = self::raise_risk( $risk, 'critical' );
			$messages[]      = $wporg['message'];
		} elseif ( 'notfound' === $status ) {
			$flags['not_found'] = true;
			$risk               = self::raise_risk( $risk, 'warning' );
			$messages[]         = $wporg['message'];
		} else {
			$flags['lookup_error'] = true;
			$messages[]            = __( 'Could not reach WordPress.org to check this theme. It will be re-checked on the next scan.', 'simbe-ai-website-care' );
		}

		return array(
			'name'     => $name ? $name : $theme_slug,
			'slug'     => $theme_slug,
			'version'  => $version,
			'active'   => $active,
			'risk'     => $risk,
			'flags'    => $flags,
			'messages' => $messages,
		);
	}

	/**
	 * Extracts a display name from the themes API author field.
	 *
	 * The themes API returns the author as an object, unlike the plugins API
	 * which returns a string. Handles both shapes.
	 *
	 * @param object $info Theme API response object.
	 * @return string
	 */
	private static function theme_author( $info ) {
		if ( ! isset( $info->author ) ) {
			return '';
		}

		if ( is_string( $info->author ) ) {
			return wp_strip_all_tags( $info->author );
		}

		if ( is_object( $info->author ) ) {
			if ( isset( $info->author->display_name ) ) {
				return (string) $info->author->display_name;
			}
			if ( isset( $info->author->author ) ) {
				return (string) $info->author->author;
			}
		}

		return '';
	}

	/**
	 * Increments a summary bucket by risk level.
	 *
	 * @param array  $summary Summary array by reference.
	 * @param string $risk    Risk level.
	 */
	private static function tally( &$summary, $risk ) {
		if ( 'critical' === $risk ) {
			$summary['critical']++;
		} elseif ( 'warning' === $risk ) {
			$summary['warning']++;
		} else {
			$summary['ok']++;
		}
	}

	/**
	 * Gathers installed plugins and themes and their pending updates once.
	 *
	 * @return array
	 */
	private static function scan_data() {
		return array(
			'plugins'        => get_plugins(),
			'plugin_updates' => get_plugin_updates(),
			'themes'         => wp_get_themes(),
			'theme_updates'  => get_theme_updates(),
		);
	}

	/**
	 * Derives a plugin slug from the plugin file path.
	 *
	 * @param string $plugin_file Plugin file relative path.
	 * @return string
	 */
	private static function get_slug( $plugin_file ) {
		$parts = explode( '/', $plugin_file );
		if ( isset( $parts[1] ) && $parts[1] ) {
			return sanitize_title( $parts[0] );
		}
		return sanitize_title( str_replace( '.php', '', basename( $plugin_file ) ) );
	}

	/**
	 * Normalizes a version to major.minor for comparisons.
	 *
	 * @param string $version Version string.
	 * @return string
	 */
	private static function normalize_version( $version ) {
		$parts = explode( '.', (string) $version );
		if ( count( $parts ) >= 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) ) {
			return $parts[0] . '.' . $parts[1];
		}
		return (string) $version;
	}

	/**
	 * Raises a risk level (never lowers).
	 *
	 * @param string $current Current risk.
	 * @param string $new     New risk.
	 * @return string
	 */
	private static function raise_risk( $current, $new ) {
		$map = array(
			'ok'       => 0,
			'warning'  => 1,
			'critical' => 2,
		);
		if ( ! isset( $map[ $current ] ) || ! isset( $map[ $new ] ) ) {
			return $current;
		}
		return ( $map[ $current ] >= $map[ $new ] ) ? $current : $new;
	}

	/**
	 * Ensures the admin functions we depend on are available.
	 */
	private static function load_admin_dependencies() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
	}

	/**
	 * Clears cached scan results.
	 */
	public static function flush() {
		delete_transient( self::RESULTS_TRANSIENT );
	}
}
