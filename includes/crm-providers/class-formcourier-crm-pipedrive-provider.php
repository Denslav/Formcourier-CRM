<?php
/**
 * Pipedrive CRM provider.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pipedrive Provider.
 */
final class FormCourier_CRM_Pipedrive_Provider {

	private const CUSTOM_FIELD_TYPE_CACHE_TTL = 43200;

	/**
	 * Creates or updates the contact.
	 *
	 * @param string $company_domain Company domain.
	 * @param string $api_token      Api token.
	 * @param array  $properties     Properties.
	 * @param string $entity_type    Entity type.
	 * @param string $pipeline_id    Pipeline id.
	 * @param string $stage_id       Stage id.
	 * @param string $owner_id       Owner id.
	 * @param string $currency       Currency.
	 */
	public function create_or_update_contact(
		string $company_domain,
		string $api_token,
		array $properties,
		string $entity_type = 'deal',
		string $pipeline_id = '',
		string $stage_id = '',
		string $owner_id = '',
		string $currency = ''
	): array {
		$company_domain = $this->normalize_company_domain( $company_domain );
		$api_token      = trim( $api_token );
		$entity_type    = $this->normalize_entity_type( $entity_type );
		$pipeline_id    = trim( $pipeline_id );
		$stage_id       = trim( $stage_id );
		$owner_id       = trim( $owner_id );
		$currency       = strtoupper( trim( $currency ) );
		$payload        = $this->build_payload( $properties );

		if ( empty( $company_domain ) || empty( $api_token ) ) {
			return $this->make_result(
				false,
				'error',
				'skipped',
				__( 'Pipedrive company domain or API token is empty.', 'formcourier-crm' ),
				0,
				null,
				array()
			);
		}

		if ( '' !== $owner_id ) {
			$payload['person']['owner_id'] = $payload['person']['owner_id'] ?? absint( $owner_id );
			$payload['deal']['owner_id']   = $payload['deal']['owner_id'] ?? absint( $owner_id );
			$payload['lead']['owner_id']   = $payload['lead']['owner_id'] ?? absint( $owner_id );
		}

		if ( '' !== $pipeline_id ) {
			$payload['deal']['pipeline_id'] = $payload['deal']['pipeline_id'] ?? absint( $pipeline_id );
		}

		if ( '' !== $stage_id ) {
			$payload['deal']['stage_id'] = $payload['deal']['stage_id'] ?? absint( $stage_id );
		}

		if ( '' !== $currency ) {
			$payload['deal']['currency']          = $payload['deal']['currency'] ?? sanitize_text_field( $currency );
			$payload['lead']['value']['currency'] = $payload['lead']['value']['currency'] ?? sanitize_text_field( $currency );
		}

		if ( empty( $payload['person']['name'] ) && empty( $payload['person']['emails'] ) && empty( $payload['person']['phones'] ) ) {
			unset( $payload['custom_field_types'] );

			return $this->make_result(
				false,
				'error',
				'skipped',
				__( 'At least one Pipedrive person identifier is required.', 'formcourier-crm' ),
				0,
				null,
				array(
					'payload' => $payload,
				)
			);
		}

		$payload = $this->prepare_custom_field_values(
			$company_domain,
			$api_token,
			$payload
		);

		$organization_id = null;

		if ( ! empty( $payload['organization']['name'] ) ) {
			$organization_response = $this->call(
				$company_domain,
				$api_token,
				'POST',
				'/api/v2/organizations',
				$payload['organization']
			);

			if ( $organization_response['success'] ) {
				$organization_id = absint( $this->extract_entity_id( $organization_response['body'] ) );
			}
		}

		$person_id = $this->find_person_id( $company_domain, $api_token, $payload['person'] );

		if ( $person_id > 0 ) {
			$person_response = $this->call(
				$company_domain,
				$api_token,
				'PATCH',
				'/api/v2/persons/' . $person_id,
				$this->add_org_id_to_person_payload( $payload['person'], $organization_id )
			);
		} else {
			$person_response = $this->call(
				$company_domain,
				$api_token,
				'POST',
				'/api/v2/persons',
				$this->add_org_id_to_person_payload( $payload['person'], $organization_id )
			);
		}

		if ( ! $person_response['success'] ) {
			return $this->make_result(
				false,
				'error',
				'failed',
				__( 'Pipedrive returned HTTP/API error while saving a person.', 'formcourier-crm' ),
				$person_response['status_code'],
				null,
				$person_response['body']
			);
		}

		$person_id = absint( $this->extract_entity_id( $person_response['body'] ) );

		if ( $person_id <= 0 ) {
			return $this->make_result(
				false,
				'error',
				'failed',
				__( 'Pipedrive did not return a person ID.', 'formcourier-crm' ),
				$person_response['status_code'],
				null,
				$person_response['body']
			);
		}

		if ( 'lead' === $entity_type ) {
			$existing_lead_id = $this->find_open_lead_id(
				$company_domain,
				$api_token,
				$person_id,
				$organization_id
			);

			if ( '' !== $existing_lead_id ) {
				$entity_response = $this->update_lead(
					$company_domain,
					$api_token,
					$existing_lead_id,
					$payload,
					$person_id,
					$organization_id
				);
				$entity_action   = 'updated';
			} else {
				$entity_response = $this->create_lead(
					$company_domain,
					$api_token,
					$payload,
					$person_id,
					$organization_id
				);
				$entity_action   = 'created';
			}

			$entity_id = $this->extract_entity_id( $entity_response['body'] ) ?? $existing_lead_id;

			if ( ! $entity_response['success'] ) {
				return $this->make_result(
					false,
					'error',
					'failed',
					__( 'Pipedrive returned HTTP/API error while saving a lead.', 'formcourier-crm' ),
					$entity_response['status_code'],
					(string) $person_id,
					$entity_response['body']
				);
			}

			$note_result = $this->maybe_add_note(
				$company_domain,
				$api_token,
				$payload['note'],
				array(
					'lead_id'   => $entity_id,
					'person_id' => $person_id,
				)
			);

			if ( ! empty( $note_result['warning'] ) ) {
				$message = 'updated' === $entity_action
					? __( 'Lead updated in Pipedrive, but note was not added.', 'formcourier-crm' )
					: __( 'Lead created in Pipedrive, but note was not added.', 'formcourier-crm' );
			} else {
				$message = 'updated' === $entity_action
					? __( 'Lead updated in Pipedrive.', 'formcourier-crm' )
					: __( 'Lead created in Pipedrive.', 'formcourier-crm' );
			}

			return $this->make_result(
				true,
				'success',
				$entity_action,
				$message,
				$entity_response['status_code'],
				null !== $entity_id && '' !== $entity_id ? $entity_id : (string) $person_id,
				array(
					'person' => $person_response['body'],
					'lead'   => $entity_response['body'],
					'note'   => $note_result['body'] ?? null,
				)
			);
		}

		$existing_deal_id = $this->find_open_deal_id(
			$company_domain,
			$api_token,
			$person_id,
			$payload['deal']
		);

		if ( $existing_deal_id > 0 ) {
			$entity_response = $this->update_deal(
				$company_domain,
				$api_token,
				$existing_deal_id,
				$payload,
				$person_id,
				$organization_id
			);
			$entity_action   = 'updated';
		} else {
			$entity_response = $this->create_deal(
				$company_domain,
				$api_token,
				$payload,
				$person_id,
				$organization_id
			);
			$entity_action   = 'created';
		}

		$entity_id = $this->extract_entity_id( $entity_response['body'] ) ?? ( $existing_deal_id > 0 ? (string) $existing_deal_id : null );

		if ( ! $entity_response['success'] ) {
			return $this->make_result(
				false,
				'error',
				'failed',
				__( 'Pipedrive returned HTTP/API error while saving a deal.', 'formcourier-crm' ),
				$entity_response['status_code'],
				(string) $person_id,
				$entity_response['body']
			);
		}

		$note_result = $this->maybe_add_note(
			$company_domain,
			$api_token,
			$payload['note'],
			array(
				'deal_id'   => $entity_id,
				'person_id' => $person_id,
			)
		);

		if ( ! empty( $note_result['warning'] ) ) {
			$message = 'updated' === $entity_action
				? __( 'Deal updated in Pipedrive, but note was not added.', 'formcourier-crm' )
				: __( 'Deal created in Pipedrive, but note was not added.', 'formcourier-crm' );
		} else {
			$message = 'updated' === $entity_action
				? __( 'Deal updated in Pipedrive.', 'formcourier-crm' )
				: __( 'Deal created in Pipedrive.', 'formcourier-crm' );
		}

		return $this->make_result(
			true,
			'success',
			$entity_action,
			$message,
			$entity_response['status_code'],
			null !== $entity_id && '' !== $entity_id ? $entity_id : (string) $person_id,
			array(
				'person' => $person_response['body'],
				'deal'   => $entity_response['body'],
				'note'   => $note_result['body'] ?? null,
			)
		);
	}

