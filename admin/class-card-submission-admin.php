<?php
/**
 * Admin list UI for card_submission: columns and filter by parent virtual card.
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
 * Hooks for submission management in wp-admin.
 */
class Card_Submission_Admin {

	/** @var string[] Allowed list-table column keys (order preserved). */
	private const LIST_COLUMNS = [
		'cb',
		'title',
		'vce_parent_card',
		'vce_sender',
		'vce_receiver_email',
		'vce_status',
		'vce_preview_link',
		'date',
	];

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_filter( 'manage_' . Post_Type::CARD_SUBMISSION_POST_TYPE . '_posts_columns', [ $this, 'columns' ], 5 );
		add_filter( 'manage_' . Post_Type::CARD_SUBMISSION_POST_TYPE . '_posts_columns', [ $this, 'strip_unwanted_columns' ], 999 );
		add_filter( 'the_title', [ $this, 'filter_submission_list_title' ], 10, 2 );
		add_action( 'manage_' . Post_Type::CARD_SUBMISSION_POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
		add_filter( 'manage_edit-' . Post_Type::CARD_SUBMISSION_POST_TYPE . '_sortable_columns', [ $this, 'sortable_columns' ] );
		add_action( 'restrict_manage_posts', [ $this, 'render_list_filters' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'apply_list_filters' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_parent_meta_box' ] );
		add_action( 'save_post', [ $this, 'save_parent_meta_box' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'post_row_actions', [ $this, 'add_send_row_action' ], 10, 2 );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_status_labels(): array {
		return [
			'saved'     => __( 'Saved', VCE_TEXT_DOMAIN ),
			'scheduled' => __( 'Scheduled', VCE_TEXT_DOMAIN ),
			'sent'      => __( 'Sent', VCE_TEXT_DOMAIN ),
			'viewed'    => __( 'Viewed', VCE_TEXT_DOMAIN ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function get_status_colors(): array {
		return [
			'saved'     => '#f0ad4e',
			'scheduled' => '#6f42c1',
			'sent'      => '#5bc0de',
			'viewed'    => '#5cb85c',
		];
	}

	/**
	 * @param string $status Status slug.
	 */
	private function render_status_badge( string $status ): void {
		$labels = $this->get_status_labels();
		$colors = $this->get_status_colors();
		Template::render(
			'admin/partials/submission-status-badge.php',
			[
				'status'       => $status,
				'status_label' => $labels[ $status ] ?? ucfirst( $status ),
				'status_color' => $colors[ $status ] ?? '#999',
			]
		);
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
			$new['title'] = __( 'Submission', VCE_TEXT_DOMAIN );
		}
		$new['vce_parent_card']     = __( 'Virtual card', VCE_TEXT_DOMAIN );
		$new['vce_sender']           = __( 'Sender', VCE_TEXT_DOMAIN );
		$new['vce_receiver_email']   = __( 'Receiver Email', VCE_TEXT_DOMAIN );
		$new['vce_status']           = __( 'Status', VCE_TEXT_DOMAIN );
		$new['vce_preview_link']     = __( 'Preview Link', VCE_TEXT_DOMAIN );
		if ( isset( $columns['date'] ) ) {
			$new['date'] = $columns['date'];
		}
		return $new;
	}

	/**
	 * Remove third-party columns (e.g. AIOSEO Details) added after our column filter.
	 *
	 * @param string[] $columns Column headers.
	 * @return string[]
	 */
	public function strip_unwanted_columns( array $columns ): array {
		$ordered = [];
		foreach ( self::LIST_COLUMNS as $key ) {
			if ( isset( $columns[ $key ] ) ) {
				$ordered[ $key ] = $columns[ $key ];
			}
		}
		return $ordered;
	}

	/**
	 * Shorter, readable label in the Submission column (replaces auto post title).
	 *
	 * @param string $title   Post title.
	 * @param int    $post_id Post ID.
	 */
	public function filter_submission_list_title( string $title, int $post_id = 0 ): string {
		if ( ! is_admin() || $post_id <= 0 || Post_Type::CARD_SUBMISSION_POST_TYPE !== get_post_type( $post_id ) ) {
			return $title;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-card_submission' !== $screen->id ) {
			return $title;
		}
		return $this->get_submission_list_label( $post_id );
	}

	/**
	 * @param int $post_id Submission post ID.
	 */
	private function get_submission_list_label( int $post_id ): string {
		$parent_id    = (int) wp_get_post_parent_id( $post_id );
		$parent_title = $parent_id ? get_the_title( $parent_id ) : '';
		$receiver     = (string) get_post_meta( $post_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, true );

		if ( $parent_title && $receiver ) {
			return sprintf(
				'%s → %s',
				$parent_title,
				$receiver
			);
		}
		if ( $parent_title ) {
			return $parent_title;
		}
		if ( $receiver ) {
			return $receiver;
		}
		return sprintf(
			/* translators: %d: submission post ID */
			__( 'Submission #%d', VCE_TEXT_DOMAIN ),
			$post_id
		);
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( string $column, int $post_id ): void {
		if ( 'vce_preview_link' === $column ) {
			$url = get_permalink( $post_id );
			if ( ! $url ) {
				$url = add_query_arg(
					[
						'post_type' => Post_Type::CARD_SUBMISSION_POST_TYPE,
						'p'         => $post_id,
					],
					home_url( '/' )
				);
			}
			printf(
				'<a href="%1$s" class="vce-preview-link" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'Preview', VCE_TEXT_DOMAIN )
			);
			return;
		}

		if ( 'vce_status' === $column ) {
			$status = get_post_meta( $post_id, Panel_Meta::SUBMISSION_STATUS, true ) ?: 'saved';
			$this->render_status_badge( (string) $status );
			return;
		}

		if ( 'vce_sender' === $column ) {
			$this->render_sender_column( $post_id );
			return;
		}

		if ( 'vce_receiver_email' === $column ) {
			$this->render_receiver_email_column( $post_id );
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
	 * @param int $post_id Submission post ID.
	 */
	private function render_sender_column( int $post_id ): void {
		$sender_id   = (int) get_post_meta( $post_id, Panel_Meta::SUBMISSION_SENDER_ID, true );
		$sender_user = $sender_id ? get_userdata( $sender_id ) : null;

		if ( $sender_user ) {
			$edit_link = get_edit_user_link( $sender_id );
			if ( $edit_link ) {
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url( $edit_link ),
					esc_html( $sender_user->display_name ?: $sender_user->user_login )
				);
			} else {
				echo esc_html( $sender_user->display_name ?: $sender_user->user_login );
			}
			return;
		}

		if ( $sender_id ) {
			echo esc_html( '#' . (string) $sender_id );
			return;
		}

		echo '<span class="vce-submission-empty">—</span>';
	}

	/**
	 * @param int $post_id Submission post ID.
	 */
	private function render_receiver_email_column( int $post_id ): void {
		$receiver_email = get_post_meta( $post_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, true );
		if ( $receiver_email ) {
			printf(
				'<a href="mailto:%1$s" class="vce-receiver-email">%2$s</a>',
				esc_attr( $receiver_email ),
				esc_html( $receiver_email )
			);
			return;
		}

		echo '<span class="vce-submission-empty">—</span>';
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
	 * Status + parent virtual card filters (before the Filter button).
	 *
	 * @param string $post_type Post type slug.
	 * @param string $which     Position (top/extra).
	 */
	public function render_list_filters( string $post_type, string $which = '' ): void {
		if ( Post_Type::CARD_SUBMISSION_POST_TYPE !== $post_type || 'top' !== $which ) {
			return;
		}

		$status_selected = isset( $_GET['vce_submission_status'] )
			? sanitize_key( wp_unslash( $_GET['vce_submission_status'] ) )
			: '';
		$parent_selected = isset( $_GET['vce_parent_card'] ) ? absint( wp_unslash( $_GET['vce_parent_card'] ) ) : 0;

		echo '<span class="vce-submission-filters">';

		$labels = $this->get_status_labels();
		echo '<label for="vce_submission_status" class="screen-reader-text">' . esc_html__( 'Filter by status', VCE_TEXT_DOMAIN ) . '</label>';
		echo '<select name="vce_submission_status" id="vce_submission_status" class="vce-list-filter-select">';
		printf(
			'<option value="">%s</option>',
			esc_html__( 'All statuses', VCE_TEXT_DOMAIN )
		);
		foreach ( $labels as $slug => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $slug ),
				selected( $status_selected, $slug, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		$this->render_virtual_card_select(
			'vce_parent_card',
			'vce_parent_card',
			$parent_selected,
			__( 'All E-cards', VCE_TEXT_DOMAIN ),
			'list'
		);

		echo '</span>';
	}

	/**
	 * Apply list filters when status or parent virtual card is chosen.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function apply_list_filters( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || Post_Type::CARD_SUBMISSION_POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! empty( $_GET['vce_parent_card'] ) ) {
			$parent = absint( wp_unslash( $_GET['vce_parent_card'] ) );
			if ( $parent > 0 && Post_Type::POST_TYPE === get_post_type( $parent ) ) {
				$query->set( 'post_parent', $parent );
			}
		}

		if ( ! empty( $_GET['vce_submission_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_GET['vce_submission_status'] ) );
			$labels = $this->get_status_labels();
			if ( isset( $labels[ $status ] ) ) {
				$meta_query = (array) $query->get( 'meta_query' );
				if ( 'saved' === $status ) {
					$meta_query[] = [
						'relation' => 'OR',
						[
							'key'     => Panel_Meta::SUBMISSION_STATUS,
							'value'   => 'saved',
							'compare' => '=',
						],
						[
							'key'     => Panel_Meta::SUBMISSION_STATUS,
							'compare' => 'NOT EXISTS',
						],
					];
				} else {
					$meta_query[] = [
						'key'     => Panel_Meta::SUBMISSION_STATUS,
						'value'   => $status,
						'compare' => '=',
					];
				}
				$query->set( 'meta_query', $meta_query );
			}
		}
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
	 * @param string $name       Select name attribute.
	 * @param string $element_id Select id attribute.
	 * @param int    $selected   Selected post ID.
	 * @param string $none_label Label for value 0.
	 * @param string $context    list|meta-box
	 */
	private function render_virtual_card_select( string $name, string $element_id, int $selected, string $none_label, string $context = 'meta-box' ): void {
		$cards         = $this->get_virtual_cards_for_dropdown();
		$is_meta_box   = 'meta-box' === $context;
		$select_class  = $is_meta_box ? 'widefat vce-virtual-card-parent-select' : 'vce-list-filter-select';

		if ( $is_meta_box ) {
			echo '<div class="vce-parent-virtual-card-picker">';
			if ( ! empty( $cards ) ) {
				printf(
					'<p><label for="%1$s_filter" class="screen-reader-text">%2$s</label><input type="search" id="%1$s_filter" class="widefat vce-virtual-card-parent-filter" placeholder="%3$s" autocomplete="off" /></p>',
					esc_attr( $element_id ),
					esc_html__( 'Filter virtual cards', VCE_TEXT_DOMAIN ),
					esc_attr__( 'Search by title…', VCE_TEXT_DOMAIN )
				);
			}
		} else {
			echo '<label for="' . esc_attr( $element_id ) . '" class="screen-reader-text">' . esc_html__( 'Filter by E-card', VCE_TEXT_DOMAIN ) . '</label>';
		}

		$size_attr = '';
		if ( $is_meta_box && ! empty( $cards ) ) {
			$size_attr = ' size="' . (int) min( 12, max( 4, count( $cards ) + 1 ) ) . '"';
		}

		printf(
			'<select name="%1$s" id="%2$s" class="%3$s"%4$s>',
			esc_attr( $name ),
			esc_attr( $element_id ),
			esc_attr( $select_class ),
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

		if ( $is_meta_box ) {
			echo '</div>';
		}
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Post_Type::CARD_SUBMISSION_POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'vce-admin-card-submission',
			VCE_PLUGIN_URL . 'assets/css/admin-card-submission.css',
			[],
			vce_asset_version( 'assets/css/admin-card-submission.css' )
		);


		if ( in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			$this->enqueue_parent_picker_script();
			return;
		}

		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'vce-admin-card-send',
			VCE_PLUGIN_URL . 'assets/js/admin-card-send.js',
			[ 'jquery' ],
			vce_asset_version( 'assets/js/admin-card-send.js' ),
			true
		);

		wp_localize_script(
			'vce-admin-card-send',
			'vceAdminSend',
			[
				'restUrl' => esc_url_raw( rest_url( 'vce/v1/admin-send-email' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => [
					'send'          => __( 'Send', VCE_TEXT_DOMAIN ),
					'sending'       => __( 'Sending...', VCE_TEXT_DOMAIN ),
					'sent'          => __( 'Card sent!', VCE_TEXT_DOMAIN ),
					'failed'        => __( 'Failed to send.', VCE_TEXT_DOMAIN ),
					'error'         => __( 'Could not send card.', VCE_TEXT_DOMAIN ),
					'requiredEmail' => __( 'Please enter recipient email.', VCE_TEXT_DOMAIN ),
				],
			]
		);

		Template::render( 'admin/card-submission-send-modal.php' );
	}

	/**
	 * Typing filters the virtual card list (meta box only).
	 */
	private function enqueue_parent_picker_script(): void {
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
			__( 'Parent E-card', VCE_TEXT_DOMAIN ),
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
			'meta-box'
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

	/**
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    Post.
	 * @return array
	 */
	public function add_send_row_action( $actions, $post ) {
		if ( Post_Type::CARD_SUBMISSION_POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		$status = get_post_meta( $post->ID, Panel_Meta::SUBMISSION_STATUS, true );
		if ( 'saved' !== $status ) {
			return $actions;
		}
		$actions['send'] = sprintf(
			'<a href="#" class="vce-admin-send" data-post-id="%d">%s</a>',
			(int) $post->ID,
			esc_html__( 'Send', VCE_TEXT_DOMAIN )
		);
		return $actions;
	}
}
