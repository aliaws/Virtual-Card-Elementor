<?php
/**
 * Profile-related hooks for WooCommerce and Ultimate Member integration.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles profile-related functionality.
 */
class Profile_Hooks {

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_account_menu_items', [ $this, 'modify_account_menu_items' ], 999 );
		add_filter( 'woocommerce_get_endpoint_url', [ $this, 'modify_endpoint_url' ], 10, 2 );
	}

	/**
	 * Modify WooCommerce account menu items to use UM profile page.
	 */
	public function modify_account_menu_items( $menu_items ) {
		if ( isset( $menu_items['edit-account'] ) ) {
			unset( $menu_items['edit-account'] );
		}
		$menu_items['account-details'] = 'Account Details';
		return $menu_items;
	}

	/**
	 * Modify endpoint URL for account-details to point to UM profile.
	 */
	public function modify_endpoint_url( $url, $endpoint ) {
		if ( 'account-details' === $endpoint ) {
			return home_url( '/account-details/' );
		}
		return $url;
	}
}

