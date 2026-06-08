<?php
/**
 * Cron: send card submissions when their scheduled time is due.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Submission_Scheduler {

	private const CRON_HOOK = 'vce_check_scheduled_submissions';

	public function register_hooks(): void {
		add_action( 'init', [ $this, 'maybe_schedule_cron' ] );
		add_action( self::CRON_HOOK, [ $this, 'process_due_submissions' ] );
	}

	public function maybe_schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'five_minutes', self::CRON_HOOK );
		}
	}

	public function process_due_submissions(): void {
		$now = time();

		// Status=scheduled only; due time checked in PHP so legacy + UTC meta both work.
		$candidates = get_posts(
			[
				'post_type'      => Post_Type::CARD_SUBMISSION_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => Panel_Meta::SUBMISSION_STATUS,
						'value'   => 'scheduled',
						'compare' => '=',
					],
				],
				'orderby'        => 'date',
				'order'          => 'ASC',
			]
		);

		foreach ( $candidates as $submission_id ) {
			$due_at = Panel_Meta::get_scheduled_utc_timestamp( (int) $submission_id );
			if ( $due_at <= 0 || $due_at > $now ) {
				continue;
			}
			// Past-due or due now: cron sends (polled every 5 minutes, not exact second).
			Card_Email_Rest::send_submission_email( (int) $submission_id, '', '' );
		}
	}
}

add_filter(
	'cron_schedules',
	static function ( array $schedules ): array {
		if ( ! isset( $schedules['five_minutes'] ) ) {
			$schedules['five_minutes'] = [
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 5 minutes', VCE_TEXT_DOMAIN ),
			];
		}
		return $schedules;
	}
);
