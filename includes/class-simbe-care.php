<?php
/**
 * Core plugin class: loads dependencies and bootstraps the plugin.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Simbe_Care
 */
final class Simbe_Care {

	/**
	 * Settings option key.
	 *
	 * @var string
	 */
	const SETTINGS_KEY = 'simbe_care_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Simbe_Care|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return Simbe_Care
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required class files.
	 */
	private function load_dependencies() {
		require_once SIMBE_CARE_PLUGIN_DIR . 'includes/class-simbe-care-wporg.php';
		require_once SIMBE_CARE_PLUGIN_DIR . 'includes/class-simbe-care-scanner.php';
		require_once SIMBE_CARE_PLUGIN_DIR . 'includes/class-simbe-care-notifications.php';
		require_once SIMBE_CARE_PLUGIN_DIR . 'includes/class-simbe-care-issues.php';
		require_once SIMBE_CARE_PLUGIN_DIR . 'includes/class-simbe-care-health.php';
		require_once SIMBE_CARE_PLUGIN_DIR . 'includes/class-simbe-care-admin.php';
	}

	/**
	 * Register hooks.
	 */
	private function init_hooks() {
		add_action( 'simbe_care_scan', array( 'Simbe_Care_Scanner', 'refresh_wporg_data' ) );
		add_filter( 'site_status_tests', array( 'Simbe_Care_Health', 'register_tests' ) );

		Simbe_Care_Admin::init();
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'abandoned_days' => 730,
			'wporg_ttl_hours' => 12,
			'schedule'       => 'weekly',
			'notify_email'   => '',
		);
	}

	/**
	 * Returns merged plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::SETTINGS_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		$settings = self::get_settings();
		add_option( self::SETTINGS_KEY, $settings, '', false );

		self::reschedule_scan( $settings['schedule'] );
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'simbe_care_scan' );
	}

	/**
	 * Schedules (or reschedules) the background scan.
	 *
	 * @param string $schedule 'daily' or 'weekly'.
	 */
	public static function reschedule_scan( $schedule ) {
		$schedule = ( 'daily' === $schedule ) ? 'daily' : 'weekly';

		wp_clear_scheduled_hook( 'simbe_care_scan' );

		if ( ! wp_next_scheduled( 'simbe_care_scan' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $schedule, 'simbe_care_scan' );
		}
	}
}
