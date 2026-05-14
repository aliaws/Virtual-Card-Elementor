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
        add_filter( 'um_user_profile_tabs', [ $this, 'modify_um_user_profile_tabs' ], 10, 1 );
        add_filter( 'um_myprofile_edit_menu_items', [ $this, 'modify_um_myprofile_edit_menu_items' ], 10, 1 );



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
     * Modify Tabs on UM Profile
     */
    public function modify_um_user_profile_tabs($tabs) {
        if (empty($tabs)) {
            error_log('UM Items array is empty!');
            return $tabs;
        }
        $unsets = ["posts", "comments"];
        foreach ( $tabs as $key => $tab ) {
            if ( in_array( $key, $unsets ) ) {
                unset( $tabs[ $key ] );
            }
        }
        return $tabs;
    }

    public function modify_um_myprofile_edit_menu_items($items) {
        if (empty($items)) {
            error_log('UM Items array is empty!');
            return $items;
        }

        $unsets = ["editprofile"];
        foreach ( $items as $key => $item ) {
            if ( in_array( $key, $unsets ) ) {
                unset( $items[ $key ] );
            }
        }
        $items["myaccount"] = sprintf(
            '<a href="%s" class="real_url">Edit Account</a>',
            esc_url(add_query_arg('um_action', 'edit', home_url('/account-details/')))
        );

        $items["view_account"] = sprintf(
            '<a href="%s" class="real_url">My Account</a>',
            esc_url(home_url('/my-account/'))
        );

        $view_account_item = ['view_account' => $items['view_account']];
        unset($items['view_account']);
        $items = array_slice($items, 0, 1, true) + $view_account_item + $items;

        return $items;
    }




	/**
	 * Modify WooCommerce account menu items to use UM profile page.
	 */
	public function modify_account_menu_items( $menu_items ) {

		if ( isset( $menu_items['edit-account'] ) ) {
			unset( $menu_items['edit-account'] );
		}

		if ( empty( $menu_items ) ) {
			$menu_items['account-details'] = 'Edit Account';
            $menu_items['edit_profile_picture'] = 'Edit Profile Picture';
			return $menu_items;
		}

		// Insert before customer-logout to make it second last
		if ( isset( $menu_items['customer-logout'] ) ) {
			$logout = $menu_items['customer-logout'];
			unset( $menu_items['customer-logout'] );
			$menu_items['account-details'] = 'Edit Account';
            $menu_items['edit_profile_picture'] = 'Edit Profile Picture';
			$menu_items['customer-logout'] = $logout;
		} else {
			$menu_items['account-details'] = 'Edit Account';
            $menu_items['edit_profile_picture'] = 'Edit Profile Picture';
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
        else if ( 'edit_profile_picture' === $endpoint ) {
            $user_id = get_current_user_id();
            return $this->get_um_profile_url_custom($user_id)."?um_action=edit";
        }
		return $url;
	}

    function get_um_profile_url_custom($user_id) {

        $user_page_id = get_option('um_core_page_user');
        $user_slug = $user_page_id ? get_post_field('post_name', $user_page_id) : 'user';
        $profile_slug = UM()->user()->get_profile_slug($user_id, true);

        return home_url("/{$user_slug}/{$profile_slug}/");
    }
}

