<?php
/**
 * Parse and validate schedule timezones for card submissions.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schedule_Timezone {

	/**
	 * Schedule dropdown zones (order preserved).
	 *
	 * @return array<string, array{region: string, city: string, observes_dst: bool}>
	 */
	private static function zone_definitions(): array {
		return [
			'America/Chicago'     => [
				'region'       => 'Central',
				'city'         => 'Chicago',
				'observes_dst' => true,
			],
			'America/Denver'      => [
				'region'       => 'Mountain',
				'city'         => 'Denver',
				'observes_dst' => true,
			],
			'America/Phoenix'     => [
				'region'       => 'Mountain',
				'city'         => 'Phoenix',
				'observes_dst' => false, // Arizona: no DST; label always "Standard".
			],
			'America/Los_Angeles' => [
				'region'       => 'Pacific',
				'city'         => 'Los Angeles',
				'observes_dst' => true,
			],
			'America/Anchorage'   => [
				'region'       => 'Alaska',
				'city'         => 'Anchorage',
				'observes_dst' => true,
			],
			'Pacific/Honolulu'    => [
				'region'       => 'Hawaii-Aleutian',
				'city'         => 'Honolulu',
				'observes_dst' => false, // Hawaii: no DST; label always "Standard".
			],
		];
	}

	/**
	 * @return string[] Valid IANA timezone identifiers.
	 */
	public static function allowed_list(): array {
		static $list = null;
		if ( null === $list ) {
			$list = timezone_identifiers_list();
		}
		return $list;
	}

	/**
	 * IANA identifiers shown in the schedule dropdown.
	 *
	 * @return string[]
	 */
	public static function dropdown_list(): array {
		return array_keys( self::zone_definitions() );
	}

	/**
	 * Dynamic schedule dropdown labels (IANA identifier => display label).
	 *
	 * @return array<string, string>
	 */
	public static function dropdown_choices(): array {
		$choices = [];
		foreach ( self::dropdown_list() as $iana_id ) {
			$choices[ $iana_id ] = self::build_label( $iana_id );
		}
		return $choices;
	}

	/**
	 * Dropdown options (value => label), including a stored timezone when outside the fixed list.
	 *
	 * @param string $include_if_set Previously saved timezone to keep selectable on edit.
	 * @return array<string, string>
	 */
	public static function dropdown_options( string $include_if_set = '' ): array {
		$choices = self::dropdown_choices();
		$include_if_set = self::sanitize( $include_if_set );
		// On edit: keep a legacy stored timezone selectable even if not in the fixed list.
		if ( '' !== $include_if_set && ! isset( $choices[ $include_if_set ] ) ) {
			$choices[ $include_if_set ] = self::display_label( $include_if_set );
		}
		return $choices;
	}

	/**
	 * Friendly label for a stored timezone identifier.
	 */
	public static function display_label( string $timezone ): string {
		$timezone = trim( $timezone );
		if ( isset( self::zone_definitions()[ $timezone ] ) ) {
			return self::build_label( $timezone );
		}
		return $timezone;
	}

	/**
	 * Build a dropdown label: "Central Daylight Time - Chicago (GMT-5)".
	 *
	 * @param string   $iana_id IANA timezone identifier.
	 * @param int|null $at      Unix timestamp for DST/offset; null uses now.
	 */
	public static function build_label( string $iana_id, ?int $at = null ): string {
		$config = self::zone_definitions()[ $iana_id ] ?? null;
		if ( ! $config ) {
			return $iana_id;
		}

		try {
			$tz        = new \DateTimeZone( $iana_id );
			$timestamp = null !== $at ? $at : time();
			$dt        = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $tz );
			$offset    = $dt->getOffset();
			// Standard vs Daylight from IANA rules at $timestamp (labels refresh when DST changes).
			$std_dst   = self::is_daylight_time( $iana_id, $timestamp ) ? 'Daylight' : 'Standard';
			$gmt       = self::format_gmt_offset( $offset );

			return sprintf(
				'%s %s Time - %s (%s)',
				$config['region'],
				$std_dst,
				$config['city'],
				$gmt
			);
		} catch ( \Exception $e ) {
			return $iana_id;
		}
	}

	/**
	 * Whether DST is active for a zone at the given timestamp.
	 */
	private static function is_daylight_time( string $iana_id, int $timestamp ): bool {
		$config = self::zone_definitions()[ $iana_id ] ?? null;
		if ( ! $config || ! $config['observes_dst'] ) {
			return false;
		}

		try {
			$tz            = new \DateTimeZone( $iana_id );
			$dt            = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $tz );
			$year          = (int) $dt->format( 'Y' );
			// Compare Jan vs Jul offset; current matches the larger offset when DST is active.
			$winter        = new \DateTimeImmutable( $year . '-01-15 12:00:00', $tz );
			$summer        = new \DateTimeImmutable( $year . '-07-15 12:00:00', $tz );
			$winter_offset = $winter->getOffset();
			$summer_offset = $summer->getOffset();

			if ( $winter_offset === $summer_offset ) {
				return false;
			}

			$current_offset = $dt->getOffset();
			return $current_offset === max( $winter_offset, $summer_offset );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Format UTC offset seconds as GMT-5, GMT+5:30, etc.
	 */
	private static function format_gmt_offset( int $offset_seconds ): string {
		$sign    = $offset_seconds >= 0 ? '+' : '-';
		$abs     = abs( $offset_seconds );
		$hours   = (int) floor( $abs / 3600 );
		$minutes = (int) ( ( $abs % 3600 ) / 60 );

		if ( $minutes > 0 ) {
			return sprintf( 'GMT%s%d:%02d', $sign, $hours, $minutes );
		}

		return sprintf( 'GMT%s%d', $sign, $hours );
	}

	/**
	 * Sanitize a timezone string; empty means use the WordPress site timezone.
	 */
	public static function sanitize( string $timezone ): string {
		$timezone = sanitize_text_field( $timezone );
		if ( '' === $timezone ) {
			return '';
		}
		if ( in_array( $timezone, self::dropdown_list(), true ) ) {
			return $timezone;
		}
		// Legacy submissions saved before the fixed dropdown list.
		if ( in_array( $timezone, self::allowed_list(), true ) ) {
			return $timezone;
		}
		return '';
	}

	/**
	 * Resolve stored or site timezone for parsing/display.
	 */
	public static function effective_timezone( string $timezone ): string {
		$timezone = self::sanitize( $timezone );
		if ( '' !== $timezone ) {
			return $timezone;
		}
		// Empty dropdown selection → parse/display in WordPress site timezone.
		$site = wp_timezone_string();
		return $site ?: 'UTC';
	}

	/**
	 * Parse a datetime-local value in the given timezone to a UTC unix timestamp.
	 *
	 * @param string $raw      Value from datetime-local (Y-m-d\TH:i).
	 * @param string $timezone IANA identifier; empty uses site timezone.
	 */
	public static function parse_to_utc_timestamp( string $raw, string $timezone = '' ): int {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return 0;
		}

		try {
			// datetime-local has no TZ suffix; interpret in selected (or site) timezone, store UTC.
			$tz = new \DateTimeZone( self::effective_timezone( $timezone ) );
			$dt = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $raw, $tz );
			if ( ! $dt ) {
				$normalized = str_replace( 'T', ' ', substr( $raw, 0, 16 ) );
				$dt         = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $normalized, $tz );
			}
			return $dt ? $dt->getTimestamp() : 0;
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Format a UTC timestamp for datetime-local in the given timezone.
	 */
	public static function utc_timestamp_for_input( int $utc_timestamp, string $timezone = '' ): string {
		if ( $utc_timestamp <= 0 ) {
			return '';
		}
		try {
			$tz = new \DateTimeZone( self::effective_timezone( $timezone ) );
			$dt = ( new \DateTimeImmutable( '@' . $utc_timestamp ) )->setTimezone( $tz );
			return $dt->format( 'Y-m-d\TH:i' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

}
