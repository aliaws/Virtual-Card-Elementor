<?php
/**
 * Public full-page preview: /card-submission/{uuid}/
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite + template for submission preview.
 */
class Submission_Preview {

	/**
	 * Query var for UUID.
	 */
	public const QUERY_VAR = 'vce_submission_uuid';

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'add_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_filter( 'template_include', [ $this, 'template_include' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ], 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'dequeue_third_party_on_submission_preview' ], 1000 );
		add_action( 'wp_print_footer_scripts', [ $this, 'dequeue_third_party_on_submission_preview' ], 1 );
		add_action(
			'template_redirect',
			static function (): void {
				if ( get_query_var( Submission_Preview::QUERY_VAR ) ) {
					show_admin_bar( false );
				}
			},
			0
		);
	}

	/**
	 * Rewrite rule.
	 */
	public function add_rewrite(): void {
		add_rewrite_rule(
			'^card-submission/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Load plugin template when query var is set.
	 *
	 * @param string $template Default template.
	 * @return string
	 */
	public function template_include( string $template ): string {
		$uuid = get_query_var( self::QUERY_VAR );
		if ( ! is_string( $uuid ) || '' === $uuid ) {
			return $template;
		}
		$file = VCE_PLUGIN_DIR . 'templates/submission-preview.php';
		return is_readable( $file ) ? $file : $template;
	}

	/**
	 * Strip Elementor (and similar) frontend scripts from the bare preview template — they expect inline config and throw ReferenceError.
	 */
	public function dequeue_third_party_on_submission_preview(): void {
		$uuid = get_query_var( self::QUERY_VAR );
		if ( ! is_string( $uuid ) || '' === $uuid ) {
			return;
		}

		global $wp_scripts;
		if ( $wp_scripts instanceof \WP_Scripts ) {
			foreach ( (array) $wp_scripts->queue as $handle ) {
				if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
					continue;
				}
				$src = (string) $wp_scripts->registered[ $handle ]->src;
				if ( $src !== '' && false !== strpos( $src, 'elementor/assets/js' ) ) {
					wp_dequeue_script( $handle );
				}
			}
		}
	}

	/**
	 * Enqueue assets only on preview route.
	 */
	public function maybe_enqueue(): void {
		$uuid = get_query_var( self::QUERY_VAR );
		if ( ! is_string( $uuid ) || '' === $uuid ) {
			return;
		}

		$post = Submission::find_by_uuid( $uuid );
		if ( ! $post ) {
			if ( Debug_Log::enabled() ) {
				Debug_Log::log( 'preview_enqueue skip=no_submission_post uuid_len=' . strlen( $uuid ) );
			}
			return;
		}

		$card_id = (int) get_post_meta( $post->ID, Submission::META_CARD_ID, true );
		if ( $card_id <= 0 ) {
			if ( Debug_Log::enabled() ) {
				Debug_Log::log( 'preview_enqueue skip=no_card_id submission_post_id=' . $post->ID );
			}
			return;
		}

		$panels = Submission::get_panels_data_for_card( $card_id );
		$layers = Submission::get_layers_map_from_meta( $post->ID );

		if ( Debug_Log::enabled() ) {
			$raw_meta = (string) get_post_meta( $post->ID, Submission::META_DESIGN, true );
			$obj_total = 0;
			foreach ( $layers as $entry ) {
				if ( isset( $entry['objects'] ) && is_array( $entry['objects'] ) ) {
					$obj_total += count( $entry['objects'] );
				}
			}
			Debug_Log::log(
				sprintf(
					'submission_preview_enqueue post_id=%d card_id=%d uuid=%s panel_count=%d layer_map_keys=%s total_layer_objects=%d raw_meta_bytes=%d',
					$post->ID,
					$card_id,
					$uuid,
					count( $panels ),
					implode( ',', array_keys( $layers ) ),
					$obj_total,
					strlen( $raw_meta )
				)
			);
		}

		wp_register_style(
			'vce-submission-preview',
			VCE_PLUGIN_URL . 'assets/css/submission-preview.css',
			[],
			VCE_VERSION
		);
		wp_enqueue_style( 'vce-submission-preview' );

		wp_register_script(
			'fabric',
			'https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js',
			[],
			'5.3.0',
			true
		);

		$spv_deps = [ 'fabric' ];
		if ( Debug_Log::register_debug_client_assets() ) {
			$spv_deps[] = 'vce-debug-client';
		}

		wp_register_script(
			'vce-submission-preview',
			VCE_PLUGIN_URL . 'assets/js/submission-preview.js',
			$spv_deps,
			VCE_VERSION,
			true
		);

		wp_enqueue_script( 'vce-submission-preview' );

		$localize = [
				'panels' => $panels,
				'layers' => $layers,
				'i18n'   => [
					'loading'      => __( 'Loading…', VCE_TEXT_DOMAIN ),
					'panelPreview' => __( 'Panel preview', VCE_TEXT_DOMAIN ),
					'prevPanel'    => __( 'Previous panel', VCE_TEXT_DOMAIN ),
					'nextPanel'    => __( 'Next panel', VCE_TEXT_DOMAIN ),
					'close'        => __( 'Close', VCE_TEXT_DOMAIN ),
					'error'        => __( 'Unable to load preview.', VCE_TEXT_DOMAIN ),
				],
		];
		if ( Debug_Log::vce_debug_client_enabled() ) {
			$localize['vceDiag'] = [
				'panels'     => count( $panels ),
				'layerKeys'  => array_keys( $layers ),
				'objects'    => array_sum(
					array_map(
						static function ( $entry ): int {
							return isset( $entry['objects'] ) && is_array( $entry['objects'] ) ? count( $entry['objects'] ) : 0;
						},
						$layers
					)
				),
				'rawMetaLen' => strlen( (string) get_post_meta( $post->ID, Submission::META_DESIGN, true ) ),
			];
		}

		wp_localize_script( 'vce-submission-preview', 'vceSubmissionPreview', $localize );
	}
}
