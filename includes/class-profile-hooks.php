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
		add_filter( 'um_profile_permalink', [ $this, 'modify_um_profile_permalink' ], 10, 3 );
		add_filter( 'um_get_option_filter__account_tab_privacy', [ $this, 'disable_um_account_tab_privacy' ] );
	}

	/**
	 * Disable UM account privacy tab via options filter.
	 */
	public function disable_um_account_tab_privacy( $value ) {
		return 0;
	}

	/**
	 * Modify UM profile permalink to point to WooCommerce my-account.
	 */
	public function modify_um_profile_permalink( $profile_url, $page_id, $slug ) {
		return home_url( '/my-account/' );
	}

	/**
	 * Modify WooCommerce account menu items to use UM profile page.
	 */
	public function modify_account_menu_items( $menu_items ) {
		if ( isset( $menu_items['edit-account'] ) ) {
			unset( $menu_items['edit-account'] );
		}

		if ( empty( $menu_items ) ) {
			$menu_items['account-details'] = 'Account Details';
			return $menu_items;
		}

		// Insert before customer-logout to make it second last
		if ( isset( $menu_items['customer-logout'] ) ) {
			$logout = $menu_items['customer-logout'];
			unset( $menu_items['customer-logout'] );
			$menu_items['account-details'] = 'Account Details';
			$menu_items['customer-logout'] = $logout;
		} else {
			$menu_items['account-details'] = 'Account Details';
		}

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

