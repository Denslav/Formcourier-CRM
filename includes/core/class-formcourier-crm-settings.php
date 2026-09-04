<?php
/**
 * Plugin settings.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

/**
 * Settings.
 */
final class FormCourier_CRM_Settings {
	/**
	 * CRM provider router.
	 *
	 * @var FormCourier_CRM_Provider_Router|null
	 */
	private ?FormCourier_CRM_Provider_Router $provider_router = null;

	/**
	 * Handles the defaults operation.
	 */
	public static function defaults(): array {
		return array(
			'integration_enabled'        => '0',
			'form_provider'              => 'contact_form_7',
			'crm_provider'               => 'hubspot',
			'cf7_dns_validation_enabled' => '0',
			'hubspot_access_token'       => '',
			'hubspot_submission_mode'    => 'contact',
			'hubspot_deal_pipeline_id'   => '',
			'hubspot_deal_stage_id'      => 'appointmentscheduled',
			'pipedrive_company_domain'   => '',
			'pipedrive_api_token'        => '',
			'pipedrive_entity_type'      => 'deal',
			'pipedrive_pipeline_id'      => '',
			'pipedrive_stage_id'         => '',
			'pipedrive_owner_id'         => '',
			'pipedrive_currency'         => '',
			'field_mappings'             => self::default_mappings(),
			'log_payload_data_enabled'   => '0',
			'delete_data_on_uninstall'   => '0',
		);
	}

	/**
	 * Handles the default mappings operation.
	 */
	public static function default_mappings(): array {
		return array(
			'hubspot'   => array(
				'contact_form_7' => array(
					'your-name'    => 'firstname',
					'your-email'   => 'email',
					'your-tel'     => 'phone',
					'your-message' => 'message',
				),
				'wpforms'        => array(
					'2' => 'firstname',
					'3' => 'email',
					'4' => 'phone',
					'6' => 'message',
				),
			),
			'pipedrive' => array(
				'contact_form_7' => array(
					'your-name'    => 'person.name',
					'your-email'   => 'person.email',
					'your-tel'     => 'person.phone',
					'your-message' => 'note',
				),
				'wpforms'        => array(
					'2' => 'person.name',
					'3' => 'person.email',
					'4' => 'person.phone',
					'6' => 'note',
				),
			),
		);
	}

