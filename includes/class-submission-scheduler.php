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
		$now = current_time( 'mysql' );

		$due = get_posts(
			[
				'post_type'      => Post_Type::CARD_SUBMISSION_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => Panel_Meta::SUBMISSION_STATUS,
						'value'   => 'scheduled',
						'compare' => '=',
					],
					[
						'key'     => Panel_Meta::SUBMISSION_SCHEDULED_AT,
						'value'   => $now,
						'compare' => '<=',
						'type'    => 'DATETIME',
					],
				],
			]
		);

		foreach ( $due as $submission_id ) {
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