	/**
	 * Tests the connection.
	 *
	 * @param string $company_domain Company domain.
	 * @param string $api_token      Api token.
	 */
	public function test_connection( string $company_domain, string $api_token ): array {
		$company_domain = $this->normalize_company_domain( $company_domain );
		$api_token      = trim( $api_token );

		if ( empty( $company_domain ) || empty( $api_token ) ) {
			return $this->make_result(
				false,
				'error',
				'connection_test',
				__( 'Pipedrive company domain or API token is empty.', 'formcourier-crm' ),
				0,
				null,
				array()
			);
		}

		$response = $this->call(
			$company_domain,
			$api_token,
			'GET',
			'/api/v1/users/me',
			array()
		);

		if ( ! $response['success'] ) {
			return $this->make_result(
				false,
				'error',
				'connection_test',
				__( 'Pipedrive connection failed.', 'formcourier-crm' ),
				$response['status_code'],
				null,
				$response['body']
			);
		}

		return $this->make_result(
			true,
			'success',
			'connection_test',
			__( 'Connection successful. Pipedrive API is available.', 'formcourier-crm' ),
			$response['status_code'],
			null,
			$response['body']
		);
	}

	/**
	 * Builds the payload.
	 *
	 * @param array $properties Properties.
	 */
	private function build_payload( array $properties ): array {
		$payload = array(
			'person'             => array(),
			'organization'       => array(),
			'deal'               => array(),
			'lead'               => array(),
			'note'               => '',
			'custom_field_types' => array(),
		);

		foreach ( $properties as $field_name => $field_value ) {
			$field_name = trim( (string) $field_name );

			if ( '' === $field_name ) {
				continue;
			}

			$custom_field = $this->parse_custom_field_name( $field_name );

			if ( null !== $custom_field ) {
				$field_value = $this->sanitize_custom_field_input( $field_value );

				if ( ! $this->is_empty_custom_field_value( $field_value ) ) {
					$this->add_custom_field_value(
						$payload,
						$custom_field['key'],
						$field_value,
						$custom_field['type']
					);
				}

				continue;
			}

			if ( is_array( $field_value ) ) {
				$field_value = implode( ', ', array_map( 'strval', $field_value ) );
			}

			$field_value = sanitize_textarea_field( (string) $field_value );

			if ( '' === $field_value ) {
				continue;
			}

			$field_name = $this->normalize_pipedrive_field_name( $field_name );

			if ( 0 === strpos( $field_name, 'person.' ) ) {
				$person_field = substr( $field_name, 7 );

				if ( 'email' === $person_field ) {
					$email = sanitize_email( $field_value );

					if ( is_email( $email ) ) {
						$payload['person']['emails'] = array(
							array(
								'value'   => $email,
								'primary' => true,
								'label'   => 'work',
							),
						);
					}

					continue;
				}

				if ( 'phone' === $person_field ) {
					$payload['person']['phones'] = array(
						array(
							'value'   => $field_value,
							'primary' => true,
							'label'   => 'work',
						),
					);

					continue;
				}

				if ( 'owner_id' === $person_field ) {
					$payload['person']['owner_id'] = absint( $field_value );
					continue;
				}

				if ( 'name' === $person_field ) {
					$payload['person']['name'] = $field_value;
				}

				continue;
			}

			if ( 0 === strpos( $field_name, 'organization.' ) ) {
				$organization_field = substr( $field_name, 13 );

				if ( 'name' === $organization_field ) {
					$payload['organization']['name'] = $field_value;
				}

				continue;
			}

			if ( 'note' === $field_name ) {
				$payload['note'] = $field_value;
				continue;
			}

			if ( in_array( $field_name, array( 'pipeline_id', 'stage_id', 'owner_id' ), true ) ) {
				$payload['deal'][ $field_name ] = absint( $field_value );
				$payload['lead'][ $field_name ] = absint( $field_value );
				continue;
			}

			if ( 'value' === $field_name ) {
				$payload['deal']['value']           = (float) str_replace( ',', '.', $field_value );
				$payload['lead']['value']['amount'] = (float) str_replace( ',', '.', $field_value );
				continue;
			}

			if ( 'currency' === $field_name ) {
				$payload['deal']['currency']          = strtoupper( $field_value );
				$payload['lead']['value']['currency'] = strtoupper( $field_value );
				continue;
			}

			if ( 'title' === $field_name ) {
				$payload['deal']['title'] = $field_value;
				$payload['lead']['title'] = $field_value;
				continue;
			}

			$payload['deal'][ $field_name ] = $field_value;
			$payload['lead'][ $field_name ] = $field_value;
		}

		if ( empty( $payload['person']['name'] ) ) {
			$payload['person']['name'] = $this->generate_person_name( $payload['person'] );
		}

		if ( empty( $payload['deal']['title'] ) ) {
			$payload['deal']['title'] = $this->generate_entity_title( $payload['person'] );
		}

		if ( empty( $payload['lead']['title'] ) ) {
			$payload['lead']['title'] = $payload['deal']['title'];
		}

		return $payload;
	}

