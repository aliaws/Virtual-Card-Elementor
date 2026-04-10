<?php
/**
 * Virtual Card custom post type.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the virtual_card post type.
 */
class Post_Type {

	public const POST_TYPE = 'virtual_card';

	/**
	 * Hook callbacks.
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
	}

	/**
	 * Register CPT on init.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => __( 'Virtual Cards', VCE_TEXT_DOMAIN ),
					'singular_name' => __( 'Virtual Card', VCE_TEXT_DOMAIN ),
				],
				'public'       => true,
				'menu_icon'    => 'dashicons-images-alt2',
				'supports'     => [ 'title', 'editor', 'thumbnail' ],
				'show_in_rest' => true,
			]
		);
	}
}
