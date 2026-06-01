<?php
/**
 * E-card category URL filtering for Elementor loops and category tabs.
 *
 * Branch feature: custom_e_cards query + [vce_ecard_category_tabs] share this class.
 *
 * - Reads ?vce_category={slug} from tab shortcode links (primary).
 * - Still supports Elementor ?e-filter-*-virtual_card_category=… URLs.
 * - Builds tax_query on virtual_card_category without changing other query logic.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses request params into WP_Query tax_query clauses.
 */
final class Ecard_Category_Filter {

	/** Query arg used by [vce_ecard_category_tabs] links. */
	public const QUERY_VAR = 'vce_category';

	public const TAXONOMY = 'virtual_card_category';

	/**
	 * Active filter slug from ?vce_category= (empty = "All").
	 */
	public static function get_active_value(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public filter URLs.
		if ( ! isset( $_REQUEST[ self::QUERY_VAR ] ) ) {
			return '';
		}

		$value = wp_unslash( $_REQUEST[ self::QUERY_VAR ] );
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return sanitize_title( sanitize_text_field( (string) $value ) );
	}

	/**
	 * Apply category filter from the current request (called from custom_e_cards hook).
	 *
	 * @param \WP_Query|\ElementorPro\Modules\QueryControl\Classes\Elementor_Query $query Query instance.
	 */
	public static function apply_to_query( $query ): void {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return;
		}

		$parsed   = self::parse_request_filters();
		$term_ids = self::filter_valid_term_ids( $parsed['term_ids'] );
		$slugs    = array_values( array_unique( $parsed['slugs'] ) );

		$clauses = [];

		if ( ! empty( $term_ids ) ) {
			$clauses[] = self::make_tax_clause( 'term_id', $term_ids );
		}

		if ( ! empty( $slugs ) ) {
			$clauses[] = self::make_tax_clause( 'slug', $slugs );
		}

		if ( empty( $clauses ) ) {
			return;
		}

