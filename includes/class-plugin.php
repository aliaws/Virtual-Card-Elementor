<?php
/**
 * Main plugin bootstrap.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

use Virtual_Card_Elementor\Admin\Panel_Meta_Box;
use Virtual_Card_Elementor\Elementor\Card_Panels_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		$post_type = new Post_Type();
		$post_type->register_hooks();

		$panels = new Panel_Meta_Box();
		$panels->register_hooks();

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
			VCE_VERSION
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
