<?php
/**
 * Virtual Card and Card Submission custom post types.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers virtual_card and card_submission (post_parent = virtual_card ID).
 */
class Post_Type {

	public const POST_TYPE = 'virtual_card';

	public const CARD_SUBMISSION_POST_TYPE = 'card_submission';

	public function register_hooks(): void {

		add_action( 'init', [ $this, 'register_post_types' ] );
		// Meta box parent field is POSTed on save; block editor uses REST without that POST.
		add_filter( 'use_block_editor_for_post_type', [ $this, 'classic_editor_for_submissions' ], 10, 2 );


        add_action( 'init', [ $this, 'register_post_taxonomy' ] );
	}

	public function classic_editor_for_submissions( bool $use, string $post_type ): bool {
		return self::CARD_SUBMISSION_POST_TYPE === $post_type ? false : $use;
	}

	public function register_post_types(): void {
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


		register_post_type(
			self::CARD_SUBMISSION_POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'Card submissions', VCE_TEXT_DOMAIN ),
					'singular_name' => __( 'Card submission', VCE_TEXT_DOMAIN ),
				],
				'public'              => false,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::POST_TYPE,
				'hierarchical'        => false,
				'supports'            => [ 'title', 'editor', 'thumbnail' ],
				'show_in_rest'        => true,
				'rewrite'             => [
					'slug'       => 'card-submission',
					'with_front' => false,
				],
				'query_var'           => self::CARD_SUBMISSION_POST_TYPE,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'has_archive'         => false,
			]
		);


	}

    public function register_post_taxonomy(): void {

        register_taxonomy(
            'virtual_card_category',
            'virtual_card',
            [
                'label'        => 'Categories',
                'hierarchical' => true,
                'show_admin_column' => true, // 👈 IMPORTANT
                'show_in_rest' => true,
            ]
        );

        add_action('init', function () {
            register_taxonomy_for_object_type('virtual_card_category', 'virtual_card');
        });

        add_filter('use_block_editor_for_post', '__return_false', 10);

    }
}
