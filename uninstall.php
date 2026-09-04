<?php
/**
 * Plugin uninstall routine.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; }
$formcourier_crm_settings = get_option( 'formcourier_crm_settings', array() );
if ( ! is_array( $formcourier_crm_settings ) || empty( $formcourier_crm_settings['delete_data_on_uninstall'] ) ) {
	return; }
global $wpdb;
$formcourier_crm_table = $wpdb->prefix . 'formcourier_crm_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The plugin removes its own custom table during an explicit uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $formcourier_crm_table ) );
delete_option( 'formcourier_crm_settings' );
delete_option( 'formcourier_crm_db_version' );
