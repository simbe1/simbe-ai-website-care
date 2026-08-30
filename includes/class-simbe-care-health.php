<?php
/**
 * Extends WordPress Site Health with Simbe Care checks.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Simbe_Care_Health
 */
class Simbe_Care_Health {

	/**
	 * Registers our tests in the Site Health suite.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public static function register_tests( $tests ) {
		$tests['direct']['simbe_care_plugin_scan'] = array(
			'label' => __( 'Plugins show no critical risk indicators', 'simbe-ai-website-care' ),
			'test'  => array( __CLASS__, 'test_plugin_scan' ),
		);
		$tests['direct']['simbe_care_php_version'] = array(
			'label' => __( 'PHP version is supported', 'simbe-ai-website-care' ),
			'test'  => array( __CLASS__, 'test_php_version' ),
		);
		$tests['direct']['simbe_care_debug_mode'] = array(
			'label' => __( 'Debug mode is disabled', 'simbe-ai-website-care' ),
			'test'  => array( __CLASS__, 'test_debug_mode' ),
		);
		$tests['direct']['simbe_care_https'] = array(
			'label' => __( 'Site is served over HTTPS', 'simbe-ai-website-care' ),
			'test'  => array( __CLASS__, 'test_https' ),
		);
		$tests['direct']['simbe_care_config_permissions'] = array(
			'label' => __( 'wp-config.php is protected', 'simbe-ai-website-care' ),
			'test'  => array( __CLASS__, 'test_config_permissions' ),
		);
		$tests['direct']['simbe_care_plugin_autoupdates'] = array(
			'label' => __( 'Plugin auto-updates are enabled', 'simbe-ai-website-care' ),
			'test'  => array( __CLASS__, 'test_plugin_autoupdates' ),
		);
		$tests['direct']['simbe_care_backups'] = array(
			'label' => __( 'A backup solution is active', 'simbe-ai-website-care' ),
			'test'  => array( __CLASS__, 'test_backups' ),
		);

		return $tests;
	}

	/**
	 * Plugin risk scan.
	 *
	 * @return array
	 */
	public static function test_plugin_scan() {
		$scan = Simbe_Care_Scanner::get_scan( false, true );

		if ( null === $scan ) {
			return self::result(
				'recommended',
				'blue',
				__( 'Plugin risk scan has not run yet', 'simbe-ai-website-care' ),
				__( 'Run a scan from the Simbe Care dashboard to see the first results.', 'simbe-ai-website-care' ),
				sprintf(
					'<a href="%s" class="button button-primary">%s</a>',
					esc_url( admin_url( 'admin.php?page=simbe-care' ) ),
					esc_html__( 'Run plugin scan', 'simbe-ai-website-care' )
				)
			);
		}

		$critical = isset( $scan['summary']['critical'] ) ? (int) $scan['summary']['critical'] : 0;
		$warning  = isset( $scan['summary']['warning'] ) ? (int) $scan['summary']['warning'] : 0;
		$total    = isset( $scan['summary']['total'] ) ? (int) $scan['summary']['total'] : 0;
		$scanned  = gmdate( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $scan['time'] );

		if ( $critical > 0 ) {
			return self::result(
				'critical',
				'red',
				sprintf(
					/* translators: %d: number of critical plugins. */
					__( '%d plugin(s) have critical risk indicators', 'simbe-ai-website-care' ),
					$critical
				),
				sprintf(
					/* translators: %1$d: critical count, %2$d: warning count, %3$d: total count, %4$s: scan time. */
					__( 'Simbe Care found %1$d critical and %2$d warning(s) across %3$d installed plugin(s) (scanned %4$s).', 'simbe-ai-website-care' ),
					$critical,
					$warning,
					$total,
					$scanned
				),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=simbe-care' ) ),
					esc_html__( 'Review the plugin health report', 'simbe-ai-website-care' )
				)
			);
		}

