<?php
/**
 * Admin list UI for card_submission: columns and filter by parent virtual card.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

use Virtual_Card_Elementor\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks for submission management in wp-admin.
 */
class Card_Submission_Admin {

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_filter( 'manage_' . Post_Type::CARD_SUBMISSION_POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_' . Post_Type::CARD_SUBMISSION_POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
		add_filter( 'manage_edit-' . Post_Type::CARD_SUBMISSION_POST_TYPE . '_sortable_columns', [ $this, 'sortable_columns' ] );
		add_action( 'restrict_manage_posts', [ $this, 'filter_dropdown_parent_card' ], 10, 2 );
		add_action( 'add_meta_boxes', [ $this, 'add_parent_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_parent_meta_box' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_parent_picker_script' ] );
	}

	/**
	 * @param string[] $columns Default columns.
	 * @return string[]
	 */
	public function columns( array $columns ): array {
		$new = [];
		if ( isset( $columns['cb'] ) ) {
			$new['cb'] = $columns['cb'];
		}
		if ( isset( $columns['title'] ) ) {
			$new['title'] = $columns['title'];
		}
		$new['vce_parent_card'] = __( 'Virtual card', VCE_TEXT_DOMAIN );
		$new['vce_final_view']  = __( 'Final view', VCE_TEXT_DOMAIN );
		if ( isset( $columns['author'] ) ) {
			$new['author'] = $columns['author'];
		}
		if ( isset( $columns['date'] ) ) {
			$new['date'] = $columns['date'];
		}
		return $new;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( string $column, int $post_id ): void {
		if ( 'vce_final_view' === $column ) {
			$url = add_query_arg(
				[
					'post_type' => Post_Type::CARD_SUBMISSION_POST_TYPE,
					'p'         => $post_id,
				],
				home_url( '/' )
			);
			printf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $url ),
				esc_html__( 'Open final view', VCE_TEXT_DOMAIN )
			);
			return;
		}
		if ( 'vce_parent_card' !== $column ) {
			return;
		}
		$parent_id = (int) wp_get_post_parent_id( $post_id );
		if ( $parent_id <= 0 ) {
			echo '<span class="vce-submission-no-parent">—</span>';
			return;
		}
		if ( get_post_type( $parent_id ) !== Post_Type::POST_TYPE ) {
			echo esc_html( (string) $parent_id );
			return;
		}
		$title = get_the_title( $parent_id );
		$link  = get_edit_post_link( $parent_id, 'raw' );
		if ( $link ) {
			printf(
				'<a href="%s">%s</a>',
				esc_url( $link ),
				esc_html( $title ?: (string) $parent_id )
			);
		} else {
			echo esc_html( $title ?: (string) $parent_id );
		}
	}

	/**
	 * @param string[] $columns Sortable map.
	 * @return string[]
	 */
	public function sortable_columns( array $columns ): array {
		$columns['vce_parent_card'] = 'post_parent';
		return $columns;
	}

	/**
	 * Dropdown above the list: filter by parent virtual card.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $which     Position (top/extra).
	 */
	public function filter_dropdown_parent_card( string $post_type, string $which = '' ): void {
		if ( Post_Type::CARD_SUBMISSION_POST_TYPE !== $post_type ) {
			return;
		}
		if ( 'extra' === $which ) {
			return;
		}
		$selected = isset( $_GET['vce_parent_card'] ) ? absint( wp_unslash( $_GET['vce_parent_card'] ) ) : 0;
		$this->render_virtual_card_select(
			'vce_parent_card',
			'vce_parent_card',
			$selected,
			__( 'All virtual cards', VCE_TEXT_DOMAIN ),
			false
		);
	}

	/**
	 * Apply list filter when a virtual card is chosen.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function apply_parent_filter( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || Post_Type::CARD_SUBMISSION_POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( empty( $_GET['vce_parent_card'] ) ) {
			return;
		}
		$parent = absint( wp_unslash( $_GET['vce_parent_card'] ) );
		if ( $parent <= 0 || Post_Type::POST_TYPE !== get_post_type( $parent ) ) {
			return;
		}
		$query->set( 'post_parent', $parent );
	}

	/**
	 * @return \WP_Post[]
	 */
	private function get_virtual_cards_for_dropdown(): array {
		return get_posts(
			[
				'post_type'              => Post_Type::POST_TYPE,
				'post_status'            => [ 'publish', 'draft', 'pending', 'future', 'private' ],
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			]
		);
	}

	/**
	 * @param string $name         Select name attribute.
	 * @param string $element_id   Select id attribute.
	 * @param int    $selected     Selected post ID.
	 * @param string $none_label   Label for value 0.
	 * @param bool   $search_field Show filter field and tall list (submission edit screen only).
	 */
	private function render_virtual_card_select( string $name, string $element_id, int $selected, string $none_label, bool $search_field ): void {
		$cards = $this->get_virtual_cards_for_dropdown();

		if ( $search_field ) {
			echo '<div class="vce-parent-virtual-card-picker">';
			if ( ! empty( $cards ) ) {
				printf(
					'<p><label for="%1$s_filter" class="screen-reader-text">%2$s</label><input type="search" id="%1$s_filter" class="widefat vce-virtual-card-parent-filter" placeholder="%3$s" autocomplete="off" /></p>',
					esc_attr( $element_id ),
					esc_html__( 'Filter virtual cards', VCE_TEXT_DOMAIN ),
					esc_attr__( 'Search by title…', VCE_TEXT_DOMAIN )
				);
			}
		}

		$size_attr = '';
		if ( $search_field && ! empty( $cards ) ) {
			$size_attr = ' size="' . (int) min( 12, max( 4, count( $cards ) + 1 ) ) . '"';
		}

		printf(
			'<select name="%1$s" id="%2$s" class="widefat%3$s"%4$s>',
			esc_attr( $name ),
			esc_attr( $element_id ),
			$search_field ? ' vce-virtual-card-parent-select' : '',
			$size_attr
		);
		printf(
			'<option value="0" %s>%s</option>',
			selected( $selected, 0, false ),
			esc_html( $none_label )
		);
		foreach ( $cards as $card ) {
			$title = get_the_title( $card );
			if ( '' === $title ) {
				$title = '#' . (string) $card->ID;
			}
			printf(
				'<option value="%1$d" %3$s>%2$s</option>',
				(int) $card->ID,
				esc_html( $title ),
				selected( $selected, (int) $card->ID, false )
			);
		}
		echo '</select>';

		if ( empty( $cards ) ) {
			echo '<p class="description">' . esc_html__( 'Create a Virtual Card first—it will appear here.', VCE_TEXT_DOMAIN ) . '</p>';
		}

		if ( $search_field ) {
			echo '</div>';
		}
	}

	/**
	 * Typing filters the virtual card list (meta box only).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_parent_picker_script( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Type::CARD_SUBMISSION_POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
		$js = <<<'JS'
(function($){$(function(){$('.vce-virtual-card-parent-select').each(function(){var $s=$(this);$s.data('vceAllOptions',$s.html());});$(document).on('input','.vce-virtual-card-parent-filter',function(){var q=$(this).val().toLowerCase().trim();var $w=$(this).closest('.vce-parent-virtual-card-picker');var $sel=$w.find('.vce-virtual-card-parent-select');var all=$sel.data('vceAllOptions');var v=$sel.val();if(!q){$sel.html(all).val(v);return;}var $t=$('<select>'+all+'</select>');$sel.empty();$t.find('option').each(function(){var $o=$(this),val=$o.val(),text=$o.text();if(val==='0'||text.toLowerCase().indexOf(q)!==-1){$sel.append($('<option></option>').val(val).text(text));}});if($sel.find('option[value="'+v+'"]').length){$sel.val(v);}});});})(jQuery);
JS;
		wp_add_inline_script( 'jquery', $js, 'after' );
	}

	/**
	 * Parent virtual card (classic editor + POST save).
	 */
	public function add_parent_meta_box(): void {
		add_meta_box(
			'vce_submission_parent',
			__( 'Parent virtual card', VCE_TEXT_DOMAIN ),
			[ $this, 'render_parent_meta_box' ],
			Post_Type::CARD_SUBMISSION_POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * @param \WP_Post $post Current post.
	 */
	public function render_parent_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'vce_save_submission_parent', 'vce_submission_parent_nonce' );
		$this->render_virtual_card_select(
			'vce_post_parent',
			'vce_post_parent',
			(int) $post->post_parent,
			__( '— Select —', VCE_TEXT_DOMAIN ),
			true
		);
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save_parent_meta_box( int $post_id, \WP_Post $post ): void {
		if ( Post_Type::CARD_SUBMISSION_POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['vce_submission_parent_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vce_submission_parent_nonce'] ) ), 'vce_save_submission_parent' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$parent = isset( $_POST['vce_post_parent'] ) ? absint( wp_unslash( $_POST['vce_post_parent'] ) ) : 0;
		if ( $parent > 0 && get_post_type( $parent ) !== Post_Type::POST_TYPE ) {
			$parent = 0;
		}
		if ( $parent === (int) $post->post_parent ) {
			return;
		}
		remove_action( 'save_post', [ $this, 'save_parent_meta_box' ], 10 );
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_parent' => $parent,
			]
		);
		add_action( 'save_post', [ $this, 'save_parent_meta_box' ], 10, 2 );
	}

}
