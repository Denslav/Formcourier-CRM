<?php
/**
 * Submission logs administration page.
 *
 * @package FormCourierCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and manages privacy-aware submission logs.
 */
final class FormCourier_CRM_Logs_Page {

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_formcourier_crm_delete_log', array( $this, 'delete' ) );
		add_action( 'admin_post_formcourier_crm_clear_logs', array( $this, 'clear' ) );
	}

	/**
	 * Add the logs submenu.
	 */
	public function menu(): void {
		add_submenu_page(
			'formcourier-crm',
			__( 'Submission Logs', 'formcourier-crm' ),
			__( 'Submission Logs', 'formcourier-crm' ),
			'manage_options',
			'formcourier-crm-logs',
			array( $this, 'render' )
		);
	}

	/**
	 * Delete one log entry.
	 */
	public function delete(): void {
		$this->authorize();

		$log_id = isset( $_POST['log_id'] ) ? absint( wp_unslash( $_POST['log_id'] ) ) : 0;
		check_admin_referer( 'formcourier_crm_delete_log_' . $log_id );

		FormCourier_CRM_Logger::delete( $log_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'formcourier-crm-logs',
					'deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete all log entries.
	 */
	public function clear(): void {
		$this->authorize();
		check_admin_referer( 'formcourier_crm_clear_logs' );

		FormCourier_CRM_Logger::clear();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'formcourier-crm-logs',
					'cleared' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the logs table.
	 */
	public function render(): void {
		$this->authorize();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These GET values only filter the read-only log list.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These GET values only filter the read-only log list.
		$email = isset( $_GET['email'] ) ? sanitize_text_field( wp_unslash( $_GET['email'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination is a read-only GET value.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$per_page     = 20;
		$logs         = FormCourier_CRM_Logger::get_logs(
			array(
				'status' => $status,
				'email'  => $email,
				'limit'  => $per_page,
				'offset' => ( $current_page - 1 ) * $per_page,
			)
		);
		$total        = FormCourier_CRM_Logger::count_logs(
			array(
				'status' => $status,
				'email'  => $email,
			)
		);
		$total_pages  = max( 1, (int) ceil( $total / $per_page ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FormCourier CRM Submission Logs', 'formcourier-crm' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- These flags only display an informational notice. ?>
			<?php if ( isset( $_GET['deleted'] ) || isset( $_GET['cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Logs updated successfully.', 'formcourier-crm' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="page" value="formcourier-crm-logs">
				<select name="status">
					<option value=""><?php esc_html_e( 'All statuses', 'formcourier-crm' ); ?></option>
					<option value="success" <?php selected( $status, 'success' ); ?>><?php esc_html_e( 'Success', 'formcourier-crm' ); ?></option>
					<option value="error" <?php selected( $status, 'error' ); ?>><?php esc_html_e( 'Error', 'formcourier-crm' ); ?></option>
				</select>
				<input
					type="search"
					name="email"
					value="<?php echo esc_attr( $email ); ?>"
					placeholder="<?php esc_attr_e( 'Search by masked email', 'formcourier-crm' ); ?>"
				>
				<?php submit_button( __( 'Filter', 'formcourier-crm' ), 'secondary', '', false ); ?>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Status', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Action', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Form', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'CRM', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Email', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Message', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Request', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Response', 'formcourier-crm' ); ?></th>
						<th><?php esc_html_e( 'Delete', 'formcourier-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="10"><?php esc_html_e( 'No log entries found.', 'formcourier-crm' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log['created_at'] ); ?></td>
								<td><?php echo esc_html( FormCourier_CRM_I18N::status_label( $log['status'] ) ); ?></td>
								<td><?php echo esc_html( FormCourier_CRM_I18N::action_label( $log['action'] ) ); ?></td>
								<td><?php echo esc_html( $log['form_provider'] ); ?></td>
								<td><?php echo esc_html( $log['crm_provider'] ); ?></td>
								<td><?php echo esc_html( $log['email'] ); ?></td>
								<td><?php echo esc_html( $log['message'] ); ?></td>
								<td><?php $this->details( $log['request_body'] ); ?></td>
								<td><?php $this->details( $log['response_body'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<?php wp_nonce_field( 'formcourier_crm_delete_log_' . absint( $log['id'] ) ); ?>
										<input type="hidden" name="action" value="formcourier_crm_delete_log">
										<input type="hidden" name="log_id" value="<?php echo esc_attr( absint( $log['id'] ) ); ?>">
										<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'formcourier-crm' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			if ( $total_pages > 1 ) {
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $current_page,
							'total'     => $total_pages,
							'prev_text' => __( '&laquo; Previous', 'formcourier-crm' ),
							'next_text' => __( 'Next &raquo;', 'formcourier-crm' ),
						)
					)
				);
			}
			?>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				onsubmit="return confirm('<?php echo esc_js( __( 'Clear all logs?', 'formcourier-crm' ) ); ?>');"
			>
				<?php wp_nonce_field( 'formcourier_crm_clear_logs' ); ?>
				<input type="hidden" name="action" value="formcourier_crm_clear_logs">
				<?php submit_button( __( 'Clear all logs', 'formcourier-crm' ), 'delete', '', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Require administrator permission.
	 */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'formcourier-crm' ) );
		}
	}

	/**
	 * Render an expandable JSON payload.
	 *
	 * @param string $body Masked JSON body.
	 */
	private function details( string $body ): void {
		if ( '' === trim( $body ) ) {
			echo '&mdash;';
			return;
		}
		?>
		<details>
			<summary><?php esc_html_e( 'View', 'formcourier-crm' ); ?></summary>
			<pre><?php echo esc_html( $body ); ?></pre>
		</details>
		<?php
	}
}
