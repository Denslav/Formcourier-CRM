<?php
/**
 * HubSpot CRM provider.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hubspot Provider.
 */
final class FormCourier_CRM_HubSpot_Provider {

	private const CONTACTS_ENDPOINT = 'https://api.hubapi.com/crm/v3/objects/contacts';
	private const DEALS_ENDPOINT    = 'https://api.hubapi.com/crm/v3/objects/deals';

	/**
	 * Tests the connection.
	 *
	 * @param string $access_token Access token.
	 */
	public function test_connection( string $access_token ): array {
		if ( empty( $access_token ) ) {
			return $this->make_result(
				false,
				'connection_test',
				__( 'HubSpot Service Key is empty.', 'formcourier-crm' )
			);
		}

		$test_email = 'formcourier-crm-test-' . time() . '@example.invalid';

		$response = wp_remote_post(
			self::CONTACTS_ENDPOINT . '/search',
			array(
				'timeout'            => 20,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'headers'            => $this->get_headers( $access_token ),
				'body'               => wp_json_encode(
					array(
						'filterGroups' => array(
							array(
								'filters' => array(
									array(
										'propertyName' => 'email',
										'operator'     => 'EQ',
										'value'        => $test_email,
									),
								),
							),
						),
						'properties'   => array(
							'email',
						),
						'limit'        => 1,
					),
					JSON_UNESCAPED_UNICODE
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->make_result(
				false,
				'connection_test',
				__( 'HubSpot connection failed.', 'formcourier-crm' ),
				array(
					'response_body' => $response->get_error_message(),
				)
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 401 === $status_code ) {
			return $this->make_result(
				false,
				'connection_test',
				__( 'HubSpot Service Key is invalid or expired.', 'formcourier-crm' ),
				array(
					'status_code'   => $status_code,
					'response_body' => $response_body,
				)
			);
		}

		if ( 403 === $status_code ) {
			return $this->make_result(
				false,
				'connection_test',
				__( 'HubSpot Service Key does not have the required contact read scope.', 'formcourier-crm' ),
				array(
					'status_code'   => $status_code,
					'response_body' => $response_body,
				)
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->make_result(
				false,
				'connection_test',
				__( 'HubSpot returned an unexpected API error.', 'formcourier-crm' ),
				array(
					'status_code'   => $status_code,
					'response_body' => $response_body,
				)
			);
		}

		return $this->make_result(
			true,
			'connection_test',
			__( 'Connection successful. HubSpot API is available and the Service Key works.', 'formcourier-crm' ),
			array(
				'status_code'   => $status_code,
				'response_body' => $response_body,
			)
		);
	}

	/**
	 * Creates or updates the contact.
	 *
	 * @param string $access_token Access token.
	 * @param array  $properties   Properties.
	 */
	public function create_or_update_contact( string $access_token, array $properties ): array {
		if ( empty( $properties['email'] ) ) {
			return $this->make_result(
				false,
				'skipped',
				__( 'Email is required for HubSpot contact.', 'formcourier-crm' )
			);
		}

		$email = sanitize_email( $properties['email'] );

		if ( ! is_email( $email ) ) {
			return $this->make_result(
				false,
				'skipped',
				__( 'Invalid email address.', 'formcourier-crm' ),
				array(
					'response_body' => array(
						'email' => $properties['email'],
					),
				)
			);
		}

		$properties['email'] = $email;

		$search_result = $this->find_contact_id_by_email( $access_token, $email );

		if ( ! empty( $search_result['success'] ) && ! empty( $search_result['contact_id'] ) ) {
			return $this->update_contact(
				$access_token,
				$search_result['contact_id'],
				$properties
			);
		}

		if ( empty( $search_result['success'] ) ) {
			return $search_result;
		}

		/*
		 * Extra safety layer: HubSpot search can occasionally miss a freshly
		 * created contact because of indexing delay. Before creating a new
		 * contact, try to PATCH the contact directly by the email idProperty.
		 */
		$update_by_email_result = $this->update_contact_by_email(
			$access_token,
			$email,
			$properties
		);

		if ( ! empty( $update_by_email_result['success'] ) ) {
			return $update_by_email_result;
		}

		if ( ! $this->is_not_found_result( $update_by_email_result ) ) {
			return $update_by_email_result;
		}

		$create_result = $this->create_contact( $access_token, $properties );

		if ( ! empty( $create_result['success'] ) ) {
			return $create_result;
		}

		if ( ! $this->is_conflict_result( $create_result ) ) {
			return $create_result;
		}

		$duplicate_contact_id = $this->extract_duplicate_contact_id( $create_result['response_body'] ?? '' );

		if ( '' !== $duplicate_contact_id ) {
			return $this->update_contact(
				$access_token,
				$duplicate_contact_id,
				$properties
			);
		}

		$retry_search_result = $this->find_contact_id_by_email( $access_token, $email );

		if ( ! empty( $retry_search_result['success'] ) && ! empty( $retry_search_result['contact_id'] ) ) {
			return $this->update_contact(
				$access_token,
				$retry_search_result['contact_id'],
				$properties
			);
		}

		return $create_result;
	}

	/**
	 * Creates or updates the contact and deal.
	 *
	 * @param string $access_token Access token.
	 * @param array  $properties   Properties.
	 * @param string $pipeline_id  Pipeline id.
	 * @param string $stage_id     Stage id.
	 */
	public function create_or_update_contact_and_deal(
		string $access_token,
		array $properties,
		string $pipeline_id = '',
		string $stage_id = ''
	): array {
		$contact_result = $this->create_or_update_contact( $access_token, $properties );

		if ( empty( $contact_result['success'] ) ) {
			return $contact_result;
		}

		$contact_id = ! empty( $contact_result['contact_id'] )
			? (string) $contact_result['contact_id']
			: $this->extract_object_id( $contact_result['response_body'] ?? '' );

		if ( '' === $contact_id ) {
			return $this->make_result(
				false,
				'failed',
				__( 'Contact was saved, but HubSpot contact ID was not found for deal association.', 'formcourier-crm' ),
				array(
					'response_body' => array(
						'contact' => $this->decode_response_body( $contact_result['response_body'] ?? '' ),
					),
				)
			);
		}

		$deal_result = $this->create_associated_deal(
			$access_token,
			$contact_id,
			$properties,
			$pipeline_id,
			$stage_id
		);

		if ( empty( $deal_result['success'] ) ) {
			return $this->make_result(
				false,
				'failed',
				__( 'Contact was saved, but HubSpot deal creation failed.', 'formcourier-crm' ),
				array(
					'contact_id'    => $contact_id,
					'status_code'   => $deal_result['status_code'] ?? '',
					'response_body' => array(
						'contact' => $this->decode_response_body( $contact_result['response_body'] ?? '' ),
						'deal'    => $this->decode_response_body( $deal_result['response_body'] ?? '' ),
					),
				)
			);
		}

		$contact_action = isset( $contact_result['action'] ) ? (string) $contact_result['action'] : 'saved';
		$result_action  = 'updated' === $contact_action ? 'updated' : 'created';
		$message        = 'updated' === $contact_action
			? __( 'Contact updated and deal created in HubSpot.', 'formcourier-crm' )
			: __( 'Contact created and deal created in HubSpot.', 'formcourier-crm' );

		return $this->make_result(
			true,
			$result_action,
			$message,
			array(
				'contact_id'     => $contact_id,
				'deal_id'        => $deal_result['deal_id'] ?? '',
				'contact_action' => $contact_action,
				'deal_action'    => $deal_result['action'] ?? 'created',
				'status_code'    => $deal_result['status_code'] ?? '',
				'response_body'  => array(
					'contact' => $this->decode_response_body( $contact_result['response_body'] ?? '' ),
					'deal'    => $this->decode_response_body( $deal_result['response_body'] ?? '' ),
				),
			)
		);
	}

	/**
	 * Finds the contact id by email.
	 *
	 * @param string $access_token Access token.
	 * @param string $email        Email.
	 */
	private function find_contact_id_by_email( string $access_token, string $email ): array {
		$response = wp_remote_post(
			self::CONTACTS_ENDPOINT . '/search',
			array(
				'timeout'            => 20,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'headers'            => $this->get_headers( $access_token ),
				'body'               => wp_json_encode(
					array(
						'filterGroups' => array(
							array(
								'filters' => array(
									array(
										'propertyName' => 'email',
										'operator'     => 'EQ',
										'value'        => $email,
									),
								),
							),
						),
						'properties'   => array(
							'email',
							'firstname',
							'lastname',
							'phone',
						),
						'limit'        => 1,
					),
					JSON_UNESCAPED_UNICODE
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $this->make_result(
				false,
				'failed',
				__( 'HubSpot contact search request failed.', 'formcourier-crm' ),
				array(
					'response_body' => $response->get_error_message(),
				)
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->make_result(
				false,
				'failed',
				__( 'HubSpot contact search returned HTTP error.', 'formcourier-crm' ),
				array(
					'status_code'   => $status_code,
					'response_body' => $response_body,
				)
			);
		}

		$data = json_decode( $response_body, true );

		if (
			json_last_error() === JSON_ERROR_NONE
			&& ! empty( $data['results'][0]['id'] )
		) {
			return $this->make_result(
				true,
				'found',
				__( 'HubSpot contact found.', 'formcourier-crm' ),
				array(
					'contact_id'    => (string) $data['results'][0]['id'],
					'status_code'   => $status_code,
					'response_body' => $response_body,
				)
			);
		}

		return $this->make_result(
			true,
			'not_found',
			__( 'HubSpot contact not found.', 'formcourier-crm' ),
			array(
				'status_code'   => $status_code,
				'response_body' => $response_body,
			)
		);
	}

	/**
	 * Creates the contact.
	 *
	 * @param string $access_token Access token.
	 * @param array  $properties   Properties.
	 */
	private function create_contact( string $access_token, array $properties ): array {
		$response = wp_remote_post(
			self::CONTACTS_ENDPOINT,
			array(
				'timeout'            => 20,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'headers'            => $this->get_headers( $access_token ),
				'body'               => wp_json_encode(
					array(
						'properties' => $properties,
					),
					JSON_UNESCAPED_UNICODE
				),
			)
		);

		return $this->handle_response(
			$response,
			'created',
			__( 'Contact created in HubSpot.', 'formcourier-crm' )
		);
	}

	/**
	 * Updates the contact.
	 *
	 * @param string $access_token Access token.
	 * @param string $contact_id   Contact id.
	 * @param array  $properties   Properties.
	 */
	private function update_contact(
		string $access_token,
		string $contact_id,
		array $properties
	): array {
		$response = wp_remote_request(
			self::CONTACTS_ENDPOINT . '/' . rawurlencode( $contact_id ),
			array(
				'method'             => 'PATCH',
				'timeout'            => 20,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'headers'            => $this->get_headers( $access_token ),
				'body'               => wp_json_encode(
					array(
						'properties' => $properties,
					),
					JSON_UNESCAPED_UNICODE
				),
			)
		);

		return $this->handle_response(
			$response,
			'updated',
			__( 'Contact updated in HubSpot.', 'formcourier-crm' )
		);
	}

	/**
	 * Updates the contact by email.
	 *
	 * @param string $access_token Access token.
	 * @param string $email        Email.
	 * @param array  $properties   Properties.
	 */
	private function update_contact_by_email(
		string $access_token,
		string $email,
		array $properties
	): array {
		$response = wp_remote_request(
			self::CONTACTS_ENDPOINT . '/' . rawurlencode( $email ) . '?idProperty=email',
			array(
				'method'             => 'PATCH',
				'timeout'            => 20,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'headers'            => $this->get_headers( $access_token ),
				'body'               => wp_json_encode(
					array(
						'properties' => $properties,
					),
					JSON_UNESCAPED_UNICODE
				),
			)
		);

		return $this->handle_response(
			$response,
			'updated',
			__( 'Contact updated in HubSpot by email.', 'formcourier-crm' )
		);
	}

	/**
	 * Creates the associated deal.
	 *
	 * @param string $access_token       Access token.
	 * @param string $contact_id         Contact id.
	 * @param array  $contact_properties Contact properties.
	 * @param string $pipeline_id        Pipeline id.
	 * @param string $stage_id           Stage id.
	 */
	private function create_associated_deal(
		string $access_token,
		string $contact_id,
		array $contact_properties,
		string $pipeline_id,
		string $stage_id
	): array {
		$deal_properties = array(
			'dealname'  => $this->build_deal_name( $contact_properties ),
			'dealstage' => '' !== trim( $stage_id ) ? trim( $stage_id ) : 'appointmentscheduled',
		);

		if ( '' !== trim( $pipeline_id ) ) {
			$deal_properties['pipeline'] = trim( $pipeline_id );
		}

		$response = wp_remote_post(
			self::DEALS_ENDPOINT,
			array(
				'timeout'            => 20,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
				'headers'            => $this->get_headers( $access_token ),
				'body'               => wp_json_encode(
					array(
						'properties'   => $deal_properties,
						'associations' => array(
							array(
								'to'    => array( 'id' => $contact_id ),
								'types' => array(
									array(
										'associationCategory' => 'HUBSPOT_DEFINED',
										'associationTypeId'   => 3,
									),
								),
							),
						),
					),
					JSON_UNESCAPED_UNICODE
				),
			)
		);

		return $this->handle_deal_response(
			$response,
			__( 'Deal created and associated with HubSpot contact.', 'formcourier-crm' )
		);
	}

	/**
	 * Builds the deal name.
	 *
	 * @param array $properties Properties.
	 */
	private function build_deal_name( array $properties ): string {
		$form_name = $this->get_property_value( $properties, array( 'form_name', 'fb_form_name' ) );
		$email     = $this->get_property_value( $properties, array( 'email', 'Email', 'EMAIL' ) );
		$name      = $this->get_property_value( $properties, array( 'firstname', 'first_name', 'name', 'Name' ) );

		$parts     = array_filter( array( __( 'Website request', 'formcourier-crm' ), $form_name, '' !== $name ? $name : $email ) );
		$deal_name = sanitize_text_field( implode( ' - ', $parts ) );

		return '' !== $deal_name ? $deal_name : __( 'Website request', 'formcourier-crm' );
	}

	/**
	 * Returns the property value.
	 *
	 * @param array $properties Properties.
	 * @param array $keys       Keys.
	 */
	private function get_property_value( array $properties, array $keys ): string {
		foreach ( $keys as $key ) {
			if ( ! isset( $properties[ $key ] ) || is_array( $properties[ $key ] ) ) {
				continue;
			}
			$value = trim( (string) $properties[ $key ] );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * Returns the headers.
	 *
	 * @param string $access_token Access token.
	 */
	private function get_headers( string $access_token ): array {
		return array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Handles the response request.
	 *
	 * @param mixed  $response        Response.
	 * @param string $success_action  Success action.
	 * @param string $success_message Success message.
	 */
	private function handle_response(
		$response,
		string $success_action,
		string $success_message
	): array {
		if ( is_wp_error( $response ) ) {
			return $this->make_result(
				false,
				'failed',
				__( 'HubSpot request failed.', 'formcourier-crm' ),
				array(
					'response_body' => $response->get_error_message(),
				)
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->make_result(
				false,
				'failed',
				__( 'HubSpot returned HTTP error.', 'formcourier-crm' ),
				array(
					'status_code'   => $status_code,
					'response_body' => $response_body,
				)
			);
		}

		$extra = array(
			'status_code'   => $status_code,
			'response_body' => $response_body,
		);

		$object_id = $this->extract_object_id( $response_body );

		if ( '' !== $object_id ) {
			$extra['contact_id'] = $object_id;
		}

		return $this->make_result(
			true,
			$success_action,
			$success_message,
			$extra
		);
	}

	/**
	 * Handles the deal response request.
	 *
	 * @param mixed  $response        Response.
	 * @param string $success_message Success message.
	 */
	private function handle_deal_response(
		$response,
		string $success_message
	): array {
		if ( is_wp_error( $response ) ) {
			return $this->make_result(
				false,
				'failed',
				__( 'HubSpot deal request failed.', 'formcourier-crm' ),
				array( 'response_body' => $response->get_error_message() )
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return $this->make_result(
				false,
				'failed',
				__( 'HubSpot deal request returned HTTP error.', 'formcourier-crm' ),
				array(
					'status_code'   => $status_code,
					'response_body' => $response_body,
				)
			);
		}

		return $this->make_result(
			true,
			'created',
			$success_message,
			array(
				'deal_id'       => $this->extract_object_id( $response_body ),
				'status_code'   => $status_code,
				'response_body' => $response_body,
			)
		);
	}

	/**
	 * Checks whether not found result.
	 *
	 * @param array $result Result.
	 */
	private function is_not_found_result( array $result ): bool {
		$status_code = isset( $result['status_code'] ) ? (int) $result['status_code'] : 0;

		if ( 404 === $status_code ) {
			return true;
		}

		$response_body = $this->decode_response_body( $result['response_body'] ?? '' );

		return is_array( $response_body )
			&& ! empty( $response_body['category'] )
			&& 'OBJECT_NOT_FOUND' === $response_body['category'];
	}

	/**
	 * Checks whether conflict result.
	 *
	 * @param array $result Result.
	 */
	private function is_conflict_result( array $result ): bool {
		$status_code = isset( $result['status_code'] ) ? (int) $result['status_code'] : 0;

		if ( 409 === $status_code ) {
			return true;
		}

		$response_body = $this->decode_response_body( $result['response_body'] ?? '' );

		return is_array( $response_body )
			&& ! empty( $response_body['category'] )
			&& 'CONFLICT' === $response_body['category'];
	}

	/**
	 * Extracts the duplicate contact id.
	 *
	 * @param mixed $response_body Response body.
	 */
	private function extract_duplicate_contact_id( $response_body ): string {
		$decoded_body = $this->decode_response_body( $response_body );
		$message      = '';

		if ( is_array( $decoded_body ) && ! empty( $decoded_body['message'] ) ) {
			$message = (string) $decoded_body['message'];
		} elseif ( is_string( $decoded_body ) ) {
			$message = $decoded_body;
		}

		if ( '' === $message ) {
			return '';
		}

		if ( preg_match( '/Existing ID:\s*(\d+)/i', $message, $matches ) ) {
			return (string) $matches[1];
		}

		if ( preg_match( '/ID:\s*(\d+)/i', $message, $matches ) ) {
			return (string) $matches[1];
		}

		return '';
	}

	/**
	 * Extracts the object id.
	 *
	 * @param mixed $response_body Response body.
	 */
	private function extract_object_id( $response_body ): string {
		if ( is_array( $response_body ) && ! empty( $response_body['id'] ) ) {
			return (string) $response_body['id'];
		}

		if ( ! is_string( $response_body ) || '' === $response_body ) {
			return '';
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['id'] ) ) {
			return '';
		}

		return (string) $data['id'];
	}

	/**
	 * Decodes the response body.
	 *
	 * @param mixed $response_body Response body.
	 */
	private function decode_response_body( $response_body ) {
		if ( is_array( $response_body ) || is_object( $response_body ) ) {
			return $response_body;
		}

		if ( ! is_string( $response_body ) || '' === $response_body ) {
			return $response_body;
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return $response_body;
		}

		return $data;
	}

	/**
	 * Handles the make result operation.
	 *
	 * @param bool   $success Success.
	 * @param string $action  Action.
	 * @param string $message Message.
	 * @param array  $extra   Extra.
	 */
	private function make_result(
		bool $success,
		string $action,
		string $message,
		array $extra = array()
	): array {
		return wp_parse_args(
			$extra,
			array(
				'success'       => $success,
				'status'        => $success ? 'success' : 'error',
				'action'        => $action,
				'message'       => $message,
				'contact_id'    => '',
				'deal_id'       => '',
				'status_code'   => '',
				'response_body' => '',
			)
		);
	}
}
