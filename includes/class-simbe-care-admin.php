<?php
/**
 * Admin dashboard, settings, AJAX and dashboard widget.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Simbe_Care_Admin
 */
class Simbe_Care_Admin {

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_simbe_care_rescan', array( __CLASS__, 'ajax_rescan' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Registers the admin menu page.
	 */
	public static function admin_menu() {
		add_menu_page(
			__( 'Simbe AI Website Care', 'simbe-ai-website-care' ),
			__( 'Simbe Care', 'simbe-ai-website-care' ),
			'manage_options',
			'simbe-care',
			array( __CLASS__, 'render_page' ),
			'dashicons-heart',
			80
		);
	}

	/**
	 * Enqueues assets only on our page and the dashboard.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function admin_enqueue_scripts( $hook ) {
		$screen = get_current_screen();
		$is_ours = $screen && 'toplevel_page_simbe-care' === $screen->id;

		if ( $is_ours || 'dashboard' === $hook ) {
			wp_enqueue_style( 'simbe-care-admin', SIMBE_CARE_PLUGIN_URL . 'assets/css/admin.css', array(), SIMBE_CARE_VERSION );
		}

		if ( $is_ours ) {
			wp_enqueue_script( 'simbe-care-admin', SIMBE_CARE_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SIMBE_CARE_VERSION, true );
			wp_localize_script(
				'simbe-care-admin',
				'SimbeCare',
				array(
					'nonce'    => wp_create_nonce( 'simbe_care_rescan' ),
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'i18n'     => array(
						'scanning'    => __( 'Scanning…', 'simbe-ai-website-care' ),
						'scan_failed' => __( 'The scan failed. Please try again.', 'simbe-ai-website-care' ),
					),
				)
			);
		}
	}

	/**
	 * AJAX handler: force a fresh scan.
	 */
	public static function ajax_rescan() {
		check_ajax_referer( 'simbe_care_rescan', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'simbe-ai-website-care' ) ), 403 );
		}

		$scan = Simbe_Care_Scanner::refresh_wporg_data();

		wp_send_json_success(
			array(
				'time'     => isset( $scan['time'] ) ? $scan['time'] : time(),
				'summary'  => isset( $scan['summary'] ) ? $scan['summary'] : array(),
				'message'  => __( 'Scan complete.', 'simbe-ai-website-care' ),
			)
		);
	}

	/**
	 * Registers settings for the dashboard settings section.
	 */
	public static function register_settings() {
		register_setting(
			'simbe_care',
			Simbe_Care::SETTINGS_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'simbe_care_settings_section',
			__( 'Scan settings', 'simbe-ai-website-care' ),
			'__return_false',
			'simbe-care'
		);

		add_settings_field(
			'abandoned_days',
			__( 'Flag plugins as abandoned after', 'simbe-ai-website-care' ),
			array( __CLASS__, 'field_abandoned_days' ),
			'simbe-care',
			'simbe_care_settings_section'
		);

		add_settings_field(
			'wporg_ttl_hours',
			__( 'Check WordPress.org every', 'simbe-ai-website-care' ),
			array( __CLASS__, 'field_wporg_ttl' ),
			'simbe-care',
			'simbe_care_settings_section'
		);

		add_settings_field(
			'schedule',
			__( 'Background scan frequency', 'simbe-ai-website-care' ),
			array( __CLASS__, 'field_schedule' ),
			'simbe-care',
			'simbe_care_settings_section'
		);

		add_settings_field(
			'notify_email',
			__( 'Alert email', 'simbe-ai-website-care' ),
			array( __CLASS__, 'field_notify_email' ),
			'simbe-care',
			'simbe_care_settings_section'
		);
	}

	/**
	 * Sanitizes settings.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$defaults = Simbe_Care::default_settings();
		$old      = Simbe_Care::get_settings();
		$input    = is_array( $input ) ? $input : array();

		$output = array(
			'abandoned_days'  => isset( $input['abandoned_days'] ) ? max( 90, min( 3650, absint( $input['abandoned_days'] ) ) ) : $defaults['abandoned_days'],
			'wporg_ttl_hours' => in_array( (int) $input['wporg_ttl_hours'], array( 1, 6, 12, 24, 48 ), true ) ? (int) $input['wporg_ttl_hours'] : $defaults['wporg_ttl_hours'],
			'schedule'        => in_array( $input['schedule'], array( 'daily', 'weekly' ), true ) ? $input['schedule'] : $defaults['schedule'],
			'notify_email'    => self::sanitize_email_list( isset( $input['notify_email'] ) ? $input['notify_email'] : '' ),
			'last_scan'       => isset( $old['last_scan'] ) ? (int) $old['last_scan'] : 0,
		);

		if ( $output['schedule'] !== $old['schedule'] ) {
			Simbe_Care::reschedule_scan( $output['schedule'] );
		}

		return $output;
	}

	/**
	 * Settings field: abandoned days.
	 */
	public static function field_abandoned_days() {
		$settings = Simbe_Care::get_settings();
		printf(
			'<input type="number" min="90" max="3650" step="1" name="%1$s[abandoned_days]" value="%2$d" class="small-text" /> %3$s',
			esc_attr( Simbe_Care::SETTINGS_KEY ),
			(int) $settings['abandoned_days'],
			esc_html__( 'days without an update', 'simbe-ai-website-care' )
		);
	}

	/**
	 * Settings field: WordPress.org cache TTL.
	 */
	public static function field_wporg_ttl() {
		$settings = Simbe_Care::get_settings();
		$options  = array(
			1  => __( '1 hour', 'simbe-ai-website-care' ),
			6  => __( '6 hours', 'simbe-ai-website-care' ),
			12 => __( '12 hours', 'simbe-ai-website-care' ),
			24 => __( '1 day', 'simbe-ai-website-care' ),
			48 => __( '2 days', 'simbe-ai-website-care' ),
		);

		echo '<select name="' . esc_attr( Simbe_Care::SETTINGS_KEY ) . '[wporg_ttl_hours]">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $value,
				selected( (int) $settings['wporg_ttl_hours'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Settings field: scan schedule.
	 */
	public static function field_schedule() {
		$settings = Simbe_Care::get_settings();
		$options  = array(
			'daily'  => __( 'Daily', 'simbe-ai-website-care' ),
			'weekly' => __( 'Weekly', 'simbe-ai-website-care' ),
		);

		echo '<select name="' . esc_attr( Simbe_Care::SETTINGS_KEY ) . '[schedule]">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $settings['schedule'], $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Settings field: notification email(s).
	 */
	public static function field_notify_email() {
		$settings = Simbe_Care::get_settings();
		echo '<input type="email" name="' . esc_attr( Simbe_Care::SETTINGS_KEY ) . '[notify_email]" value="' . esc_attr( $settings['notify_email'] ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Leave empty to disable. Separate multiple addresses with a comma. A digest is sent after a scan when new critical plugin or theme issues are found.', 'simbe-ai-website-care' ) . '</p>';
	}

	/**
	 * Sanitizes a comma-separated list of email addresses.
	 *
	 * @param string $raw Raw input.
	 * @return string
	 */
	private static function sanitize_email_list( $raw ) {
		$emails = array();
		foreach ( explode( ',', (string) $raw ) as $email ) {
			$email = sanitize_email( trim( $email ) );
			if ( $email && ! in_array( $email, $emails, true ) ) {
				$emails[] = $email;
			}
		}
		return implode( ',', $emails );
	}

	/**
	 * Renders the main dashboard page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$scan    = Simbe_Care_Scanner::get_scan();
		$site    = self::get_site_info();
		$issues  = Simbe_Care_Issues::get_issues();
		$last    = ( $scan && ! empty( $scan['time'] ) ) ? $scan['time'] : 0;

		echo '<div class="wrap simbe-care-wrap">';
		self::render_header( $last );
		self::render_overview( $site );
		self::render_plugins( $scan );
		self::render_themes( $scan );
		self::render_issues( $issues );
		self::render_settings();
		echo '</div>';
	}

	/**
	 * Renders the page header.
	 *
	 * @param int $last_scan Unix timestamp of last scan.
	 */
	private static function render_header( $last_scan ) {
		echo '<div class="simbe-care-header">';
		echo '<div class="simbe-care-title">';
		echo '<span class="dashicons dashicons-heart simbe-care-logo" aria-hidden="true"></span>';
		echo '<div>';
		echo '<h1>' . esc_html__( 'Simbe AI Website Care', 'simbe-ai-website-care' ) . '</h1>';
		echo '<p class="simbe-care-subtitle">' . esc_html__( 'Plugin health and common issue checks for WordPress sites.', 'simbe-ai-website-care' ) . '</p>';
		echo '</div></div>';

		echo '<div class="simbe-care-actions">';
		if ( $last_scan ) {
			echo '<span class="simbe-care-last">' . sprintf(
				/* translators: %s: formatted scan time. */
				esc_html__( 'Last scanned: %s', 'simbe-ai-website-care' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_scan ) )
			) . '</span>';
		}
		echo '<button type="button" class="button button-primary simbe-care-rescan" data-action="rescan">' . esc_html__( 'Scan now', 'simbe-ai-website-care' ) . '</button>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Renders the site overview cards.
	 *
	 * @param array $site Site info.
	 */
	private static function render_overview( $site ) {
		echo '<div class="simbe-care-cards">';

		$cards = array(
			array(
				'label' => __( 'WordPress', 'simbe-ai-website-care' ),
				'value' => $site['wp_version'],
				'icon'  => 'dashicons-wordpress',
			),
			array(
				'label' => __( 'PHP', 'simbe-ai-website-care' ),
				'value' => $site['php_version'],
				'icon'  => 'dashicons-database',
			),
			array(
				'label' => __( 'Database', 'simbe-ai-website-care' ),
				'value' => $site['mysql_version'],
				'icon'  => 'dashicons-storage',
			),
			array(
				'label' => __( 'Plugins', 'simbe-ai-website-care' ),
				'value' => $site['plugins_total'],
				'icon'  => 'dashicons-admin-plugins',
			),
			array(
				'label' => __( 'Themes', 'simbe-ai-website-care' ),
				'value' => $site['themes_total'],
				'icon'  => 'dashicons-admin-appearance',
			),
			array(
				'label' => __( 'Updates pending', 'simbe-ai-website-care' ),
				'value' => (int) $site['core_updates'] + (int) $site['plugin_updates'] + (int) $site['theme_updates'],
				'icon'  => 'dashicons-update',
			),
		);

		foreach ( $cards as $card ) {
			echo '<div class="simbe-care-card">';
			echo '<span class="dashicons ' . esc_attr( $card['icon'] ) . '" aria-hidden="true"></span>';
			echo '<div>';
			echo '<div class="simbe-care-card-value">' . esc_html( $card['value'] ) . '</div>';
			echo '<div class="simbe-care-card-label">' . esc_html( $card['label'] ) . '</div>';
			echo '</div></div>';
		}

		echo '</div>';

		if ( ! $site['https'] ) {
			echo '<div class="simbe-care-note simbe-care-note-warn">' . esc_html__( 'This site is not served over HTTPS.', 'simbe-ai-website-care' ) . '</div>';
		}
	}

	/**
	 * Renders the plugin health table.
	 *
	 * @param array $scan Scan results.
	 */
	private static function render_plugins( $scan ) {
		self::render_asset_section(
			__( 'Plugin security scan', 'simbe-ai-website-care' ),
			__( 'Indicators are derived from WordPress.org data: directory status, update availability, last activity, and compatibility requirements. For a complete CVE database, consider a dedicated vulnerability scanner (e.g. WPScan).', 'simbe-ai-website-care' ),
			( $scan && isset( $scan['summary'] ) ) ? $scan['summary'] : array(),
			( $scan && isset( $scan['plugins'] ) ) ? $scan['plugins'] : array(),
			__( 'Plugin', 'simbe-ai-website-care' ),
			__( 'No plugin data yet. Run a scan.', 'simbe-ai-website-care' )
		);
	}

	/**
	 * Renders the theme health table.
	 *
	 * @param array $scan Scan results.
	 */
	private static function render_themes( $scan ) {
		self::render_asset_section(
			__( 'Theme security scan', 'simbe-ai-website-care' ),
			__( 'Indicators are derived from WordPress.org data: directory status, update availability, last activity, and compatibility requirements. For a complete CVE database, consider a dedicated vulnerability scanner (e.g. WPScan).', 'simbe-ai-website-care' ),
			( $scan && isset( $scan['theme_summary'] ) ) ? $scan['theme_summary'] : array(),
			( $scan && isset( $scan['themes'] ) ) ? $scan['themes'] : array(),
			__( 'Theme', 'simbe-ai-website-care' ),
			__( 'No theme data yet. Run a scan.', 'simbe-ai-website-care' )
		);
	}

	/**
	 * Renders a generic plugin/theme health table.
	 *
	 * @param string $title      Section title.
	 * @param string $note       Explanatory note.
	 * @param array  $summary    Risk summary counts.
	 * @param array  $entries    Item assessments.
	 * @param string $item_label Singular item label for the table header.
	 * @param string $empty_text Text shown when there are no entries.
	 */
	private static function render_asset_section( $title, $note, $summary, $entries, $item_label, $empty_text ) {
		echo '<div class="simbe-care-section">';
		echo '<div class="simbe-care-section-head">';
		echo '<h2>' . esc_html( $title ) . '</h2>';

		if ( $summary ) {
			echo '<div class="simbe-care-summary">';
			echo '<span class="simbe-care-summary-item is-critical">' . esc_html( (int) $summary['critical'] ) . ' ' . esc_html__( 'critical', 'simbe-ai-website-care' ) . '</span>';
			echo '<span class="simbe-care-summary-item is-warning">' . esc_html( (int) $summary['warning'] ) . ' ' . esc_html__( 'warning', 'simbe-ai-website-care' ) . '</span>';
			echo '<span class="simbe-care-summary-item is-ok">' . esc_html( (int) $summary['ok'] ) . ' ' . esc_html__( 'ok', 'simbe-ai-website-care' ) . '</span>';
			echo '</div>';
		}

		echo '</div>';

		echo '<div class="simbe-care-note">' . esc_html( $note ) . '</div>';

		if ( empty( $entries ) ) {
			echo '<p class="simbe-care-empty">' . esc_html( $empty_text ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped simbe-care-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html( $item_label ) . '</th>';
		echo '<th>' . esc_html__( 'Risk', 'simbe-ai-website-care' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'simbe-ai-website-care' ) . '</th>';
		echo '<th>' . esc_html__( 'Indicators', 'simbe-ai-website-care' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $entries as $key => $item ) {
			$has_details = ! empty( $item['messages'] );
			$row_classes = 'simbe-care-row is-' . esc_attr( $item['risk'] );

			echo '<tr class="' . esc_attr( $row_classes ) . '">';

			// Item name.
			echo '<td class="simbe-care-plugin">';
			echo '<strong>' . esc_html( $item['name'] ) . '</strong>';
			echo '<span class="simbe-care-meta">v' . esc_html( $item['version'] ) . ' · ' . esc_html( $item['slug'] ) . '</span>';
			echo '</td>';

			// Risk badge.
			echo '<td><span class="simbe-care-badge is-' . esc_attr( $item['risk'] ) . '">' . esc_html( self::risk_label( $item['risk'] ) ) . '</span></td>';

			// Active status.
			echo '<td>';
			if ( $item['active'] ) {
				echo '<span class="simbe-care-chip is-active">' . esc_html__( 'Active', 'simbe-ai-website-care' ) . '</span>';
			} else {
				echo '<span class="simbe-care-chip is-inactive">' . esc_html__( 'Inactive', 'simbe-ai-website-care' ) . '</span>';
			}
			echo '</td>';

			// Indicators / flags.
			echo '<td>';
			$chips = self::risk_chips( $item['flags'] );
			if ( $chips ) {
				echo wp_kses( implode( '', $chips ), self::allowed_chip_html() );
			} elseif ( ! $has_details ) {
				echo '<span class="simbe-care-chip is-ok">' . esc_html__( 'No issues found', 'simbe-ai-website-care' ) . '</span>';
			}
			if ( $has_details ) {
				echo '<button type="button" class="button-link simbe-care-details-toggle" aria-expanded="false">' . esc_html__( 'Details', 'simbe-ai-website-care' ) . '</button>';
			}
			echo '</td>';

			echo '</tr>';

			if ( $has_details ) {
				echo '<tr class="simbe-care-details-row"><td colspan="4"><div class="simbe-care-details">';
				echo '<ul>';
				foreach ( $item['messages'] as $message ) {
					echo '<li>' . esc_html( $message ) . '</li>';
				}
				echo '</ul></div></td></tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Renders the common issues checklist.
	 *
	 * @param array $issues List of issues.
	 */
	private static function render_issues( $issues ) {
		echo '<div class="simbe-care-section">';
		echo '<div class="simbe-care-section-head">';
		echo '<h2>' . esc_html__( 'Common issues', 'simbe-ai-website-care' ) . '</h2>';
		echo '<span class="simbe-care-section-sub">' . esc_html__( 'Configuration checks every site should pass.', 'simbe-ai-website-care' ) . '</span>';
		echo '</div>';

		echo '<ul class="simbe-care-issues">';
		foreach ( $issues as $issue ) {
			$severity = isset( $issue['severity'] ) ? $issue['severity'] : 'info';
			echo '<li class="simbe-care-issue is-' . esc_attr( $severity ) . '">';
			echo '<span class="simbe-care-issue-icon"><span class="dashicons ' . esc_attr( self::issue_icon_class( $severity ) ) . '" aria-hidden="true"></span></span>';
			echo '<div class="simbe-care-issue-body">';
			echo '<strong class="simbe-care-issue-title">' . esc_html( $issue['title'] ) . '</strong>';
			if ( ! empty( $issue['description'] ) ) {
				echo '<p class="simbe-care-issue-desc">' . esc_html( $issue['description'] ) . '</p>';
			}
			if ( ! empty( $issue['recommendation'] ) ) {
				echo '<p class="simbe-care-issue-fix"><span class="simbe-care-fix-label">' . esc_html__( 'How to fix:', 'simbe-ai-website-care' ) . '</span> ' . esc_html( $issue['recommendation'] ) . '</p>';
			}
			echo '</div>';
			echo '<span class="simbe-care-issue-badge is-' . esc_attr( $severity ) . '">' . esc_html( self::severity_label( $severity ) ) . '</span>';
			echo '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Renders the settings section.
	 */
	private static function render_settings() {
		echo '<div class="simbe-care-section simbe-care-settings">';
		echo '<h2>' . esc_html__( 'Settings', 'simbe-ai-website-care' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'simbe_care' );
		do_settings_sections( 'simbe-care' );
		submit_button( __( 'Save settings', 'simbe-ai-website-care' ) );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Renders the dashboard widget.
	 */
	public static function render_dashboard_widget() {
		$scan = Simbe_Care_Scanner::get_scan( false, true );

		echo '<div class="simbe-care-widget">';

		if ( ! $scan ) {
			echo '<p>' . esc_html__( 'No scan has been run yet.', 'simbe-ai-website-care' ) . '</p>';
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=simbe-care' ) ) . '" class="button button-primary">' . esc_html__( 'Run first scan', 'simbe-ai-website-care' ) . '</a>';
			echo '</div>';
			return;
		}

		$summary = $scan['summary'];
		$themes  = isset( $scan['theme_summary'] ) ? $scan['theme_summary'] : array();

		echo '<div class="simbe-care-widget-stats">';
		echo '<div class="simbe-care-widget-stat is-critical"><strong>' . esc_html( (int) $summary['critical'] ) . '</strong>' . esc_html__( 'critical', 'simbe-ai-website-care' ) . '</div>';
		echo '<div class="simbe-care-widget-stat is-warning"><strong>' . esc_html( (int) $summary['warning'] ) . '</strong>' . esc_html__( 'warnings', 'simbe-ai-website-care' ) . '</div>';
		echo '<div class="simbe-care-widget-stat is-ok"><strong>' . esc_html( (int) $summary['ok'] ) . '</strong>' . esc_html__( 'ok', 'simbe-ai-website-care' ) . '</div>';
		if ( $themes ) {
			echo '<div class="simbe-care-widget-stat is-critical"><strong>' . esc_html( (int) $themes['critical'] ) . '</strong>' . esc_html__( 'theme critical', 'simbe-ai-website-care' ) . '</div>';
			echo '<div class="simbe-care-widget-stat is-warning"><strong>' . esc_html( (int) $themes['warning'] ) . '</strong>' . esc_html__( 'theme warnings', 'simbe-ai-website-care' ) . '</div>';
		}
		echo '</div>';

		echo '<p class="simbe-care-widget-link"><a href="' . esc_url( admin_url( 'admin.php?page=simbe-care' ) ) . '">' . esc_html__( 'Open Simbe Care', 'simbe-ai-website-care' ) . '</a></p>';
		echo '</div>';
	}

	/**
	 * Registers the dashboard widget.
	 */
	public static function dashboard_widget() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget(
				'simbe-care-status',
				__( 'Simbe Care — Site Health', 'simbe-ai-website-care' ),
				array( __CLASS__, 'render_dashboard_widget' )
			);
		}
	}

	/**
	 * Returns site overview data.
	 *
	 * @return array
	 */
	private static function get_site_info() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		global $wpdb;

		$core_updates = get_core_updates();
		$core_pending = 0;
		if ( is_array( $core_updates ) ) {
			foreach ( $core_updates as $update ) {
				if ( isset( $update->response ) && 'upgrade' === $update->response ) {
					$core_pending++;
				}
			}
		}

		return array(
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'mysql_version'  => (string) $wpdb->db_version(),
			'plugins_total'  => count( get_plugins() ),
			'themes_total'   => count( wp_get_themes() ),
			'plugin_updates' => count( get_plugin_updates() ),
			'theme_updates'  => count( get_theme_updates() ),
			'core_updates'   => $core_pending,
			'https'          => is_ssl(),
		);
	}

	/**
	 * Risk label for badges.
	 *
	 * @param string $risk Risk level.
	 * @return string
	 */
	private static function risk_label( $risk ) {
		$labels = array(
			'critical' => __( 'Critical', 'simbe-ai-website-care' ),
			'warning'  => __( 'Warning', 'simbe-ai-website-care' ),
			'ok'       => __( 'OK', 'simbe-ai-website-care' ),
		);
		return isset( $labels[ $risk ] ) ? $labels[ $risk ] : __( 'Unknown', 'simbe-ai-website-care' );
	}

	/**
	 * Builds indicator chips for a plugin.
	 *
	 * @param array $flags Plugin flags.
	 * @return array
	 */
	private static function risk_chips( $flags ) {
		$chips = array();

		if ( ! empty( $flags['closed'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-critical">' . esc_html__( 'Removed from directory', 'simbe-ai-website-care' ) . '</span>';
		}
		if ( ! empty( $flags['not_found'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-warning">' . esc_html__( 'Not in directory', 'simbe-ai-website-care' ) . '</span>';
		}
		if ( ! empty( $flags['update_available'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-warning">' . sprintf(
				/* translators: %s: new version number. */
				esc_html__( 'Update available (v%s)', 'simbe-ai-website-care' ),
				esc_html( $flags['new_version'] )
			) . '</span>';
		}
		if ( ! empty( $flags['untested'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-warning">' . esc_html__( 'Untested with your WP version', 'simbe-ai-website-care' ) . '</span>';
		}
		if ( ! empty( $flags['abandoned'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-warning">' . esc_html__( 'Possibly abandoned', 'simbe-ai-website-care' ) . '</span>';
		}
		if ( ! empty( $flags['php_requirement'] ) || ! empty( $flags['update_php_requirement'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-critical">' . esc_html__( 'PHP requirement not met', 'simbe-ai-website-care' ) . '</span>';
		}
		if ( ! empty( $flags['wp_requirement'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-critical">' . esc_html__( 'WP requirement not met', 'simbe-ai-website-care' ) . '</span>';
		}
		if ( ! empty( $flags['lookup_error'] ) ) {
			$chips[] = '<span class="simbe-care-chip is-inactive">' . esc_html__( 'Check pending', 'simbe-ai-website-care' ) . '</span>';
		}

		return $chips;
	}

	/**
	 * Severity label for issue badges.
	 *
	 * @param string $severity Severity.
	 * @return string
	 */
	private static function severity_label( $severity ) {
		$labels = array(
			'ok'       => __( 'Pass', 'simbe-ai-website-care' ),
			'warning'  => __( 'Needs attention', 'simbe-ai-website-care' ),
			'critical' => __( 'Critical', 'simbe-ai-website-care' ),
			'info'     => __( 'Suggestion', 'simbe-ai-website-care' ),
		);
		return isset( $labels[ $severity ] ) ? $labels[ $severity ] : __( 'Unknown', 'simbe-ai-website-care' );
	}

	/**
	 * Icon class for an issue severity.
	 *
	 * @param string $severity Severity.
	 * @return string
	 */
	private static function issue_icon_class( $severity ) {
		switch ( $severity ) {
			case 'ok':
				return 'dashicons-yes-alt';
			case 'warning':
				return 'dashicons-warning';
			case 'critical':
				return 'dashicons-dismiss';
			default:
				return 'dashicons-info-outline';
		}
	}

	/**
	 * Allowed HTML for indicator chips (spans with a class attribute).
	 *
	 * @return array
	 */
	private static function allowed_chip_html() {
		return array(
			'span' => array(
				'class' => array(),
			),
		);
	}
}
