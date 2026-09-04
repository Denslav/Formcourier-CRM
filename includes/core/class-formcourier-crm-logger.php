<?php
/**
 * Submission logger.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Logger.
 */
final class FormCourier_CRM_Logger {
	/**
	 * Returns the table name.
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'formcourier_crm_logs';
	}

	/**
	 * Performs the create table operation when needed.
	 */
	public static function maybe_create_table(): void {
		if ( FORMCOURIER_CRM_DB_VERSION !== get_option( 'formcourier_crm_db_version' ) ) {
			self::create_table();
			update_option( 'formcourier_crm_db_version', FORMCOURIER_CRM_DB_VERSION );
		}
	}

	/**
	 * Creates the table.
	 */
	public static function create_table(): void {
		global $wpdb;
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( file_exists( $upgrade_file ) ) {
				require_once $upgrade_file; }
		}
		$table = self::get_table_name();
		$sql   = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            form_provider VARCHAR(100) NOT NULL DEFAULT '',
            crm_provider VARCHAR(100) NOT NULL DEFAULT '',
            form_id VARCHAR(100) NOT NULL DEFAULT '',
            form_name VARCHAR(191) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            action VARCHAR(50) NOT NULL DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT '',
            message TEXT NULL,
            request_body LONGTEXT NULL,
            response_body LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY email (email),
            KEY status (status),
            KEY action (action)
        ) " . $wpdb->get_charset_collate() . ';';
		if ( function_exists( 'dbDelta' ) ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Adds a log record.
	 *
	 * @param array $data Data.
	 */
	public static function add( array $data ): int {
		global $wpdb;
		$data = wp_parse_args(
			$data,
			array(
				'created_at'    => current_time( 'mysql' ),
				'form_provider' => '',
				'crm_provider'  => '',
				'form_id'       => '',
				'form_name'     => '',
				'email'         => '',
				'action'        => '',
				'status'        => '',
				'message'       => '',
				'request_body'  => '',
				'response_body' => '',
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The plugin writes to its dedicated log table.
		$inserted = $wpdb->insert(
			self::get_table_name(),
			array(
				'created_at'    => sanitize_text_field( $data['created_at'] ),
				'form_provider' => sanitize_text_field( $data['form_provider'] ),
				'crm_provider'  => sanitize_text_field( $data['crm_provider'] ),
				'form_id'       => sanitize_text_field( (string) $data['form_id'] ),
				'form_name'     => sanitize_text_field( $data['form_name'] ),
				'email'         => self::mask_email( sanitize_email( (string) $data['email'] ) ),
				'action'        => sanitize_key( (string) $data['action'] ),
				'status'        => sanitize_key( (string) $data['status'] ),
				'message'       => sanitize_textarea_field( (string) $data['message'] ),
				'request_body'  => self::prepare_body( $data['request_body'] ),
				'response_body' => self::prepare_body( $data['response_body'] ),
			)
		);
		return false === $inserted ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Returns the logs.
	 *
	 * @param array $args Args.
	 */
	public static function get_logs( array $args = array() ): array {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'limit'  => 20,
				'offset' => 0,
				'status' => '',
				'email'  => '',
			)
		);

		$table      = self::get_table_name();
		$limit      = max( 1, absint( $args['limit'] ) );
		$offset     = max( 0, absint( $args['offset'] ) );
		$status     = in_array( $args['status'], array( 'success', 'error' ), true ) ? $args['status'] : '';
		$email      = sanitize_text_field( (string) $args['email'] );
		$email_like = '' !== trim( $email ) ? '%' . $wpdb->esc_like( $email ) . '%' : '';

		if ( '' !== $status && '' !== $email_like ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Log queries intentionally bypass the object cache.
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s AND email LIKE %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
					$table,
					$status,
					$email_like,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Log queries intentionally bypass the object cache.
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
					$table,
					$status,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		if ( '' !== $email_like ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Log queries intentionally bypass the object cache.
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE email LIKE %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
					$table,
					$email_like,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Log queries intentionally bypass the object cache.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
				$table,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Handles the count logs operation.
	 *
	 * @param array $args Args.
	 */
	public static function count_logs( array $args = array() ): int {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'status' => '',
				'email'  => '',
			)
		);

		$table      = self::get_table_name();
		$status     = in_array( $args['status'], array( 'success', 'error' ), true ) ? $args['status'] : '';
		$email      = sanitize_text_field( (string) $args['email'] );
		$email_like = '' !== trim( $email ) ? '%' . $wpdb->esc_like( $email ) . '%' : '';

		if ( '' !== $status && '' !== $email_like ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Log counts intentionally bypass the object cache.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s AND email LIKE %s',
					$table,
					$status,
					$email_like
				)
			);
		}

		if ( '' !== $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Log counts intentionally bypass the object cache.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE status = %s',
					$table,
					$status
				)
			);
		}

		if ( '' !== $email_like ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Log counts intentionally bypass the object cache.
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE email LIKE %s',
					$table,
					$email_like
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The query contains only a SQL-escaped plugin-owned table identifier.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Deletes the requested record.
	 *
	 * @param int $id Id.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin deletes from its dedicated log table and does not cache mutable log rows.
		return false !== $wpdb->delete( self::get_table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Clears stored records.
	 */
	public static function clear(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Clearing the dedicated log table is an explicit administrator action.
		$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::get_table_name() ) );
	}

	/**
	 * Prepares the body.
	 *
	 * @param mixed $value Value.
	 */
	private static function prepare_body( $value ): string {
		$settings = get_option( FORMCOURIER_CRM_OPTION_NAME, array() );
		if ( empty( $settings['log_payload_data_enabled'] ) ) {
			return ''; }
		$value = self::mask_sensitive_data( $value );
		return is_array( $value ) || is_object( $value )
			? (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
			: sanitize_textarea_field( (string) $value );
	}

	/**
	 * Handles the mask sensitive data operation.
	 *
	 * @param mixed  $value Value.
	 * @param string $path  Path.
	 */
	private static function mask_sensitive_data( $value, string $path = '' ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			$result = array();

			foreach ( $value as $key => $item ) {
				$key_string = (string) $key;
				$item_path  = '' === $path ? $key_string : $path . '.' . $key_string;

				$result[ $key ] = self::is_sensitive_path( $item_path )
					? '***hidden***'
					: self::mask_sensitive_data( $item, $item_path );
			}

			return $result;
		}

		if ( is_string( $value ) ) {
			$trimmed = trim( $value );

			if ( '' !== $trimmed && in_array( substr( $trimmed, 0, 1 ), array( '{', '[' ), true ) ) {
				$decoded = json_decode( $trimmed, true );

				if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
					return self::mask_sensitive_data( $decoded, $path );
				}
			}

			$value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '***email***', $value ) ?? $value;
			$value = preg_replace( '/(?<![\w])\+?\d[\d\s().\-]{6,}\d(?![\w])/', '***phone***', $value ) ?? $value;
		}

		return $value;
	}

	/**
	 * Checks whether sensitive path.
	 *
	 * @param string $path Path.
	 */
	private static function is_sensitive_path( string $path ): bool {
		$segments = preg_split( '/[.\[\]]+/', strtolower( trim( $path ) ), -1, PREG_SPLIT_NO_EMPTY );
		$segments = is_array( $segments ) ? $segments : array();
		$leaf     = $segments ? (string) end( $segments ) : $path;
		$leaf     = self::normalize_key( $leaf );

		$technical_keys = array(
			'form_id',
			'form_name',
			'form_provider',
			'crm',
			'crm_provider',
			'pipeline_id',
			'stage_id',
			'owner_id',
			'creator_id',
			'org_id',
			'status',
			'success',
			'visible_to',
			'is_deleted',
			'created_at',
			'updated_at',
			'add_time',
			'update_time',
			'picture_id',
			'label_ids',
		);

		if ( in_array( $leaf, $technical_keys, true ) ) {
			return false;
		}

		$sensitive_keys = array(
			'name',
			'firstname',
			'first_name',
			'lastname',
			'last_name',
			'full_name',
			'contact_name',
			'person_name',
			'customer_name',
			'dealname',
			'deal_name',
			'lead_title',
			'title',
			'email',
			'e_mail',
			'email_address',
			'phone',
			'mobile',
			'telephone',
			'tel',
			'message',
			'description',
			'note',
			'notes',
			'comment',
			'comments',
			'details',
			'content',
			'subject',
			'address',
			'street',
			'city',
			'postcode',
			'postal_code',
			'zip',
			'api_key',
			'apikey',
			'token',
			'access_token',
			'secret',
			'authorization',
			'password',
			'webhook',
		);

		if ( in_array( $leaf, $sensitive_keys, true ) ) {
			return true;
		}

		$normalized_segments = array_map( array( self::class, 'normalize_key' ), $segments );
		$parent_segments     = array_slice( $normalized_segments, 0, -1 );

		if ( 'value' === $leaf ) {
			foreach ( $parent_segments as $segment ) {
				if ( in_array( $segment, array( 'email', 'emails', 'phone', 'phones', 'mobile', 'mobiles' ), true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Normalizes the key.
	 *
	 * @param string $key Key.
	 */
	private static function normalize_key( string $key ): string {
		$key = strtolower( trim( $key ) );
		$key = preg_replace( '/[^a-z0-9]+/', '_', $key ) ?? $key;
		return trim( preg_replace( '/_+/', '_', $key ) ?? $key, '_' );
	}

	/**
	 * Handles the mask email operation.
	 *
	 * @param string $email Email.
	 */
	private static function mask_email( string $email ): string {
		if ( '' === $email || false === strpos( $email, '@' ) ) {
			return ''; }
		[ $local, $domain ] = explode( '@', $email, 2 );
		return substr( $local, 0, 1 ) . '***@' . $domain;
	}
}
