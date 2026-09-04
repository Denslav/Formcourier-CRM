<?php
/**
 * Translation helpers.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps stored status and action keys to translated labels.
 */
final class FormCourier_CRM_I18N {


	/**
	 * Translate a stored status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			'success' => __( 'Success', 'formcourier-crm' ),
			'error'   => __( 'Error', 'formcourier-crm' ),
			'warning' => __( 'Warning', 'formcourier-crm' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Translate a stored action key.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	public static function action_label( string $action ): string {
		$labels = array(
			'created'         => __( 'Created', 'formcourier-crm' ),
			'updated'         => __( 'Updated', 'formcourier-crm' ),
			'failed'          => __( 'Failed', 'formcourier-crm' ),
			'skipped'         => __( 'Skipped', 'formcourier-crm' ),
			'connection_test' => __( 'Connection test', 'formcourier-crm' ),
		);

		return $labels[ $action ] ?? $action;
	}
}
