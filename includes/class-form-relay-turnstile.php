<?php
defined( 'ABSPATH' ) || exit;

class Form_Relay_Turnstile {
	const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	public static function required( $form, $spam = null ) {
		if ( null === $spam ) { $spam = Form_Relay::settings()['spam']; }
		return 'turnstile' === $spam['provider'] && ! empty( $form['turnstile'] );
	}
	public static function configured( $spam = null ) {
		if ( null === $spam ) { $spam = Form_Relay::settings()['spam']; }
		return 'turnstile' === $spam['provider'] && ! empty( $spam['turnstile_site_key'] ) && (bool) self::secret( $spam );
	}

	public static function secret( $spam = null ) {
		if ( defined( 'TANGO_FORM_WIRE_TURNSTILE_SECRET' ) ) { return trim( (string) TANGO_FORM_WIRE_TURNSTILE_SECRET ); }
		if ( null === $spam ) { $spam = Form_Relay::settings()['spam']; }
		return isset( $spam['turnstile_secret'] ) ? trim( (string) $spam['turnstile_secret'] ) : '';
	}

	public function verify( $token, $form ) {
		$spam = Form_Relay::settings()['spam'];
		if ( ! self::required( $form, $spam ) ) { return true; }
		$site_key = trim( (string) $spam['turnstile_site_key'] ); $secret = self::secret( $spam );
		if ( ! $site_key || ! $secret ) { return new WP_Error( 'captcha_unavailable', 'Turnstile is not fully configured.' ); }
		$token = sanitize_text_field( (string) $token );
		if ( ! $token || strlen( $token ) > 2048 ) { return new WP_Error( 'captcha_required', 'A Turnstile token is required.' ); }
		$response = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 8,
				'body' => array( 'secret' => $secret, 'response' => $token ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { return new WP_Error( 'captcha_unavailable', 'Turnstile could not be reached.' ); }
		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $result ) || empty( $result['success'] ) ) { return new WP_Error( 'captcha_failed', 'Turnstile rejected the token.' ); }
		$expected_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$actual_host = isset( $result['hostname'] ) ? strtolower( sanitize_text_field( $result['hostname'] ) ) : '';
		$actual_action = isset( $result['action'] ) ? sanitize_text_field( $result['action'] ) : '';
		if ( ! $actual_host || ! hash_equals( $expected_host, $actual_host ) || ! hash_equals( $form['id'], $actual_action ) ) { return new WP_Error( 'captcha_failed', 'Turnstile response context did not match.' ); }
		return true;
	}
}