	/**
	 * Initializes plugin hooks.
	 */
	public function init(): void {
		$this->maybe_encrypt_stored_credentials();
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_formcourier_crm_test_connection', array( $this, 'handle_connection_test' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Handles the set provider router operation.
	 *
	 * @param FormCourier_CRM_Provider_Router $router Router.
	 */
	public function set_provider_router( FormCourier_CRM_Provider_Router $router ): void {
		$this->provider_router = $router; }

	/**
	 * Adds the settings page.
	 */
	public function add_settings_page(): void {
		add_menu_page( 'FormCourier CRM', 'FormCourier CRM', 'manage_options', 'formcourier-crm', array( $this, 'render_page' ), 'dashicons-randomize', 56 );
		add_submenu_page( 'formcourier-crm', __( 'Settings', 'formcourier-crm' ), __( 'Settings', 'formcourier-crm' ), 'manage_options', 'formcourier-crm', array( $this, 'render_page' ) );
	}

	/**
	 * Handles the register settings operation.
	 */
	public function register_settings(): void {
		register_setting( 'formcourier_crm_settings_group', FORMCOURIER_CRM_OPTION_NAME, array( $this, 'sanitize' ) );
	}

	/**
	 * Handles the enqueue assets operation.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, 'formcourier-crm' ) ) {
			return; }
		wp_enqueue_style( 'formcourier-crm-admin', FORMCOURIER_CRM_URL . 'assets/admin.css', array(), FORMCOURIER_CRM_VERSION );
		wp_enqueue_script( 'formcourier-crm-admin', FORMCOURIER_CRM_URL . 'assets/admin.js', array(), FORMCOURIER_CRM_VERSION, true );
		wp_localize_script(
			'formcourier-crm-admin',
			'formcourierCrmAdmin',
			array(
				'completeMappingRow' => __( 'Complete both fields in this mapping row.', 'formcourier-crm' ),
			)
		);
	}

	/**
	 * Returns the all.
	 */
	public function get_all(): array {
		$raw      = get_option( FORMCOURIER_CRM_OPTION_NAME, array() );
		$settings = wp_parse_args( is_array( $raw ) ? $raw : array(), self::defaults() );
		foreach ( array( 'hubspot_access_token', 'pipedrive_api_token' ) as $key ) {
			$settings[ $key ] = FormCourier_CRM_Encryption::decrypt( (string) $settings[ $key ] );
		}
		return $settings;
	}

	/**
	 * Handles the get operation.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Fallback value.
	 */
	public function get( string $key, $fallback = '' ) {
		$settings = $this->get_all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	/**
	 * Returns the field mapping.
	 *
	 * @param string $form_provider Form provider.
	 * @param string $crm_provider  Crm provider.
	 */
	public function get_field_mapping( string $form_provider, string $crm_provider = '' ): string {
		$crm_provider = '' !== $crm_provider ? $crm_provider : sanitize_key( (string) $this->get( 'crm_provider', 'hubspot' ) );
		$mappings     = $this->get( 'field_mappings', array() );
		$mapping      = $mappings[ $crm_provider ][ sanitize_key( $form_provider ) ] ?? array();
		return wp_json_encode( is_array( $mapping ) ? $mapping : array(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	/**
	 * Maps the submission data.
	 *
	 * @param array  $data         Data.
	 * @param string $mapping_json Mapping json.
	 */
	public function map_submission_data( array $data, string $mapping_json ): array {
		$mapping = json_decode( $mapping_json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $mapping ) ) {
			return array(); }
		$mapped = array();
		foreach ( $mapping as $form_field => $crm_field ) {
			$form_field = (string) $form_field;
			$crm_field  = trim( (string) $crm_field );
			if ( '' !== $form_field && '' !== $crm_field && isset( $data[ $form_field ] ) && '' !== $data[ $form_field ] ) {
				$mapped[ $crm_field ] = $data[ $form_field ]; }
		}
		return $mapped;
	}

	/**
	 * Handles the sanitize operation.
	 *
	 * @param mixed $input Input.
	 */
	public function sanitize( $input ): array {
		$input   = is_array( $input ) ? wp_unslash( $input ) : array();
		$section = sanitize_key( (string) ( $input['_section'] ?? '' ) );

		if ( ! in_array( $section, array( '', 'general', 'crm', 'mapping' ), true ) ) {
			$section = '';
		}

		$current = get_option( FORMCOURIER_CRM_OPTION_NAME, array() );
		$current = is_array( $current ) ? $current : array();
		$output  = wp_parse_args( $current, self::defaults() );

		$is_section = static function ( string $name ) use ( $section ): bool {
			return '' === $section || $name === $section;
		};

		if ( $is_section( 'general' ) ) {
			if ( 'general' === $section || array_key_exists( 'integration_enabled', $input ) ) {
				$output['integration_enabled'] = ! empty( $input['integration_enabled'] ) ? '1' : '0';
			}

			if ( array_key_exists( 'form_provider', $input ) ) {
				$form_provider           = sanitize_key( (string) $input['form_provider'] );
				$output['form_provider'] = in_array( $form_provider, array( 'contact_form_7', 'wpforms' ), true )
					? $form_provider
					: 'contact_form_7';
			}

			if ( array_key_exists( 'crm_provider', $input ) ) {
				$crm_provider           = sanitize_key( (string) $input['crm_provider'] );
				$output['crm_provider'] = in_array( $crm_provider, array( 'hubspot', 'pipedrive' ), true )
					? $crm_provider
					: 'hubspot';
			}

			foreach ( array( 'cf7_dns_validation_enabled', 'log_payload_data_enabled', 'delete_data_on_uninstall' ) as $key ) {
				if ( 'general' === $section || array_key_exists( $key, $input ) ) {
					$output[ $key ] = ! empty( $input[ $key ] ) ? '1' : '0';
				}
			}
		}

		if ( $is_section( 'crm' ) ) {
			if ( array_key_exists( 'hubspot_submission_mode', $input ) ) {
				$hubspot_mode                      = sanitize_key( (string) $input['hubspot_submission_mode'] );
				$output['hubspot_submission_mode'] = in_array( $hubspot_mode, array( 'contact', 'contact_deal' ), true )
					? $hubspot_mode
					: 'contact';
			}

			foreach ( array( 'hubspot_deal_pipeline_id', 'hubspot_deal_stage_id' ) as $key ) {
				if ( array_key_exists( $key, $input ) ) {
					$output[ $key ] = sanitize_text_field( (string) $input[ $key ] );
				}
			}

			if ( array_key_exists( 'pipedrive_company_domain', $input ) ) {
				$output['pipedrive_company_domain'] = sanitize_text_field( (string) $input['pipedrive_company_domain'] );
			}

			if ( array_key_exists( 'pipedrive_entity_type', $input ) ) {
				$entity_type                     = sanitize_key( (string) $input['pipedrive_entity_type'] );
				$output['pipedrive_entity_type'] = in_array( $entity_type, array( 'deal', 'lead' ), true )
					? $entity_type
					: 'deal';
			}

			foreach ( array( 'pipedrive_pipeline_id', 'pipedrive_stage_id', 'pipedrive_owner_id', 'pipedrive_currency' ) as $key ) {
				if ( array_key_exists( $key, $input ) ) {
					$output[ $key ] = sanitize_text_field( (string) $input[ $key ] );
				}
			}

			foreach ( array( 'hubspot_access_token', 'pipedrive_api_token' ) as $key ) {
				if ( ! array_key_exists( $key, $input ) ) {
					continue;
				}

				$submitted = trim( (string) $input[ $key ] );

				if ( '' === $submitted ) {
					continue;
				}

				$encrypted = FormCourier_CRM_Encryption::encrypt( sanitize_text_field( $submitted ) );

				if ( '' === $encrypted ) {
					add_settings_error(
						FORMCOURIER_CRM_OPTION_NAME,
						'formcourier_crm_encryption_failed',
						__( 'The CRM credential was not saved because secure encryption is unavailable. Enable the PHP OpenSSL extension and try again.', 'formcourier-crm' ),
						'error'
					);
					continue;
				}

				$output[ $key ] = $encrypted;
			}
		}

		if ( $is_section( 'mapping' ) && ( 'mapping' === $section || array_key_exists( 'field_mappings', $input ) ) ) {
			$has_incomplete_mapping = false;
			$clean_mappings         = $this->sanitize_mappings( $input['field_mappings'] ?? array(), $has_incomplete_mapping );

			if ( $has_incomplete_mapping ) {
				add_settings_error(
					FORMCOURIER_CRM_OPTION_NAME,
					'formcourier_crm_incomplete_mapping',
					__( 'Each mapping row must contain both a form field and a CRM field. The mapping changes were not saved.', 'formcourier-crm' ),
					'error'
				);
			} else {
				$output['field_mappings'] = $clean_mappings;
			}
		}

		unset( $output['_section'] );

		return $output;
	}

	/**
	 * Sanitizes the mappings.
	 *
	 * @param mixed $input          Input.
	 * @param bool  $has_incomplete Whether an incomplete row was found.
	 */
	private function sanitize_mappings( $input, bool &$has_incomplete = false ): array {
		$result         = array();
		$input          = is_array( $input ) ? $input : array();
		$default_values = self::default_mappings();

		foreach ( array( 'hubspot', 'pipedrive' ) as $crm ) {
			foreach ( array( 'contact_form_7', 'wpforms' ) as $form ) {
				$rows       = $input[ $crm ][ $form ] ?? array();
				$clean      = array();
				$crm_fields = array();

				if ( is_array( $rows ) ) {
					foreach ( $rows as $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}

						$form_field = trim( sanitize_text_field( (string) ( $row['form'] ?? '' ) ) );
						$crm_field  = trim( sanitize_text_field( (string) ( $row['crm'] ?? '' ) ) );

						if ( '' === $form_field && '' === $crm_field ) {
							continue;
						}

						if ( '' === $form_field || '' === $crm_field ) {
							$has_incomplete = true;
							continue;
						}

						if ( isset( $clean[ $form_field ] ) || isset( $crm_fields[ $crm_field ] ) ) {
							continue;
						}

						$clean[ $form_field ]     = $crm_field;
						$crm_fields[ $crm_field ] = true;
					}
				}

				$has_submitted_rows      = isset( $input[ $crm ][ $form ] );
				$result[ $crm ][ $form ] = $has_submitted_rows
					? $clean
					: ( $default_values[ $crm ][ $form ] ?? array() );
			}
		}

		return $result;
	}

	/**
	 * Performs the encrypt stored credentials operation when needed.
	 */
	private function maybe_encrypt_stored_credentials(): void {
		if ( ! FormCourier_CRM_Encryption::is_available() ) {
			return;
		}

		$settings = get_option( FORMCOURIER_CRM_OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			return;
		}

		$updated = false;

		foreach ( array( 'hubspot_access_token', 'pipedrive_api_token' ) as $key ) {
			if ( empty( $settings[ $key ] ) || FormCourier_CRM_Encryption::is_encrypted( (string) $settings[ $key ] ) ) {
				continue;
			}

			$encrypted = FormCourier_CRM_Encryption::encrypt( (string) $settings[ $key ] );

			if ( '' === $encrypted ) {
				continue;
			}

			$settings[ $key ] = $encrypted;
			$updated          = true;
		}

		if ( $updated ) {
			update_option( FORMCOURIER_CRM_OPTION_NAME, $settings );
		}
	}

	/**
	 * Handles the connection test request.
	 */
	public function handle_connection_test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'formcourier-crm' ) );
		}

