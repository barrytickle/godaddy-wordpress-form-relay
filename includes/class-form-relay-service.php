<?php
defined( 'ABSPATH' ) || exit;

class Form_Relay_Service {
	const MAX_FIELDS = 50;
	const MAX_KEY = 100;
	const MAX_VALUE = 10000;
	const MAX_PAYLOAD = 102400;
	private $renderer;
	public function __construct() { $this->renderer = new Form_Relay_Renderer(); }

	public function normalise( $input ) {
		if ( ! is_array( $input ) || empty( $input['fields'] ) || ! is_array( $input['fields'] ) ) {
			return new WP_Error( 'form_relay_invalid', 'Invalid form submission.', array( 'status' => 400 ) );
		}
		$max_fields = (int) apply_filters( 'form_relay_max_fields', self::MAX_FIELDS );
		if ( count( $input['fields'] ) > $max_fields ) { return new WP_Error( 'form_relay_too_many', 'Too many fields.', array( 'status' => 400 ) ); }
		$fields = array();
		foreach ( $input['fields'] as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key || mb_strlen( $key ) > apply_filters( 'form_relay_max_field_name_length', self::MAX_KEY ) ) {
				return new WP_Error( 'form_relay_field_name', 'Invalid field name.', array( 'status' => 400 ) );
			}
			if ( $this->value_length( $value ) > apply_filters( 'form_relay_max_field_value_length', self::MAX_VALUE ) ) {
				return new WP_Error( 'form_relay_field_value', 'A field value is too long.', array( 'status' => 400 ) );
			}
			$fields[ sanitize_text_field( $key ) ] = $this->clean_value( $value );
		}
		$meta_in = isset( $input['meta'] ) && is_array( $input['meta'] ) ? $input['meta'] : array();
		$data = array(
			'form_name' => sanitize_text_field( isset( $input['form_name'] ) ? $input['form_name'] : 'Form' ),
			'fields' => $fields,
			'meta' => array(
				'site_name' => sanitize_text_field( isset( $meta_in['site_name'] ) ? $meta_in['site_name'] : get_bloginfo( 'name' ) ),
				'page_title' => sanitize_text_field( isset( $meta_in['page_title'] ) ? $meta_in['page_title'] : 'Form page' ),
				'page_url' => esc_url_raw( isset( $meta_in['page_url'] ) ? $meta_in['page_url'] : '' ),
			),
		);
		return apply_filters( 'form_relay_submission_data', $data );
	}

	public function email( $data, $send = true, $form = null ) {
		$settings = $form ? $form : Form_Relay::settings()['forms'][0];
		$data['form_name'] = $settings['name'];
		$html = $this->renderer->render( $data, $settings );
		$subject = $this->renderer->subject( $data, $settings );
		if ( ! $send ) { return array( 'html' => $html, 'subject' => $subject ); }
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_email = $this->sender_email( $settings );
		$from_name = str_replace( array( '{{site_name}}', '{{form_name}}' ), array( get_bloginfo( 'name' ), $settings['name'] ), $settings['from_name'] );
		if ( $from_email ) { $headers[] = 'From: ' . sanitize_text_field( $from_name ) . ' <' . $from_email . '>'; }
		$reply_key = $settings['reply_to_field'];
		$reply_email = isset( $data['fields'][ $reply_key ] ) && is_email( $data['fields'][ $reply_key ] ) ? sanitize_email( $data['fields'][ $reply_key ] ) : $this->find_reply_email( $data['fields'] );
		if ( $reply_email ) { $headers[] = 'Reply-To: ' . $reply_email; }
		do_action( 'form_relay_before_send', $data, $subject, $html, $headers );
		$sent = wp_mail( $settings['recipient'], $subject, $html, $headers );
		do_action( 'form_relay_after_send', $sent, $data );
		return $sent;
	}
	private function sender_email( $settings ) {
		$domain = isset( $settings['sender_domain'] ) ? $settings['sender_domain'] : Form_Relay::site_domain();
		$local = isset( $settings['sender_email'] ) ? $settings['sender_email'] : 'wordpress';
		$email = sanitize_email( $local . '@' . $domain );
		if ( ! is_email( $email ) ) { $email = sanitize_email( get_option( 'admin_email' ) ); }
		return sanitize_email( apply_filters( 'form_relay_from_email', $email, $domain ) );
	}
	private function find_reply_email( $fields ) {
		foreach ( $fields as $value ) { if ( is_string( $value ) && is_email( trim( $value ) ) ) { return sanitize_email( trim( $value ) ); } }
		return '';
	}

	private function value_length( $value ) { return mb_strlen( wp_json_encode( $value ) ); }
	private function clean_value( $value, $depth = 0 ) {
		if ( $depth > 5 ) { return ''; }
		if ( is_object( $value ) ) { $value = (array) $value; }
		if ( is_array( $value ) ) { $clean = array(); foreach ( $value as $key => $item ) { $clean[ sanitize_text_field( (string) $key ) ] = $this->clean_value( $item, $depth + 1 ); } return $clean; }
		if ( is_bool( $value ) || is_numeric( $value ) ) { return $value; }
		return sanitize_textarea_field( (string) $value );
	}
}
