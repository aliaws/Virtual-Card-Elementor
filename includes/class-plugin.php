<?php
/**
 * Main plugin bootstrap.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

use Virtual_Card_Elementor\Admin\Attachment_Tags;
use Virtual_Card_Elementor\Admin\Panel_Meta_Box;
use Virtual_Card_Elementor\Admin\Virtual_Card_Admin_Columns;
use Virtual_Card_Elementor\Elementor\Card_Panels_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once VCE_PLUGIN_DIR . 'admin/class-virtual-card-admin-columns.php';
require_once VCE_PLUGIN_DIR . 'admin/class-attachment-tags.php';
require_once VCE_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once VCE_PLUGIN_DIR . 'admin/class-vce-debug-page.php';
require_once VCE_PLUGIN_DIR . 'includes/class-vce-debug-rest.php';

/**
 * Loads components and hooks.
 */
class Plugin {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Wire WordPress hooks.
	 */
	public function run(): void {
		Debug_Log::register_shutdown_logger();

		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action(
			'init',
			static function (): void {
				if ( get_option( 'vce_flush_rewrite_rules' ) === '1' ) {
					flush_rewrite_rules( false );
					delete_option( 'vce_flush_rewrite_rules' );
				}
			},
			999
		);

		$post_type = new Post_Type();
		$post_type->register_hooks();

		$vce_debug_rest = new Vce_Debug_Rest();
		$vce_debug_rest->register_hooks();

		$vce_debug_page = new Admin\Vce_Debug_Page();
		$vce_debug_page->register_hooks();

		$panels = new Panel_Meta_Box();
		$panels->register_hooks();

		$list_columns = new Virtual_Card_Admin_Columns();
		$list_columns->register_hooks();

		$attachment_tags = new Attachment_Tags();
		$attachment_tags->register_hooks();

		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_elementor_frontend_assets' ] );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			VCE_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( VCE_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register styles used by the Elementor widget on the frontend.
	 */
	public function register_elementor_frontend_assets(): void {
		wp_register_style(
			'vce-frontend-panel',
			VCE_PLUGIN_URL . 'assets/css/frontend-panel.css',
			[],
			vce_asset_version( 'assets/css/frontend-panel.css' )
		);

		wp_register_style(
			'vce-frontend-panel-editor',
			VCE_PLUGIN_URL . 'assets/css/frontend-panel-editor.css',
			[ 'vce-frontend-panel' ],
			vce_asset_version( 'assets/css/frontend-panel-editor.css' )
		);

		wp_register_script(
			'fabric',
			'https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js',
			[],
			'5.3.0',
			true
		);

		$editor_deps = [ 'fabric' ];
		if ( Debug_Log::register_debug_client_assets() ) {
			$editor_deps[] = 'vce-debug-client';
		}

		wp_register_script(
			'vce-frontend-panel-editor',
			VCE_PLUGIN_URL . 'assets/js/frontend-panel-editor.js',
			$editor_deps,
			vce_asset_version( 'assets/js/frontend-panel-editor.js' ),
			true
		);
	}

	/**
	 * Register the widget when Elementor is available.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_elementor_widget( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once VCE_PLUGIN_DIR . 'elementor/class-card-panels-widget.php';
		$widgets_manager->register( new Card_Panels_Widget() );
	}
}
