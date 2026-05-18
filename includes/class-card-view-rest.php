<?php
/**
 * REST endpoint for tracking Virtual Card submission views.
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

final class Card_View_Rest {

	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'vce/v1',
			'/track-view',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'track_view' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function track_view( WP_REST_Request $request ) {
		$submission_id = absint( $request->get_param( 'submissionId' ) );
		if ( $submission_id > 0 && Post_Type::CARD_SUBMISSION_POST_TYPE === get_post_type( $submission_id ) ) {
			$status = get_post_meta( $submission_id, Panel_Meta::SUBMISSION_STATUS, true );
			if ( 'sent' === $status ) {
				update_post_meta( $submission_id, Panel_Meta::SUBMISSION_STATUS, 'viewed' );
			}
			$count = (int) get_post_meta( $submission_id, Panel_Meta::SUBMISSION_VIEWED_COUNT, true );
			update_post_meta( $submission_id, Panel_Meta::SUBMISSION_VIEWED_COUNT, $count + 1 );

			Submission_Logger::log(
				$submission_id,
				'viewed',
				sprintf(
					'View #%d',
					$count + 1
				)
			);
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}
}