	/**
	 * Adds a custom field value to deal and lead payloads.
	 *
	 * @param array  $payload          Payload passed by reference.
	 * @param string $custom_field_key Pipedrive custom field key.
	 * @param mixed  $field_value      Sanitized custom field value.
	 * @param string $field_type       Optional Pipedrive field type.
	 */
	private function add_custom_field_value(
		array &$payload,
		string $custom_field_key,
		$field_value,
		string $field_type = ''
	): void {
		$custom_field_key = trim( $custom_field_key );
		$field_type       = sanitize_key( $field_type );

		if ( '' === $custom_field_key ) {
			return;
		}

		/*
		 * Pipedrive API v2 expects deal custom fields inside the custom_fields
		 * object. Leads inherit the deal custom-field schema, but the current
		 * v1 lead endpoint accepts the field hashes as first-level fields.
		 */
		$payload['deal']['custom_fields'][ $custom_field_key ] = $field_value;
		$payload['lead'][ $custom_field_key ]                  = $field_value;

		if ( '' !== $field_type ) {
			$payload['custom_field_types'][ $custom_field_key ] = $field_type;
		}
	}

	/**
	 * Parses supported Pipedrive custom field mapping formats.
	 *
	 * @param string $field_name Mapped field name.
	 * @return array{key:string,type:string}|null Parsed field metadata.
	 */
	private function parse_custom_field_name( string $field_name ): ?array {
		$field_name = trim( $field_name );

		if ( $this->is_custom_field_key( $field_name ) ) {
			return array(
				'key'  => $field_name,
				'type' => '',
			);
		}

		if ( 0 !== strpos( $field_name, 'custom:' ) ) {
			return null;
		}

		$custom_field = trim( substr( $field_name, 7 ) );

		if ( $this->is_custom_field_key( $custom_field ) ) {
			return array(
				'key'  => $custom_field,
				'type' => '',
			);
		}

		if ( 1 === preg_match( '/^address:([a-f0-9]{40})$/i', $custom_field, $matches ) ) {
			return array(
				'key'  => strtolower( $matches[1] ),
				'type' => 'address',
			);
		}

		return null;
	}

