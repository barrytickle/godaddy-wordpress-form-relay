<?php
/**
 * Plugin Name: Form Relay
 * Description: Securely relays external JSON form submissions to a configured email address.
 * Version: 1.8.0
 * Author: Form Relay Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-relay
 */

defined( 'ABSPATH' ) || exit;

define( 'FORM_RELAY_VERSION', '1.8.0' );
define( 'FORM_RELAY_FILE', __FILE__ );
define( 'FORM_RELAY_DIR', plugin_dir_path( __FILE__ ) );

require_once FORM_RELAY_DIR . 'includes/class-form-relay.php';

register_activation_hook( __FILE__, array( 'Form_Relay', 'activate' ) );
Form_Relay::instance()->run();
