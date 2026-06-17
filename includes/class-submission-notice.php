<?php
/**
 * Success/error notices for submission saves (WordPress transients + AJAX display).
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Submission_Notice {

	/** Per-user transient key prefix (see {@see set()}). */
	private const TRANSIENT_PREFIX = 'vce_submission_notice_';

	/** Seconds before an unread notice expires. */
	private const TTL = 300;

	/**
	 * Store a notice for the current user (shown on next render or via REST response).
	 *
	 * @param int    $user_id User ID; guests are skipped.
	 * @param string $message Notice text.
	 * @param string $type    success|error.
	 */
	public static function set( int $user_id, string $message, string $type = 'success' ): void {
		if ( $user_id <= 0 || '' === trim( $message ) ) {
			return;
		}
		set_transient(
			self::TRANSIENT_PREFIX . $user_id,
			[
				'message' => $message,
				'type'    => 'error' === $type ? 'error' : 'success',
			],
			self::TTL
		);
	}

	/**
	 * Read and remove a pending notice for a user.
	 *
	 * @return array{message: string, type: string}|null
	 */
	public static function get_and_clear( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		$key  = self::TRANSIENT_PREFIX . $user_id;
		$data = get_transient( $key );
		delete_transient( $key ); // One-shot: consumed on first page render after save.
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return null;
		}
		return [
			'message' => (string) $data['message'],
			'type'    => 'error' === ( $data['type'] ?? '' ) ? 'error' : 'success',
		];
	}

	/**
	 * Payload for REST / localized script (does not clear transient).
	 *
	 * @return array{message: string, type: string}|null
	 */
	public static function peek( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		$data = get_transient( self::TRANSIENT_PREFIX . $user_id );
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return null;
		}
		return [
			'message' => (string) $data['message'],
			'type'    => 'error' === ( $data['type'] ?? '' ) ? 'error' : 'success',
		];
	}

}
