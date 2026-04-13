<?php
/**
 * Front-end editor access: logged-in users vs guests.
 *
 * Default is {@see Editor_Access::MODE_LOGGED_IN} so saves can be tied to a user ID
 * and REST can use standard cookies + nonces. Guest mode allows anonymous use; you
 * must pair it with session or opaque tokens when you implement persistence (REST).
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
 * Who may use the future front-end card editor and how saves are scoped.
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
	 * Whether the current visitor may persist design data (REST POST/PUT).
	 *
	 * For {@see self::MODE_GUEST}, this returns true so anonymous flows can save once you
	 * add token/session checks inside REST callbacks. Tighten with filter if needed.
	 */
	public static function can_persist_design(): bool {
		$mode = self::get_mode();

		if ( self::MODE_LOGGED_IN === $mode ) {
			$allowed = is_user_logged_in();
		} else {
			$allowed = true;
		}

		return (bool) apply_filters( 'vce_front_editor_can_persist', $allowed, $mode );
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
