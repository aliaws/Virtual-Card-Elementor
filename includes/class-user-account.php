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
		add_shortcode( 'vce_my_submissions', [ $this, 'render_my_submissions_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'init', [ $this, 'register_my_submissions_endpoint' ] );
		add_action( 'woocommerce_account_my-submissions_endpoint', [ $this, 'render_my_submissions' ] );
	}

	/**
	 * Register WooCommerce My Account endpoint for My Submissions.
	 */
	public function register_my_submissions_endpoint(): void {
		if ( function_exists( 'add_rewrite_endpoint' ) ) {
			add_rewrite_endpoint( 'my-submissions', EP_ROOT | EP_PAGES );
		}
	}

	/**
	 * Render My Submissions content for WooCommerce My Account page.
	 */
	public function render_my_submissions(): void {
		$this->enqueue_assets();
		wp_enqueue_style( 'vce-user-account' );
		echo $this->get_my_submissions_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Shortcode to render My Submissions on any page.
	 *
	 * @return string
	 */
	public function render_my_submissions_shortcode(): string {
		$this->enqueue_assets();
		wp_enqueue_style( 'vce-user-account' );
		return $this->get_my_submissions_html();
	}

	/**
	 * Build the My Submissions table HTML.
	 *
	 * @return string
	 */
	private function get_my_submissions_html(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your submissions.', VCE_TEXT_DOMAIN ) . '</p>';
		}

		$user_id  = get_current_user_id();
		$submissions = get_posts(
			[
				'post_type'      => Post_Type::CARD_SUBMISSION_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => Panel_Meta::SUBMISSION_SENDER_ID,
				'meta_value'     => $user_id,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		if ( empty( $submissions ) ) {
			return '<p>' . esc_html__( 'No submissions yet.', VCE_TEXT_DOMAIN ) . '</p>';
		}

		$status_labels = [
			'saved'     => __( 'Saved', VCE_TEXT_DOMAIN ),
			'scheduled' => __( 'Scheduled', VCE_TEXT_DOMAIN ),
			'sent'      => __( 'Sent', VCE_TEXT_DOMAIN ),
			'viewed'    => __( 'Viewed', VCE_TEXT_DOMAIN ),
		];

		$status_colors = [
			'saved'     => '#f0ad4e',
			'scheduled' => '#f0ad4e',
			'sent'      => '#5bc0de',
			'viewed'    => '#5cb85c',
		];

		ob_start();
		Template::render(
			'frontend/my-submissions.php',
			[
				'submissions'    => $submissions,
				'status_labels'  => $status_labels,
				'status_colors'  => $status_colors,
				'user_id'        => $user_id,
			]
		);
		return (string) ob_get_clean();
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
	 * Allowed tags for inline SVG icons in the account menu.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private function svg_kses_allowed(): array {
		return [
			'svg'    => [
				'xmlns'       => true,
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'fill'        => true,
				'stroke'      => true,
				'stroke-width' => true,
			],
			'path'   => [
				'd'    => true,
				'fill' => true,
			],
			'circle'   => [
				'cx'     => true,
				'cy'     => true,
				'r'      => true,
				'stroke' => true,
				'fill'   => true,
			],
			'polyline' => [
				'points' => true,
			],
			'line'     => [
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			],
		];
	}

	/**
	 * Render logged-in state (avatar + dropdown).
	 *
	 * @return string HTML output.
	 */
	private function render_logged_in(): string {
		$user = wp_get_current_user();

		$default_account = function_exists( 'wc_get_page_permalink' )
			? wc_get_page_permalink( 'myaccount' )
			: home_url( '/' );

		/**
		 * Filters the "My account" (dashboard) URL in the user account menu.
		 *
		 * @param string   $url  Default URL (WooCommerce my account if available, else home).
		 * @param \WP_User $user Current user.
		 */
		$account_url = apply_filters( 'vce_user_account_menu_account_url', $default_account, $user );

		$logout_url = wp_logout_url( home_url( '/' ) );

		$display_name = $user->display_name ? $user->display_name : $user->user_login;

		$avatar_html = get_avatar(
			$user->ID,
			60,
			'',
			'',
			[
				'class' => 'avatar',
			]
		);

		$chevron = wp_kses(
			'<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>',
			$this->svg_kses_allowed()
		);

		$icon_account = wp_kses(
			'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
			$this->svg_kses_allowed()
		);

		$icon_logout = wp_kses(
			'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
			$this->svg_kses_allowed()
		);

		ob_start();
		?>
		<span class="vce-user-account-dropdown">
			<button type="button" class="vce-user-account-trigger" aria-expanded="false" aria-haspopup="true">
				<span class="vce-user-account-avatar"><?php echo $avatar_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns escaped HTML. ?></span>
				<span class="vce-user-account-name"><?php echo esc_html( $display_name ); ?></span>
				<span class="vce-user-account-chevron" aria-hidden="true"><?php echo $chevron; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</button>
			<div class="vce-user-account-menu" role="menu">
				<a href="<?php echo esc_url( $account_url ); ?>" class="vce-user-account-item" role="menuitem">
					<?php echo $icon_account; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo esc_html__( 'My account', 'virtual-card-elementor' ); ?>
				</a>
				<a href="<?php echo esc_url( $logout_url ); ?>" class="vce-user-account-item vce-user-account-logout" role="menuitem">
					<?php echo $icon_logout; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo esc_html__( 'Log out', 'virtual-card-elementor' ); ?>
				</a>
			</div>
		</span>
		<?php
		return (string) ob_get_clean();
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
