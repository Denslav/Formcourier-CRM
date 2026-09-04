<?php
/**
 * Privacy policy helper.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Privacy.
 */
final class FormCourier_CRM_Privacy {
	/**
	 * Initializes plugin hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'add_privacy_policy_content' ) );
	}

	/**
	 * Adds the privacy policy content.
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'This suggested text describes the data flow created when FormCourier CRM is enabled.', 'formcourier-crm' ) . '</p>';
		$content .= '<h2>' . esc_html__( 'FormCourier CRM', 'formcourier-crm' ) . '</h2>';
		$content .= '<p>' . esc_html__( 'When a visitor submits a supported form, the site may send mapped form fields such as name, email address, phone number and message content to the CRM provider selected by the site administrator. The destination is either HubSpot or Pipedrive. When HubSpot Contact + Deal mode is enabled, the plugin also creates a CRM deal associated with the contact. The data is sent only after the administrator enables the integration and supplies CRM credentials.', 'formcourier-crm' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The plugin can store a local technical log containing the form provider, CRM provider, processing result and a masked email address. Full request and response payload logging is disabled by default. CRM credentials are stored in encrypted form when OpenSSL is available.', 'formcourier-crm' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Review the privacy policy and terms of the selected CRM provider and update this site privacy policy to reflect the fields mapped by the administrator.', 'formcourier-crm' ) . '</p>';

		wp_add_privacy_policy_content( 'FormCourier CRM', wp_kses_post( wpautop( $content, false ) ) );
	}
}
