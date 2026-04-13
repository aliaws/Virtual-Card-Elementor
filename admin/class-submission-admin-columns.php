<?php
/**
 * Admin list columns for vce_submission.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

use Virtual_Card_Elementor\Post_Type as Virtual_Card_Post_Type;
use Virtual_Card_Elementor\Submission;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom columns: virtual card, author, preview link.
 */
class Submission_Admin_Columns {

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_filter( 'manage_' . Submission::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
		add_action( 'manage_' . Submission::POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
	}

	/**
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function columns( array $columns ): array {
		$new = [];
		$new['cb']            = $columns['cb'] ?? '';
		$new['title']         = $columns['title'] ?? __( 'Title', 'default' );
		$new['vce_card']      = __( 'Virtual card', VCE_TEXT_DOMAIN );
		$new['vce_author']    = __( 'Submitted by', VCE_TEXT_DOMAIN );
		$new['vce_preview']   = __( 'Preview link', VCE_TEXT_DOMAIN );
		$new['date']          = $columns['date'] ?? __( 'Date', 'default' );
		return $new;
	}

	/**
	 * @param string $column Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( string $column, int $post_id ): void {
		if ( 'vce_card' === $column ) {
			$cid = (int) get_post_meta( $post_id, Submission::META_CARD_ID, true );
			if ( $cid <= 0 ) {
				echo '—';
				return;
			}
			$title = get_the_title( $cid );
			$url   = get_edit_post_link( $cid, 'raw' );
			if ( $url && get_post_type( $cid ) === Virtual_Card_Post_Type::POST_TYPE ) {
				printf(
					'<a href="%s">%s</a>',
					esc_url( $url ),
					esc_html( $title ?: (string) $cid )
				);
			} else {
				echo esc_html( (string) $cid );
			}
			return;
		}

		if ( 'vce_author' === $column ) {
			$p = get_post( $post_id );
			if ( ! $p ) {
				echo '—';
				return;
			}
			$aid = (int) $p->post_author;
			if ( $aid <= 0 ) {
				esc_html_e( 'Guest', VCE_TEXT_DOMAIN );
				return;
			}
			the_author_meta( 'display_name', $aid );
			return;
		}

		if ( 'vce_preview' === $column ) {
			$uuid = get_post_meta( $post_id, Submission::META_UUID, true );
			if ( ! is_string( $uuid ) || '' === $uuid ) {
				echo '—';
				return;
			}
			$url = home_url( '/card-submission/' . rawurlencode( $uuid ) . '/' );
			printf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $url ),
				esc_html__( 'Open preview', VCE_TEXT_DOMAIN )
			);
		}
	}
}
