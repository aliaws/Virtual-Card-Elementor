<?php
/**
 * Plugin Name: Virtual Card Elementor
 * Description: Registers the Virtual Card custom post type, admin Card Panels (images per card), and an Elementor widget that outputs those panels for the current post in the loop.
 * Version: 1.0.9
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

require_once VCE_PLUGIN_DIR . 'includes/class-panel-meta.php';
require_once VCE_PLUGIN_DIR . 'includes/class-template.php';
require_once VCE_PLUGIN_DIR . 'includes/class-post-type.php';
require_once VCE_PLUGIN_DIR . 'admin/class-panel-meta-box.php';
require_once VCE_PLUGIN_DIR . 'includes/class-plugin.php';

Virtual_Card_Elementor\Plugin::instance()->run();
