<?php
/**
 * WPForms provider.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wpforms Provider.
 */
final class FormCourier_CRM_WPForms_Provider {

	/**
	 * Plugin settings.
	 *
	 * @var FormCourier_CRM_Settings
	 */
	private $settings;

	/**
	 * CRM dispatcher.
	 *
	 * @var FormCourier_CRM_CRM_Dispatcher
	 */
	private $crm_dispatcher;

	/**
	 * Initializes the class.
	 *
	 * @param FormCourier_CRM_Settings       $settings       Settings.
	 * @param FormCourier_CRM_CRM_Dispatcher $crm_dispatcher Crm dispatcher.
	 */
	public function __construct(
		FormCourier_CRM_Settings $settings,
		FormCourier_CRM_CRM_Dispatcher $crm_dispatcher
	) {
		$this->settings       = $settings;
		$this->crm_dispatcher = $crm_dispatcher;
	}

	/**
	 * Initializes plugin hooks.
	 */
	public function init(): void {
		add_action( 'wpforms_process_complete', array( $this, 'handle_submission' ), 20, 4 );
	}

	/**
	 * Handles the submission request.
	 *
	 * @param array $fields    Fields.
	 * @param array $entry     Entry.
	 * @param array $form_data Form data.
	 * @param int   $entry_id  Entry id.
	 */
	public function handle_submission( array $fields, array $entry, array $form_data, int $entry_id ): void {
		unset( $entry_id );

		if ( '1' !== $this->settings->get( 'integration_enabled', '0' ) ) {
			return;
		}

		if ( 'wpforms' !== $this->settings->get( 'form_provider', 'contact_form_7' ) ) {
			return;
		}

		$form_id    = $this->get_form_id( $form_data );
		$form_name  = $this->get_form_title( $form_data );
		$clean_data = $this->clean_wpforms_fields( $fields );
		$clean_data = FormCourier_CRM_Submission_Meta::append_form_data( $clean_data, $form_id, $form_name );

		if ( empty( $clean_data ) ) {
			return;
		}

		$properties = $this->map_fields(
			$clean_data,
			$this->settings->get_field_mapping( 'wpforms' )
		);

		if ( empty( $properties ) ) {
			FormCourier_CRM_Logger::add(
				array(
					'form_provider' => 'WPForms',
					'crm_provider'  => $this->crm_dispatcher->get_current_provider_label(),
					'form_id'       => $form_id,
					'form_name'     => $form_name,
					'email'         => FormCourier_CRM_Submission_Meta::extract_email( $clean_data ),
					'action'        => 'skipped',
					'status'        => 'error',
					'message'       => __( 'Submission was not sent because field mapping is empty or no CRM fields were mapped.', 'formcourier-crm' ),
					'request_body'  => '',
					'response_body' => '',
				)
			);

			return;
		}

		$logged_email = $this->get_logged_email( $properties );
		$result       = $this->crm_dispatcher->create_or_update_contact(
			$properties,
			array(
				'form_provider'     => 'WPForms',
				'form_provider_key' => 'wpforms',
				'form_id'           => $form_id,
				'form_name'         => $form_name,
				'email'             => $logged_email,
				'source_payload'    => $clean_data,
			)
		);

		FormCourier_CRM_Logger::add(
			array(
				'form_provider' => 'WPForms',
				'crm_provider'  => $this->crm_dispatcher->get_current_provider_label(),
				'form_id'       => $form_id,
				'form_name'     => $form_name,
				'email'         => $logged_email,
				'action'        => $result['action'] ?? '',
				'status'        => $result['status'] ?? '',
				'message'       => $result['message'] ?? '',
				'request_body'  => $properties,
				'response_body' => $result['response_body'] ?? '',
			)
		);
	}

	/**
	 * Handles the clean wpforms fields operation.
	 *
	 * @param array $fields Fields.
	 */
	private function clean_wpforms_fields( array $fields ): array {
		$clean_data = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['id'] ) ) {
				continue;
			}

			$field_id    = FormCourier_CRM_Form_Data_Sanitizer::sanitize_field_name( $field['id'] );
			$field_name  = isset( $field['name'] )
				? FormCourier_CRM_Form_Data_Sanitizer::sanitize_field_name( $field['name'] )
				: '';
			$field_value = $this->get_field_value( $field );

			if ( '' === $field_value ) {
				continue;
			}

			/*
			 * Allows mapping by WPForms field ID:
			 * 1 -> firstname
			 * 2 -> email
			 */
			$clean_data[ $field_id ] = $field_value;

			/*
			 * Allows mapping by "field_1":
			 * field_1 -> firstname
			 * field_2 -> email
			 */
			$clean_data[ 'field_' . $field_id ] = $field_value;

			/*
			 * Allows mapping by field label:
			 * Name -> firstname
			 * Email -> email
			 */
			if ( '' !== $field_name ) {
				$clean_data[ $field_name ] = $field_value;
			}
		}

		return $clean_data;
	}

	/**
	 * Returns the field value.
	 *
	 * @param array $field Field.
	 */
	private function get_field_value( array $field ): string {
		if ( array_key_exists( 'value', $field ) ) {
			return FormCourier_CRM_Form_Data_Sanitizer::sanitize_value( $field['value'], ', ' );
		}

		if ( array_key_exists( 'value_raw', $field ) ) {
			return FormCourier_CRM_Form_Data_Sanitizer::sanitize_value( $field['value_raw'], ', ' );
		}

		return '';
	}

	/**
	 * Maps the fields.
	 *
	 * @param array  $data         Data.
	 * @param string $mapping_json Mapping json.
	 */
	private function map_fields( array $data, string $mapping_json ): array {
		return $this->settings->map_submission_data( $data, $mapping_json );
	}

	/**
	 * Returns the form id.
	 *
	 * @param array $form_data Form data.
	 */
	private function get_form_id( array $form_data ): int {
		return ! empty( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
	}

	/**
	 * Returns the form title.
	 *
	 * @param array $form_data Form data.
	 */
	private function get_form_title( array $form_data ): string {
		return ! empty( $form_data['settings']['form_title'] )
			? sanitize_text_field( (string) $form_data['settings']['form_title'] )
			: '';
	}

	/**
	 * Returns the logged email.
	 *
	 * @param array $properties Properties.
	 */
	private function get_logged_email( array $properties ): string {
		return FormCourier_CRM_Submission_Meta::extract_email( $properties );
	}
}
