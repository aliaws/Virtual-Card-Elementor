<?php
/**
 * Submission activity logger.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Submission_Logger {

	/**
	 * Add a log entry to a submission.
	 *
	 * @param int    $submission_id Post ID.
	 * @param string $action        Action type: created, sent, viewed.
	 * @param string $details       Human-readable details.
	 */
	public static function log( int $submission_id, string $action, string $details = '' ): void {
		if ( ! $submission_id || Post_Type::CARD_SUBMISSION_POST_TYPE !== get_post_type( $submission_id ) ) {
			return;
		}

		$logs = get_post_meta( $submission_id, Panel_Meta::SUBMISSION_LOG, true );
		if ( ! is_array( $logs ) ) {
			$logs = [];
		}

		$logs[] = [
			'time'    => current_time( 'Y-m-d H:i:s' ),
			'action'  => $action,
			'details' => $details,
		];

		update_post_meta( $submission_id, Panel_Meta::SUBMISSION_LOG, $logs );
	}
}
