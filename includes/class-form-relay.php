<?php
defined( 'ABSPATH' ) || exit;

require_once FORM_RELAY_DIR . 'includes/class-form-relay-renderer.php';
require_once FORM_RELAY_DIR . 'includes/class-form-relay-service.php';
require_once FORM_RELAY_DIR . 'includes/class-form-relay-rest.php';
require_once FORM_RELAY_DIR . 'admin/class-form-relay-admin.php';

final class Form_Relay {
	const OPTION = 'form_relay_settings';
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public static function defaults() {
		return array(
			'logging' => 0,
			'mail' => self::mail_defaults(),
			'forms' => array(),
		);
	}
	public static function mail_defaults() {
		return array(
			'method' => 'wordpress', 'host' => '', 'port' => 587, 'encryption' => 'tls',
			'authentication' => 1, 'username' => '', 'password' => '',
		);
	}
	public static function new_form( $name = 'New Form' ) {
		return array(
			'id' => 'f_' . wp_generate_password( 8, false, false ), 'name' => $name, 'enabled' => 1,
			'recipient' => 'recipient@example.com', 'from_name' => '{{site_name}} Enquiries',
			'sender_domain' => self::site_domain(), 'sender_email' => 'wordpress',
			'subject' => 'New {{form_name}} submission from {{site_name}}', 'reply_to_field' => 'email',
			'success_message' => 'Thanks, your message has been sent.', 'error_message' => 'Sorry, something went wrong. {{error_message}}', 'reset_after_success' => 1,
			'response_type' => 'message', 'thank_you_page' => 0, 'success_classes' => '', 'error_classes' => '',
			'disable_while_submitting' => 1, 'scroll_to_response' => 1,
			'ignored_fields' => "g-recaptcha-response\ncsrf_token\nnonce\nhoneypot\n_recaptcha", 'rate_limit' => 30, 'rate_window' => 10,
			'email_template' => Form_Relay_Renderer::default_email_template(), 'row_template' => Form_Relay_Renderer::default_row_template(),
		);
	}

	public static function settings() {
		$stored = get_option( self::OPTION, array() );
		$changed = false;
		// Versions before 1.8 always used the local cPanel relay. Preserve that behaviour on upgrade.
		if ( ! empty( $stored ) && ! isset( $stored['mail'] ) ) {
			$stored['mail'] = wp_parse_args( array( 'method' => 'local' ), self::mail_defaults() );
			$changed = true;
		}
		$settings = wp_parse_args( $stored, self::defaults() );
		$settings['mail'] = wp_parse_args( $settings['mail'], self::mail_defaults() );
		if ( empty( $settings['forms'] ) ) {
			$form = self::new_form( 'Sample Form' );
			foreach ( array_keys( $form ) as $key ) { if ( isset( $settings[ $key ] ) ) { $form[ $key ] = $settings[ $key ]; } }
			$settings['forms'] = array( $form ); update_option( self::OPTION, $settings, false );
		}
		if ( isset( $settings['clients'] ) ) { unset( $settings['clients'] ); update_option( self::OPTION, $settings, false ); }
		foreach ( $settings['forms'] as &$form ) { $form = wp_parse_args( $form, self::new_form( isset( $form['name'] ) ? $form['name'] : 'Form' ) ); if ( isset( $form['from_email'] ) ) { unset( $form['from_email'] ); $changed = true; } }
		if ( $changed ) { update_option( self::OPTION, $settings, false ); }
		return $settings;
	}
	public static function activate() { if ( ! get_option( self::OPTION ) ) { $settings = self::defaults(); $settings['forms'][] = self::new_form( 'Sample Form' ); add_option( self::OPTION, $settings, '', false ); } }
	public static function site_domain() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return preg_replace( '/^www\./', '', $host );
	}
	public function run() {
		$service = new Form_Relay_Service();
		( new Form_Relay_REST( $service ) )->hooks();
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		if ( is_admin() ) { ( new Form_Relay_Admin( $service ) )->hooks(); }
	}
	public function frontend_assets() {
		wp_enqueue_script( 'form-relay', plugins_url( 'assets/form-relay.js', FORM_RELAY_FILE ), array(), FORM_RELAY_VERSION, true );
		wp_enqueue_style( 'form-relay', plugins_url( 'assets/form-relay.css', FORM_RELAY_FILE ), array(), FORM_RELAY_VERSION );
		$behaviour = array(); foreach ( self::settings()['forms'] as $form ) { $behaviour[ $form['id'] ] = array( 'disable' => ! empty( $form['disable_while_submitting'] ), 'scroll' => ! empty( $form['scroll_to_response'] ), 'responseType' => $form['response_type'], 'thankYouUrl' => $form['thank_you_page'] ? get_permalink( $form['thank_you_page'] ) : '', 'successClasses' => self::class_names( $form['success_classes'] ), 'errorClasses' => self::class_names( $form['error_classes'] ) ); }
		wp_localize_script( 'form-relay', 'FormRelayConfig', array( 'endpoint' => rest_url( 'form-relay/v1/send' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'siteName' => get_bloginfo( 'name' ), 'forms' => $behaviour ) );
	}
	private static function class_names( $classes ) { return array_values( array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $classes ) ) ) ); }
}
