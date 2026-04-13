<?php
/**
 * REST API: save card submissions (design JSON per virtual_card).
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

/**
 * Registers /wp-json/vce/v1/submissions
 */
class Submission_Rest {

	/**
	 * Hook REST routes.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'vce/v1',
			'/submissions',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save_submission' ],
				'permission_callback' => [ $this, 'permission_save' ],
				'args'                => [
					'card_id'       => [
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'layers'        => [
						'required' => true,
					],
					'submission_id' => [
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Require REST nonce and persist capability.
	 */
	public function permission_save(): bool {
		return function_exists( 'vce_can_persist_front_editor_design' ) && vce_can_persist_front_editor_design();
	}

	/**
	 * Verify nonce from request (called from callback because permission_callback cannot read body easily for all clients).
	 */
	private function verify_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			return false;
		}
		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Create or update submission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_submission( WP_REST_Request $request ) {
		if ( ! $this->verify_nonce( $request ) ) {
			if ( Debug_Log::enabled() ) {
				Debug_Log::log( 'rest_submissions fail=vce_invalid_nonce' );
			}
			return new WP_Error( 'vce_invalid_nonce', __( 'Invalid or missing nonce.', VCE_TEXT_DOMAIN ), [ 'status' => 403 ] );
		}

		/**
		 * Require a logged-in user to create/update submission posts (WordPress caps).
		 * Return false to allow guests (you must then handle caps via map_meta_cap or custom storage).
		 *
		 * @param bool $require_login Default true.
		 */
		if ( apply_filters( 'vce_submission_require_login', true ) && ! is_user_logged_in() ) {
			if ( Debug_Log::enabled() ) {
				Debug_Log::log( 'rest_submissions fail=vce_auth_required' );
			}
			return new WP_Error(
				'vce_auth_required',
				__( 'Log in to save your card to the site. Your draft still works in this browser.', VCE_TEXT_DOMAIN ),
				[ 'status' => 401 ]
			);
		}

		$card_id = (int) $request->get_param( 'card_id' );
		if ( $card_id <= 0 || get_post_type( $card_id ) !== Post_Type::POST_TYPE ) {
			if ( Debug_Log::enabled() ) {
				Debug_Log::log(
					'rest_submissions fail=vce_invalid_card card_id=' . $card_id . ' post_type=' . ( $card_id > 0 ? get_post_type( $card_id ) : '0' )
				);
			}
			return new WP_Error( 'vce_invalid_card', __( 'Invalid virtual card.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$layers_raw = $request->get_param( 'layers' );
		$design     = Submission::sanitize_design_payload( $layers_raw );
		if ( null === $design ) {
			if ( Debug_Log::enabled() ) {
				$hint = is_array( $layers_raw ) ? ( 'keys=' . implode( ',', array_keys( $layers_raw ) ) ) : ( 'type=' . gettype( $layers_raw ) );
				Debug_Log::log( 'rest_submissions fail=vce_invalid_layers ' . $hint );
			}
			return new WP_Error( 'vce_invalid_layers', __( 'Invalid design data.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		if ( Debug_Log::enabled() ) {
			Debug_Log::log(
				'rest_submissions accept card_id=' . $card_id . ' client_submission_id=' . (int) $request->get_param( 'submission_id' )
			);
		}

		$submission_id   = (int) $request->get_param( 'submission_id' );
		$user_id           = get_current_user_id();
		$used_update_route = ( $submission_id > 0 && is_user_logged_in() );
		$postarr           = [
			'post_type'   => Submission::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: 1: card title, 2: date */
				__( 'Submission — %1$s — %2$s', VCE_TEXT_DOMAIN ),
				get_the_title( $card_id ) ?: (string) $card_id,
				wp_date( 'Y-m-d H:i' )
			),
			'post_author' => $user_id,
		];

		if ( $used_update_route ) {
			$existing = get_post( $submission_id );
			if ( ! $existing || $existing->post_type !== Submission::POST_TYPE ) {
				if ( Debug_Log::enabled() ) {
					Debug_Log::log( 'rest_submissions fail=vce_not_found submission_id=' . $submission_id );
				}
				return new WP_Error( 'vce_not_found', __( 'Submission not found.', VCE_TEXT_DOMAIN ), [ 'status' => 404 ] );
			}
			if ( (int) get_post_meta( $submission_id, Submission::META_CARD_ID, true ) !== $card_id ) {
				if ( Debug_Log::enabled() ) {
					Debug_Log::log( 'rest_submissions fail=vce_card_mismatch submission_id=' . $submission_id . ' card_id=' . $card_id );
				}
				return new WP_Error( 'vce_card_mismatch', __( 'Submission does not belong to this card.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
			}
			$can = current_user_can( 'edit_post', $submission_id )
				|| ( (int) $existing->post_author === $user_id && $user_id > 0 );
			if ( ! $can ) {
				if ( Debug_Log::enabled() ) {
					Debug_Log::log( 'rest_submissions fail=vce_forbidden submission_id=' . $submission_id . ' user_id=' . $user_id );
				}
				return new WP_Error( 'vce_forbidden', __( 'You cannot edit this submission.', VCE_TEXT_DOMAIN ), [ 'status' => 403 ] );
			}
			$postarr['ID'] = $submission_id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) ) {
			if ( Debug_Log::enabled() ) {
				Debug_Log::log( 'rest_submissions fail=wp_insert_update ' . $result->get_error_code() . ' ' . $result->get_error_message() );
			}
			return $result;
		}

		$sid = (int) $result;
		update_post_meta( $sid, Submission::META_CARD_ID, $card_id );

		$design_json = Submission::encode_design_json( $design );
		if ( null === $design_json ) {
			if ( Debug_Log::enabled() ) {
				Debug_Log::log( 'rest_submissions fail=vce_design_encode_json json_err=' . json_last_error_msg() );
			}
			return new WP_Error(
				'vce_design_encode_failed',
				__( 'Could not store design data. Try removing unusual characters from text and save again.', VCE_TEXT_DOMAIN ),
				[ 'status' => 500 ]
			);
		}
		update_post_meta( $sid, Submission::META_DESIGN, Submission::pack_design_meta_value( $design_json ) );

		$uuid = get_post_meta( $sid, Submission::META_UUID, true );
		if ( ! is_string( $uuid ) || '' === $uuid ) {
			$uuid = Submission::generate_uuid();
			update_post_meta( $sid, Submission::META_UUID, $uuid );
		}

		if ( Debug_Log::enabled() ) {
			$stored = (string) get_post_meta( $sid, Submission::META_DESIGN, true );
			$counts = [];
			foreach ( $design['panels'] as $pk => $panel ) {
				$n = isset( $panel['objects'] ) && is_array( $panel['objects'] ) ? count( $panel['objects'] ) : 0;
				$counts[] = (string) $pk . ':' . $n;
			}
			Debug_Log::log(
				sprintf(
					'submission_save submission_id=%d card_id=%d client_submission_id_param=%d is_update=%s uuid=%s panel_keys=%s object_counts=%s stored_meta_bytes=%d storage=b64',
					$sid,
					$card_id,
					$submission_id,
					( $used_update_route && (int) $sid === $submission_id ) ? 'yes' : 'no',
					$uuid,
					implode( ',', array_keys( $design['panels'] ) ),
					implode( ';', $counts ),
					strlen( $stored )
				)
			);
		}

		$preview_url = home_url( '/card-submission/' . rawurlencode( $uuid ) . '/' );

		return new WP_REST_Response(
			[
				'submission_id' => $sid,
				'uuid'          => $uuid,
				'preview_url'   => $preview_url,
				'message'       => __( 'Saved.', VCE_TEXT_DOMAIN ),
			],
			200
		);
	}
}
