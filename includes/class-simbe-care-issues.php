<?php
/**
 * Detects common WordPress configuration issues.
 *
 * Each check returns an array with: id, severity (ok|warning|critical|info),
 * title, description and recommendation.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Simbe_Care_Issues
 */
class Simbe_Care_Issues {

	/**
	 * Known backup/security plugin identifiers used by the backup check.
	 *
	 * @var string[]
	 */
	const BACKUP_MARKERS = array(
		'updraftplus',
		'backwpup',
		'backupbuddy',
		'duplicator',
		'vaultpress',
		'blogvault',
		'wp-time-capsule',
		'solid-backups',
		'snapshot',
		'all-in-one-wp-migration',
		'backup',
	);

	/**
	 * Returns all common issues.
	 *
	 * @return array[]
	 */
	public static function get_issues() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array(
			self::check_debug_mode(),
			self::check_debug_log(),
			self::check_file_edit(),
			self::check_file_mods(),
			self::check_core_autoupdates(),
			self::check_plugin_autoupdates(),
			self::check_backups(),
			self::check_wp_cron(),
			self::check_php_version(),
			self::check_mysql_version(),
			self::check_https(),
			self::check_config_permissions(),
			self::check_core_updates(),
			self::check_memory_limit(),
			self::check_unused_themes(),
			self::check_security_headers(),
			self::check_exposed_files(),
		);
	}

	/**
	 * WP_DEBUG should be disabled in production.
	 *
	 * @return array
	 */
	private static function check_debug_mode() {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( ! $debug ) {
			return self::pass( 'debug_mode', __( 'Debug mode (WP_DEBUG) is disabled.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'debug_mode',
			'severity'       => 'warning',
			'title'          => __( 'Debug mode is enabled', 'simbe-ai-website-care' ),
			'description'    => __( 'WP_DEBUG is enabled, which can expose sensitive information and slow down production sites.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'In wp-config.php set define( \'WP_DEBUG\', false ); for production sites.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * A public debug.log file can leak data.
	 *
	 * @return array
	 */
	private static function check_debug_log() {
		$logging   = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		$log_path  = WP_CONTENT_DIR . '/debug.log';
		$has_log   = $logging && @is_file( $log_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $has_log ) {
			return self::pass( 'debug_log', __( 'No debug.log file is being written.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'debug_log',
			'severity'       => 'warning',
			'title'          => __( 'A debug.log file exists on your site', 'simbe-ai-website-care' ),
			'description'    => __( 'The wp-content/debug.log file may contain errors and internal paths that should not be publicly accessible.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Disable WP_DEBUG_LOG in production and remove the debug.log file.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * Plugin/theme file editing should be disabled.
	 *
	 * @return array
	 */
	private static function check_file_edit() {
		$disabled = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;

		if ( $disabled ) {
			return self::pass( 'file_edit', __( 'Plugin and theme file editing is disabled.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'file_edit',
			'severity'       => 'info',
			'title'          => __( 'Plugin and theme editors are enabled', 'simbe-ai-website-care' ),
			'description'    => __( 'The built-in file editors let any user who can edit files modify plugin and theme code from the admin area.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Add define( \'DISALLOW_FILE_EDIT\', true ); to wp-config.php.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * Installing/updating files from the admin can be restricted.
	 *
	 * @return array
	 */
	private static function check_file_mods() {
		$disabled = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;

		if ( $disabled ) {
			return self::pass( 'file_mods', __( 'File modifications from the admin area are disabled.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'file_mods',
			'severity'       => 'info',
			'title'          => __( 'Automatic file modifications are enabled', 'simbe-ai-website-care' ),
			'description'    => __( 'DISALLOW_FILE_MODS is not set, so plugins, themes and core can be modified from the admin area.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Consider adding define( \'DISALLOW_FILE_MODS\', true ); and managing deployments via FTP, Git or WP-CLI instead.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * Core automatic updates.
	 *
	 * @return array
	 */
	private static function check_core_autoupdates() {
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			$setting = WP_AUTO_UPDATE_CORE;
			if ( true === $setting || 'true' === $setting || 'minor' === $setting ) {
				return self::pass( 'core_autoupdates', __( 'Automatic WordPress core updates are enabled.', 'simbe-ai-website-care' ) );
			}

			return array(
				'id'             => 'core_autoupdates',
				'severity'       => 'warning',
				'title'          => __( 'Automatic core updates are disabled', 'simbe-ai-website-care' ),
				'description'    => __( 'WP_AUTO_UPDATE_CORE is set to false, so security releases must be installed manually.', 'simbe-ai-website-care' ),
				'recommendation' => __( 'Enable minor security updates, e.g. define( \'WP_AUTO_UPDATE_CORE\', true );', 'simbe-ai-website-care' ),
			);
		}

		// Default since WordPress 5.6: minor core security releases auto-update.
		return self::pass( 'core_autoupdates', __( 'Minor core security releases auto-update (WordPress default).', 'simbe-ai-website-care' ) );
	}

	/**
	 * Plugin automatic updates.
	 *
	 * @return array
	 */
	private static function check_plugin_autoupdates() {
		$auto_update_option = 'auto_update_' . 'plugins';
		$auto               = get_site_option( $auto_update_option, array() );
		$active             = array();
		foreach ( (array) $auto as $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$active[] = $plugin_file;
			}
		}

		if ( ! empty( $active ) ) {
			return self::pass(
				'plugin_autoupdates',
				sprintf(
					/* translators: %d: number of plugins with auto-updates enabled. */
					__( '%d plugin(s) have automatic updates enabled.', 'simbe-ai-website-care' ),
					count( $active )
				)
			);
		}

		return array(
			'id'             => 'plugin_autoupdates',
			'severity'       => 'info',
			'title'          => __( 'No plugin auto-updates are enabled', 'simbe-ai-website-care' ),
			'description'    => __( 'Plugins must be updated manually, which can leave known-vulnerable versions active for longer.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Enable auto-updates for security-sensitive plugins from Plugins > Installed Plugins, or ask your developer to manage plugin auto-updates with a code snippet.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * A backup solution should be present.
	 *
	 * @return array
	 */
	private static function check_backups() {
		$found = array();

		foreach ( get_plugins() as $plugin_file => $plugin_data ) {
			if ( ! is_plugin_active( $plugin_file ) ) {
				continue;
			}

			$slug = dirname( $plugin_file );
			$name = strtolower( isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '' );

			foreach ( self::BACKUP_MARKERS as $marker ) {
				if ( false !== strpos( $slug, $marker ) || false !== strpos( $name, $marker ) ) {
					$found[] = $plugin_data['Name'];
					break;
				}
			}
		}

		if ( ! empty( $found ) ) {
			return self::pass(
				'backups',
				sprintf(
					/* translators: %s: detected backup plugin names. */
					__( 'Backup capability detected: %s', 'simbe-ai-website-care' ),
					implode( ', ', $found )
				)
			);
		}

		return array(
			'id'             => 'backups',
			'severity'       => 'info',
			'title'          => __( 'No backup plugin detected', 'simbe-ai-website-care' ),
			'description'    => __( 'No known backup solution is active on this site. Off-site backups are strongly recommended before any update.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Install and configure a backup plugin such as UpdraftPlus and verify restore tests periodically.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * WP-Cron should not be disabled without an external cron.
	 *
	 * @return array
	 */
	private static function check_wp_cron() {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		if ( ! $disabled ) {
			return self::pass( 'wp_cron', __( 'WP-Cron is enabled.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'wp_cron',
			'severity'       => 'warning',
			'title'          => __( 'WP-Cron is disabled', 'simbe-ai-website-care' ),
			'description'    => __( 'DISABLE_WP_CRON is set to true. Scheduled tasks (updates, backups) will only run if a server cron calls wp-cron.php.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Make sure a real server cron job calls wp-cron.php regularly, otherwise scheduled maintenance will not run.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * PHP version should be current.
	 *
	 * @return array
	 */
	private static function check_php_version() {
		if ( version_compare( PHP_VERSION, '8.1', '>=' ) ) {
			return self::pass(
				'php_version',
				sprintf(
					/* translators: %s: PHP version. */
					__( 'PHP %s is supported by the WordPress community.', 'simbe-ai-website-care' ),
					PHP_VERSION
				)
			);
		}

		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			return array(
				'id'             => 'php_version',
				'severity'       => 'critical',
				'title'          => __( 'PHP version is unsupported', 'simbe-ai-website-care' ),
				'description'    => sprintf(
					/* translators: %s: PHP version. */
					__( 'This site runs PHP %s, which no longer receives security fixes.', 'simbe-ai-website-care' ),
					PHP_VERSION
				),
				'recommendation' => __( 'Upgrade PHP to a supported version (8.1 or newer) as soon as possible.', 'simbe-ai-website-care' ),
			);
		}

		return array(
			'id'             => 'php_version',
			'severity'       => 'warning',
			'title'          => __( 'PHP version is outdated', 'simbe-ai-website-care' ),
			'description'    => sprintf(
				/* translators: %s: PHP version. */
				__( 'This site runs PHP %s, which is older than the recommended 8.1.', 'simbe-ai-website-care' ),
				PHP_VERSION
			),
			'recommendation' => __( 'Ask your host to upgrade to PHP 8.1 or newer and test the site afterwards.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * MySQL/MariaDB version should be current.
	 *
	 * @return array
	 */
	private static function check_mysql_version() {
		global $wpdb;

		$version = (string) $wpdb->db_version();
		$mariadb = ( false !== stripos( $version, 'MariaDB' ) );
		$number  = preg_replace( '/[^0-9.].*/', '', $version );

		if ( $mariadb ) {
			$minimum = '10.3';
		} else {
			$minimum = '5.7';
		}

		if ( ! $number || version_compare( $number, $minimum, '<' ) ) {
			return array(
				'id'             => 'mysql_version',
				'severity'       => 'warning',
				'title'          => __( 'Database version is outdated', 'simbe-ai-website-care' ),
				'description'    => sprintf(
					/* translators: %1$s: current DB version, %2$s: minimum version. */
					__( 'This site runs %1$s, below the recommended minimum of %2$s.', 'simbe-ai-website-care' ),
					$version,
					$minimum
				),
				'recommendation' => __( 'Contact your host to upgrade MySQL/MariaDB to a supported version.', 'simbe-ai-website-care' ),
			);
		}

		return self::pass(
			'mysql_version',
			sprintf(
				/* translators: %s: DB version. */
				__( 'Database version %s is supported.', 'simbe-ai-website-care' ),
				$version
			)
		);
	}

	/**
	 * The site should be served over HTTPS.
	 *
	 * @return array
	 */
	private static function check_https() {
		if ( is_ssl() ) {
			return self::pass( 'https', __( 'This site is served over HTTPS.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'https',
			'severity'       => 'warning',
			'title'          => __( 'This site is not served over HTTPS', 'simbe-ai-website-care' ),
			'description'    => __( 'Traffic between visitors and the site is not encrypted.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Install an SSL certificate and force HTTPS (e.g. via a security plugin or .htaccess).', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * wp-config.php should not be writable by the web server.
	 *
	 * @return array
	 */
	private static function check_config_permissions() {
		$config  = ABSPATH . 'wp-config.php';
		$writable = wp_is_writable( $config );

		if ( ! $writable ) {
			return self::pass( 'config_permissions', __( 'wp-config.php is not writable.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'config_permissions',
			'severity'       => 'warning',
			'title'          => __( 'wp-config.php is writable', 'simbe-ai-website-care' ),
			'description'    => __( 'The web server user can modify wp-config.php, which contains your database credentials and secret keys.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Restrict permissions on wp-config.php (e.g. chmod 400 or 440) after making changes.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * WordPress core should be up to date.
	 *
	 * @return array
	 */
	private static function check_core_updates() {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates = get_core_updates();
		$pending = false;

		if ( is_array( $updates ) ) {
			foreach ( $updates as $update ) {
				if ( isset( $update->response ) && 'upgrade' === $update->response ) {
					$pending = true;
					break;
				}
			}
		}

		if ( ! $pending ) {
			return self::pass(
				'core_updates',
				sprintf(
					/* translators: %s: WordPress version. */
					__( 'WordPress %s is up to date.', 'simbe-ai-website-care' ),
					get_bloginfo( 'version' )
				)
			);
		}

		return array(
			'id'             => 'core_updates',
			'severity'       => 'warning',
			'title'          => __( 'WordPress core update available', 'simbe-ai-website-care' ),
			'description'    => __( 'A newer version of WordPress core is available and should be installed.', 'simbe-ai-website-care' ),
			'recommendation' => __( 'Run a backup, then update WordPress core from the Updates screen.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * PHP memory limit should be reasonable.
	 *
	 * @return array
	 */
	private static function check_memory_limit() {
		$limit = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
		$bytes = wp_convert_hr_to_bytes( $limit );

		if ( $bytes >= 128 * MB_IN_BYTES ) {
			return self::pass(
				'memory_limit',
				sprintf(
					/* translators: %s: memory limit. */
					__( 'WordPress memory limit is %s.', 'simbe-ai-website-care' ),
					$limit
				)
			);
		}

		return array(
			'id'             => 'memory_limit',
			'severity'       => 'info',
			'title'          => __( 'WordPress memory limit is low', 'simbe-ai-website-care' ),
			'description'    => sprintf(
				/* translators: %s: memory limit. */
				__( 'WP_MEMORY_LIMIT is set to %s. Complex plugins and page builders may run out of memory.', 'simbe-ai-website-care' ),
				$limit
			),
			'recommendation' => __( 'Increase the limit in wp-config.php: define( \'WP_MEMORY_LIMIT\', \'256M\' );', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * Unused themes can be a maintenance/attack surface.
	 *
	 * @return array
	 */
	private static function check_unused_themes() {
		$themes = wp_get_themes();
		$active = wp_get_theme();

		if ( count( $themes ) < 2 ) {
			return self::pass( 'unused_themes', __( 'No extra themes are installed.', 'simbe-ai-website-care' ) );
		}

		$inactive = 0;
		foreach ( $themes as $slug => $theme ) {
			if ( $slug === $active->get_stylesheet() || $slug === $active->get_template() ) {
				continue;
			}
			if ( $theme->get( 'Version' ) ) {
				$inactive++;
			}
		}

		if ( 0 === $inactive ) {
			return self::pass( 'unused_themes', __( 'No unused themes are installed.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'unused_themes',
			'severity'       => 'info',
			'title'          => __( 'Unused themes are installed', 'simbe-ai-website-care' ),
			'description'    => sprintf(
				/* translators: %d: number of unused themes. */
				__( '%d inactive theme(s) are installed. Unused code increases the potential attack surface.', 'simbe-ai-website-care' ),
				$inactive
			),
			'recommendation' => __( 'Delete themes that are no longer in use from Appearance > Themes.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * Security headers should be present on the site.
	 *
	 * Makes a loopback HEAD request and verifies common hardening headers.
	 * Results are cached for an hour so the check is not performed on every view.
	 *
	 * @return array
	 */
	private static function check_security_headers() {
		$headers = self::fetch_security_headers();

		if ( null === $headers ) {
			return array(
				'id'             => 'security_headers',
				'severity'       => 'info',
				'title'          => __( 'Security headers could not be verified', 'simbe-ai-website-care' ),
				'description'    => __( 'The plugin could not reach this site over HTTP to inspect its response headers, so the security headers check was skipped.', 'simbe-ai-website-care' ),
				'recommendation' => __( 'Confirm the site responds correctly over HTTP(S) and run the check again.', 'simbe-ai-website-care' ),
			);
		}

		$missing = array();

		if ( is_ssl() && empty( $headers['strict-transport-security'] ) ) {
			$missing[] = __( 'Strict-Transport-Security (HSTS)', 'simbe-ai-website-care' );
		}

		if ( empty( $headers['x-content-type-options'] ) || false === stripos( (string) $headers['x-content-type-options'], 'nosniff' ) ) {
			$missing[] = 'X-Content-Type-Options: nosniff';
		}

		$frame_protected = ! empty( $headers['x-frame-options'] )
			|| ( ! empty( $headers['content-security-policy'] ) && false !== stripos( (string) $headers['content-security-policy'], 'frame-ancestors' ) );

		if ( ! $frame_protected ) {
			$missing[] = __( 'frame protection (X-Frame-Options or CSP frame-ancestors)', 'simbe-ai-website-care' );
		}

		if ( empty( $headers['referrer-policy'] ) ) {
			$missing[] = 'Referrer-Policy';
		}

		if ( empty( $headers['content-security-policy'] ) ) {
			$missing[] = 'Content-Security-Policy';
		}

		if ( empty( $missing ) ) {
			return self::pass( 'security_headers', __( 'Security headers are present.', 'simbe-ai-website-care' ) );
		}

		return array(
			'id'             => 'security_headers',
			'severity'       => 'warning',
			'title'          => sprintf(
				/* translators: %d: number of missing security headers. */
				_n( 'A security header is missing or weak', '%d security headers are missing or weak', count( $missing ), 'simbe-ai-website-care' ),
				count( $missing )
			),
			'description'    => sprintf(
				/* translators: %s: comma-separated list of missing headers. */
				__( 'The following security headers were not found: %s.', 'simbe-ai-website-care' ),
				implode( ', ', $missing )
			),
			'recommendation' => __( 'Add the missing headers at the server level (e.g. .htaccess, your nginx/Apache config, or a security plugin such as Really Simple SSL).', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * Publicly accessible sensitive files can leak data.
	 *
	 * Looks for debug.log files, database dumps in web-accessible directories,
	 * and backup copies of wp-config.php in the site root.
	 *
	 * @return array
	 */
	private static function check_exposed_files() {
		$found = array();

		$log_path = WP_CONTENT_DIR . '/debug.log';
		if ( @is_file( $log_path ) && is_readable( $log_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$found[] = 'wp-content/debug.log';
		}

		$uploads = wp_upload_dir();
		$dirs    = array_unique(
			array(
				WP_CONTENT_DIR,
				isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR,
			)
		);

		foreach ( $dirs as $dir ) {
			foreach ( array( '*.sql', '*.sql.gz', '*.db' ) as $pattern ) {
				foreach ( (array) glob( $dir . '/' . $pattern ) as $file ) {
					$found[] = str_replace( ABSPATH, '', wp_normalize_path( $file ) );
				}
			}
		}

		foreach ( (array) glob( ABSPATH . 'wp-config.*' ) as $file ) {
			$name = wp_basename( $file );
			if ( in_array( $name, array( 'wp-config.php', 'wp-config-sample.php' ), true ) ) {
				continue;
			}
			$found[] = $name;
		}

		$found = array_values( array_unique( $found ) );

		if ( empty( $found ) ) {
			return self::pass( 'exposed_files', __( 'No exposed sensitive files were found.', 'simbe-ai-website-care' ) );
		}

		$display  = array_slice( $found, 0, 5 );
		$omitted  = count( $found ) - count( $display );

		return array(
			'id'             => 'exposed_files',
			'severity'       => 'warning',
			'title'          => sprintf(
				/* translators: %d: number of exposed files. */
				_n( 'An exposed sensitive file was found', '%d exposed sensitive files were found', count( $found ), 'simbe-ai-website-care' ),
				count( $found )
			),
			'description'    => sprintf(
				/* translators: 1: list of exposed files, 2: number of additional files not listed. */
				__( 'These files may be publicly accessible and can leak data: %1$s%2$s', 'simbe-ai-website-care' ),
				implode( ', ', $display ),
				0 < $omitted ? sprintf( /* translators: %d: number of omitted files. */ __( ' (and %d more)', 'simbe-ai-website-care' ), $omitted ) : ''
			),
			'recommendation' => __( 'Remove database dumps and debug logs from web-accessible directories and store them outside the document root or in a private backup location.', 'simbe-ai-website-care' ),
		);
	}

	/**
	 * Fetches and normalizes the site's response headers.
	 *
	 * @return string[]|null Headers keyed by lowercase name, or null on failure.
	 */
	private static function fetch_security_headers() {
		$key    = 'simbe_care_headers_' . md5( home_url( '/' ) );
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_head(
			home_url( '/' ),
			array(
				'timeout'    => 10,
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$all    = wp_remote_retrieve_headers( $response );
		$flat   = array();
		foreach ( $all as $name => $value ) {
			$flat[ strtolower( $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}

		set_transient( $key, $flat, HOUR_IN_SECONDS );

		return $flat;
	}

	/**
	 * Builds a "passed" check result.
	 *
	 * @param string $id   Check id.
	 * @param string $desc Pass description.
	 * @return array
	 */
	private static function pass( $id, $desc ) {
		return array(
			'id'             => $id,
			'severity'       => 'ok',
			'title'          => $desc,
			'description'    => '',
			'recommendation' => '',
		);
	}
}
