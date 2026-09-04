<?php
/**
 * Contact Form 7 provider.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cf7 Provider.
 */
final class FormCourier_CRM_CF7_Provider {

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
		add_filter( 'wpcf7_validate_email', array( $this, 'validate_email_field' ), 20, 2 );
		add_filter( 'wpcf7_validate_email*', array( $this, 'validate_email_field' ), 20, 2 );

		add_action( 'wpcf7_before_send_mail', array( $this, 'handle_submission' ) );
	}

	/**
	 * Handles the submission request.
	 *
	 * @param mixed $contact_form Contact form.
	 */
	public function handle_submission( $contact_form ): void {
		if ( '1' !== $this->settings->get( 'integration_enabled', '0' ) ) {
			return;
		}

		if ( 'contact_form_7' !== $this->settings->get( 'form_provider', 'contact_form_7' ) ) {
			return;
		}

		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			return;
		}

		$posted_data = $submission->get_posted_data();

		if ( empty( $posted_data ) || ! is_array( $posted_data ) ) {
			return;
		}

		$form_id    = $this->get_form_id( $contact_form );
		$form_name  = $this->get_form_title( $contact_form );
		$clean_data = $this->clean_posted_data( $posted_data );
		$clean_data = FormCourier_CRM_Submission_Meta::append_form_data( $clean_data, $form_id, $form_name );
		$properties = $this->map_fields(
			$clean_data,
			$this->settings->get_field_mapping( 'contact_form_7' )
		);

		if ( empty( $properties ) ) {
			FormCourier_CRM_Logger::add(
				array(
					'form_provider' => 'Contact Form 7',
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
				'form_provider'     => 'Contact Form 7',
				'form_provider_key' => 'contact_form_7',
				'form_id'           => $form_id,
				'form_name'         => $form_name,
				'email'             => $logged_email,
				'source_payload'    => $clean_data,
			)
		);

		FormCourier_CRM_Logger::add(
			array(
				'form_provider' => 'Contact Form 7',
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
	 * Validates the email field.
	 *
	 * @param mixed $result Result.
	 * @param mixed $tag    Tag.
	 */
	public function validate_email_field( $result, $tag ) {
		if ( ! is_object( $tag ) || empty( $tag->name ) ) {
			return $result;
		}

		$field_name = (string) $tag->name;

		if ( ! $this->is_crm_email_field( $field_name ) ) {
			return $result;
		}

		$submission   = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;
		$posted_value = is_object( $submission ) ? $submission->get_posted_data( $field_name ) : '';

		if ( is_array( $posted_value ) ) {
			$posted_value = reset( $posted_value );
		}

		$email = sanitize_email( wp_unslash( (string) $posted_value ) );

		if ( empty( $email ) || ! is_email( $email ) ) {
			$result->invalidate(
				$tag,
				esc_html__( 'Please enter a valid email address.', 'formcourier-crm' )
			);

			return $result;
		}

		if ( $this->is_email_dns_validation_enabled() && ! $this->email_domain_exists( $email ) ) {
			$result->invalidate(
				$tag,
				esc_html__( 'Please enter a valid email address.', 'formcourier-crm' )
			);
		}

		return $result;
	}

	/**
	 * Handles the clean posted data operation.
	 *
	 * @param array $posted_data Posted data.
	 */
	private function clean_posted_data( array $posted_data ): array {
		$clean_data = array();

		foreach ( $posted_data as $field_name => $field_value ) {
			$field_name = FormCourier_CRM_Form_Data_Sanitizer::sanitize_field_name( $field_name );

			if ( '' === $field_name || 0 === strpos( $field_name, '_' ) ) {
				continue;
			}

			if ( 'g-recaptcha-response' === $field_name ) {
				continue;
			}

			FormCourier_CRM_Form_Data_Sanitizer::add_field(
				$clean_data,
				$field_name,
				$field_value,
				', '
			);
		}

		return $clean_data;
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
	 * Checks whether crm email field.
	 *
	 * @param string $field_name Field name.
	 */
	private function is_crm_email_field( string $field_name ): bool {
		$mapping_json = $this->settings->get_field_mapping( 'contact_form_7' );
		$mapping      = json_decode( $mapping_json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $mapping ) ) {
			return false;
		}

		$email_fields = array(
			'email',
			'Email',
			'EMAIL',
			'person.email',
		);

		foreach ( $mapping as $form_field => $crm_field ) {
			if (
				$field_name === (string) $form_field
				&& in_array( (string) $crm_field, $email_fields, true )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether email dns validation enabled.
	 */
	private function is_email_dns_validation_enabled(): bool {
		return '1' === $this->settings->get( 'cf7_dns_validation_enabled', '0' );
	}

	/**
	 * Handles the email domain exists operation.
	 *
	 * @param string $email Email.
	 */
	private function email_domain_exists( string $email ): bool {
		$parts = explode( '@', $email );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$domain = strtolower( trim( $parts[1] ) );

		if ( empty( $domain ) ) {
			return false;
		}

		if ( ! function_exists( 'checkdnsrr' ) ) {
			return true;
		}

		return checkdnsrr( $domain, 'MX' ) || checkdnsrr( $domain, 'A' );
	}

	/**
	 * Returns the form id.
	 *
	 * @param mixed $contact_form Contact form.
	 */
	private function get_form_id( $contact_form ): int {
		if ( is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ) {
			return (int) $contact_form->id();
		}

		return 0;
	}

	/**
	 * Returns the form title.
	 *
	 * @param mixed $contact_form Contact form.
	 */
	private function get_form_title( $contact_form ): string {
		if ( is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ) {
			return (string) $contact_form->title();
		}

		return '';
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
