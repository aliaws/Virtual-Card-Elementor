<?php
/**
 * REST endpoints for front-end card submission saves.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Card_Submission_Rest {

	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'vce/v1',
			'/submission',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_submission' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_submission( WP_REST_Request $request ) {
		$parent_id = absint( $request->get_param( 'parentId' ) );
		if ( $parent_id <= 0 || Post_Type::POST_TYPE !== get_post_type( $parent_id ) ) {
			return new WP_Error( 'vce_invalid_parent', __( 'Invalid virtual card parent.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$layers = $request->get_param( 'layers' );
		if ( ! is_array( $layers ) ) {
			$json = $request->get_json_params();
			if ( isset( $json['layers'] ) && is_array( $json['layers'] ) ) {
				$layers = $json['layers'];
			}
		}
		if ( ! is_array( $layers ) ) {
			$layers = [];
		}

		$serialized = wp_json_encode( $layers );
		if ( false === $serialized || strlen( $serialized ) > 800000 ) {
			return new WP_Error( 'vce_invalid_layers', __( 'Submission data is invalid or too large.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$decoded = json_decode( $serialized, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = [];
		}

		$user_id = get_current_user_id();

		$post_id = wp_insert_post(
			[
				'post_type'   => Post_Type::CARD_SUBMISSION_POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => $parent_id,
				'post_name'   => sanitize_title( 'submission-' . wp_date( 'Y-m-d-His' ) . '-' . wp_generate_password( 4, false, false ) ),
				'post_title'  => sprintf(
					'(VC - %d, Sender - %d)',
					$parent_id,
					$user_id ?: 0
				),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, Panel_Meta::SUBMISSION_LAYERS_META_KEY, $decoded );
		update_post_meta( $post_id, Panel_Meta::SUBMISSION_STATUS, 'saved' );

		if ( $user_id ) {
			update_post_meta( $post_id, Panel_Meta::SUBMISSION_SENDER_ID, $user_id );
		}

		$parent_title = get_the_title( $parent_id );
		Submission_Logger::log(
			$post_id,
			'created',
			sprintf(
				'Virtual Card: %s (#%d), Sender: %s (#%d)',
				$parent_title ?: '#', (string) $parent_id,
				$user_id ? ( get_userdata( $user_id )->display_name ?: 'User' ) : 'Guest',
				$user_id ?: 0
			)
		);

		$preview_url = add_query_arg(
			[
				'post_type' => Post_Type::CARD_SUBMISSION_POST_TYPE,
				'p'         => (int) $post_id,
			],
			home_url( '/' )
		);
		$permalink   = $preview_url;

		return new WP_REST_Response(
			[
				'id'          => (int) $post_id,
				'url'         => $permalink,
				'preview_url' => $preview_url,
				'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
			],
			200
		);
	}
}
