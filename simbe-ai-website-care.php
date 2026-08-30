<?php
/**
 * Plugin Name:       Simbe AI Website Care
 * Plugin URI:        https://wordpress.org/plugins/simbe-ai-website-care/
 * Description:       Monitors your WordPress site for plugin and theme vulnerabilities, flags common issues, and gives freelancers and agencies a clear health overview for every site they manage.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Simbe1
 * Author URI:        https://profiles.wordpress.org/simbe1/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simbe-ai-website-care
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

define( 'SIMBE_CARE_VERSION', '1.1.0' );
define( 'SIMBE_CARE_PLUGIN_FILE', __FILE__ );
define( 'SIMBE_CARE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SIMBE_CARE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMBE_CARE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIMBE_CARE_MIN_PHP', '7.4' );

if ( version_compare( PHP_VERSION, SIMBE_CARE_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p><strong>Simbe AI Website Care</strong> ' . esc_html__( 'requires PHP 7.4 or newer. Your site is running an older version, so the plugin has been deactivated.', 'simbe-ai-website-care' ) . '</p></div>';
		}
	);
	return;
}

require_once SIMBE_CARE_PLUGIN_DIR . 'includes/class-simbe-care.php';

register_activation_hook( __FILE__, array( 'Simbe_Care', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Simbe_Care', 'deactivate' ) );

/**
 * Returns the main plugin instance.
 *
 * @return Simbe_Care
 */
function simbe_care() {
	return Simbe_Care::instance();
}

add_action( 'plugins_loaded', 'simbe_care' );
