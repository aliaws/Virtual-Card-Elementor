<?php
/**
 * Temporary Tools screen: view / clear VCE diagnostic log (VCE_DEBUG).
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

use Virtual_Card_Elementor\Debug_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Tools → VCE debug (temporary).
 */
final class Vce_Debug_Page {

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
	}

	public function add_menu(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_management_page(
			__( 'VCE debug (temporary)', VCE_TEXT_DOMAIN ),
			__( 'VCE debug', VCE_TEXT_DOMAIN ),
			'manage_options',
			'vce-debug-log',
			[ $this, 'render_page' ]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$path = Debug_Log::log_file_path();

		if ( isset( $_POST['vce_clear_debug_log'] ) && check_admin_referer( 'vce_clear_debug_log' ) ) {
			Debug_Log::clear_log_file();
			wp_safe_redirect( admin_url( 'tools.php?page=vce-debug-log&cleared=1' ) );
			exit;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Virtual Card — diagnostic log (temporary)', VCE_TEXT_DOMAIN ); ?></h1>

			<?php if ( ! Debug_Log::vce_debug_active() ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						esc_html_e(
							'File logging is off. In wp-config.php, add: define( \'VCE_DEBUG\', true ); above the stop editing comment. Then reproduce the issue. You may leave WP_DEBUG off so only this file fills.',
							VCE_TEXT_DOMAIN
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Log cleared.', VCE_TEXT_DOMAIN ); ?></p></div>
			<?php endif; ?>

			<p>
				<strong><?php esc_html_e( 'Log file:', VCE_TEXT_DOMAIN ); ?></strong>
				<code><?php echo esc_html( $path ?: '(uploads unavailable)' ); ?></code>
			</p>
			<p class="description">
				<?php esc_html_e( 'Remove VCE_DEBUG and this Tools menu entry when finished. Browser errors from logged-in administrators are appended with a [JS] prefix when VCE_DEBUG is on.', VCE_TEXT_DOMAIN ); ?>
			</p>

			<form method="post" style="margin: 1em 0;">
				<?php wp_nonce_field( 'vce_clear_debug_log' ); ?>
				<input type="hidden" name="vce_clear_debug_log" value="1" />
				<?php submit_button( __( 'Clear log file', VCE_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Tail of log (most recent ~120 KB)', VCE_TEXT_DOMAIN ); ?></h2>
			<pre style="background:#1e1e1e;color:#d4d4d4;padding:12px;overflow:auto;max-height:70vh;font-size:12px;line-height:1.4;"><?php
				$tail = Debug_Log::read_log_tail( 120000 );
				echo $tail !== '' ? esc_html( $tail ) : esc_html__( '(empty — no entries yet)', VCE_TEXT_DOMAIN );
			?></pre>
		</div>
		<?php
	}
}