		if ( $warning > 0 ) {
			return self::result(
				'recommended',
				'orange',
				sprintf(
					/* translators: %d: number of plugin warnings. */
					__( '%d plugin(s) need attention', 'simbe-ai-website-care' ),
					$warning
				),
				sprintf(
					/* translators: %1$d: warning count, %2$d: total count, %3$s: scan time. */
					__( 'Simbe Care found %1$d plugin warning(s) across %2$d installed plugin(s) (scanned %3$s).', 'simbe-ai-website-care' ),
					$warning,
					$total,
					$scanned
				),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=simbe-care' ) ),
					esc_html__( 'Review the plugin health report', 'simbe-ai-website-care' )
				)
			);
		}

		return self::result(
			'good',
			'green',
			__( 'All plugins show no critical risk indicators', 'simbe-ai-website-care' ),
			sprintf(
				/* translators: %1$d: total count, %2$s: scan time. */
				__( 'Simbe Care found no risk indicators across %1$d installed plugin(s) (scanned %2$s).', 'simbe-ai-website-care' ),
				$total,
				$scanned
			),
			''
		);
	}

	/**
	 * PHP version test.
	 *
	 * @return array
	 */
	public static function test_php_version() {
		if ( version_compare( PHP_VERSION, '8.1', '>=' ) ) {
			return self::result(
				'good',
				'blue',
				__( 'PHP version is supported', 'simbe-ai-website-care' ),
				sprintf(
					/* translators: %s: PHP version. */
					__( 'PHP %s is supported by the WordPress community.', 'simbe-ai-website-care' ),
					PHP_VERSION
				),
				''
			);
		}

		return self::result(
			'recommended',
			'orange',
			__( 'PHP version is outdated', 'simbe-ai-website-care' ),
			sprintf(
				/* translators: %s: PHP version. */
				__( 'This site runs PHP %s, which is older than the recommended 8.1.', 'simbe-ai-website-care' ),
				PHP_VERSION
			),
			'<a href="https://wordpress.org/about/requirements/">' . esc_html__( 'WordPress PHP requirements', 'simbe-ai-website-care' ) . '</a>'
		);
	}

	/**
	 * Debug mode test.
	 *
	 * @return array
	 */
	public static function test_debug_mode() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return self::result(
				'recommended',
				'orange',
				__( 'Debug mode is enabled', 'simbe-ai-website-care' ),
				__( 'WP_DEBUG is enabled, which can expose sensitive information on a live site.', 'simbe-ai-website-care' ),
				''
			);
		}

		return self::result(
			'good',
			'green',
			__( 'Debug mode is disabled', 'simbe-ai-website-care' ),
			__( 'WP_DEBUG is off, which is correct for production sites.', 'simbe-ai-website-care' ),
			''
		);
	}

	/**
	 * HTTPS test.
	 *
	 * @return array
	 */
	public static function test_https() {
		if ( is_ssl() ) {
			return self::result(
				'good',
				'green',
				__( 'Site is served over HTTPS', 'simbe-ai-website-care' ),
				__( 'All traffic to this site is encrypted.', 'simbe-ai-website-care' ),
				''
			);
		}

		return self::result(
			'recommended',
			'orange',
			__( 'Site is not served over HTTPS', 'simbe-ai-website-care' ),
			__( 'Traffic between visitors and the site is not encrypted.', 'simbe-ai-website-care' ),
			''
		);
	}

	/**
	 * wp-config.php permissions test.
	 *
	 * @return array
	 */
	public static function test_config_permissions() {
		if ( wp_is_writable( ABSPATH . 'wp-config.php' ) ) {
			return self::result(
				'recommended',
				'orange',
				__( 'wp-config.php is writable', 'simbe-ai-website-care' ),
				__( 'The web server user can modify wp-config.php, which contains database credentials and secret keys.', 'simbe-ai-website-care' ),
				''
			);
		}

		return self::result(
			'good',
			'green',
			__( 'wp-config.php is protected', 'simbe-ai-website-care' ),
			__( 'The web server cannot write to wp-config.php.', 'simbe-ai-website-care' ),
			''
		);
	}

	/**
	 * Plugin auto-updates test.
	 *
	 * @return array
	 */
	public static function test_plugin_autoupdates() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$auto_update_option = 'auto_update_' . 'plugins';
		$auto               = get_site_option( $auto_update_option, array() );
		$active             = array();

		foreach ( (array) $auto as $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$active[] = $plugin_file;
			}
		}

		if ( ! empty( $active ) ) {
			return self::result(
				'good',
				'green',
				__( 'Plugin auto-updates are enabled', 'simbe-ai-website-care' ),
				sprintf(
					/* translators: %d: number of plugins. */
					__( '%d plugin(s) have automatic updates enabled.', 'simbe-ai-website-care' ),
					count( $active )
				),
				''
			);
		}

		return self::result(
			'recommended',
			'orange',
			__( 'No plugin auto-updates are enabled', 'simbe-ai-website-care' ),
			__( 'Plugins must be updated manually, which can leave known-vulnerable versions active for longer.', 'simbe-ai-website-care' ),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'plugins.php?plugin_status=all&auto_update=on' ) ),
				esc_html__( 'Enable auto-updates', 'simbe-ai-website-care' )
			)
		);
	}

	/**
	 * Backup test.
	 *
	 * @return array
	 */
	public static function test_backups() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$markers = Simbe_Care_Issues::BACKUP_MARKERS;
		$found   = array();

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			if ( ! is_plugin_active( $plugin_file ) ) {
				continue;
			}
			$slug = dirname( $plugin_file );
			$name = strtolower( isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '' );
			foreach ( $markers as $marker ) {
				if ( false !== strpos( $slug, $marker ) || false !== strpos( $name, $marker ) ) {
					$found[] = $plugin_data['Name'];
					break;
				}
			}
		}

		if ( ! empty( $found ) ) {
			return self::result(
				'good',
				'green',
				__( 'A backup solution is active', 'simbe-ai-website-care' ),
				sprintf(
					/* translators: %s: detected plugins. */
					__( 'Detected backup capability: %s', 'simbe-ai-website-care' ),
					implode( ', ', $found )
				),
				''
			);
		}

		return self::result(
			'recommended',
			'orange',
			__( 'No backup plugin detected', 'simbe-ai-website-care' ),
			__( 'No known backup solution is active. Off-site backups are strongly recommended before any update.', 'simbe-ai-website-care' ),
			''
		);
	}

	/**
	 * Builds a Site Health result array.
	 *
	 * @param string $status      good|recommended|critical.
	 * @param string $color       Badge color.
	 * @param string $label       Result label.
	 * @param string $description Result description.
	 * @param string $actions     HTML actions.
	 * @return array
	 */
	private static function result( $status, $color, $label, $description, $actions ) {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Simbe Care', 'simbe-ai-website-care' ),
				'color' => $color,
			),
			'description' => $description,
			'actions'     => $actions,
			'test'        => 'simbe_care_' . substr( md5( $label ), 0, 8 ),
			'error'       => false,
		);
	}
}
