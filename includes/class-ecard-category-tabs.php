<?php
/**
 * [vce_ecard_category_tabs] — category filter tabs for the e-cards archive.
 *
 * Branch feature summary:
 * - Renders purple tab buttons (replaces Elementor Taxonomy Filter).
 * - Links use ?vce_category={slug}; custom_e_cards query filters the card loop.
 * - exclude / exclude_ids hide categories from the tab list only.
 * - CSS loads in <head> + critical inline rules to avoid unstyled flash on refresh.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode: vce_ecard_category_tabs
 */
final class Ecard_Category_Tabs {

	private const SHORTCODE = 'vce_ecard_category_tabs';

	private const STYLE_HANDLE = 'vce-ecard-category-tabs';

	private static bool $styles_enqueued = false;

	public function register_hooks(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets_early' ], 20 );
	}

	/** Register tab stylesheet (enqueued when the shortcode is on the page). */
	public function register_assets(): void {
		wp_register_style(
			self::STYLE_HANDLE,
			VCE_PLUGIN_URL . 'assets/css/ecard-category-tabs.css',
			[],
			vce_asset_version( 'assets/css/ecard-category-tabs.css' )
		);
	}

	/**
	 * Load CSS in head when Elementor/post content contains the shortcode (prevents FOUC).
	 */
	public function maybe_enqueue_assets_early(): void {
		if ( is_admin() || ! $this->current_page_has_shortcode() ) {
			return;
		}

		$this->enqueue_styles();
	}

	/**
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render_shortcode( $atts ): string {
		if ( ! taxonomy_exists( Ecard_Category_Filter::TAXONOMY ) ) {
			return '';
		}

		$this->enqueue_styles();

		$atts  = $this->parse_atts( $atts );
		$terms = $this->get_terms_for_tabs( $atts );

		if ( empty( $terms ) ) {
			return '';
		}

		return $this->render_nav( $terms, $atts );
	}

	// --- Shortcode data ---

	/**
	 * @param array<string, string>|string $atts Raw shortcode attributes.
	 * @return array<string, string>
	 */
	private function parse_atts( $atts ): array {
		return shortcode_atts(
			[
				'show_all'    => 'yes',
				'all_label'   => __( 'All', VCE_TEXT_DOMAIN ),
				'parent'      => '',
				'ids'         => '',
				'exclude'     => '',
				'exclude_ids' => '',
				'hide_empty'  => 'no',
				'orderby'     => 'name',
				'order'       => 'ASC',
				'base_url'    => '',
			],
			$atts,
			self::SHORTCODE
		);
	}

	/**
	 * @param array<string, string> $atts Parsed attributes.
	 * @return \WP_Term[]
	 */
	private function get_terms_for_tabs( array $atts ): array {
		$hide_empty = $this->is_yes( $atts['hide_empty'] );

		$args = [
			'taxonomy'   => Ecard_Category_Filter::TAXONOMY,
			'hide_empty' => $hide_empty,
			'orderby'    => sanitize_key( $atts['orderby'] ),
			'order'      => 'DESC' === strtoupper( $atts['order'] ) ? 'DESC' : 'ASC',
		];

		if ( '' !== $atts['parent'] && ctype_digit( (string) $atts['parent'] ) ) {
			$args['parent'] = (int) $atts['parent'];
		}

		if ( '' !== $atts['ids'] ) {
			$include = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
			if ( ! empty( $include ) ) {
				$args['include'] = $include;
			}
		}

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		return $this->remove_excluded_terms( $terms, $atts['exclude'], $atts['exclude_ids'] );
	}

	/**
	 * @param \WP_Term[] $terms Terms from get_terms().
	 * @return \WP_Term[]
	 */
	private function remove_excluded_terms( array $terms, string $exclude, string $exclude_ids ): array {
		$exclude_slugs = Ecard_Category_Filter::resolve_exclude_slugs( $exclude, $exclude_ids );

		if ( empty( $exclude_slugs ) ) {
			return $terms;
		}

		return array_values(
			array_filter(
				$terms,
				static function ( $term ) use ( $exclude_slugs ) {
					return $term instanceof \WP_Term && ! in_array( $term->slug, $exclude_slugs, true );
				}
			)
		);
	}

	// --- Markup ---

