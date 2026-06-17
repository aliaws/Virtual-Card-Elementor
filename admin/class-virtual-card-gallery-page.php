<?php
/**
 * Custom admin gallery page for Virtual Cards with big thumbnails.
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

class Virtual_Card_Gallery_Page {

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_vce_gallery_update_card', [ $this, 'ajax_update_card' ] );
	}

	public function add_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Type::POST_TYPE,
			__( 'Card Gallery', VCE_TEXT_DOMAIN ),
			__( 'Card Gallery', VCE_TEXT_DOMAIN ),
			'edit_posts',
			'vce-card-gallery',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'virtual_card_page_vce-card-gallery' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'vce-gallery',
			VCE_PLUGIN_URL . 'assets/css/admin-gallery.css',
			[],
			vce_asset_version( 'assets/css/admin-gallery.css' )
		);

		wp_enqueue_script(
			'vce-gallery',
			VCE_PLUGIN_URL . 'assets/js/admin-gallery.js',
			[ 'jquery' ],
			vce_asset_version( 'assets/js/admin-gallery.js' ),
			true
		);

		wp_localize_script(
			'vce-gallery',
			'vceGallery',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vce_gallery_update_card' ),
				'i18n'    => [
					'saving'        => __( 'Saving...', VCE_TEXT_DOMAIN ),
					'saved'         => __( 'Card updated!', VCE_TEXT_DOMAIN ),
					'error'         => __( 'Update failed.', VCE_TEXT_DOMAIN ),
					'requiredTitle' => __( 'Title is required.', VCE_TEXT_DOMAIN ),
				],
			]
		);
	}

	public function render_page(): void {
		$selected_category = isset( $_GET['vce_category'] ) ? (int) $_GET['vce_category'] : 0;

		$categories = get_terms(
			[
				'taxonomy'   => 'virtual_card_category',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		$args = [
			'post_type'      => Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		if ( $selected_category > 0 ) {
			$args['tax_query'] = [
				[
					'taxonomy'         => 'virtual_card_category',
					'field'            => 'term_id',
					'terms'            => $selected_category,
					'include_children' => true,
				],
			];
		}

		$raw = get_posts( $args );

		usort(
			$raw,
			function ( $a, $b ) {
				$oa = (int) get_post_meta( $a->ID, Panel_Meta::ORDER_META_KEY, true );
				$ob = (int) get_post_meta( $b->ID, Panel_Meta::ORDER_META_KEY, true );
				if ( $oa !== $ob ) {
					return $oa - $ob;
				}
				return strcmp( $a->post_title, $b->post_title );
			}
		);

		$total             = count( $raw );
		$query             = new \WP_Query();
		$query->posts      = $raw;
		$query->post_count = count( $raw );

		Template::render(
			'admin/virtual-card-gallery.php',
			[
				'query'              => $query,
				'total'              => $total,
				'order_meta'         => Panel_Meta::ORDER_META_KEY,
				'categories'         => $categories,
				'selected_category'  => $selected_category,
			]
		);
	}

	public function ajax_update_card(): void {
		if ( ! check_ajax_referer( 'vce_gallery_update_card', false, false ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid nonce.', VCE_TEXT_DOMAIN ) ] );
		}

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$order   = isset( $_POST['order'] ) ? (int) $_POST['order'] : 0;

		if ( $post_id <= 0 || Post_Type::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid card.', VCE_TEXT_DOMAIN ) ] );
		}

		if ( '' === $title ) {
			wp_send_json_error( [ 'message' => __( 'Title is required.', VCE_TEXT_DOMAIN ) ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', VCE_TEXT_DOMAIN ) ] );
		}

		$updated = wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => $title,
			],
			true
		);

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( [ 'message' => $updated->get_error_message() ] );
		}

		if ( $order > 0 ) {
			update_post_meta( $post_id, Panel_Meta::ORDER_META_KEY, $order );
		} else {
			delete_post_meta( $post_id, Panel_Meta::ORDER_META_KEY );
		}

		wp_send_json_success();
	}
}
