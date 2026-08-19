<?php
defined( 'ABSPATH' ) || exit;

class Form_Relay_Renderer {
	public static function default_email_template() {
		return '<div style="margin:0;padding:32px 12px;background:#f3f4f6;font-family:Arial,sans-serif;color:#17202a"><div style="max-width:640px;margin:0 auto;background:#fff;border-radius:8px;padding:32px"><h1 style="margin:0 0 8px;font-size:25px">New {{form_name}} submission</h1><p style="margin:0 0 28px;color:#667085">A new enquiry was received from {{site_name}}.</p>{{fields}}<div style="border-top:1px solid #e5e7eb;margin-top:28px;padding-top:18px;color:#667085;font-size:13px">Submitted from <a style="color:#2563eb" href="{{page_url}}">{{page_title}}</a><br>{{submitted_at}}</div></div></div>';
	}
	public static function default_row_template() {
		return '<div style="margin-bottom:20px"><div style="font-weight:bold;margin-bottom:5px;color:#344054">{{field_label}}</div><div style="line-height:1.55">{{field_value}}</div></div>';
	}

	public function render( $data, $settings ) {
		$ignored = array_filter( array_map( 'trim', preg_split( '/\R/', $settings['ignored_fields'] ) ) );
		$rows = '';
		foreach ( $data['fields'] as $key => $value ) {
			if ( in_array( (string) $key, $ignored, true ) ) { continue; }
			$rows .= $this->replace( $settings['row_template'], array(
				'field_key' => esc_html( $key ), 'field_label' => esc_html( $this->label( $key ) ),
				'field_value' => $this->value( $value ),
			) );
		}
		$meta = $data['meta'];
		$vars = array(
			'form_name' => esc_html( $data['form_name'] ), 'site_name' => esc_html( $meta['site_name'] ),
			'page_title' => esc_html( $meta['page_title'] ), 'page_url' => esc_url( $meta['page_url'] ),
			'submitted_at' => esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ), 'fields' => $rows,
		);
		$html = $this->replace( $settings['email_template'], $vars );
		$html = apply_filters( 'form_relay_email_html', $html, $data );
		return wp_kses( $html, $this->allowed_html() );
	}

	public function subject( $data, $settings ) {
		$subject = $this->replace( $settings['subject'], array(
			'form_name' => $data['form_name'], 'site_name' => $data['meta']['site_name'],
			'page_title' => $data['meta']['page_title'], 'page_url' => $data['meta']['page_url'],
			'submitted_at' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
		) );
		$subject = apply_filters( 'form_relay_email_subject', $subject, $data );
		return sanitize_text_field( $subject );
	}

	private function replace( $template, $vars ) {
		foreach ( $vars as $key => $value ) { $template = str_replace( '{{' . $key . '}}', $value, $template ); }
		return preg_replace( '/{{[a-z_]+}}/', '', $template );
	}
	private function label( $key ) {
		$key = preg_replace( '/([a-z])([A-Z])/', '$1 $2', (string) $key );
		return ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
	}
	private function value( $value ) {
		if ( is_bool( $value ) ) { return $value ? 'Yes' : 'No'; }
		if ( is_array( $value ) || is_object( $value ) ) {
			$out = '<ul style="margin:0;padding-left:20px">';
			foreach ( (array) $value as $key => $item ) {
				$prefix = is_string( $key ) ? '<strong>' . esc_html( $this->label( $key ) ) . ':</strong> ' : '';
				$out .= '<li>' . $prefix . $this->value( $item ) . '</li>';
			}
			return $out . '</ul>';
		}
		$text = (string) $value;
		if ( is_email( $text ) ) { return '<a href="mailto:' . esc_attr( $text ) . '">' . esc_html( $text ) . '</a>'; }
		if ( filter_var( $text, FILTER_VALIDATE_URL ) ) { return '<a href="' . esc_url( $text ) . '">' . esc_html( $text ) . '</a>'; }
		if ( preg_match( '/^\+?[0-9][0-9 ()\-.]{6,20}$/', $text ) && preg_match_all( '/\d/', $text ) >= 7 ) {
			return '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $text ) ) . '">' . esc_html( $text ) . '</a>';
		}
		return nl2br( esc_html( $text ) );
	}
	private function allowed_html() {
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( $allowed as &$attrs ) { $attrs['style'] = true; }
		$allowed['a']['href'] = true; $allowed['a']['style'] = true;
		return $allowed;
	}
}
