<?php
/**
 * Main plugin bootstrap.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

use Virtual_Card_Elementor\Admin\Attachment_Tags;
use Virtual_Card_Elementor\Admin\Card_Submission_Admin;
use Virtual_Card_Elementor\Admin\Panel_Meta_Box;
use Virtual_Card_Elementor\Admin\Virtual_Card_Admin_Columns;
use Virtual_Card_Elementor\Elementor\Card_Panels_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once VCE_PLUGIN_DIR . 'admin/class-virtual-card-admin-columns.php';
require_once VCE_PLUGIN_DIR . 'admin/class-card-submission-admin.php';
require_once VCE_PLUGIN_DIR . 'admin/class-attachment-tags.php';
require_once VCE_PLUGIN_DIR . 'includes/class-debug-log.php';
require_once VCE_PLUGIN_DIR . 'admin/class-vce-debug-page.php';
require_once VCE_PLUGIN_DIR . 'includes/class-vce-debug-rest.php';
require_once VCE_PLUGIN_DIR . 'includes/class-card-submission-rest.php';
require_once VCE_PLUGIN_DIR . 'includes/class-user-account.php';

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

		$card_submission_admin = new Card_Submission_Admin();
		$card_submission_admin->register_hooks();

		$vce_debug_rest = new Vce_Debug_Rest();
		$vce_debug_rest->register_hooks();

		$card_submission_rest = new Card_Submission_Rest();
		$card_submission_rest->register_hooks();

		$vce_debug_page = new Admin\Vce_Debug_Page();
		$vce_debug_page->register_hooks();

		$panels = new Panel_Meta_Box();
		$panels->register_hooks();

		$list_columns = new Virtual_Card_Admin_Columns();
		$list_columns->register_hooks();

		$attachment_tags = new Attachment_Tags();
		$attachment_tags->register_hooks();

		$user_account = new User_Account();
		$user_account->register_hooks();

		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'register_elementor_frontend_assets' ] );
		add_filter( 'the_content', [ $this, 'append_submission_final_view' ] );
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
			'vce-frontend-panel-renderer',
			VCE_PLUGIN_URL . 'assets/js/frontend-panel-renderer.js',
			[ 'fabric' ],
			vce_asset_version( 'assets/js/frontend-panel-renderer.js' ),
			true
		);

		wp_register_script(
			'vce-frontend-panel-editor',
			VCE_PLUGIN_URL . 'assets/js/frontend-panel-editor.js',
			array_merge( $editor_deps, [ 'vce-frontend-panel-renderer' ] ),
			vce_asset_version( 'assets/js/frontend-panel-editor.js' ),
			true
		);

		wp_register_script(
			'vce-frontend-panel-submission',
			VCE_PLUGIN_URL . 'assets/js/frontend-panel-submission.js',
			[ 'vce-frontend-panel-renderer' ],
			vce_asset_version( 'assets/js/frontend-panel-submission.js' ),
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

	/**
	 * Auto-render final submission cards on single card_submission pages.
	 *
	 * @param string $content Post content.
	 */
	public function append_submission_final_view( string $content ): string {
		if ( ! is_singular( Post_Type::CARD_SUBMISSION_POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || Post_Type::CARD_SUBMISSION_POST_TYPE !== $post->post_type ) {
			return $content;
		}

		$parent_id = (int) wp_get_post_parent_id( $post->ID );
		if ( $parent_id <= 0 || Post_Type::POST_TYPE !== get_post_type( $parent_id ) ) {
			return $content;
		}

		$ids = get_post_meta( $parent_id, Panel_Meta::META_KEY, true );
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return $content;
		}

		$panels_data = [];
		foreach ( $ids as $aid ) {
			$aid   = (int) $aid;
			$large = $aid ? wp_get_attachment_image_src( $aid, 'large' ) : null;
			$url   = ( $large && ! empty( $large[0] ) ) ? $large[0] : '';
			if ( '' === $url && $aid ) {
				$url = wp_get_attachment_url( $aid ) ?: '';
			}
			$panels_data[] = [
				'id'  => $aid,
				'url' => $url,
				'w'   => isset( $large[1] ) ? (int) $large[1] : 0,
				'h'   => isset( $large[2] ) ? (int) $large[2] : 0,
			];
		}

		if ( empty( $panels_data ) ) {
			return $content;
		}

		$saved_layers = get_post_meta( $post->ID, Panel_Meta::SUBMISSION_LAYERS_META_KEY, true );
		if ( ! is_array( $saved_layers ) ) {
			$saved_layers = [];
		}

		if ( ! wp_style_is( 'vce-frontend-panel', 'registered' ) ) {
			wp_register_style(
				'vce-frontend-panel',
				VCE_PLUGIN_URL . 'assets/css/frontend-panel.css',
				[],
				vce_asset_version( 'assets/css/frontend-panel.css' )
			);
		}
		if ( ! wp_style_is( 'vce-frontend-panel-editor', 'registered' ) ) {
			wp_register_style(
				'vce-frontend-panel-editor',
				VCE_PLUGIN_URL . 'assets/css/frontend-panel-editor.css',
				[ 'vce-frontend-panel' ],
				vce_asset_version( 'assets/css/frontend-panel-editor.css' )
			);
		}
		if ( ! wp_script_is( 'fabric', 'registered' ) ) {
			wp_register_script(
				'fabric',
				'https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js',
				[],
				'5.3.0',
				true
			);
		}
		if ( ! wp_script_is( 'vce-frontend-panel-submission', 'registered' ) ) {
			if ( ! wp_script_is( 'vce-frontend-panel-renderer', 'registered' ) ) {
				wp_register_script(
					'vce-frontend-panel-renderer',
					VCE_PLUGIN_URL . 'assets/js/frontend-panel-renderer.js',
					[ 'fabric' ],
					vce_asset_version( 'assets/js/frontend-panel-renderer.js' ),
					true
				);
			}
			wp_register_script(
				'vce-frontend-panel-submission',
				VCE_PLUGIN_URL . 'assets/js/frontend-panel-submission.js',
				[ 'vce-frontend-panel-renderer' ],
				vce_asset_version( 'assets/js/frontend-panel-submission.js' ),
				true
			);
		}

		wp_enqueue_style( 'vce-frontend-panel' );
		wp_enqueue_style( 'vce-frontend-panel-editor' );
		wp_enqueue_script( 'vce-frontend-panel-submission' );

		ob_start();
		Template::render(
			'frontend/card-panels-submission.php',
			[
				'panels_data'  => $panels_data,
				'saved_layers' => $saved_layers,
			]
		);
		return $content . (string) ob_get_clean();
	}
}
