<?php
/**
 * Standalone shortcode functions.
 *
 * @package Virtual_Card_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'vce_dynamic_post_title' ) ) {
	/**
	 * Dynamic post title shortcode – falls back to post title if meta empty.
	 *
	 * @return string
	 */
	function vce_dynamic_post_title(): string {
		$custom_title  = get_post_meta( get_the_ID(), '_vce_second_level_label', true );
		$display_title = ! empty( $custom_title ) ? $custom_title : get_the_title();

		return '<h1 class="vce-custom-heading">' . esc_html( $display_title ) . '</h1>';
	}
}
add_shortcode( 'vce_dynamic_title', 'vce_dynamic_post_title' );
