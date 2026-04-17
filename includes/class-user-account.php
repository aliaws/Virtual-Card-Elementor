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
		wp_register_style(
			'vce-user-account',
			VCE_PLUGIN_URL . 'assets/css/user-account.css',
			[],
			vce_asset_version( 'assets/css/user-account.css' )
		);

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
		wp_enqueue_style( 'vce-user-account' );
		wp_enqueue_script( 'vce-user-account' );

		$output = '<div class="vce-user-account-wrapper">';

		if ( is_user_logged_in() ) {
			$output .= $this->render_logged_in();
		} else {
			$output .= $this->render_logged_out();
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render logged out state.
	 *
	 * @return string HTML output.
	 */
	private function render_logged_out(): string {
		$login_url = '/login/';
		$icon      = $this->get_user_icon();

		return sprintf(
			'<a href="%s" class="vce-user-account-link vce-user-account-logged-out" title="%s">%s</a>',
			esc_url( $login_url ),
			esc_attr__( 'Login', 'virtual-card-elementor' ),
			$icon
		);
	}

	/**
	 * Render logged in state.
	 *
	 * @return string HTML output.
	 */
	private function render_logged_in(): string {
		$user_id   = get_current_user_id();
		$user      = get_userdata( $user_id );
		$full_name = $user ? $user->display_name : '';
		$avatar    = get_avatar( $user_id, 40 );
		$account_url = '/my-account/';
		$logout_url  = add_query_arg( 'action', 'logout', '/login/' );

		$user_nonce = wp_create_nonce( 'log-out' );
		$logout_href = wp_logout_url( home_url( '/login/' ) );

		ob_start();
		?>
		<div class="vce-user-account-dropdown">
			<button type="button" class="vce-user-account-trigger" aria-expanded="false" aria-haspopup="true">
				<span class="vce-user-account-avatar"><?php echo wp_kses_post( $avatar ); ?></span>
				<span class="vce-user-account-name"><?php echo esc_html( $full_name ); ?></span>
				<span class="vce-user-account-chevron">
					<svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</span>
			</button>
			<div class="vce-user-account-menu">
				<a href="<?php echo esc_url( $account_url ); ?>" class="vce-user-account-item">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M8 8.5C9.933 8.5 11.5 6.933 11.5 5C11.5 3.067 9.933 1.5 8 1.5C6.067 1.5 4.5 3.067 4.5 5C4.5 6.933 6.067 8.5 8 8.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M13 14.5C13 11.738 10.761 9.5 8 9.5C5.239 9.5 3 11.738 3 14.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<span><?php esc_html_e( 'My Account', 'virtual-card-elementor' ); ?></span>
				</a>
				<a href="<?php echo esc_url( $logout_href ); ?>" class="vce-user-account-item vce-user-account-logout">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M10.5 12.5L13.5 8.5L10.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M13.5 8.5H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
						<path d="M6 12.5V10.167C6 9.914 6 9.788 5.966 9.678C5.936 9.579 5.89 9.487 5.829 9.404C5.76 9.311 5.669 9.231 5.486 9.072L3.5 7.5C3.19315 7.24632 3.03972 7.11949 2.9491 6.97571C2.86926 6.84855 2.81947 6.70524 2.80348 6.55655C2.7855 6.38845 2.82534 6.1675 2.90503 5.7256C3 5.1913 3.19145 4.94113 3.5 4.5L5.486 2.928C5.669 2.769 5.76 2.689 5.829 2.596C5.89 2.513 5.936 2.421 5.966 2.322C6 2.212 6 2.086 6 1.833V1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<span><?php esc_html_e( 'Logout', 'virtual-card-elementor' ); ?></span>
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get user icon SVG.
	 *
	 * @return string SVG icon.
	 */
	private function get_user_icon(): string {
		return '<svg class="vce-user-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2"/>
			<path d="M20 21C20 17.134 16.418 14 12 14C7.582 14 4 17.134 4 21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
		</svg>';
	}
}
