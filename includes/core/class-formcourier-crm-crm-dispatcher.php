<?php
/**
 * CRM dispatcher.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends mapped form data to the selected CRM provider.
 */
final class FormCourier_CRM_CRM_Dispatcher {

	/**
	 * Provider router.
	 *
	 * @var FormCourier_CRM_Provider_Router
	 */
	private FormCourier_CRM_Provider_Router $router;

	/**
	 * Constructor.
	 *
	 * @param FormCourier_CRM_Provider_Router $router Provider router.
	 */
	public function __construct( FormCourier_CRM_Provider_Router $router ) {
		$this->router = $router;
	}

	/**
	 * Create or update the selected CRM record.
	 *
	 * @param array<string,mixed> $properties Mapped CRM properties.
	 * @param array<string,mixed> $context    Submission context reserved for compatible providers.
	 * @return array<string,mixed>
	 */
	public function create_or_update_contact( array $properties, array $context = array() ): array {
		unset( $context );

		if ( empty( $properties ) ) {
			return array(
				'success'       => false,
				'status'        => 'error',
				'action'        => 'skipped',
				'message'       => __( 'Submission was not sent because the mapped CRM payload is empty.', 'formcourier-crm' ),
				'contact_id'    => null,
				'status_code'   => 0,
				'response_body' => '',
			);
		}

		return $this->router->send( $properties );
	}

	/**
	 * Return the current provider label.
	 */
	public function get_current_provider_label(): string {
		return $this->router->get_provider_label();
	}
}
