<?php
/**
 * Admin panel meta box for Virtual Card.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

use Virtual_Card_Elementor\Panel_Meta;
use Virtual_Card_Elementor\Post_Type;
use Virtual_Card_Elementor\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card Panel meta box and save handler.
 */
class Panel_Meta_Box {

	public const NONCE_ACTION = 'virtual_card_panel_nonce';
	public const NONCE_FIELD  = 'virtual_card_panel_nonce_field';
	public const IDS_FIELD    = 'virtual_card_panel_ids';

	/**
	 * Hook callbacks.
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'save_post_' . Post_Type::POST_TYPE, [ $this, 'save_panels' ], 10, 3 );
	}

	/**
	 * Register the meta box.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'virtual_card_panels',
			__( 'Card Panels', VCE_TEXT_DOMAIN ),
			[ $this, 'render_meta_box' ],
			Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue admin CSS/JS on virtual card edit screens.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$post_id = 0;
		if ( 'post.php' === $hook_suffix && isset( $_GET['post'] ) ) {
			$post_id = (int) $_GET['post'];
		}

		wp_enqueue_media( [ 'post' => $post_id ] );

		wp_enqueue_style(
			'vce-admin-panel',
			VCE_PLUGIN_URL . 'assets/css/admin-panel.css',
			[],
			VCE_VERSION
		);

		wp_enqueue_script(
			'vce-admin-panel',
			VCE_PLUGIN_URL . 'assets/js/admin-panel.js',
			[ 'jquery', 'media-editor' ],
			VCE_VERSION,
			true
		);

		wp_localize_script(
			'vce-admin-panel',
			'vceAdminPanel',
			[
				'frameTitle'  => __( 'Select images', VCE_TEXT_DOMAIN ),
				'removeLabel' => __( 'Remove image', VCE_TEXT_DOMAIN ),
			]
		);
	}

	/**
	 * Render meta box HTML via template.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_meta_box( $post ): void {
		$ids = get_post_meta( $post->ID, Panel_Meta::META_KEY, true );
		$ids = is_array( $ids ) ? $ids : [];

		Template::render(
			'admin/panel-meta-box.php',
			[
				'post' => $post,
				'ids'  => $ids,
			]
		);
	}

	/**
	 * Persist panel attachment IDs.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function save_panels( int $post_id, $post, bool $update ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! empty( $_POST[ self::IDS_FIELD ] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST[ self::IDS_FIELD ] ) );
			$ids = array_map( 'intval', array_filter( explode( ',', $raw ) ) );
			update_post_meta( $post_id, Panel_Meta::META_KEY, $ids );
			return;
		}

		delete_post_meta( $post_id, Panel_Meta::META_KEY );
	}
}