		check_admin_referer( 'formcourier_crm_test_connection' );

		$result = $this->provider_router
			? $this->provider_router->test_connection()
			: array(
				'success' => false,
				'message' => __( 'CRM provider is unavailable.', 'formcourier-crm' ),
			);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'formcourier-crm',
					'connection_status'  => ! empty( $result['success'] ) ? 'success' : 'error',
					'connection_message' => (string) ( $result['message'] ?? __( 'Connection test finished.', 'formcourier-crm' ) ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renders the plugin settings page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return; }
		$settings = $this->get_all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The tab value only selects a read-only settings view.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		if ( ! in_array( $tab, array( 'general', 'crm', 'mapping' ), true ) ) {
			$tab = 'general';
		}
		?>
		<div class="wrap formcourier-crm-wrap">
			<h1><?php esc_html_e( 'FormCourier CRM', 'formcourier-crm' ); ?></h1>
			<p><?php esc_html_e( 'Connect Contact Form 7 or WPForms to HubSpot or Pipedrive.', 'formcourier-crm' ); ?></p>
			<?php settings_errors( FORMCOURIER_CRM_OPTION_NAME ); ?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These values display the result of a nonce-protected admin action. ?>
			<?php if ( isset( $_GET['connection_message'] ) ) : ?>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value displays the result of a nonce-protected admin action.
				$connection_status = isset( $_GET['connection_status'] ) ? sanitize_key( wp_unslash( $_GET['connection_status'] ) ) : 'error';
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value displays the result of a nonce-protected admin action.
				$connection_message = sanitize_text_field( wp_unslash( $_GET['connection_message'] ) );
				?>
				<div class="notice notice-<?php echo esc_attr( 'success' === $connection_status ? 'success' : 'error' ); ?>">
					<p><?php echo esc_html( $connection_message ); ?></p>
				</div>
			<?php endif; ?>
			<nav class="nav-tab-wrapper">
				<?php
				$tabs = array(
					'general' => __( 'General', 'formcourier-crm' ),
					'crm'     => __( 'CRM', 'formcourier-crm' ),
					'mapping' => __( 'Forms & Mapping', 'formcourier-crm' ),
				); foreach ( $tabs as $key => $label ) :
					?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=formcourier-crm&tab=' . $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="formcourier-crm-layout">
				<div class="formcourier-crm-main">
					<form method="post" action="options.php">
						<?php settings_fields( 'formcourier_crm_settings_group' ); ?>
						<input type="hidden" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[_section]" value="<?php echo esc_attr( $tab ); ?>">
						<?php
						if ( 'general' === $tab ) {
							$this->render_general( $settings );
						} elseif ( 'crm' === $tab ) {
							$this->render_crm( $settings );
						} else {
							$this->render_mapping( $settings );
						}
						?>
						<?php submit_button(); ?>
					</form>
				</div>
				<aside class="formcourier-crm-sidebar">
					<?php $this->render_current_integration( $settings ); ?>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the general section.
	 *
	 * @param array $s S.
	 */
	private function render_general( array $s ): void {

		?>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Integration status', 'formcourier-crm' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[integration_enabled]" value="1" <?php checked( $s['integration_enabled'], '1' ); ?>> <?php esc_html_e( 'Enable CRM integration', 'formcourier-crm' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Form plugin', 'formcourier-crm' ); ?></th><td><select name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[form_provider]"><option value="contact_form_7" <?php selected( $s['form_provider'], 'contact_form_7' ); ?>>Contact Form 7</option><option value="wpforms" <?php selected( $s['form_provider'], 'wpforms' ); ?>>WPForms</option></select></td></tr>
			<tr><th><?php esc_html_e( 'CRM system', 'formcourier-crm' ); ?></th><td><select name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[crm_provider]"><option value="hubspot" <?php selected( $s['crm_provider'], 'hubspot' ); ?>>HubSpot CRM</option><option value="pipedrive" <?php selected( $s['crm_provider'], 'pipedrive' ); ?>>Pipedrive</option></select></td></tr>
			<tr><th><?php esc_html_e( 'Contact Form 7 email DNS validation', 'formcourier-crm' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[cf7_dns_validation_enabled]" value="1" <?php checked( $s['cf7_dns_validation_enabled'], '1' ); ?>> <?php esc_html_e( 'Validate mapped Contact Form 7 email domains', 'formcourier-crm' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Detailed payload logs', 'formcourier-crm' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[log_payload_data_enabled]" value="1" <?php checked( $s['log_payload_data_enabled'], '1' ); ?>> <?php esc_html_e( 'Store masked request and response payloads for troubleshooting', 'formcourier-crm' ); ?></label><p class="description"><?php esc_html_e( 'Disabled by default. Emails, phone numbers, tokens and other sensitive values are masked.', 'formcourier-crm' ); ?></p></td></tr>
			<tr><th><?php esc_html_e( 'Delete data on uninstall', 'formcourier-crm' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $s['delete_data_on_uninstall'], '1' ); ?>> <?php esc_html_e( 'Delete settings and logs when the plugin is uninstalled', 'formcourier-crm' ); ?></label></td></tr>
		</table>
		<?php
	}


