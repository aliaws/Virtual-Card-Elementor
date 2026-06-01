<?php
/**
 * UM and E-Cards frontend hooks.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles logout redirect, Elementor e-card queries, and display-order sorting.
 */
class Um_Hooks {

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

	/**
	 * Wire hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_logout', [ $this, 'redirect_to_login' ] );
		add_action( 'elementor/query/custom_e_cards', [ $this, 'handle_ecards_filtering' ] );
		add_filter( 'posts_clauses', [ $this, 'posts_clauses_display_order' ], 10, 2 );

		$elementor_widgets = [ 'posts', 'archive-posts', 'loop-grid', 'loop-carousel', 'portfolio' ];
		foreach ( $elementor_widgets as $widget ) {
			add_action(
				"elementor/element/{$widget}/section_query/after_section_end",
				[ $this, 'add_elementor_display_order_option' ],
				10,
				2
			);
		}
	}

	/**
	 * Redirect to UM login page after logout.
	 */
	public function redirect_to_login(): void {
		wp_redirect( home_url( '/login/' ) );
		exit;
	}

	/**
	 * Add "Display order" to Elementor query Order By dropdowns (Posts, Loop Grid, etc.).
	 *
	 * @param \Elementor\Controls_Stack $element Widget instance.
	 * @param array                     $args    Section args.
	 */
	public function add_elementor_display_order_option( $element, array $args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! method_exists( $element, 'get_controls' ) || ! method_exists( $element, 'update_control' ) ) {
			return;
		}

		$label  = __( 'Display order', VCE_TEXT_DOMAIN );
		$option = [ Panel_Meta::ORDERBY_DISPLAY_ORDER => $label ];

		foreach ( $element->get_controls() as $control_id => $control ) {
			if ( ! empty( $control['options'] ) && is_array( $control['options'] ) && $this->is_orderby_control( $control['options'] ) ) {
				if ( isset( $control['options'][ Panel_Meta::ORDERBY_DISPLAY_ORDER ] ) ) {
					return;
				}
				$element->update_control(
					$control_id,
					[
						'options' => array_merge( $control['options'], $option ),
					]
				);
				return;
			}

			if ( empty( $control['fields'] ) || ! is_array( $control['fields'] ) ) {
				continue;
			}

			$fields_changed = false;
			$fields         = $control['fields'];

			foreach ( $fields as $field_id => $field ) {
				if ( empty( $field['options'] ) || ! is_array( $field['options'] ) || ! $this->is_orderby_control( $field['options'] ) ) {
					continue;
				}
				if ( isset( $field['options'][ Panel_Meta::ORDERBY_DISPLAY_ORDER ] ) ) {
					return;
				}
				$fields[ $field_id ]['options'] = array_merge( $field['options'], $option );
				$fields_changed                 = true;
				break;
			}

			if ( $fields_changed ) {
				$element->update_control( $control_id, [ 'fields' => $fields ] );
				return;
			}
		}
	}

	/**
	 * Whether a select options array looks like Elementor's Order By control.
	 *
	 * @param array<string, string> $options Control options.
	 */
	private function is_orderby_control( array $options ): bool {
		return isset( $options['post_date'] )
			|| isset( $options['date'] )
			|| isset( $options['title'] )
			|| isset( $options['post_title'] )
			|| isset( $options['menu_order'] )
			|| isset( $options['rand'] );
	}

	/**
	 * Handle frontend filtering and sorting for E-Cards.
	 *
	 * @param \WP_Query|\ElementorPro\Modules\QueryControl\Classes\Elementor_Query $query Query instance.
	 */
	public function handle_ecards_filtering( $query ): void {
		if ( is_admin() ) {
			return;
		}

		foreach ( $_REQUEST as $key => $value ) {
			if ( strpos( $key, 'e-filter' ) !== false ) {
				$query->set(
					'tax_query',
					[
						[
							'taxonomy' => 'virtual_card_category',
							'field'    => 'slug',
							'terms'    => sanitize_text_field( wp_unslash( $value ) ),
						],
					]
				);
			}
		}

		$url_orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : '';
		$url_order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : '';

		$query_orderby = $query->get( 'orderby' );
		if ( is_array( $query_orderby ) ) {
			$query_orderby = '';
		} else {
			$query_orderby = (string) $query_orderby;
		}

		$direction = $this->normalize_sort_direction(
			$url_order ? $url_order : (string) $query->get( 'order' )
		);

		$use_display_order = false;

		if ( $url_orderby ) {
			if ( Panel_Meta::ORDERBY_DISPLAY_ORDER === $url_orderby ) {
				$use_display_order = true;
			} else {
				$query->set( 'orderby', $url_orderby );
				$query->set( 'order', $direction );
			}
		} elseif ( Panel_Meta::ORDERBY_DISPLAY_ORDER === $query_orderby ) {
			$use_display_order = true;
		}

		if ( $use_display_order ) {
			$this->apply_display_order_sort( $query, $direction );
		}
	}

	/**
	 * Sort by display-order meta: numbered cards first (asc/desc), unassigned cards last by date.
	 *
	 * @param \WP_Query|\ElementorPro\Modules\QueryControl\Classes\Elementor_Query $query     Query instance.
	 * @param string                                                               $direction ASC or DESC.
	 */
	private function apply_display_order_sort( $query, string $direction ): void {
		$query->set( 'vce_display_order_sort', true );
		$query->set( 'vce_display_order_dir', $this->normalize_sort_direction( $direction ) );
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}

	/**
	 * @param string $order Sort direction from widget or URL.
	 */
	private function normalize_sort_direction( string $order ): string {
		return 'DESC' === strtoupper( $order ) ? 'DESC' : 'ASC';
	}

	/**
	 * Custom ORDER BY for display-order meta (LEFT JOIN so cards without meta still appear).
	 *
	 * @param string[]  $clauses Query clauses.
	 * @param \WP_Query $query   Query instance.
	 * @return string[]
	 */
	public function posts_clauses_display_order( array $clauses, \WP_Query $query ): array {
		if ( ! $query->get( 'vce_display_order_sort' ) ) {
			return $clauses;
		}

		if ( strpos( $clauses['join'], 'vce_ord_pm' ) !== false ) {
			return $clauses;
		}

		global $wpdb;

		$direction = 'DESC' === $query->get( 'vce_display_order_dir' ) ? 'DESC' : 'ASC';

		$clauses['join'] .= $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS vce_ord_pm ON ({$wpdb->posts}.ID = vce_ord_pm.post_id AND vce_ord_pm.meta_key = %s) ",
			Panel_Meta::ORDER_META_KEY
		);

		$clauses['orderby'] = "(CASE WHEN vce_ord_pm.meta_id IS NULL OR vce_ord_pm.meta_value = '' OR vce_ord_pm.meta_value = '0' THEN 1 ELSE 0 END) ASC, CAST(vce_ord_pm.meta_value AS UNSIGNED) {$direction}, {$wpdb->posts}.post_date DESC";

		return $clauses;
	}
}
