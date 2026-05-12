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
		add_action( 'restrict_manage_posts', [ $this, 'render_category_dropdown' ] );
		add_action( 'restrict_manage_posts', [ $this, 'render_favorite_dropdown' ] );
		add_action( 'parse_query', [ $this, 'filter_by_selected_category' ] );
		add_action( 'parse_query', [ $this, 'filter_by_favorite' ] );
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
				$new['vce_panels'] = __( 'No. of panels', VCE_TEXT_DOMAIN );
				$new['wix_id']   = __( 'WIX ID', VCE_TEXT_DOMAIN );
				$new['vce_favorite'] = __( 'Favorite', VCE_TEXT_DOMAIN );
			}

		}
		return $new;
	}

	/**
	 * Taxonomy dropdown on the Virtual Cards list (same behaviour as core category filter).
	 */
	public function render_category_dropdown(): void {
		global $typenow;
		if ( Post_Type::POST_TYPE !== $typenow ) {
			return;
		}

		$taxonomy = 'virtual_card_category';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$selected = isset( $_GET[ $taxonomy ] ) ? (int) $_GET[ $taxonomy ] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_dropdown_categories(
			[
				'show_option_all' => __( 'All categories', VCE_TEXT_DOMAIN ),
				'taxonomy'        => $taxonomy,
				'name'            => $taxonomy,
				'orderby'         => 'name',
				'order'           => 'ASC',
				'selected'        => $selected,
				'hierarchical'    => true,
				'depth'           => 0,
				'show_count'      => false,
				'hide_empty'      => false,
				'value_field'     => 'term_id',
			]
		);
	}

	/**
	 * Apply category filter to the main admin list query.
	 *
	 * @param \WP_Query $query Query instance.
	 */
	public function filter_by_selected_category( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$taxonomy = 'virtual_card_category';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		if ( empty( $_GET[ $taxonomy ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$term_id = (int) $_GET[ $taxonomy ]; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $term_id <= 0 ) {
			return;
		}

		$query->set(
			'tax_query',
			[
				[
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => [ $term_id ],
					'include_children' => true,
				],
			]
		);
	}

	/**
	 * Output column cell HTML.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id     Post ID.
	 */
	public function render_column( string $column_name, int $post_id ): void {

		if ( 'vce_panels' === $column_name ) {
			$ids = get_post_meta( $post_id, Panel_Meta::META_KEY, true );
			if ( ! is_array( $ids ) ) {
				$ids = [];
			}
			$ids = array_filter( array_map( 'intval', $ids ) );
			echo esc_html( (string) count( $ids ) );
			return;
		}

		if ( 'wix_id' === $column_name ) {
			$wix_id = get_post_meta( $post_id, Panel_Meta::WIX_META_KEY, true );
			echo '' !== (string) $wix_id ? esc_html( (string) $wix_id ) : '—';
			return;
		}

		if ( 'vce_favorite' === $column_name ) {
			$is_favorite = get_post_meta( $post_id, Panel_Meta::IS_FAVORITE_META_KEY, true );
			if ( '1' === $is_favorite ) {
				echo '<span class="dashicons dashicons-star-filled" style="color:#f0ad4e;"></span>';
			} else {
				echo '—';
			}
			return;
		}

	}

	public function render_favorite_dropdown(): void {
		global $typenow;
		if ( Post_Type::POST_TYPE !== $typenow ) {
			return;
		}
		$selected = isset( $_GET['vce_favorite_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['vce_favorite_filter'] ) ) : '';
		?>
		<select name="vce_favorite_filter">
			<option value="" <?php selected( $selected, '' ); ?>><?php esc_html_e( 'All', VCE_TEXT_DOMAIN ); ?></option>
			<option value="yes" <?php selected( $selected, 'yes' ); ?>><?php esc_html_e( 'Favorites', VCE_TEXT_DOMAIN ); ?></option>
			<option value="no" <?php selected( $selected, 'no' ); ?>><?php esc_html_e( 'Not Favorites', VCE_TEXT_DOMAIN ); ?></option>
		</select>
		<?php
	}

	public function filter_by_favorite( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		$filter = isset( $_GET['vce_favorite_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['vce_favorite_filter'] ) ) : '';
		if ( empty( $filter ) ) {
			return;
		}
		$meta_query = $query->get( 'meta_query' ) ?: [];
		if ( ! is_array( $meta_query ) ) {
			$meta_query = [];
		}
		if ( 'yes' === $filter ) {
			$meta_query[] = [
				'key'   => Panel_Meta::IS_FAVORITE_META_KEY,
				'value' => '1',
			];
		} elseif ( 'no' === $filter ) {
			$meta_query[] = [
				'key'     => Panel_Meta::IS_FAVORITE_META_KEY,
				'compare' => 'NOT EXISTS',
			];
		}
		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

}
