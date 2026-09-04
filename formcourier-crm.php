<?php
/**
 * Plugin Name: FormCourier CRM
 * Description: Connect Contact Form 7 and WPForms to HubSpot or Pipedrive with visual field mapping and privacy-aware logs.
 * Version: 1.0.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Den Slav
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: formcourier-crm
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FORMCOURIER_CRM_VERSION', '1.0.1' );
define( 'FORMCOURIER_CRM_DB_VERSION', '1.0.0' );
define( 'FORMCOURIER_CRM_FILE', __FILE__ );
define( 'FORMCOURIER_CRM_PATH', plugin_dir_path( __FILE__ ) );
define( 'FORMCOURIER_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'FORMCOURIER_CRM_BASENAME', plugin_basename( __FILE__ ) );
define( 'FORMCOURIER_CRM_OPTION_NAME', 'formcourier_crm_settings' );
define( 'FORMCOURIER_CRM_PRO_URL', 'https://formcourier.site/' );

/**
 * Loads the files.
 */
function formcourier_crm_load_files(): void {
	$files = array(
		'includes/core/class-formcourier-crm-i18n.php',
		'includes/core/class-formcourier-crm-encryption.php',
		'includes/core/class-formcourier-crm-form-data-sanitizer.php',
		'includes/core/class-formcourier-crm-submission-meta.php',
		'includes/core/class-formcourier-crm-privacy.php',
		'includes/core/class-formcourier-crm-logger.php',
		'includes/core/class-formcourier-crm-settings.php',
		'includes/core/class-formcourier-crm-provider-router.php',
		'includes/core/class-formcourier-crm-crm-dispatcher.php',
		'includes/core/class-formcourier-crm-logs-page.php',
		'includes/form-providers/class-formcourier-crm-cf7-provider.php',
		'includes/form-providers/class-formcourier-crm-wpforms-provider.php',
		'includes/crm-providers/class-formcourier-crm-hubspot-provider.php',
		'includes/crm-providers/class-formcourier-crm-pipedrive-provider.php',
		'includes/core/class-formcourier-crm-plugin.php',
	);

	foreach ( $files as $file ) {
		require_once FORMCOURIER_CRM_PATH . $file;
	}
}

/**
 * Handles the activate operation.
 */
function formcourier_crm_activate(): void {
	require_once FORMCOURIER_CRM_PATH . 'includes/core/class-formcourier-crm-logger.php';
	FormCourier_CRM_Logger::create_table();
	update_option( 'formcourier_crm_db_version', FORMCOURIER_CRM_DB_VERSION );
}
register_activation_hook( FORMCOURIER_CRM_FILE, 'formcourier_crm_activate' );

/**
 * Initializes plugin hooks.
 */
function formcourier_crm_init(): void {
	formcourier_crm_load_files();
	FormCourier_CRM_Privacy::init();
	FormCourier_CRM_Logger::maybe_create_table();

	$plugin = new FormCourier_CRM_Plugin();
	$plugin->init();
}
add_action( 'plugins_loaded', 'formcourier_crm_init', 99 );
