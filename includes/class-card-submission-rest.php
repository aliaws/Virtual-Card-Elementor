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
        $submission_id = absint( $request->get_param( 'submission_id' ) );
		if ( $parent_id <= 0 || Post_Type::POST_TYPE !== get_post_type( $parent_id ) ) {
			return new WP_Error( 'vce_invalid_parent', __( 'Invalid E-card parent.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
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

		// Update existing submission (edit from My Submissions); validate parent + owner.
		if ( $submission_id > 0 ) {
			$existing = get_post( $submission_id );
			if (
				! $existing
				|| Post_Type::CARD_SUBMISSION_POST_TYPE !== $existing->post_type
				|| (int) $existing->post_parent !== $parent_id
			) {
				return new WP_Error(
					'vce_invalid_submission',
					__( 'Invalid submission.', VCE_TEXT_DOMAIN ),
					[ 'status' => 400 ]
				);
			}
			$sender_id = (int) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENDER_ID, true );
			if ( $user_id && $sender_id && $sender_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'vce_forbidden',
					__( 'You cannot edit this submission.', VCE_TEXT_DOMAIN ),
					[ 'status' => 403 ]
				);
			}
			wp_update_post(
				[
					'ID'                => $submission_id,
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql', 1 ),
				]
			);
			$post_id = $submission_id;
		} else {
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
		}

		update_post_meta( $post_id, Panel_Meta::SUBMISSION_LAYERS_META_KEY, $decoded );
		if ( $user_id ) {
			update_option( "LAST_DRAFT_SUBMISSION_{$user_id}", $post_id );
		}

		if ( $user_id ) {
			update_post_meta( $post_id, Panel_Meta::SUBMISSION_SENDER_ID, $user_id );
		}

		$dispatch = filter_var( $request->get_param( 'dispatch' ), FILTER_VALIDATE_BOOLEAN );
		if ( $dispatch ) {
			$recipient_email = sanitize_email( (string) ( $request->get_param( 'recipientEmail' ) ?: '' ) );
			if ( ! is_email( $recipient_email ) ) {
				return new WP_Error( 'vce_invalid_email', __( 'Invalid recipient email.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
			}
			update_post_meta( $post_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, $recipient_email );
			// Persist Schedule-or-Send form fields (used on edit + cron email).
			update_post_meta( $post_id, Panel_Meta::SUBMISSION_SENDER_NAME, sanitize_text_field( (string) ( $request->get_param( 'senderName' ) ?: '' ) ) );
			update_post_meta( $post_id, Panel_Meta::SUBMISSION_MESSAGE, sanitize_textarea_field( (string) ( $request->get_param( 'message' ) ?: '' ) ) );

			$send_mode = sanitize_key( (string) ( $request->get_param( 'sendMode' ) ?: 'now' ) );
			if ( 'schedule' === $send_mode ) {
				$scheduled_raw = sanitize_text_field( (string) ( $request->get_param( 'scheduledAt' ) ?: '' ) );
				$timezone      = Schedule_Timezone::sanitize( (string) ( $request->get_param( 'scheduledTimezone' ) ?: '' ) );
				$scheduled_utc = Schedule_Timezone::parse_to_utc_timestamp( $scheduled_raw, $timezone );
				if ( ! $scheduled_utc ) {
					return new WP_Error(
						'vce_invalid_schedule',
						__( 'Please choose a valid date and time.', VCE_TEXT_DOMAIN ),
						[ 'status' => 400 ]
					);
				}

				try {
					$effective_tz    = new \DateTimeZone( Schedule_Timezone::effective_timezone( $timezone ) );
					$dt              = ( new \DateTimeImmutable( '@' . $scheduled_utc ) )->setTimezone( $effective_tz );
					$scheduled_mysql = $dt->format( 'Y-m-d H:i:s' );
				} catch ( \Exception $e ) {
					return new WP_Error(
						'vce_invalid_schedule',
						__( 'Please choose a valid date and time.', VCE_TEXT_DOMAIN ),
						[ 'status' => 400 ]
					);
				}

				// UTC for cron; local mysql + IANA tz for display/edit (empty tz = site timezone).
				update_post_meta( $post_id, Panel_Meta::SUBMISSION_SCHEDULED_UTC, $scheduled_utc );
				update_post_meta( $post_id, Panel_Meta::SUBMISSION_SCHEDULED_AT, $scheduled_mysql );
				update_post_meta( $post_id, Panel_Meta::SUBMISSION_SCHEDULED_TIMEZONE, $timezone );
				update_post_meta( $post_id, Panel_Meta::SUBMISSION_STATUS, 'scheduled' );

				$sender_id = $user_id ?: (int) get_post_meta( $post_id, Panel_Meta::SUBMISSION_SENDER_ID, true );
				wp_update_post(
					[
						'ID'         => $post_id,
						'post_title' => sprintf( '(VC - %d, Sender - %d, RC - %s)', $parent_id, $sender_id, $recipient_email ),
					]
				);

				if ( $user_id ) {
					delete_option( "LAST_DRAFT_SUBMISSION_{$user_id}" );
				}

				Submission_Logger::log(
					$post_id,
					'scheduled',
					sprintf(
						'Recipient: %s, Scheduled: %s (%s)',
						$recipient_email,
						$scheduled_mysql,
						Schedule_Timezone::effective_timezone( $timezone )
					)
				);
			} else {
				Panel_Meta::clear_schedule_meta( $post_id );
				update_post_meta( $post_id, Panel_Meta::SUBMISSION_STATUS, 'saved' );
			}
		} else {
			// Save submission (layers only): do not downgrade scheduled → saved.
			$current_status = get_post_meta( $post_id, Panel_Meta::SUBMISSION_STATUS, true ) ?: 'saved';
			if ( 'scheduled' !== $current_status ) {
				update_post_meta( $post_id, Panel_Meta::SUBMISSION_STATUS, 'saved' );
			}
		}

		$parent_title = get_the_title( $parent_id );
		if ( 0 === $submission_id ) {
			Submission_Logger::log(
				$post_id,
				'created',
				sprintf(
					'Virtual Card: %s (#%d), Sender: %s (#%d)',
					$parent_title ?: '#',
					(string) $parent_id,
					$user_id ? ( get_userdata( $user_id )->display_name ?: 'User' ) : 'Guest',
					$user_id ?: 0
				)
			);
		}

		$preview_url = add_query_arg(
			[
				'post_type' => Post_Type::CARD_SUBMISSION_POST_TYPE,
				'p'         => (int) $post_id,
			],
			home_url( '/' )
		);
		$permalink = $preview_url;
		// Return saved form values so JS can refresh fields after Schedule or Send.
		$dispatch  = Panel_Meta::get_submission_dispatch(
			$post_id,
			$user_id ? ( get_userdata( $user_id )->display_name ?: get_userdata( $user_id )->user_login ) : ''
		);

		return new WP_REST_Response(
			array_merge(
				[
					'id'          => (int) $post_id,
					'url'         => $permalink,
					'preview_url' => $preview_url,
					'edit_url'    => get_edit_post_link( $post_id, 'raw' ),
					'status'      => get_post_meta( $post_id, Panel_Meta::SUBMISSION_STATUS, true ) ?: 'saved',
				],
				$dispatch
			),
			200
		);
	}
}