	/**
	 * Renders the crm section.
	 *
	 * @param array $s S.
	 */
	private function render_crm( array $s ): void {

		?>
		<h2>HubSpot CRM</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Private app access token', 'formcourier-crm' ); ?></th>
				<td>
					<input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[hubspot_access_token]" value="" placeholder="<?php echo esc_attr( ! empty( $s['hubspot_access_token'] ) ? '••••••••••••' : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Leave empty to keep the saved token. Contact mode requires contact read and write scopes. Contact + Deal also requires the deal write scope.', 'formcourier-crm' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="formcourier-crm-hubspot-submission-mode"><?php esc_html_e( 'HubSpot Save Mode', 'formcourier-crm' ); ?></label></th>
				<td>
					<select id="formcourier-crm-hubspot-submission-mode" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[hubspot_submission_mode]">
						<option value="contact" <?php selected( $s['hubspot_submission_mode'], 'contact' ); ?>><?php esc_html_e( 'Contact only', 'formcourier-crm' ); ?></option>
						<option value="contact_deal" <?php selected( $s['hubspot_submission_mode'], 'contact_deal' ); ?>><?php esc_html_e( 'Contact + Deal', 'formcourier-crm' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Contact + Deal creates or updates the contact and then creates a new associated HubSpot deal.', 'formcourier-crm' ); ?></p>
				</td>
			</tr>
			<tr data-formcourier-crm-hubspot-deal-row>
				<th><label for="formcourier-crm-hubspot-deal-pipeline-id"><?php esc_html_e( 'HubSpot Deal Pipeline ID', 'formcourier-crm' ); ?></label></th>
				<td>
					<input type="text" id="formcourier-crm-hubspot-deal-pipeline-id" class="regular-text" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[hubspot_deal_pipeline_id]" value="<?php echo esc_attr( $s['hubspot_deal_pipeline_id'] ); ?>" placeholder="default">
					<p class="description"><?php esc_html_e( 'Optional. Leave empty to use the default HubSpot pipeline.', 'formcourier-crm' ); ?></p>
				</td>
			</tr>
			<tr data-formcourier-crm-hubspot-deal-row>
				<th><label for="formcourier-crm-hubspot-deal-stage-id"><?php esc_html_e( 'HubSpot Deal Stage ID', 'formcourier-crm' ); ?></label></th>
				<td>
					<input type="text" id="formcourier-crm-hubspot-deal-stage-id" class="regular-text" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[hubspot_deal_stage_id]" value="<?php echo esc_attr( $s['hubspot_deal_stage_id'] ); ?>" placeholder="appointmentscheduled">
					<p class="description"><?php esc_html_e( 'Required for Contact + Deal. Use the internal stage ID from HubSpot pipeline settings.', 'formcourier-crm' ); ?></p>
				</td>
			</tr>
		</table>
		<h2>Pipedrive</h2><table class="form-table">
			<tr><th><?php esc_html_e( 'Company domain', 'formcourier-crm' ); ?></th><td><input class="regular-text" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[pipedrive_company_domain]" value="<?php echo esc_attr( $s['pipedrive_company_domain'] ); ?>" placeholder="company.pipedrive.com"></td></tr>
			<tr><th><?php esc_html_e( 'API token', 'formcourier-crm' ); ?></th><td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[pipedrive_api_token]" value="" placeholder="<?php echo esc_attr( ! empty( $s['pipedrive_api_token'] ) ? '••••••••••••' : '' ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Entity type', 'formcourier-crm' ); ?></th><td><select name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME ); ?>[pipedrive_entity_type]"><option value="deal" <?php selected( $s['pipedrive_entity_type'], 'deal' ); ?>><?php esc_html_e( 'Deal', 'formcourier-crm' ); ?></option><option value="lead" <?php selected( $s['pipedrive_entity_type'], 'lead' ); ?>><?php esc_html_e( 'Lead', 'formcourier-crm' ); ?></option></select></td></tr>
			<?php
			$pipedrive_fields = array(
				'pipedrive_pipeline_id' => __( 'Pipeline ID', 'formcourier-crm' ),
				'pipedrive_stage_id'    => __( 'Stage ID', 'formcourier-crm' ),
				'pipedrive_owner_id'    => __( 'Owner ID', 'formcourier-crm' ),
				'pipedrive_currency'    => __( 'Currency', 'formcourier-crm' ),
			); foreach ( $pipedrive_fields as $key => $label ) :
				?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><input class="regular-text" name="<?php echo esc_attr( FORMCOURIER_CRM_OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>"></td></tr><?php endforeach; ?>
		</table>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=formcourier_crm_test_connection' ), 'formcourier_crm_test_connection' ) ); ?>"><?php esc_html_e( 'Test selected CRM connection', 'formcourier-crm' ); ?></a></p>
		<?php
	}

	/**
	 * Renders the current integration section.
	 *
	 * @param array $s S.
	 */
	private function render_current_integration( array $s ): void {
		$form_labels = array(
			'contact_form_7' => 'Contact Form 7',
			'wpforms'        => 'WPForms',
		);
		$crm_labels  = array(
			'hubspot'   => 'HubSpot CRM',
			'pipedrive' => 'Pipedrive',
		);
		$enabled     = '1' === (string) $s['integration_enabled'];
		?>
		<div class="formcourier-crm-card">
			<h2><?php esc_html_e( 'Current Integration', 'formcourier-crm' ); ?></h2>
			<ul class="formcourier-crm-status-list">
				<li><strong><?php esc_html_e( 'Status:', 'formcourier-crm' ); ?></strong> <span class="formcourier-crm-status <?php echo $enabled ? 'is-enabled' : 'is-disabled'; ?>"><?php echo $enabled ? esc_html__( 'Enabled', 'formcourier-crm' ) : esc_html__( 'Disabled', 'formcourier-crm' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Form:', 'formcourier-crm' ); ?></strong> <?php echo esc_html( $form_labels[ $s['form_provider'] ] ?? $s['form_provider'] ); ?></li>
				<li><strong><?php esc_html_e( 'CRM:', 'formcourier-crm' ); ?></strong> <?php echo esc_html( $crm_labels[ $s['crm_provider'] ] ?? $s['crm_provider'] ); ?></li>
				<?php if ( 'hubspot' === $s['crm_provider'] ) : ?>
					<li><strong><?php esc_html_e( 'Save Mode:', 'formcourier-crm' ); ?></strong> <?php echo 'contact_deal' === $s['hubspot_submission_mode'] ? esc_html__( 'Contact + Deal', 'formcourier-crm' ) : esc_html__( 'Contact only', 'formcourier-crm' ); ?></li>
					<?php if ( 'contact_deal' === $s['hubspot_submission_mode'] ) : ?>
						<li><strong><?php esc_html_e( 'Deal Stage:', 'formcourier-crm' ); ?></strong> <?php echo '' !== trim( (string) $s['hubspot_deal_stage_id'] ) ? esc_html( (string) $s['hubspot_deal_stage_id'] ) : esc_html__( 'Missing', 'formcourier-crm' ); ?></li>
					<?php endif; ?>
					<li><strong><?php esc_html_e( 'API Token:', 'formcourier-crm' ); ?></strong> <?php echo ! empty( $s['hubspot_access_token'] ) ? esc_html__( 'Added', 'formcourier-crm' ) : esc_html__( 'Missing', 'formcourier-crm' ); ?></li>
				<?php else : ?>
					<li><strong><?php esc_html_e( 'Pipedrive Entity:', 'formcourier-crm' ); ?></strong> <?php echo 'lead' === $s['pipedrive_entity_type'] ? esc_html__( 'Lead', 'formcourier-crm' ) : esc_html__( 'Deal', 'formcourier-crm' ); ?></li>
					<li><strong><?php esc_html_e( 'Pipedrive Domain:', 'formcourier-crm' ); ?></strong> <?php echo '' !== trim( (string) $s['pipedrive_company_domain'] ) ? esc_html( (string) $s['pipedrive_company_domain'] ) : esc_html__( 'Missing', 'formcourier-crm' ); ?></li>
					<li><strong><?php esc_html_e( 'API Token:', 'formcourier-crm' ); ?></strong> <?php echo ! empty( $s['pipedrive_api_token'] ) ? esc_html__( 'Added', 'formcourier-crm' ) : esc_html__( 'Missing', 'formcourier-crm' ); ?></li>
				<?php endif; ?>
			</ul>
			<div class="formcourier-crm-pro-upsell">
				<h3><?php esc_html_e( 'Need more integrations?', 'formcourier-crm' ); ?></h3>
				<p><?php esc_html_e( 'Unlock more CRM integrations and advanced workflows with FormCourier CRM Pro.', 'formcourier-crm' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( FORMCOURIER_CRM_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get FormCourier CRM Pro', 'formcourier-crm' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'Opens an external commercial website.', 'formcourier-crm' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the mapping section.
	 *
	 * @param array $s S.
	 */
	private function render_mapping( array $s ): void {
		foreach ( array(
			'hubspot'   => 'HubSpot CRM',
			'pipedrive' => 'Pipedrive',
		) as $crm => $crm_label ) {
			echo '<h2>' . esc_html( $crm_label ) . '</h2>';
			foreach ( array(
				'contact_form_7' => 'Contact Form 7',
				'wpforms'        => 'WPForms',
			) as $form => $form_label ) {
				$mapping = $s['field_mappings'][ $crm ][ $form ] ?? array();
				echo '<h3>' . esc_html( $form_label ) . '</h3><table class="widefat striped formcourier-crm-mapping" data-crm="' . esc_attr( $crm ) . '" data-form="' . esc_attr( $form ) . '" data-remove-label="' . esc_attr__( 'Remove', 'formcourier-crm' ) . '"><thead><tr><th>' . esc_html__( 'Form field', 'formcourier-crm' ) . '</th><th>' . esc_html__( 'CRM field', 'formcourier-crm' ) . '</th><th>' . esc_html__( 'Action', 'formcourier-crm' ) . '</th></tr></thead><tbody>';
				$index = 0;
				foreach ( $mapping as $form_field => $crm_field ) {
					$this->render_mapping_row( $crm, $form, $index++, $form_field, $crm_field ); }
				echo '</tbody></table><button type="button" class="button formcourier-crm-add-row">' . esc_html__( 'Add field', 'formcourier-crm' ) . '</button>';
			}
		}
		echo '<p class="description">' . esc_html__( 'Lite provides global mappings. Individual mappings for specific form IDs are available in FormCourier CRM Pro.', 'formcourier-crm' ) . '</p>';
	}

	/**
	 * Renders the mapping row section.
	 *
	 * @param string $crm        Crm.
	 * @param string $form       Form.
	 * @param int    $index      Index.
	 * @param string $form_field Form field.
	 * @param string $crm_field  Crm field.
	 */
	private function render_mapping_row( string $crm, string $form, int $index, string $form_field, string $crm_field ): void {
		$base = FORMCOURIER_CRM_OPTION_NAME . '[field_mappings][' . $crm . '][' . $form . '][' . $index . ']';
		?>
		<tr><td><input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[form]' ); ?>" value="<?php echo esc_attr( $form_field ); ?>"></td><td><input type="text" class="regular-text" name="<?php echo esc_attr( $base . '[crm]' ); ?>" value="<?php echo esc_attr( $crm_field ); ?>"></td><td><button type="button" class="button-link-delete formcourier-crm-remove-row"><?php esc_html_e( 'Remove', 'formcourier-crm' ); ?></button></td></tr>
		<?php
	}
}
