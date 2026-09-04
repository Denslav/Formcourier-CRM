<?php
/**
 * CRM provider router.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Provider Router.
 */
final class FormCourier_CRM_Provider_Router {
	/**
	 * Plugin settings.
	 *
	 * @var FormCourier_CRM_Settings
	 */
	private FormCourier_CRM_Settings $settings;
	/**
	 * Instantiated CRM providers.
	 *
	 * @var array<string,object>
	 */
	private array $instances = array();
	/**
	 * Initializes the class.
	 *
	 * @param FormCourier_CRM_Settings $settings Settings.
	 */
	public function __construct( FormCourier_CRM_Settings $settings ) {
		$this->settings = $settings;}
	/**
	 * Handles the definitions operation.
	 */
	public static function definitions(): array {
		return array(
			'hubspot'   => array(
				'class' => 'FormCourier_CRM_HubSpot_Provider',
				'label' => 'HubSpot',
			),
			'pipedrive' => array(
				'class' => 'FormCourier_CRM_Pipedrive_Provider',
				'label' => 'Pipedrive',
			),
		);}
	/**
	 * Returns the provider label.
	 *
	 * @param string $provider Provider.
	 */
	public function get_provider_label( string $provider = '' ): string {
		$provider = $this->resolve( $provider );
		return self::definitions()[ $provider ]['label'] ?? $provider;}
	/**
	 * Sends mapped data to the selected provider.
	 *
	 * @param array  $properties Properties.
	 * @param string $provider   Provider.
	 */
	public function send( array $properties, string $provider = '' ): array {
		return $this->dispatch( false, $properties, $provider );}
	/**
	 * Tests the connection.
	 *
	 * @param string $provider Provider.
	 */
	public function test_connection( string $provider = '' ): array {
		return $this->dispatch( true, array(), $provider );}
	/**
	 * Resolves the selected provider key.
	 *
	 * @param string $provider Provider.
	 */
	private function resolve( string $provider ): string {
		$key = sanitize_key( '' !== $provider ? $provider : $this->settings->get( 'crm_provider', 'hubspot' ) );
		return isset( self::definitions()[ $key ] ) ? $key : 'hubspot';}
	/**
	 * Returns the selected provider instance.
	 *
	 * @param string $key Key.
	 */
	private function instance( string $key ): ?object {
		$class = self::definitions()[ $key ]['class'] ?? '';
		if ( ! $class || ! class_exists( $class ) ) {
			return null;
		}if ( ! isset( $this->instances[ $key ] ) ) {
			$this->instances[ $key ] = new $class();
		}return $this->instances[ $key ];}
	/**
	 * Dispatches a provider operation.
	 *
	 * @param bool   $test       Test.
	 * @param array  $properties Properties.
	 * @param string $provider   Provider.
	 */
	private function dispatch( bool $test, array $properties, string $provider ): array {
		$key      = $this->resolve( $provider );
		$instance = $this->instance( $key );
		if ( ! $instance ) {
			return $this->error( __( 'CRM provider is unavailable.', 'formcourier-crm' ) );
		}
		try {
			if ( 'hubspot' === $key ) {
				$token = (string) $this->settings->get( 'hubspot_access_token' );
				if ( '' === $token ) {
					return $this->error( __( 'HubSpot access token is empty.', 'formcourier-crm' ) );
				}
				if ( $test ) {
					return $instance->test_connection( $token );
				}
				if ( 'contact_deal' === (string) $this->settings->get( 'hubspot_submission_mode', 'contact' ) ) {
					return $instance->create_or_update_contact_and_deal(
						$token,
						$properties,
						(string) $this->settings->get( 'hubspot_deal_pipeline_id', '' ),
						(string) $this->settings->get( 'hubspot_deal_stage_id', 'appointmentscheduled' )
					);
				}
				return $instance->create_or_update_contact( $token, $properties );
			}
			$domain = (string) $this->settings->get( 'pipedrive_company_domain' );
			$token  = (string) $this->settings->get( 'pipedrive_api_token' );
			if ( '' === $domain || '' === $token ) {
				return $this->error( __( 'Pipedrive company domain or API token is empty.', 'formcourier-crm' ) );
			}
			return $test ? $instance->test_connection( $domain, $token ) : $instance->create_or_update_contact( $domain, $token, $properties, (string) $this->settings->get( 'pipedrive_entity_type', 'deal' ), (string) $this->settings->get( 'pipedrive_pipeline_id' ), (string) $this->settings->get( 'pipedrive_stage_id' ), (string) $this->settings->get( 'pipedrive_owner_id' ), (string) $this->settings->get( 'pipedrive_currency' ) );
		} catch ( Throwable $e ) {
			return $this->error( __( 'CRM provider request failed.', 'formcourier-crm' ) );}
	}
	/**
	 * Builds a provider error result.
	 *
	 * @param string $message Message.
	 */
	private function error( string $message ): array {
		return array(
			'success'       => false,
			'status'        => 'error',
			'action'        => 'skipped',
			'message'       => $message,
			'contact_id'    => null,
			'status_code'   => 0,
			'response_body' => '',
		);}
}
