<?php
/**
 * Main plugin coordinator.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates plugin services and form-provider hooks.
 */
final class FormCourier_CRM_Plugin {

	/**
	 * Register plugin services.
	 */
	public function init(): void {
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . FORMCOURIER_CRM_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_notices', array( $this, 'pro_conflict_notice' ) );

		if ( $this->is_pro_active() ) {
			return;
		}

		$settings   = new FormCourier_CRM_Settings();
		$router     = new FormCourier_CRM_Provider_Router( $settings );
		$dispatcher = new FormCourier_CRM_CRM_Dispatcher( $router );

		$settings->set_provider_router( $router );
		$settings->init();

		( new FormCourier_CRM_Logs_Page() )->init();
		( new FormCourier_CRM_CF7_Provider( $settings, $dispatcher ) )->init();
		( new FormCourier_CRM_WPForms_Provider( $settings, $dispatcher ) )->init();
	}


	/**
	 * Add custom action links on the Plugins screen.
	 *
	 * @param array<string,string> $links Existing action links.
	 * @return array<string,string>
	 */
	public function action_links( array $links ): array {
		$custom_links = array(
			'formcourier-crm-pro'      => sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer" style="color:#00a32a;font-weight:600;">%2$s</a>',
				esc_url( FORMCOURIER_CRM_PRO_URL ),
				esc_html__( 'Get FormCourier CRM Pro', 'formcourier-crm' )
			),
			'formcourier-crm-settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=formcourier-crm' ) ),
				esc_html__( 'Settings', 'formcourier-crm' )
			),
		);

		return array_merge( $custom_links, $links );
	}

	/**
	 * Add a localized documentation link to the Plugins screen.
	 *
	 * @param array<string,string> $links Existing plugin metadata links.
	 * @param string               $file  Plugin basename.
	 * @return array<string,string>
	 */
	public function row_meta( array $links, string $file ): array {
		if ( FORMCOURIER_CRM_BASENAME !== $file ) {
			return $links;
		}

		$locale   = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$doc_path = 'documentation/index.html';

		if ( 0 === strpos( (string) $locale, 'ru' ) ) {
			$doc_path = 'documentation/ru/index.html';
		} elseif ( 0 === strpos( (string) $locale, 'uk' ) ) {
			$doc_path = 'documentation/uk/index.html';
		}

		$links['formcourier-crm-documentation'] = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
			esc_url( FORMCOURIER_CRM_URL . $doc_path ),
			esc_html__( 'View details', 'formcourier-crm' )
		);

		return $links;
	}

	/**
	 * Check whether the separately distributed Pro edition is active.
	 *
	 * The legacy constant keeps compatibility with existing FormBridge CRM Pro
	 * installations while the Pro edition is being rebranded.
	 */
	private function is_pro_active(): bool {
		return defined( 'FORMCOURIER_CRM_PRO_VERSION' ) || defined( 'FORMBRIDGE_CRM_VERSION' );
	}

	/**
	 * Explain why Lite delivery hooks are paused while Pro is active.
	 */
	public function pro_conflict_notice(): void {
		if ( ! $this->is_pro_active() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The page value only scopes an informational admin notice.
		$page              = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$is_plugins_screen = isset( $GLOBALS['pagenow'] ) && 'plugins.php' === $GLOBALS['pagenow'];
		$is_lite_screen    = 'formcourier-crm' === $page || 0 === strpos( $page, 'formcourier-crm-' );

		if ( ! $is_plugins_screen && ! $is_lite_screen ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		esc_html_e(
			'FormCourier CRM is paused because FormCourier CRM Pro is active. This prevents duplicate form submissions.',
			'formcourier-crm'
		);
		echo '</p></div>';
	}
}
