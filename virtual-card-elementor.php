<?php
/**
 * Plugin Name: Virtual Card Elementor
 * Description: Registers the Virtual Card custom post type, admin Card Panels (images per card), and an Elementor widget that outputs those panels for the current post in the loop.
 * Version: 1.4.0
 * Author: Accurate Digital Solutions
 * Text Domain: virtual-card-elementor
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Virtual_Card_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_data = get_file_data( __FILE__, [ 'Version' => 'Version' ], 'plugin' );
define( 'VCE_VERSION', $plugin_data['Version'] );

define( 'VCE_PLUGIN_FILE', __FILE__ );
define( 'VCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VCE_TEXT_DOMAIN', 'virtual-card-elementor' );

if ( ! defined( 'VCE_DEBUG' ) ) {
	define( 'VCE_DEBUG', false );
}

require_once VCE_PLUGIN_DIR . 'includes/class-panel-meta.php';
require_once VCE_PLUGIN_DIR . 'includes/class-template.php';
require_once VCE_PLUGIN_DIR . 'includes/class-editor-access.php';
require_once VCE_PLUGIN_DIR . 'includes/class-post-type.php';
require_once VCE_PLUGIN_DIR . 'admin/class-panel-meta-box.php';
require_once VCE_PLUGIN_DIR . 'includes/class-plugin.php';

if ( ! function_exists( 'vce_get_front_editor_mode' ) ) {
	/**
	 * Front-end editor mode: logged_in or guest.
	 *
	 * @return string
	 */
	function vce_get_front_editor_mode(): string {
		return Virtual_Card_Elementor\Editor_Access::get_mode();
	}
}

if ( ! function_exists( 'vce_can_use_front_editor' ) ) {
	/**
	 * Whether the current visitor may use the future front-end editor UI.
	 */
	function vce_can_use_front_editor(): bool {
		return Virtual_Card_Elementor\Editor_Access::can_use_front_editor();
	}
}

if ( ! function_exists( 'vce_can_persist_front_editor_design' ) ) {
	/**
	 * Whether the current visitor may save design data via REST (when implemented).
	 */
	function vce_can_persist_front_editor_design(): bool {
		return Virtual_Card_Elementor\Editor_Access::can_persist_design();
	}
}

register_activation_hook(
	VCE_PLUGIN_FILE,
	static function (): void {
		add_option( 'vce_flush_rewrite_rules', '1' );
	}
);

Virtual_Card_Elementor\Plugin::instance()->run();
