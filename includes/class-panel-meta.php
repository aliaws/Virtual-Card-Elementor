<?php
/**
 * Shared panel (image set) post meta constants.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Panel attachment IDs stored in post meta.
 */
class Panel_Meta {

	public const META_KEY = '_virtual_card_panels';

	public const SUBMISSION_LAYERS_META_KEY = '_vce_submission_layers';

	public const WIX_META_KEY = '_ads_wix_card_id';

	/**
	 * Custom sort / display order for Virtual Cards (integer, 0 = default).
	 */
	public const ORDER_META_KEY = 'order';

	/**
	 * Elementor Posts / Loop "Order By" value for {@see Panel_Meta::ORDER_META_KEY}.
	 */
	public const ORDERBY_DISPLAY_ORDER = 'vce_display_order';

	/**
	 * Front-end category filter query arg ({@see Ecard_Category_Filter::QUERY_VAR}).
	 */
	public const CATEGORY_QUERY_VAR = 'vce_category';

	/**
	 * Is Favorite (checkbox).
	 */
	public const IS_FAVORITE_META_KEY = '_vce_is_favorite';

	/**
	 * First Level Label (text).
	 */
	public const FIRST_LEVEL_LABEL_META_KEY = '_vce_first_level_label';

/**
 * Second Level Label (text).
 */
public const SECOND_LEVEL_LABEL_META_KEY = '_vce_second_level_label';

/**
 * Submission sender user ID.
 */
public const SUBMISSION_SENDER_ID = '_vce_sender_id';

/**
 * Submission receiver email.
 */
public const SUBMISSION_RECEIVER_EMAIL = '_vce_receiver_email';

/**
 * Custom sender display name for the email.
 */
public const SUBMISSION_SENDER_NAME = '_vce_sender_name';

/**
 * Optional personal message for the email.
 */
public const SUBMISSION_MESSAGE = '_vce_submission_message';

/**
 * Scheduled send datetime (MySQL, local time in {@see SUBMISSION_SCHEDULED_TIMEZONE}).
 */
public const SUBMISSION_SCHEDULED_AT = '_vce_scheduled_at';

/**
 * UTC unix timestamp when the scheduled email should send.
 */
public const SUBMISSION_SCHEDULED_UTC = '_vce_scheduled_utc';

/**
 * IANA timezone for scheduled send; empty means WordPress site timezone.
 */
public const SUBMISSION_SCHEDULED_TIMEZONE = '_vce_scheduled_timezone';

/**
 * Submission status: saved, scheduled, sent, viewed.
 */
public const SUBMISSION_STATUS = '_vce_submission_status';

/**
 * Number of times email sent.
 */
public const SUBMISSION_SENT_COUNT = '_vce_sent_count';

/**
 * Number of times viewed.
 */
public const SUBMISSION_VIEWED_COUNT = '_vce_viewed_count';

/**
 * Submission activity log.
 */
public const SUBMISSION_LOG = '_vce_submission_log';

	/**
	 * IANA timezone used when the submission was scheduled (empty = site timezone).
	 *
	 * @param int $post_id Submission post ID.
	 */
	public static function get_scheduled_timezone( int $post_id ): string {
		$tz = trim( (string) get_post_meta( $post_id, self::SUBMISSION_SCHEDULED_TIMEZONE, true ) );
		if ( $tz && in_array( $tz, Schedule_Timezone::allowed_list(), true ) ) {
			return $tz;
		}
		return Schedule_Timezone::effective_timezone( '' );
	}

	/**
	 * Stored timezone identifier for forms (empty when using site timezone).
	 *
	 * @param int $post_id Submission post ID.
	 */
	public static function get_scheduled_timezone_for_input( int $post_id ): string {
		$tz = trim( (string) get_post_meta( $post_id, self::SUBMISSION_SCHEDULED_TIMEZONE, true ) );
		if ( $tz && in_array( $tz, Schedule_Timezone::allowed_list(), true ) ) {
			return $tz;
		}
		// Empty = "Use site timezone" in the dropdown (resolved at parse time).
		return '';
	}

