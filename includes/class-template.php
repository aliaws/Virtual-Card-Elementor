<?php
/**
 * Load PHP templates from the templates directory.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template renderer.
 */
class Template {

	/**
	 * Output a template file with extracted variables.
	 *
	 * @param string $relative_path Path under templates/, e.g. admin/panel-meta-box.php.
	 * @param array  $args          Variables available in the template.
	 */
	public static function render( string $relative_path, array $args = [] ): void {
		$file = VCE_PLUGIN_DIR . 'templates/' . ltrim( $relative_path, '/' );
		if ( ! is_readable( $file ) ) {
			return;
		}
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- scoped template variables.
		extract( $args, EXTR_SKIP );
		include $file;
	}
}
