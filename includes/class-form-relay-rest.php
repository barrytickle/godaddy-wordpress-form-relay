<?php
defined( 'ABSPATH' ) || exit;

class Form_Relay_REST {
	private $service;
	private $form;
	private $errors = array(
		'invalid_form' => 'This form is no longer available.', 'form_disabled' => 'This form is currently unavailable.',
		'invalid_nonce' => 'Your session has expired. Please refresh the page and try again.', 'validation_failed' => 'Please check the form and try again.',
		'honeypot_triggered' => 'Please check the form and try again.', 'rate_limited' => 'Too many submissions have been made. Please try again shortly.',
		'duplicate_submission' => 'This form appears to have already been submitted.', 'payload_too_large' => 'The submitted form contains too much information.',
		'mail_failed' => "We couldn't send your message right now.", 'server_error' => 'Something went wrong. Please try again.',
	);
	public function __construct( $service ) { $this->service = $service; }
	public function hooks() { add_action( 'rest_api_init', array( $this, 'routes' ) ); }
	public function routes() { register_rest_route( 'form-relay/v1', '/send', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'send' ), 'permission_callback' => '__return_true' ) ); }
	public function send( $request ) {
		if ( ! wp_verify_nonce( $request->get_header( 'x-wp-nonce' ), 'wp_rest' ) ) { return $this->failure( 'invalid_nonce', 403 ); }
		if ( ! $this->same_site( $request ) ) { return $this->failure( 'invalid_nonce', 403 ); }
		$raw = $request->get_body(); if ( strlen( $raw ) > apply_filters( 'form_relay_max_payload_size', Form_Relay_Service::MAX_PAYLOAD ) ) { return $this->failure( 'payload_too_large', 413 ); }
		$params = $request->get_json_params(); $form_id = isset( $params['form_id'] ) ? sanitize_text_field( $params['form_id'] ) : '';
		foreach ( Form_Relay::settings()['forms'] as $form ) { if ( hash_equals( $form['id'], $form_id ) ) { $this->form = $form; break; } }
		if ( ! $this->form ) { return $this->failure( 'invalid_form', 404 ); }
		if ( empty( $this->form['enabled'] ) ) { return $this->failure( 'form_disabled', 403 ); }
		if ( ! empty( $params['fields']['_form_relay_hp'] ) ) { $this->log( false, 'Honeypot field populated.' ); return $this->failure( 'honeypot_triggered', 400 ); }
		if ( isset( $params['fields']['_form_relay_hp'] ) ) { unset( $params['fields']['_form_relay_hp'] ); }
		$ip = $this->client_ip(); $window = max( 1, (int) $this->form['rate_window'] ) * MINUTE_IN_SECONDS;
		$bucket = 'form_relay_rate_' . hash( 'sha256', $form_id . '|' . $ip . '|' . floor( time() / $window ) ); $count = (int) get_transient( $bucket );
		if ( $count >= max( 1, (int) $this->form['rate_limit'] ) ) { return $this->failure( 'rate_limited', 429 ); }
		$fingerprint = 'form_relay_dupe_' . hash( 'sha256', $form_id . '|' . $ip . '|' . wp_json_encode( isset( $params['fields'] ) ? $params['fields'] : array() ) );
		if ( get_transient( $fingerprint ) ) { return $this->failure( 'duplicate_submission', 409 ); }
		$data = $this->service->normalise( $params ); if ( is_wp_error( $data ) ) { return $this->failure( 'validation_failed', 400 ); }
		set_transient( $bucket, $count + 1, $window ); set_transient( $fingerprint, 1, (int) apply_filters( 'form_relay_duplicate_window', 30, $form_id ) );
		$sent = $this->service->email( $data, true, $this->form ); $this->log( $sent, $sent ? '' : 'PHPMailer SMTP failed while processing Form ID ' . $form_id . ': ' . $this->service->last_error() );
		if ( ! $sent ) { return $this->failure( 'mail_failed', 500 ); }
		$message = $this->message( $this->form['success_message'], array( 'form_name' => $this->form['name'] ) );
		return new WP_REST_Response( array( 'success' => true, 'message' => $message, 'reset' => ! empty( $this->form['reset_after_success'] ) ), 200 );
	}
	private function failure( $code, $status ) {
		$form_id = $this->form ? $this->form['id'] : ''; $context = array( 'status' => $status );
		$code = sanitize_key( apply_filters( 'form_relay_error_code', $code, $form_id, $context ) );
		$public = isset( $this->errors[ $code ] ) ? $this->errors[ $code ] : $this->errors['server_error']; $public = sanitize_text_field( apply_filters( 'form_relay_error_message', $public, $code, $form_id, $context ) );
		$template = $this->form ? $this->form['error_message'] : '{{error_message}}';
		$message = $this->message( $template, array( 'error_message' => $public, 'error_code' => $code, 'form_name' => $this->form ? $this->form['name'] : '' ) );
		return new WP_REST_Response( array( 'success' => false, 'error' => array( 'code' => $code, 'message' => $message ) ), $status );
	}
	private function message( $template, $values ) { foreach ( $values as $key => $value ) { $template = str_replace( '{{' . $key . '}}', $value, $template ); } return sanitize_text_field( preg_replace( '/{{[a-z_]+}}/', '', $template ) ); }
	private function same_site( $request ) { $source = $request->get_header( 'origin' ); if ( ! $source ) { $source = $request->get_header( 'referer' ); } if ( ! $source ) { return false; } return wp_parse_url( $source, PHP_URL_SCHEME ) === wp_parse_url( home_url(), PHP_URL_SCHEME ) && wp_parse_url( $source, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) && (int) wp_parse_url( $source, PHP_URL_PORT ) === (int) wp_parse_url( home_url(), PHP_URL_PORT ); }
	private function client_ip() { return sanitize_text_field( isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : 'unknown' ); }
	private function log( $sent, $error ) { $settings = Form_Relay::settings(); if ( empty( $settings['logging'] ) || ! $this->form ) { return; } $logs = get_option( 'form_relay_logs', array() ); array_unshift( $logs, array( 'time' => current_time( 'mysql' ), 'form' => $this->form['name'], 'form_id' => $this->form['id'], 'sent' => (bool) $sent, 'error' => $error ) ); update_option( 'form_relay_logs', array_slice( $logs, 0, 100 ), false ); }
}
