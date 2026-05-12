<?php
/**
 * REST endpoint for sending Virtual Card submissions via email.
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

final class Card_Email_Rest {

	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'vce/v1',
			'/send-email',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'send_email' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			'vce/v1',
			'/admin-send-email',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'admin_send_email' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function admin_send_email( WP_REST_Request $request ) {
		$submission_id = absint( $request->get_param( 'submissionId' ) );
		if ( $submission_id <= 0 || Post_Type::CARD_SUBMISSION_POST_TYPE !== get_post_type( $submission_id ) ) {
			return new WP_Error( 'vce_invalid_submission', __( 'Invalid submission.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$recipient_email = sanitize_email( $request->get_param( 'recipientEmail' ) );
		if ( ! is_email( $recipient_email ) ) {
			return new WP_Error( 'vce_invalid_email', __( 'Invalid recipient email.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$sender_name = sanitize_text_field( $request->get_param( 'senderName' ) ?: '' );
		$message     = sanitize_textarea_field( $request->get_param( 'message' ) ?: '' );

		$sender_id    = (int) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENDER_ID, true );
		$sender_user  = $sender_id ? get_userdata( $sender_id ) : null;
		if ( ! $sender_name && $sender_user ) {
			$sender_name = $sender_user->display_name;
		}

		$preview_url = get_permalink( $submission_id );

		// Get parent card panel images as fallback
		$parent_id = wp_get_post_parent_id( $submission_id );
		$panels    = [];
		if ( $parent_id && Post_Type::POST_TYPE === get_post_type( $parent_id ) ) {
			$ids = get_post_meta( $parent_id, Panel_Meta::META_KEY, true );
			if ( is_array( $ids ) ) {
				foreach ( $ids as $aid ) {
					$url = wp_get_attachment_url( (int) $aid );
					if ( $url ) {
						$panels[] = [ 'url' => $url, 'w' => 0, 'h' => 0 ];
					}
				}
			}
		}

		ob_start();
		Template::render(
			'emails/card-email.php',
			[
				'sender_name'  => $sender_name,
				'message'      => $message,
				'card_title'   => __( 'Virtual Card', VCE_TEXT_DOMAIN ),
				'panels'       => $panels,
				'site_name'    => get_bloginfo( 'name' ),
				'preview_url'  => $preview_url,
			]
		);
		$html_body = (string) ob_get_clean();

		$subject = $sender_name
			? sprintf( __( '%s sent you a Virtual Card!', VCE_TEXT_DOMAIN ), $sender_name )
			: __( 'You received a Virtual Card!', VCE_TEXT_DOMAIN );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>',
		];

		$sent = wp_mail( $recipient_email, $subject, $html_body, $headers );

		if ( ! $sent ) {
			return new WP_Error( 'vce_email_failed', __( 'Could not send email.', VCE_TEXT_DOMAIN ), [ 'status' => 500 ] );
		}

		$per_email = get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENT_COUNT, true );
		if ( ! is_array( $per_email ) ) {
			$per_email = [];
		}
		$per_email[ $recipient_email ] = ( $per_email[ $recipient_email ] ?? 0 ) + 1;
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENT_COUNT, $per_email );
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, $recipient_email );
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_STATUS, 'sent' );

		$parent_id = wp_get_post_parent_id( $submission_id );
		wp_update_post(
			[
				'ID'         => $submission_id,
				'post_title' => sprintf( '(VC - %d, Sender - %d, RC - %s)', $parent_id ?: 0, $sender_id, $recipient_email ),
			],
		);

		Submission_Logger::log(
			$submission_id,
			'sent',
			sprintf(
				'Recipient: %s, Sender Name: %s',
				$recipient_email,
				$sender_name ?: 'N/A'
			)
		);

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Card sent successfully!', VCE_TEXT_DOMAIN ),
			],
			200
		);
	}

	public function send_email( WP_REST_Request $request ) {
		$recipient_email = sanitize_email( $request->get_param( 'recipientEmail' ) );
		if ( ! is_email( $recipient_email ) ) {
			return new WP_Error( 'vce_invalid_email', __( 'Invalid recipient email.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$submission_id = absint( $request->get_param( 'submissionId' ) );
		if ( $submission_id <= 0 || Post_Type::CARD_SUBMISSION_POST_TYPE !== get_post_type( $submission_id ) ) {
			return new WP_Error( 'vce_invalid_submission', __( 'Invalid submission.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$sender_name  = sanitize_text_field( $request->get_param( 'senderName' ) ?: '' );
		$message      = sanitize_textarea_field( $request->get_param( 'message' ) ?: '' );
		$card_title   = sanitize_text_field( $request->get_param( 'cardTitle' ) ?: __( 'Virtual Card', VCE_TEXT_DOMAIN ) );
		$panels       = $request->get_param( 'panels' );

		if ( ! is_array( $panels ) || empty( $panels ) ) {
			$panels = [];
		}

		ob_start();
		Template::render(
			'emails/card-email.php',
			[
				'sender_name'  => $sender_name,
				'message'      => $message,
				'card_title'   => $card_title,
				'panels'       => $panels,
				'site_name'    => get_bloginfo( 'name' ),
				'preview_url'  => get_permalink( $submission_id ),
			]
		);
		$html_body = (string) ob_get_clean();

		$subject = $sender_name
			? sprintf( __( '%s sent you a Virtual Card!', VCE_TEXT_DOMAIN ), $sender_name )
			: __( 'You received a Virtual Card!', VCE_TEXT_DOMAIN );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_bloginfo( 'admin_email' ) . '>',
		];

		$sent = wp_mail( $recipient_email, $subject, $html_body, $headers );

		if ( ! $sent ) {
			return new WP_Error( 'vce_email_failed', __( 'Could not send email.', VCE_TEXT_DOMAIN ), [ 'status' => 500 ] );
		}

		$per_email = get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENT_COUNT, true );
		if ( ! is_array( $per_email ) ) {
			$per_email = [];
		}
		$per_email[ $recipient_email ] = ( $per_email[ $recipient_email ] ?? 0 ) + 1;
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENT_COUNT, $per_email );
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, $recipient_email );
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_STATUS, 'sent' );

		$parent_id = wp_get_post_parent_id( $submission_id );
		$sender_id = (int) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENDER_ID, true );
		wp_update_post(
			[
				'ID'         => $submission_id,
				'post_title' => sprintf( '(VC - %d, Sender - %d, RC - %s)', $parent_id ?: 0, $sender_id, $recipient_email ),
			],
		);

		Submission_Logger::log(
			$submission_id,
			'sent',
			sprintf(
				'Recipient: %s, Sender Name: %s (via admin)',
				$recipient_email,
				$sender_name ?: 'N/A'
			)
		);

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Card sent successfully!', VCE_TEXT_DOMAIN ),
			],
			200
		);
	}
}
