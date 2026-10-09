<?php
/**
 * Plugin Name:       Mailwright for Contact Form 7
 * Plugin URI:        https://github.com/manpreetdev21/mailwright-for-contact-form-7
 * Description:       Reusable, brandable email templates for Contact Form 7. Design once, assign to any form — without ever overwriting Contact Form 7's own mail settings.
 * Version:           2.0.0
 * Author:            Manpreet Singh
 * Author URI:        https://github.com/manpreetdev21/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mailwright-for-contact-form-7
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  contact-form-7
 */

defined( 'ABSPATH' ) || exit;

define( 'MWRIGHT_VERSION', '2.0.0' );
define( 'MWRIGHT_FILE', __FILE__ );
define( 'MWRIGHT_DIR', plugin_dir_path( __FILE__ ) );
define( 'MWRIGHT_URL', plugin_dir_url( __FILE__ ) );

require_once MWRIGHT_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'MWRIGHT_Plugin', 'boot' ) );
register_activation_hook( __FILE__, array( 'MWRIGHT_Plugin', 'activate' ) );
