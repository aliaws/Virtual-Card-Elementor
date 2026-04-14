<?php
/**
 * Virtual Cards admin list table columns.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

use Virtual_Card_Elementor\Panel_Meta;
use Virtual_Card_Elementor\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Card no. and panel count columns on the Virtual Cards list screen.
 */
class Virtual_Card_Admin_Columns {

	/**
	 * Hook callbacks.
	 */
	public function register_hooks(): void {
		add_filter( 'manage_' . Post_Type::POST_TYPE . '_posts_columns', [ $this, 'add_columns' ] );
		add_action( 'manage_' . Post_Type::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
	}

	/**
	 * Insert columns after the title.
	 *
	 * @param string[] $columns Default columns.
	 * @return string[]
	 */
	public function add_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['vce_panels']  = __( 'No. of panels', VCE_TEXT_DOMAIN );
			}
		}
		return $new;
	}

	/**
	 * Output column cell HTML.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id     Post ID.
	 */
	public function render_column( string $column_name, int $post_id ): void {

		if ( 'vce_panels' !== $column_name ) {
			return;
		}

		$ids = get_post_meta( $post_id, Panel_Meta::META_KEY, true );
		if ( ! is_array( $ids ) ) {
			$ids = [];
		}
		$ids = array_filter( array_map( 'intval', $ids ) );
		echo esc_html( (string) count( $ids ) );
	}

}
