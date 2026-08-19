<?php
defined( 'ABSPATH' ) || exit;

class Form_Relay_Submissions {
	const DB_VERSION = '1.0';
	const DB_OPTION = 'form_relay_submissions_db_version';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'form_relay_submissions';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id varchar(32) NOT NULL,
			form_name varchar(191) NOT NULL,
			status varchar(20) NOT NULL,
			recipient varchar(320) NOT NULL,
			subject text NOT NULL,
			fields longtext NOT NULL,
			meta longtext NOT NULL,
			error text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY form_id (form_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( self::DB_OPTION ) ) { self::install(); }
	}

	public function create( $data, $form, $sent, $error = '' ) {
		global $wpdb;
		$renderer = new Form_Relay_Renderer();
		$ignored = array_filter( array_map( 'trim', preg_split( '/\R/', $form['ignored_fields'] ) ) );
		$fields = array_diff_key( $data['fields'], array_flip( $ignored ) );
		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Submissions are the plugin's primary data and require immediate persistence.
			self::table(),
			array(
				'form_id' => sanitize_text_field( $form['id'] ),
				'form_name' => sanitize_text_field( $form['name'] ),
				'status' => $sent ? 'sent' : 'failed',
				'recipient' => sanitize_email( $form['recipient'] ),
				'subject' => $renderer->subject( $data, $form ),
				'fields' => wp_json_encode( $fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'meta' => wp_json_encode( $data['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'error' => sanitize_textarea_field( $error ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return false !== $inserted ? (int) $wpdb->insert_id : false;
	}

	public function get( $id ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Submission detail must reflect current stored data; the table identifier is escaped.
	}

	public function query( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'page' => 1, 'per_page' => 20, 'form_id' => '', 'status' => '' ) );
		$form_id = sanitize_text_field( $args['form_id'] ); $status = in_array( $args['status'], array( 'sent', 'failed' ), true ) ? $args['status'] : ''; $table = esc_sql( self::table() ); $offset = ( max( 1, absint( $args['page'] ) ) - 1 ) * absint( $args['per_page'] ); $per_page = absint( $args['per_page'] );
		if ( $form_id && $status ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %s AND status = %s", $form_id, $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %s AND status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $form_id, $status, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
		} elseif ( $form_id ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE form_id = %s", $form_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE form_id = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $form_id, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
		} elseif ( $status ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $status, $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
			$items = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Admin list must reflect current stored data; the table identifier is escaped.
		}
		return array( 'items' => $items, 'total' => $total );
	}

	public function delete( $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) { return 0; }
		$deleted = 0; $table = self::table();
		foreach ( $ids as $id ) { $deleted += (int) $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit administrator deletion must be immediate.
		return $deleted;
	}
}
