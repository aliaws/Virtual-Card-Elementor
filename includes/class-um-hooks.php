<?php
/**
 * UM and E-Cards frontend hooks.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles logout redirect to Ultimate Member login page.
 */
class Um_Hooks {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_logout', [ $this, 'redirect_to_login' ] );
		add_action( 'elementor/query/custom_e_cards', [ $this, 'handle_ecards_filtering' ] );
	}

	/**
	 * Redirect to UM login page after logout.
	 */
	public function redirect_to_login(): void {
		wp_redirect( home_url( '/login/' ) );
		exit;
	}

	/**
	 * Handle frontend filtering and sorting for E-Cards.
	 *
	 * @param \ElementorPro\Modules\QueryControl\Classes\Elementor_Query $query Elementor query instance.
	 */
	public function handle_ecards_filtering( $query ) {
		if ( is_admin() ) {
			return;
		}

		foreach ( $_REQUEST as $key => $value ) {
			if ( strpos( $key, 'e-filter' ) !== false ) {
				$query->set(
					'tax_query',
					[
						[
							'taxonomy' => 'virtual_card_category',
							'field'    => 'slug',
							'terms'    => sanitize_text_field( $value ),
						],
					]
				);
			}
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( $_REQUEST['orderby'] ) : '';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( $_REQUEST['order'] ) : 'ASC';

		if ( ! empty( $orderby ) ) {
			$query->set( 'orderby', $orderby );
			$query->set( 'order', $order );
		}
	}
}