	/**
	 * @param \WP_Term[]              $terms Category terms.
	 * @param array<string, string> $atts  Parsed attributes.
	 */
	private function render_nav( array $terms, array $atts ): string {
		$base_url = $this->resolve_base_url( $atts['base_url'] );
		$active   = Ecard_Category_Filter::get_active_value();

		ob_start();

		// Fallback if head enqueue ran too late (Elementor / cache).
		echo $this->critical_style_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<nav class="vce-ecard-tabs" aria-label="' . esc_attr__( 'E-card categories', VCE_TEXT_DOMAIN ) . '">';
		echo '<ul class="vce-ecard-tabs__list" role="tablist">';

		if ( $this->is_yes( $atts['show_all'] ) ) {
			echo $this->render_tab_link( $atts['all_label'], Ecard_Category_Filter::build_filter_url( $base_url, '' ), '' === $active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$url = Ecard_Category_Filter::build_filter_url( $base_url, $term->slug );
			echo $this->render_tab_link( $term->name, $url, Ecard_Category_Filter::is_term_active( $term, $active ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</ul></nav>';

		return (string) ob_get_clean();
	}

	/**
	 * @param string $label     Tab label.
	 * @param string $url       Tab href.
	 * @param bool   $is_active Whether this tab is selected.
	 */
	private function render_tab_link( string $label, string $url, bool $is_active ): string {
		return sprintf(
			'<li class="vce-ecard-tabs__item" role="presentation"><a class="vce-ecard-tabs__link%s" href="%s" role="tab"%s>%s</a></li>',
			$is_active ? ' is-active' : '',
			esc_url( $url ),
			$is_active ? ' aria-selected="true"' : ' aria-selected="false"',
			esc_html( $label )
		);
	}

	// --- Assets (FOUC prevention) ---

	private function enqueue_styles(): void {
		if ( self::$styles_enqueued ) {
			return;
		}

		wp_enqueue_style( self::STYLE_HANDLE );

		if ( wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			wp_add_inline_style( self::STYLE_HANDLE, $this->critical_css(), 'before' );
		}

		self::$styles_enqueued = true;
	}

	/**
	 * Minimal rules mirrored from assets/css/ecard-category-tabs.css (keep in sync).
	 */
	private function critical_css(): string {
		return '.vce-ecard-tabs{--vce-tab-bg:#7a88d4;--vce-tab-color:#fff;--vce-tab-active-bg:rgba(122,136,212,.52);--vce-tab-hover-bg:#8b97dc;--vce-tab-radius:5px;--vce-tab-font:"Comic Sans MS","Comic Sans","Chalkboard SE",cursive,sans-serif;--vce-tab-gap:8px;--vce-tab-padding-y:6px;--vce-tab-padding-x:12px;--vce-tab-font-size:15px;margin:0 0 1.25rem}.vce-ecard-tabs__list{display:flex;flex-wrap:wrap;justify-content:center;align-items:flex-start;gap:var(--vce-tab-gap);list-style:none!important;margin:0;padding:0}.vce-ecard-tabs__item{margin:0;padding:0;list-style:none!important}.vce-ecard-tabs__link{display:inline-block;box-sizing:border-box;padding:var(--vce-tab-padding-y) var(--vce-tab-padding-x);border:none;border-radius:var(--vce-tab-radius);background-color:var(--vce-tab-bg);color:var(--vce-tab-color)!important;font-family:var(--vce-tab-font);font-size:var(--vce-tab-font-size);font-weight:400;line-height:1.25;text-align:center;text-decoration:none!important;white-space:nowrap}.vce-ecard-tabs__link.is-active,.vce-ecard-tabs__link.is-active:focus,.vce-ecard-tabs__link.is-active:hover{background-color:var(--vce-tab-active-bg);color:var(--vce-tab-color)!important}';
	}

	private function critical_style_markup(): string {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;

		return '<style id="vce-ecard-tabs-critical">' . $this->critical_css() . '</style>';
	}

	// --- Utilities ---

	private function is_yes( string $value ): bool {
		return in_array( strtolower( $value ), [ '1', 'yes', 'true' ], true );
	}

	private function current_page_has_shortcode(): bool {
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return true;
		}

		$data = get_post_meta( $post->ID, '_elementor_data', true );

		return is_string( $data ) && false !== strpos( $data, self::SHORTCODE );
	}

	private function resolve_base_url( string $base_url_attr ): string {
		if ( '' !== $base_url_attr ) {
			return remove_query_arg( Ecard_Category_Filter::QUERY_VAR, esc_url_raw( $base_url_attr ) );
		}

		$page_id = get_queried_object_id();
		if ( $page_id > 0 ) {
			$permalink = get_permalink( $page_id );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return remove_query_arg( Ecard_Category_Filter::QUERY_VAR, $permalink );
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Same-page URL only.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

		return remove_query_arg( Ecard_Category_Filter::QUERY_VAR, home_url( $request_uri ) );
	}
}
