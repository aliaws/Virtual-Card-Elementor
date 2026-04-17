<?php
/**
 * User Account Menu shortcode - displays login icon or user avatar with dropdown.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders user account menu shortcode.
 */
final class User_Account {

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_shortcode( 'user_account_menu', [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_assets(): void {
		wp_register_script(
			'vce-user-account',
			VCE_PLUGIN_URL . 'assets/js/user-account.js',
			[],
			vce_asset_version( 'assets/js/user-account.js' ),
			true
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @return string HTML output.
	 */
	public function render_shortcode(): string {
		$this->enqueue_assets();
		wp_enqueue_script( 'vce-user-account' );

		$output = '<span class="vce-user-account-wrapper">';

		if ( is_user_logged_in() ) {
			$output .= $this->render_logged_in();
		} else {
			$output .= $this->render_logged_out();
		}

		$output .= '</span>';

		return $output;
	}

	/**
	 * Render logged out state.
	 *
	 * @return string HTML output.
	 */
	private function render_logged_out(): string {
		$login_url = '/login/';

		return sprintf(
			'<a href="%s" class="vce-user-account-link vce-user-account-logged-out">%s</a>',
			esc_url( $login_url ),
			esc_html__( 'Login', 'virtual-card-elementor' )
		);
	}

}