		$tax_query = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = [];
		}

		$tax_query[] = count( $clauses ) > 1
			? array_merge( [ 'relation' => 'AND' ], $clauses )
			: $clauses[0];

		if ( count( $tax_query ) > 1 && ! isset( $tax_query['relation'] ) ) {
			$tax_query = array_merge( [ 'relation' => 'AND' ], $tax_query );
		}

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Tab link: ?vce_category={slug} or base URL only for "All".
	 *
	 * @param string $base_url Page URL without vce_category.
	 * @param string $slug     Term slug; empty clears the filter.
	 */
	public static function build_filter_url( string $base_url, string $slug = '' ): string {
		$base_url = remove_query_arg( self::QUERY_VAR, $base_url );
		$slug     = sanitize_title( $slug );

		if ( '' === $slug ) {
			return $base_url;
		}

		return add_query_arg( self::QUERY_VAR, $slug, $base_url );
	}

	/**
	 * @param \WP_Term $term   Category term.
	 * @param string   $active Sanitized slug from get_active_value().
	 */
	public static function is_term_active( \WP_Term $term, string $active ): bool {
		if ( '' === $active ) {
			return false;
		}

		$active = sanitize_title( $active );

		// Legacy URLs that still pass a numeric ID.
		if ( ctype_digit( $active ) ) {
			return (int) $active === (int) $term->term_id;
		}

		return $term->slug === $active;
	}

	/**
	 * Build exclude list for the tabs shortcode (slugs and/or term IDs → slugs).
	 *
	 * @param string $exclude     Comma-separated slugs or IDs.
	 * @param string $exclude_ids Comma-separated term IDs (optional).
	 * @return string[] Term slugs to hide from the tab list.
	 */
	public static function resolve_exclude_slugs( string $exclude, string $exclude_ids = '' ): array {
		return self::parts_to_slugs(
			array_merge(
				self::comma_list_to_parts( $exclude ),
				self::comma_list_to_parts( $exclude_ids )
			)
		);
	}

	// --- Request parsing ---

	/**
	 * @return array{term_ids: int[], slugs: string[]}
	 */
	private static function parse_request_filters(): array {
		$efilter = self::parse_raw_strings( self::collect_efilter_raw_values() );

		return [
			'term_ids' => $efilter['term_ids'],
			'slugs'    => array_merge( self::parse_vce_category_param(), $efilter['slugs'] ),
		];
	}

	/**
	 * vce_category values are always resolved to term slug(s) for the query.
	 *
	 * @return string[]
	 */
	private static function parse_vce_category_param(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public filter URLs.
		if ( ! isset( $_REQUEST[ self::QUERY_VAR ] ) ) {
			return [];
		}

		$value = wp_unslash( $_REQUEST[ self::QUERY_VAR ] );
		$items = is_array( $value ) ? $value : [ $value ];
		$parts = [];

		foreach ( $items as $item ) {
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item ) {
				$parts = array_merge( $parts, self::comma_list_to_parts( $item ) );
			}
		}

		return self::parts_to_slugs( $parts );
	}

	/**
	 * @return string[]
	 */
	private static function collect_efilter_raw_values(): array {
		$raw = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Elementor e-filter URLs.
		foreach ( $_REQUEST as $key => $value ) {
			if ( false === strpos( (string) $key, 'e-filter' ) ) {
				continue;
			}
			if ( false === stripos( (string) $key, self::TAXONOMY ) ) {
				continue;
			}

			foreach ( (array) $value as $item ) {
				$item = sanitize_text_field( wp_unslash( (string) $item ) );
				if ( '' !== $item ) {
					$raw[] = $item;
				}
			}
		}

		return $raw;
	}

	/**
	 * Elementor e-filter: numeric parts = term IDs, otherwise slugs.
	 *
	 * @param string[] $raw_strings Raw request values.
	 * @return array{term_ids: int[], slugs: string[]}
	 */
	private static function parse_raw_strings( array $raw_strings ): array {
		$term_ids = [];
		$slugs    = [];

		foreach ( $raw_strings as $raw ) {
			$parts = self::comma_list_to_parts( $raw );
			if ( empty( $parts ) ) {
				continue;
			}

			if ( self::parts_are_all_digits( $parts ) ) {
				foreach ( array_map( 'intval', $parts ) as $id ) {
					if ( $id > 0 ) {
						$term_ids[] = $id;
					}
				}
			} else {
				$slugs = array_merge( $slugs, self::parts_to_slugs( $parts ) );
			}
		}

		return [
			'term_ids' => $term_ids,
			'slugs'    => $slugs,
		];
	}

	// --- Helpers ---

	/**
	 * @param string|int[] $terms Field value(s).
	 * @return array<string, mixed>
	 */
	private static function make_tax_clause( string $field, $terms ): array {
		return [
			'taxonomy'         => self::TAXONOMY,
			'field'            => $field,
			'terms'            => $terms,
			'operator'         => 'IN',
			'include_children' => true,
		];
	}

	/**
	 * @param string[] $parts Slugs or numeric IDs as strings.
	 * @return string[]
	 */
	private static function parts_to_slugs( array $parts ): array {
		$slugs = [];

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}

			if ( ctype_digit( $part ) ) {
				$term = get_term( (int) $part, self::TAXONOMY );
				if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
					$slugs[] = $term->slug;
				}
			} else {
				$slug = sanitize_title( $part );
				if ( '' !== $slug ) {
					$slugs[] = $slug;
				}
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * @param string $list Comma-separated values.
	 * @return string[]
	 */
	private static function comma_list_to_parts( string $list ): array {
		if ( '' === $list ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map( 'trim', explode( ',', $list ) ),
				static function ( string $part ): bool {
					return '' !== $part;
				}
			)
		);
	}

	/**
	 * @param string[] $parts URL segments.
	 */
	private static function parts_are_all_digits( array $parts ): bool {
		foreach ( $parts as $part ) {
			if ( ! ctype_digit( $part ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param int[] $term_ids Candidate term IDs.
	 * @return int[]
	 */
	private static function filter_valid_term_ids( array $term_ids ): array {
		$valid = [];

		foreach ( array_unique( array_map( 'intval', $term_ids ) ) as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$term = get_term( $id, self::TAXONOMY );
			if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
				$valid[] = $id;
			}
		}

		return $valid;
	}
}
