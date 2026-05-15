<?php
/**
 * Standalone shortcode functions.
 *
 * @package Virtual_Card_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dynamic post title shortcode
 */
add_shortcode( 'vce_dynamic_title', 'vce_dynamic_post_title' );
function vce_dynamic_post_title(): string {
    if(vce_can_access_virtual_card()) {
        $custom_title = get_post_meta( get_the_ID(), '_vce_second_level_label', true );
        $display_title = ! empty( $custom_title ) ? $custom_title : get_the_title();
        return '<h1 class="vce-custom-heading">' . esc_html( $display_title ) . '</h1>';
    }
    else {
        vce_redirect_if_no_access();
    }
}

/**
 * Check if user can access virtual card
 */
function vce_can_access_virtual_card() {

    global $ads_subscription_post;

    // Admin always allowed
    if ( current_user_can( 'administrator' ) ) {
        return true;
    }

    // Check if post has Sample1 category (if not, allow access)
    $categories = wp_get_post_terms( get_the_ID(), 'virtual_card_category' );
    $has_sample = false;


    foreach ( $categories as $category ) {
        if ( strpos( strtolower( $category->slug ), 'sample' ) !== false ) {
            return true;
        }
    }


    // Check subscription for Sample1 category
    if($ads_subscription_post) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        return $ads_subscription_post->user_has_any_active_subscription( $user_id );
    }

    return false;
}

/**
 * Redirect if no access
 */
function vce_redirect_if_no_access() {
    // Store notice in URL parameter instead of transient
    $redirect_url = home_url( '/pricing/?notice=subscription_required' );

    if ( ! get_current_user_id() ) {
        $redirect_url = home_url( '/login/?redirect_to=' . urlencode( home_url( '/pricing/?notice=subscription_required' ) ) );
    }

    wp_redirect( $redirect_url );
    exit;
}
/**
 * Show notice on pricing page before Elementor heading
 */
add_action( 'wp_footer', 'vce_show_pricing_notice' );
function vce_show_pricing_notice() {
    if ( ! is_page( 'pricing' ) ) {
        return;
    }

    if ( isset( $_GET['notice'] ) && $_GET['notice'] === 'subscription_required' ) {
        ?>
        <style>
            .vce-notice {
                max-width: 1200px;
                margin: 20px auto;
                padding: 12px 20px;
                background: #f0f8ff;
                border-left: 4px solid #007cba;
                border-radius: 4px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
        </style>
        <div id="vce-notice" class="vce-notice" style="display: none;">
            <?php esc_html_e( 'You need an active subscription to access this content.', 'textdomain' ); ?>
        </div>
        <script>
            jQuery(document).ready(function($) {
                const notice = jQuery('#vce-notice');
                const elementorHeading = jQuery('.elementor-element-0ab951d.elementor-widget-heading');

                if (notice.length && elementorHeading.length) {
                    notice.show();
                    elementorHeading.before(notice);
                    console.log('Notice inserted before heading');
                } else {
                    console.log('Heading not found with class: .elementor-element-0ab951d.elementor-widget-heading');
                }

                // Remove notice from URL
                if (window.history && history.pushState) {
                    var url = new URL(window.location.href);
                    url.searchParams.delete('notice');
                    history.pushState(null, '', url.toString());
                }
            });
        </script>
        <?php
    }
}