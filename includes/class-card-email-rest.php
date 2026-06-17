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
		add_action( 'wp_ajax_vce_recipient_emails', [ $this, 'ajax_recipient_emails' ] );
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
			'/recipient-emails',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_recipient_emails' ],
				'permission_callback' => [ $this, 'check_logged_in' ],
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

	public function check_logged_in(): bool {
		return is_user_logged_in();
	}

	/**
	 * Unique recipient emails from the current user's past submissions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_recipient_emails( WP_REST_Request $request ) {
		$emails = $this->collect_recipient_emails_for_user(
			get_current_user_id(),
			sanitize_text_field( (string) ( $request->get_param( 'search' ) ?: '' ) )
		);
		if ( is_wp_error( $emails ) ) {
			return $emails;
		}

		return new WP_REST_Response( $emails, 200 );
	}

	/**
	 * AJAX handler for front-end recipient autocomplete (more reliable than REST on cached pages).
	 */
	public function ajax_recipient_emails(): void {
		check_ajax_referer( 'vce_recipient_emails', 'nonce' );

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$emails = $this->collect_recipient_emails_for_user( get_current_user_id(), $search );
		if ( is_wp_error( $emails ) ) {
			wp_send_json_error( [ 'message' => $emails->get_error_message() ], 403 );
		}

		wp_send_json_success( $emails );
	}

	/**
	 * @param int    $user_id Current user ID.
	 * @param string $search  Optional filter.
	 * @return string[]|WP_Error
	 */
	private function collect_recipient_emails_for_user( int $user_id, string $search = '' ) {
		if ( ! $user_id ) {
			return new WP_Error( 'vce_unauthorized', __( 'You must be logged in.', VCE_TEXT_DOMAIN ) );
		}

		$submission_ids = get_posts(
			[
				'post_type'      => Post_Type::CARD_SUBMISSION_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => Panel_Meta::SUBMISSION_SENDER_ID,
						'value'   => $user_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);

		$emails = [];
		foreach ( $submission_ids as $sid ) {
			$email = sanitize_email( (string) get_post_meta( (int) $sid, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, true ) );
			if ( ! is_email( $email ) ) {
				continue;
			}
			if ( '' !== $search && false === stripos( $email, $search ) ) {
				continue;
			}
			$emails[ strtolower( $email ) ] = $email;
		}

		natcasesort( $emails );

		return array_values( $emails );
	}

	/**
	 * Send the card email for a submission (used by REST and scheduled cron).
	 *
	 * @param int    $submission_id Submission post ID.
	 * @param string $sender_name   Optional override.
	 * @param string $message       Optional message.
	 * @param array  $panels        Optional panel image payloads for the email body.
	 * @return true|WP_Error
	 */
	public static function send_submission_email( int $submission_id, string $sender_name = '', string $message = '', array $panels = [] ) {
		if ( $submission_id <= 0 || Post_Type::CARD_SUBMISSION_POST_TYPE !== get_post_type( $submission_id ) ) {
			return new WP_Error( 'vce_invalid_submission', __( 'Invalid submission.', VCE_TEXT_DOMAIN ) );
		}

		$recipient_email = sanitize_email( (string) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, true ) );
		if ( ! is_email( $recipient_email ) ) {
			return new WP_Error( 'vce_invalid_email', __( 'Invalid recipient email.', VCE_TEXT_DOMAIN ) );
		}

		$sender_id   = (int) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENDER_ID, true );
		$sender_user = $sender_id ? get_userdata( $sender_id ) : null;
		// Cron / scheduled send: use name + message saved at schedule time when not passed in.
		if ( '' === $sender_name ) {
			$sender_name = (string) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENDER_NAME, true );
		}
		if ( '' === $message ) {
			$message = (string) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_MESSAGE, true );
		}
		if ( '' === $sender_name && $sender_user ) {
			$sender_name = $sender_user->display_name;
		}
		$sender_name = sanitize_text_field( $sender_name );
		$message     = sanitize_textarea_field( $message );

		if ( empty( $panels ) ) {
			$parent_id = wp_get_post_parent_id( $submission_id );
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
			return new WP_Error( 'vce_email_failed', __( 'Could not send email.', VCE_TEXT_DOMAIN ) );
		}

		$per_email = get_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENT_COUNT, true );
		if ( ! is_array( $per_email ) ) {
			$per_email = [];
		}
		$per_email[ $recipient_email ] = ( $per_email[ $recipient_email ] ?? 0 ) + 1;
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_SENT_COUNT, $per_email );
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, $recipient_email );
		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_STATUS, 'sent' );
		// Scheduled send complete: drop UTC, local time, and timezone meta.
		Panel_Meta::clear_schedule_meta( $submission_id );

		$parent_id = wp_get_post_parent_id( $submission_id );
		wp_update_post(
			[
				'ID'         => $submission_id,
				'post_title' => sprintf( '(VC - %d, Sender - %d, RC - %s)', $parent_id ?: 0, $sender_id, $recipient_email ),
			]
		);

		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_option( "LAST_DRAFT_SUBMISSION_{$user_id}" );
		}

		Submission_Logger::log(
			$submission_id,
			'sent',
			sprintf(
				'Recipient: %s, Sender Name: %s',
				$recipient_email,
				$sender_name ?: 'N/A'
			)
		);

		return true;
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
				'message' => __( 'E-Card sent successfully!', VCE_TEXT_DOMAIN ),
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

		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_RECEIVER_EMAIL, $recipient_email );

		$sender_name = sanitize_text_field( $request->get_param( 'senderName' ) ?: '' );
		$message     = sanitize_textarea_field( $request->get_param( 'message' ) ?: '' );
		$panels      = $request->get_param( 'panels' );
		if ( ! is_array( $panels ) ) {
			$panels = [];
		}

		$result = self::send_submission_email( $submission_id, $sender_name, $message, $panels );
		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				$result->get_error_code(),
				$result->get_error_message(),
				[ 'status' => 'vce_email_failed' === $result->get_error_code() ? 500 : 400 ]
			);
		}

		$is_new  = filter_var( $request->get_param( 'isNewSubmission' ), FILTER_VALIDATE_BOOLEAN );
		$message = $is_new
			? __( 'E-Card sent successfully!', VCE_TEXT_DOMAIN )
			: __( 'E-Card sent successfully!', VCE_TEXT_DOMAIN );

		// Transient + REST notice for checkout-style banner (send-now final step).
		$user_id = get_current_user_id();
		if ( $user_id ) {
			Submission_Notice::set( $user_id, $message, 'success' );
		}

		return new WP_REST_Response(
			[
				'success'         => true,
				'message'         => $message,
				'isNewSubmission' => $is_new,
				'notice'          => [
					'message' => $message,
					'type'    => 'success',
				],
			],
			200
		);
	}
}