	/**
	 * Keeps structured address values intact while sanitizing all components.
	 * Other array values retain the previous comma-separated behavior.
	 *
	 * @param mixed $field_value Raw custom-field value.
	 * @return mixed
	 */
	private function sanitize_custom_field_input( $field_value ) {
		if ( ! is_array( $field_value ) ) {
			return sanitize_textarea_field( (string) $field_value );
		}

		$sanitized = array();

		foreach ( $field_value as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) || is_resource( $value ) ) {
				continue;
			}

			$clean_value = sanitize_textarea_field( (string) $value );

			if ( '' === $clean_value ) {
				continue;
			}

			$sanitized[ is_string( $key ) ? sanitize_key( $key ) : $key ] = $clean_value;
		}

		return $sanitized;
	}

	/**
	 * Checks whether a custom field value is empty.
	 *
	 * @param mixed $field_value Custom field value.
	 * @return bool Whether the value is empty.
	 */
	private function is_empty_custom_field_value( $field_value ): bool {
		if ( is_array( $field_value ) ) {
			return array() === $field_value;
		}

		return '' === trim( (string) $field_value );
	}

	/**
	 * Resolves custom field types and formats composite values.
	 *
	 * @param string $company_domain Pipedrive company domain.
	 * @param string $api_token      Pipedrive API token.
	 * @param array  $payload        Prepared entity payload.
	 * @return array Prepared entity payload.
	 */
	private function prepare_custom_field_values(
		string $company_domain,
		string $api_token,
		array $payload
	): array {
		$custom_fields = isset( $payload['deal']['custom_fields'] ) && is_array( $payload['deal']['custom_fields'] )
			? $payload['deal']['custom_fields']
			: array();
		$known_types   = isset( $payload['custom_field_types'] ) && is_array( $payload['custom_field_types'] )
			? $payload['custom_field_types']
			: array();

		foreach ( $custom_fields as $custom_field_key => $field_value ) {
			$field_type = isset( $known_types[ $custom_field_key ] )
				? sanitize_key( (string) $known_types[ $custom_field_key ] )
				: '';

			if ( '' === $field_type ) {
				$field_type = $this->get_custom_field_type(
					$company_domain,
					$api_token,
					(string) $custom_field_key
				);
			}

			$formatted_value = $this->format_custom_field_value( $field_value, $field_type );

			if ( null === $formatted_value ) {
				unset(
					$payload['deal']['custom_fields'][ $custom_field_key ],
					$payload['lead'][ $custom_field_key ]
				);
				continue;
			}

			$payload['deal']['custom_fields'][ $custom_field_key ] = $formatted_value;
			$payload['lead'][ $custom_field_key ]                  = $formatted_value;
		}

		if ( empty( $payload['deal']['custom_fields'] ) ) {
			unset( $payload['deal']['custom_fields'] );
		}

		unset( $payload['custom_field_types'] );

		return $payload;
	}

	/**
	 * Returns the custom field type.
	 *
	 * @param string $company_domain   Company domain.
	 * @param string $api_token        Api token.
	 * @param string $custom_field_key Custom field key.
	 */
	private function get_custom_field_type(
		string $company_domain,
		string $api_token,
		string $custom_field_key
	): string {
		$custom_field_key = strtolower( trim( $custom_field_key ) );

		if ( ! $this->is_custom_field_key( $custom_field_key ) ) {
			return '';
		}

		$cache_key   = $this->get_custom_field_type_cache_key(
			$company_domain,
			$api_token,
			$custom_field_key
		);
		$cached_type = get_transient( $cache_key );

		if ( is_string( $cached_type ) && '' !== sanitize_key( $cached_type ) ) {
			return sanitize_key( $cached_type );
		}

		$response = $this->call(
			$company_domain,
			$api_token,
			'GET',
			'/api/v2/dealFields/' . rawurlencode( $custom_field_key ),
			array()
		);

		if ( ! $response['success'] ) {
			return '';
		}

		$field_type = $this->extract_custom_field_type( $response['body'] );

		if ( '' !== $field_type ) {
			set_transient( $cache_key, $field_type, self::CUSTOM_FIELD_TYPE_CACHE_TTL );
		}

		return $field_type;
	}

	/**
	 * Returns the custom field type cache key.
	 *
	 * @param string $company_domain   Company domain.
	 * @param string $api_token        Api token.
	 * @param string $custom_field_key Custom field key.
	 */
	private function get_custom_field_type_cache_key(
		string $company_domain,
		string $api_token,
		string $custom_field_key
	): string {
		$fingerprint = hash(
			'sha256',
			strtolower( trim( $company_domain ) ) . '|' . $custom_field_key . '|' . $api_token
		);

		return 'formcourier_crm_pd_field_' . substr( $fingerprint, 0, 32 );
	}

	/**
	 * Extracts a custom field type from a Pipedrive response.
	 *
	 * @param mixed $body Pipedrive deal field response body.
	 * @return string Sanitized field type.
	 */
	private function extract_custom_field_type( $body ): string {
		if ( ! is_array( $body ) ) {
			return '';
		}

		$data       = isset( $body['data'] ) && is_array( $body['data'] )
			? $body['data']
			: $body;
		$field_type = $data['field_type'] ?? $data['type'] ?? '';

		return sanitize_key( (string) $field_type );
	}

	/**
	 * Formats a custom field value for the Pipedrive API.
	 *
	 * @param mixed  $field_value Custom field value.
	 * @param string $field_type  Pipedrive field type.
	 * @return mixed Formatted value or null when empty.
	 */
	private function format_custom_field_value( $field_value, string $field_type ) {
		$field_type = sanitize_key( $field_type );

		if ( 'address' === $field_type ) {
			return $this->normalize_address_custom_field_value( $field_value );
		}

		if ( is_array( $field_value ) ) {
			$field_value = implode( ', ', array_map( 'strval', $field_value ) );
		}

		$field_value = sanitize_textarea_field( (string) $field_value );

		return '' === $field_value ? null : $field_value;
	}

	/**
	 * Normalizes an address custom field value.
	 *
	 * @param mixed $field_value Address string or prepared address data.
	 * @return array<string,string>|null Normalized address data.
	 */
	private function normalize_address_custom_field_value( $field_value ): ?array {
		if ( ! is_array( $field_value ) ) {
			$value = sanitize_textarea_field( (string) $field_value );

			return '' === $value ? null : array( 'value' => $value );
		}

		$allowed_components = array(
			'value',
			'country',
			'admin_area_level_1',
			'admin_area_level_2',
			'locality',
			'sublocality',
			'route',
			'street_number',
			'subpremise',
			'postal_code',
		);
		$address            = array();

		foreach ( $allowed_components as $component ) {
			if ( ! isset( $field_value[ $component ] ) || is_array( $field_value[ $component ] ) ) {
				continue;
			}

			$value = sanitize_textarea_field( (string) $field_value[ $component ] );

			if ( '' !== $value ) {
				$address[ $component ] = $value;
			}
		}

		if ( empty( $address['value'] ) ) {
			$fallback_parts = array();

			foreach ( $field_value as $value ) {
				if ( is_array( $value ) || is_object( $value ) || is_resource( $value ) ) {
					continue;
				}

				$value = sanitize_textarea_field( (string) $value );

				if ( '' !== $value ) {
					$fallback_parts[] = $value;
				}
			}

			if ( array() !== $fallback_parts ) {
				$address['value'] = implode( ', ', array_unique( $fallback_parts ) );
			}
		}

		return empty( $address['value'] ) ? null : $address;
	}

	/**
	 * Checks whether custom field key.
	 *
	 * @param string $field_name Field name.
	 */
	private function is_custom_field_key( string $field_name ): bool {
		return 1 === preg_match( '/^[a-f0-9]{40}$/i', trim( $field_name ) );
	}

	/**
	 * Normalizes the pipedrive field name.
	 *
	 * @param string $field_name Field name.
	 */
	private function normalize_pipedrive_field_name( string $field_name ): string {
		$field_name = trim( $field_name );

		$aliases = array(
			'name'              => 'person.name',
			'full_name'         => 'person.name',
			'person'            => 'person.name',
			'person_name'       => 'person.name',
			'firstname'         => 'person.name',
			'first_name'        => 'person.name',
			'Last_Name'         => 'person.name',
			'NAME'              => 'person.name',

			'email'             => 'person.email',
			'Email'             => 'person.email',
			'EMAIL'             => 'person.email',
			'person_email'      => 'person.email',

			'phone'             => 'person.phone',
			'phones'            => 'person.phone',
			'Phone'             => 'person.phone',
			'PHONE'             => 'person.phone',
			'person_phone'      => 'person.phone',

			'company'           => 'organization.name',
			'Company'           => 'organization.name',
			'COMPANY_TITLE'     => 'organization.name',
			'org_name'          => 'organization.name',
			'organization_name' => 'organization.name',

			'message'           => 'note',
			'Description'       => 'note',
			'comment'           => 'note',
			'comments'          => 'note',
			'COMMENTS'          => 'note',
			'deal_note'         => 'note',
			'lead_note'         => 'note',

			'deal_title'        => 'title',
			'lead_title'        => 'title',
			'subject'           => 'title',
			'source'            => 'source_name',
			'site'              => 'source_name',
		);

		return $aliases[ $field_name ] ?? $field_name;
	}

	/**
	 * Creates the deal.
	 *
	 * @param string   $company_domain  Company domain.
	 * @param string   $api_token       Api token.
	 * @param array    $payload         Payload.
	 * @param int      $person_id       Person id.
	 * @param int|null $organization_id Organization id.
	 */
	private function create_deal( string $company_domain, string $api_token, array $payload, int $person_id, ?int $organization_id ): array {
		$deal              = $payload['deal'];
		$deal['person_id'] = $person_id;

		if ( ! empty( $organization_id ) ) {
			$deal['org_id'] = $organization_id;
		}

		return $this->call(
			$company_domain,
			$api_token,
			'POST',
			'/api/v2/deals',
			$deal
		);
	}

	/**
	 * Creates the lead.
	 *
	 * @param string   $company_domain  Company domain.
	 * @param string   $api_token       Api token.
	 * @param array    $payload         Payload.
	 * @param int      $person_id       Person id.
	 * @param int|null $organization_id Organization id.
	 */
	private function create_lead( string $company_domain, string $api_token, array $payload, int $person_id, ?int $organization_id ): array {
		$lead              = $payload['lead'];
		$lead['person_id'] = $person_id;

		if ( ! empty( $organization_id ) ) {
			$lead['organization_id'] = $organization_id;
		}

		unset( $lead['stage_id'] );

		return $this->call(
			$company_domain,
			$api_token,
			'POST',
			'/api/v1/leads',
			$lead
		);
	}

	/**
	 * Updates the deal.
	 *
	 * @param string   $company_domain  Company domain.
	 * @param string   $api_token       Api token.
	 * @param int      $deal_id         Deal id.
	 * @param array    $payload         Payload.
	 * @param int      $person_id       Person id.
	 * @param int|null $organization_id Organization id.
	 */
	private function update_deal( string $company_domain, string $api_token, int $deal_id, array $payload, int $person_id, ?int $organization_id ): array {
		$deal              = $payload['deal'];
		$deal['person_id'] = $person_id;

		if ( ! empty( $organization_id ) ) {
			$deal['org_id'] = $organization_id;
		}

		return $this->call(
			$company_domain,
			$api_token,
			'PATCH',
			'/api/v2/deals/' . $deal_id,
			$deal
		);
	}

	/**
	 * Updates the lead.
	 *
	 * @param string   $company_domain  Company domain.
	 * @param string   $api_token       Api token.
	 * @param string   $lead_id         Lead id.
	 * @param array    $payload         Payload.
	 * @param int      $person_id       Person id.
	 * @param int|null $organization_id Organization id.
	 */
	private function update_lead( string $company_domain, string $api_token, string $lead_id, array $payload, int $person_id, ?int $organization_id ): array {
		$lead              = $payload['lead'];
		$lead['person_id'] = $person_id;

		if ( ! empty( $organization_id ) ) {
			$lead['organization_id'] = $organization_id;
		}

		unset( $lead['stage_id'] );

		return $this->call(
			$company_domain,
			$api_token,
			'PATCH',
			'/api/v1/leads/' . rawurlencode( $lead_id ),
			$lead
		);
	}

	/**
	 * Finds the open deal id.
	 *
	 * @param string $company_domain Company domain.
	 * @param string $api_token      Api token.
	 * @param int    $person_id      Person id.
	 * @param array  $deal_payload   Deal payload.
	 */
	private function find_open_deal_id( string $company_domain, string $api_token, int $person_id, array $deal_payload ): int {
		if ( $person_id <= 0 ) {
			return 0;
		}

		$query = array(
			'person_id'      => $person_id,
			'status'         => 'open',
			'sort_by'        => 'update_time',
			'sort_direction' => 'desc',
			'limit'          => 1,
		);

		if ( ! empty( $deal_payload['pipeline_id'] ) ) {
			$query['pipeline_id'] = absint( $deal_payload['pipeline_id'] );
		}

		if ( ! empty( $deal_payload['stage_id'] ) ) {
			$query['stage_id'] = absint( $deal_payload['stage_id'] );
		}

		$response = $this->call(
			$company_domain,
			$api_token,
			'GET',
			'/api/v2/deals?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ),
			array()
		);

		if ( ! $response['success'] || empty( $response['body']['data'] ) || ! is_array( $response['body']['data'] ) ) {
			return 0;
		}

		foreach ( $response['body']['data'] as $deal ) {
			if ( ! empty( $deal['id'] ) ) {
				return absint( $deal['id'] );
			}
		}

		return 0;
	}

	/**
	 * Finds the open lead id.
	 *
	 * @param string   $company_domain  Company domain.
	 * @param string   $api_token       Api token.
	 * @param int      $person_id       Person id.
	 * @param int|null $organization_id Organization id.
	 */
	private function find_open_lead_id( string $company_domain, string $api_token, int $person_id, ?int $organization_id ): string {
		if ( $person_id <= 0 ) {
			return '';
		}

		$query = array(
			'person_id' => $person_id,
			'limit'     => 1,
			'sort'      => 'update_time DESC',
		);

		if ( ! empty( $organization_id ) ) {
			$query['organization_id'] = $organization_id;
		}

		$response = $this->call(
			$company_domain,
			$api_token,
			'GET',
			'/api/v1/leads?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ),
			array()
		);

		if ( ! $response['success'] || empty( $response['body']['data'] ) || ! is_array( $response['body']['data'] ) ) {
			return '';
		}

		foreach ( $response['body']['data'] as $lead ) {
			if ( ! empty( $lead['id'] ) && empty( $lead['is_archived'] ) ) {
				return (string) $lead['id'];
			}
		}

		return '';
	}

	/**
	 * Performs the add note operation when needed.
	 *
	 * @param string $company_domain Company domain.
	 * @param string $api_token      Api token.
	 * @param string $note           Note.
	 * @param array  $ids            Ids.
	 */
	private function maybe_add_note( string $company_domain, string $api_token, string $note, array $ids ): array {
		if ( '' === trim( $note ) ) {
			return array(
				'warning' => false,
				'body'    => null,
			);
		}

		$body = array(
			'content' => wp_kses_post( $note ),
		);

		foreach ( array( 'deal_id', 'lead_id', 'person_id' ) as $key ) {
			if ( ! empty( $ids[ $key ] ) ) {
				$body[ $key ] = $ids[ $key ];
				break;
			}
		}

		$response = $this->call(
			$company_domain,
			$api_token,
			'POST',
			'/api/v1/notes',
			$body
		);

		return array(
			'warning' => ! $response['success'],
			'body'    => $response['body'],
		);
	}

	/**
	 * Finds the person id.
	 *
	 * @param string $company_domain Company domain.
	 * @param string $api_token      Api token.
	 * @param array  $person         Person.
	 */
	private function find_person_id( string $company_domain, string $api_token, array $person ): int {
		$term  = '';
		$field = '';

		if ( ! empty( $person['emails'][0]['value'] ) ) {
			$term  = (string) $person['emails'][0]['value'];
			$field = 'email';
		} elseif ( ! empty( $person['phones'][0]['value'] ) ) {
			$term  = (string) $person['phones'][0]['value'];
			$field = 'phone';
		}

		if ( '' === $term || strlen( $term ) < 2 ) {
			return 0;
		}

		$path = '/api/v2/persons/search?term=' . rawurlencode( $term ) . '&fields=' . rawurlencode( $field ) . '&exact_match=1&limit=1';

		$response = $this->call(
			$company_domain,
			$api_token,
			'GET',
			$path,
			array()
		);

		if ( ! $response['success'] || empty( $response['body']['data']['items'] ) || ! is_array( $response['body']['data']['items'] ) ) {
			return 0;
		}

		foreach ( $response['body']['data']['items'] as $item ) {
			if ( ! empty( $item['item']['id'] ) ) {
				return absint( $item['item']['id'] );
			}
		}

		return 0;
	}

	/**
	 * Adds the org id to person payload.
	 *
	 * @param array    $person          Person.
	 * @param int|null $organization_id Organization id.
	 */
	private function add_org_id_to_person_payload( array $person, ?int $organization_id ): array {
		if ( ! empty( $organization_id ) && empty( $person['org_id'] ) ) {
			$person['org_id'] = $organization_id;
		}

		return $person;
	}

	/**
	 * Sends a request to the Pipedrive API.
	 *
	 * @param string $company_domain Company domain.
	 * @param string $api_token      Api token.
	 * @param string $method         Method.
	 * @param string $path           Path.
	 * @param array  $body           Body.
	 */
	private function call( string $company_domain, string $api_token, string $method, string $path, array $body ): array {
		$url = 'https://' . $company_domain . '.pipedrive.com' . $path;

		$args = array(
			'method'             => strtoupper( $method ),
			'timeout'            => 20,
			'redirection'        => 0,
			'reject_unsafe_urls' => true,
			'headers'            => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'x-api-token'  => $api_token,
			),
		);

		if ( 'GET' !== strtoupper( $method ) ) {
			$args['body'] = wp_json_encode( $body, JSON_UNESCAPED_UNICODE );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success'     => false,
				'status_code' => 0,
				'body'        => array(
					'message' => $response->get_error_message(),
				),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$body_data   = json_decode( $raw_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$body_data = array(
				'raw' => $raw_body,
			);
		}

		$api_error = false;

		if ( is_array( $body_data ) ) {
			if ( isset( $body_data['success'] ) && false === (bool) $body_data['success'] ) {
				$api_error = true;
			}

			if ( ! empty( $body_data['error'] ) ) {
				$api_error = true;
			}
		}

		return array(
			'success'     => $status_code >= 200 && $status_code < 300 && ! $api_error,
			'status_code' => $status_code,
			'body'        => $body_data,
		);
	}

	/**
	 * Normalizes the company domain.
	 *
	 * @param string $company_domain Company domain.
	 */
	private function normalize_company_domain( string $company_domain ): string {
		$company_domain = trim( strtolower( $company_domain ) );
		$company_domain = preg_replace( '#^https?://#', '', $company_domain );
		$company_domain = preg_replace( '#/.*$#', '', $company_domain );
		$company_domain = preg_replace( '#\.pipedrive\.com$#', '', $company_domain );
		$company_domain = preg_replace( '/[^a-z0-9\-]/', '', (string) $company_domain );

		return trim( (string) $company_domain, '-' );
	}

	/**
	 * Normalizes the entity type.
	 *
	 * @param string $entity_type Entity type.
	 */
	private function normalize_entity_type( string $entity_type ): string {
		$entity_type = sanitize_key( $entity_type );

		return 'lead' === $entity_type ? 'lead' : 'deal';
	}

	/**
	 * Generates the person name.
	 *
	 * @param array $person Person.
	 */
	private function generate_person_name( array $person ): string {
		if ( ! empty( $person['emails'][0]['value'] ) ) {
			return (string) $person['emails'][0]['value'];
		}

		if ( ! empty( $person['phones'][0]['value'] ) ) {
			return (string) $person['phones'][0]['value'];
		}

		return __( 'Website contact', 'formcourier-crm' );
	}

	/**
	 * Generates the entity title.
	 *
	 * @param array $person Person.
	 */
	private function generate_entity_title( array $person ): string {
		$name = ! empty( $person['name'] ) ? (string) $person['name'] : $this->generate_person_name( $person );

		/* translators: %s: contact name or contact identifier. */
		return sprintf( __( 'Website lead - %s', 'formcourier-crm' ), $name );
	}

	/**
	 * Extracts the entity id.
	 *
	 * @param mixed $body Body.
	 */
	private function extract_entity_id( $body ): ?string {
		if ( ! is_array( $body ) ) {
			return null;
		}

		$possible_paths = array(
			array( 'data', 'id' ),
			array( 'data', 'lead_id' ),
			array( 'id' ),
		);

		foreach ( $possible_paths as $path ) {
			$value = $body;

			foreach ( $path as $key ) {
				if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
					$value = null;
					break;
				}

				$value = $value[ $key ];
			}

			if ( null !== $value && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		return null;
	}

	/**
	 * Handles the make result operation.
	 *
	 * @param bool        $success       Success.
	 * @param string      $status        Status.
	 * @param string      $action        Action.
	 * @param string      $message       Message.
	 * @param int         $status_code   Status code.
	 * @param string|null $contact_id    Contact id.
	 * @param mixed       $response_body Response body.
	 */
	private function make_result(
		bool $success,
		string $status,
		string $action,
		string $message,
		int $status_code,
		?string $contact_id,
		$response_body
	): array {
		return array(
			'success'       => $success,
			'status'        => $status,
			'action'        => $action,
			'message'       => $message,
			'contact_id'    => $contact_id,
			'status_code'   => $status_code,
			'response_body' => wp_json_encode( $response_body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ),
		);
	}
}