	/**
	 * UTC unix timestamp when email should send; 0 if not scheduled.
	 *
	 * @param int $post_id Submission post ID.
	 */
	public static function get_scheduled_utc_timestamp( int $post_id ): int {
		$utc = (int) get_post_meta( $post_id, self::SUBMISSION_SCHEDULED_UTC, true );
		if ( $utc > 0 ) {
			return $utc;
		}

		// Legacy submissions: _vce_scheduled_at stored in site timezone.
		$scheduled_at = trim( (string) get_post_meta( $post_id, self::SUBMISSION_SCHEDULED_AT, true ) );
		if ( '' === $scheduled_at ) {
			return 0;
		}
		try {
			$dt = new \DateTimeImmutable( $scheduled_at, wp_timezone() );
			return $dt->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Formatted scheduled send datetime for list display, or empty when not scheduled.
	 *
	 * @param int $post_id Submission post ID.
	 */
	public static function format_scheduled_at_display( int $post_id ): string {
		if ( 'scheduled' !== ( get_post_meta( $post_id, self::SUBMISSION_STATUS, true ) ?: 'saved' ) ) {
			return '';
		}
		$utc = self::get_scheduled_utc_timestamp( $post_id );
		if ( $utc <= 0 ) {
			return '';
		}
		try {
			$tz_id     = self::get_scheduled_timezone_for_input( $post_id ) ?: self::get_scheduled_timezone( $post_id );
			$tz        = new \DateTimeZone( self::get_scheduled_timezone( $post_id ) );
			$formatted = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $utc, $tz );
			$tz_label  = Schedule_Timezone::display_label( $tz_id );
			return $formatted ? $formatted . ' (' . $tz_label . ')' : '';
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Scheduled datetime for HTML datetime-local input (Y-m-d\TH:i in stored timezone).
	 *
	 * @param int $post_id Submission post ID.
	 */
	public static function scheduled_at_for_input( int $post_id ): string {
		$utc = self::get_scheduled_utc_timestamp( $post_id );
		if ( $utc <= 0 ) {
			return '';
		}
		return Schedule_Timezone::utc_timestamp_for_input(
			$utc,
			self::get_scheduled_timezone_for_input( $post_id )
		);
	}

	/**
	 * Schedule-or-send form payload for JS (edit + REST response).
	 *
	 * @param int    $post_id         Submission post ID.
	 * @param string $fallback_sender Default sender name when meta is empty.
	 * @return array<string, mixed>
	 */
	public static function get_submission_dispatch( int $post_id, string $fallback_sender = '' ): array {
		$status       = get_post_meta( $post_id, self::SUBMISSION_STATUS, true ) ?: 'saved';
		$scheduled_at = self::scheduled_at_for_input( $post_id );
		$sender_name  = (string) get_post_meta( $post_id, self::SUBMISSION_SENDER_NAME, true );

		return [
			'recipientEmail'    => (string) get_post_meta( $post_id, self::SUBMISSION_RECEIVER_EMAIL, true ),
			'sendMode'            => 'scheduled' === $status ? 'schedule' : 'now',
			'scheduledAt'         => $scheduled_at,
			'scheduledTimezone'   => self::get_scheduled_timezone_for_input( $post_id ),
			'senderName'          => $sender_name ?: $fallback_sender,
			'message'             => (string) get_post_meta( $post_id, self::SUBMISSION_MESSAGE, true ),
			// Open Schedule-or-Send form on edit when a send time is already set.
			'autoOpenForm'        => '' !== $scheduled_at,
		];
	}

	/**
	 * Remove all schedule-related meta for a submission.
	 *
	 * @param int $post_id Submission post ID.
	 */
	public static function clear_schedule_meta( int $post_id ): void {
		delete_post_meta( $post_id, self::SUBMISSION_SCHEDULED_AT );
		delete_post_meta( $post_id, self::SUBMISSION_SCHEDULED_UTC );
		delete_post_meta( $post_id, self::SUBMISSION_SCHEDULED_TIMEZONE );
	}

}
