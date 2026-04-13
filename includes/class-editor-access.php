<?php
/**
 * Front-end editor access: logged-in users vs guests.
 *
 * Switch mode from a theme or mu-plugin:
 *
 *     add_filter( 'vce_front_editor_mode', function () {
 *         return \Virtual_Card_Elementor\Editor_Access::MODE_GUEST;
 *     } );
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Who may use the front-end card editor UI.
 */
final class Editor_Access {

	public const MODE_LOGGED_IN = 'logged_in';

	public const MODE_GUEST = 'guest';

	/**
	 * Resolved mode after filter.
	 */
	public static function get_mode(): string {
		$mode = apply_filters( 'vce_front_editor_mode', self::MODE_LOGGED_IN );

		if ( ! is_string( $mode ) ) {
			return self::MODE_LOGGED_IN;
		}

		if ( self::MODE_GUEST === $mode ) {
			return self::MODE_GUEST;
		}

		return self::MODE_LOGGED_IN;
	}

	/**
	 * Whether the current visitor may open/use the editor UI on the single virtual card page.
	 */
	public static function can_use_front_editor(): bool {
		$allowed = self::evaluate_can_use();
		return (bool) apply_filters( 'vce_front_editor_can_use', $allowed );
	}

	/**
	 * @return bool
	 */
	private static function evaluate_can_use(): bool {
		$mode = self::get_mode();

		if ( self::MODE_GUEST === $mode ) {
			return true;
		}

		return is_user_logged_in();
	}
}
