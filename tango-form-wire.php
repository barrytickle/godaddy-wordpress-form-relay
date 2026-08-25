<?php
/**
 * Plugin Name: Tango Form Wire
 * Description: Adds secure email delivery to developer-built, same-site HTML forms without generating form markup.
 * Version: 1.10.0
 * Author: Tango Form Wire Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tango-form-wire
 */

defined( 'ABSPATH' ) || exit;

define( 'FORM_RELAY_VERSION', '1.10.0' );
define( 'FORM_RELAY_FILE', __FILE__ );
define( 'FORM_RELAY_DIR', plugin_dir_path( __FILE__ ) );

require_once FORM_RELAY_DIR . 'includes/class-form-relay.php';

register_activation_hook( __FILE__, array( 'Form_Relay', 'activate' ) );
Form_Relay::instance()->run();
