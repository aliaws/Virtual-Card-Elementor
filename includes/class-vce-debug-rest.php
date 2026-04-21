<?php
/**
 * REST: append browser diagnostic lines to VCE log (admins, VCE_DEBUG).
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/vce/v1/debug-client
 */
final class Vce_Debug_Rest {

	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'vce/v1',
			'/debug-client',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'append_lines' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);
	}

	public function permission(): bool {
		return Debug_Log::vce_debug_client_enabled();
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function append_lines( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'vce_invalid_nonce', __( 'Invalid nonce.', VCE_TEXT_DOMAIN ), [ 'status' => 403 ] );
		}

		$lines = $request->get_param( 'lines' );
		if ( ! is_array( $lines ) ) {
			$json = $request->get_json_params();
			if ( isset( $json['lines'] ) && is_array( $json['lines'] ) ) {
				$lines = $json['lines'];
			}
		}
		if ( ! is_array( $lines ) ) {
			return new WP_Error( 'vce_invalid_lines', __( 'Invalid payload.', VCE_TEXT_DOMAIN ), [ 'status' => 400 ] );
		}

		$lines   = array_slice( $lines, 0, 40 );
		$written = 0;
		foreach ( $lines as $line ) {
			if ( ! is_string( $line ) ) {
				continue;
			}
			$line = wp_strip_all_tags( $line );
			if ( '' === $line ) {
				continue;
			}
			Debug_Log::log_client( $line );
			++$written;
		}

		return new WP_REST_Response( [ 'ok' => true, 'accepted' => $written ], 200 );
	}
}
