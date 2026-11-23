<?php
/**
 * The plugin bootstrap file
 *
 * @link              https://www.rotsenacob.com/
 * @since             1.0
 * @package           DynamicContactInfoBlocks
 *
 * @wordpress-plugin
 * Plugin Name:       Dynamic Contact Info Blocks
 * Plugin URI:        https://www.rotsenacob.com/
 * Description:       Effortlessly manage your business details! Update phone numbers, emails, and addresses in a snap with dynamic fields. Stay organized and keep your information up to date.
 * Version:           1.0.1
 * Author:            Rotsen Mark Acob
 * Author URI:        https://www.rotsenacob.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ra-dynamic-contact-info-blocks
 * Domain Path:       /languages
 * Requires at least: 5.9
 * Requires PHP:      8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Current plugin version.
 */
define( 'DCIB_VERSION', '1.0.1' );

/**
 * Plugin path.
 */
define( 'DCIB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin base URL.
 */
define( 'DCIB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The core plugin class
 */
require_once DCIB_PLUGIN_PATH . 'includes/class-dcib.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0
 */
function dcib_run() {
	return DCIB::get_instance();
}

dcib_run();
