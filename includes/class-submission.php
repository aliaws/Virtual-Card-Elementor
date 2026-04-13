<?php
/**
 * Card submission CPT: saved designs per virtual_card (template unchanged).
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers vce_submission and helpers.
 */
class Submission {

	public const POST_TYPE = 'vce_submission';

	public const META_CARD_ID = '_vce_card_id';

	public const META_UUID = '_vce_submission_uuid';

	public const META_DESIGN = '_vce_design_json';

	/**
	 * Post meta value: this prefix + base64(utf8 JSON). Avoids broken JSON after WordPress addslashes/stripslashes on meta.
	 */
	public const DESIGN_META_B64_PREFIX = 'vceb64:';

	/**
	 * Hook into WordPress.
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_filter( 'map_meta_cap', [ $this, 'map_meta_cap' ], 10, 4 );
	}

	/**
	 * Let any logged-in user with "read" create submissions; authors may edit their own.
	 *
	 * @param string[] $caps    Primitive caps.
	 * @param string   $cap     Requested cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Extra args (post ID for edit_post).
	 * @return string[]
	 */
	public function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		$pto = get_post_type_object( self::POST_TYPE );
		if ( ! $pto ) {
			return $caps;
		}
		$edit_posts_cap = $pto->cap->edit_posts;
		if ( $cap === $edit_posts_cap && empty( $args ) ) {
			if ( $user_id > 0 && user_can( $user_id, 'read' ) ) {
				return [ 'read' ];
			}
			return $caps;
		}
		if ( ( $cap === $pto->cap->edit_post || 'edit_post' === $cap ) && ! empty( $args[0] ) ) {
			$post = get_post( (int) $args[0] );
			if ( $post && $post->post_type === self::POST_TYPE ) {
				if ( (int) $post->post_author === $user_id ) {
					return [ 'read' ];
				}
				if ( user_can( $user_id, 'edit_others_posts' ) ) {
					return [ 'edit_others_posts' ];
				}
			}
		}
		return $caps;
	}

	/**
	 * Register submission CPT.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'              => [
					'name'          => __( 'Card submissions', VCE_TEXT_DOMAIN ),
					'singular_name' => __( 'Card submission', VCE_TEXT_DOMAIN ),
					'add_new_item'  => __( 'Add New Submission', VCE_TEXT_DOMAIN ),
					'edit_item'     => __( 'Edit Submission', VCE_TEXT_DOMAIN ),
					'view_item'     => __( 'View Submission', VCE_TEXT_DOMAIN ),
					'search_items'  => __( 'Search Submissions', VCE_TEXT_DOMAIN ),
					'not_found'     => __( 'No submissions found.', VCE_TEXT_DOMAIN ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . Post_Type::POST_TYPE,
				'query_var'           => false,
				'rewrite'             => false,
				'capability_type'     => [ 'vce_submission', 'vce_submissions' ],
				'map_meta_cap'        => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => [ 'title' ],
				'show_in_rest'        => false,
			]
		);

		self::maybe_grant_submission_caps_to_admins();
	}

	/**
	 * Grant CPT caps to administrator/editor so the menu and list table work.
	 */
	private static function maybe_grant_submission_caps_to_admins(): void {
		if ( get_option( 'vce_submission_caps_granted', false ) ) {
			return;
		}
		$pto = get_post_type_object( self::POST_TYPE );
		if ( ! $pto || empty( $pto->cap ) ) {
			return;
		}
		$cap_list = [];
		foreach ( (array) $pto->cap as $c ) {
			if ( is_string( $c ) && '' !== $c ) {
				$cap_list[] = $c;
			}
		}
		$cap_list = array_unique( $cap_list );
		$roles    = [ 'administrator', 'editor' ];
		if ( get_role( 'shop_manager' ) ) {
			$roles[] = 'shop_manager';
		}
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $cap_list as $c ) {
				$role->add_cap( $c );
			}
		}
		update_option( 'vce_submission_caps_granted', true, true );
	}

	/**
	 * Generate a unique UUID for public preview URLs.
	 */
	public static function generate_uuid(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0x0fff ) | 0x4000,
			wp_rand( 0, 0x3fff ) | 0x8000,
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff ),
			wp_rand( 0, 0xffff )
		);
	}

	/**
	 * Find submission post by public UUID.
	 */
	public static function find_by_uuid( string $uuid ): ?\WP_Post {
		$uuid = sanitize_text_field( $uuid );
		if ( '' === $uuid || strlen( $uuid ) > 64 ) {
			return null;
		}
		$q = new \WP_Query(
			[
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => self::META_UUID,
				'meta_value'     => $uuid,
				'no_found_rows'  => true,
			]
		);
		if ( ! $q->have_posts() ) {
			return null;
		}
		return $q->posts[0];
	}

	/**
	 * Build panel payloads (urls, dimensions) for a virtual_card — same shape as the Elementor widget.
	 *
	 * @param int $card_id virtual_card post ID.
	 * @return array<int, array{id:int,url:string,w:int,h:int,thumb:string}>
	 */
	public static function get_panels_data_for_card( int $card_id ): array {
		$ids = get_post_meta( $card_id, Panel_Meta::META_KEY, true );
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return [];
		}
		$panels_data = [];
		foreach ( $ids as $aid ) {
			$aid   = (int) $aid;
			$large = $aid ? wp_get_attachment_image_src( $aid, 'large' ) : null;
			$thumb = $aid ? wp_get_attachment_image_src( $aid, 'thumbnail' ) : null;
			$url   = ( $large && ! empty( $large[0] ) ) ? $large[0] : '';
			if ( '' === $url && $aid ) {
				$url = wp_get_attachment_url( $aid ) ?: '';
			}
			$panels_data[] = [
				'id'    => $aid,
				'url'   => $url,
				'w'     => isset( $large[1] ) ? (int) $large[1] : 0,
				'h'     => isset( $large[2] ) ? (int) $large[2] : 0,
				'thumb' => ( $thumb && ! empty( $thumb[0] ) ) ? $thumb[0] : $url,
			];
		}
		return $panels_data;
	}

	/**
	 * Decode stored design JSON into layers map for JS (panels keyed by string index).
	 *
	 * @return array<string, array{objects: array<int, mixed>}>
	 */
	public static function get_layers_map_from_meta( int $submission_id ): array {
		$raw = get_post_meta( $submission_id, self::META_DESIGN, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}

		$raw = trim( str_replace( "\xEF\xBB\xBF", '', $raw ) );

		$json_flags = 0;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$json_flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}

		$data = self::decode_design_meta_payload( $raw, $json_flags );
		if ( ! is_array( $data ) ) {
			if ( Debug_Log::enabled() ) {
				$plen   = strlen( self::DESIGN_META_B64_PREFIX );
				$is_b64 = strlen( $raw ) > $plen && substr( $raw, 0, $plen ) === self::DESIGN_META_B64_PREFIX;
				$hint   = $is_b64
					? ( 'format=b64 stored_len=' . strlen( $raw ) . ' b64_err=1' )
					: ( 'format=legacy sample=' . substr( preg_replace( '/\s+/', ' ', $raw ), 0, 200 ) );
				Debug_Log::log(
					'get_layers_map decode_fail post_id=' . $submission_id
					. ' err=' . json_last_error_msg()
					. ' ' . $hint
				);
			}
			return [];
		}

		$panels_src = null;
		if ( isset( $data['panels'] ) && is_array( $data['panels'] ) ) {
			$panels_src = $data['panels'];
		} elseif ( self::is_sequential_int_keyed_array( $data ) ) {
			$first = $data[0] ?? null;
			if ( is_array( $first ) && array_key_exists( 'objects', $first ) ) {
				$panels_src = $data;
			}
		}

		if ( null === $panels_src ) {
			if ( Debug_Log::enabled() && strlen( $raw ) > 10 ) {
				Debug_Log::log(
					'get_layers_map no_panels post_id=' . $submission_id
					. ' top_keys=' . implode( ',', array_keys( $data ) )
				);
			}
			return [];
		}

		$out = [];
		foreach ( $panels_src as $k => $panel ) {
			if ( ! is_array( $panel ) || ! array_key_exists( 'objects', $panel ) ) {
				continue;
			}
			$objects = $panel['objects'];
			if ( is_array( $objects ) ) {
				$out[ (string) $k ] = [ 'objects' => $objects ];
			}
		}

		if ( Debug_Log::enabled() && [] !== $panels_src && [] === $out ) {
			$first = reset( $panels_src );
			$ot    = ( is_array( $first ) && array_key_exists( 'objects', $first ) ) ? gettype( $first['objects'] ) : 'no_objects_key';
			Debug_Log::log(
				'get_layers_map panels_skipped post_id=' . $submission_id
				. ' panel_count=' . count( $panels_src )
				. ' first_keys=' . ( is_array( $first ) ? implode( ',', array_keys( $first ) ) : gettype( $first ) )
				. ' first_objects_type=' . $ot
			);
		}

		return $out;
	}

	/**
	 * Wrap verified JSON for post meta storage (slash-safe).
	 */
	public static function pack_design_meta_value( string $json ): string {
		return self::DESIGN_META_B64_PREFIX . base64_encode( $json );
	}

	/**
	 * Parse meta string (base64-wrapped or legacy raw JSON).
	 *
	 * @param int $json_flags Flags for json_decode.
	 * @return array<string, mixed>|null
	 */
	private static function decode_design_meta_payload( string $raw, int $json_flags ): ?array {
		$prefix = self::DESIGN_META_B64_PREFIX;
		$plen   = strlen( $prefix );
		if ( strlen( $raw ) > $plen && substr( $raw, 0, $plen ) === $prefix ) {
			$bin = base64_decode( substr( $raw, $plen ), true );
			if ( false === $bin || '' === $bin ) {
				return null;
			}
			$depth = 2048;
			$data  = json_decode( $bin, true, $depth, $json_flags );
			if ( is_array( $data ) && JSON_ERROR_NONE === json_last_error() ) {
				return $data;
			}
			return self::json_decode_design_meta( $bin, $json_flags );
		}

		return self::json_decode_design_meta( $raw, $json_flags );
	}

	/**
	 * Decode stored design JSON (tolerate minor mangling from DB / plugins).
	 *
	 * @param int    $json_flags Flags for json_decode (e.g. JSON_INVALID_UTF8_SUBSTITUTE).
	 * @return array<string, mixed>|null
	 */
	private static function json_decode_design_meta( string $raw, int $json_flags ): ?array {
		$depth = 2048;
		$data  = json_decode( $raw, true, $depth, $json_flags );
		if ( is_array( $data ) ) {
			return $data;
		}

		$try = json_decode( stripslashes( $raw ), true, $depth, $json_flags );
		if ( is_array( $try ) ) {
			return $try;
		}

		$decoded = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( $decoded !== $raw ) {
			$try = json_decode( $decoded, true, $depth, $json_flags );
			if ( is_array( $try ) ) {
				return $try;
			}
		}

		if ( function_exists( 'iconv' ) ) {
			$scrubbed = @iconv( 'UTF-8', 'UTF-8//IGNORE', $raw );
			if ( is_string( $scrubbed ) && '' !== $scrubbed && $scrubbed !== $raw ) {
				$try = json_decode( $scrubbed, true, $depth, $json_flags );
				if ( is_array( $try ) ) {
					return $try;
				}
			}
		}

		return null;
	}

	/**
	 * Whether $a is a list (keys 0..n-1). PHP 7.4-compatible (no array_is_list).
	 *
	 * @param array<mixed> $a Array.
	 */
	private static function is_sequential_int_keyed_array( array $a ): bool {
		$i = 0;
		foreach ( array_keys( $a ) as $k ) {
			if ( $k !== $i ) {
				return false;
			}
			++$i;
		}
		return true;
	}

	/**
	 * Validate and normalize design payload from REST.
	 *
	 * @param mixed $layers Request body `layers` value.
	 * @return array{v:int, panels: array<string, array{objects: array}>}|null
	 */
	public static function sanitize_design_payload( $layers ): ?array {
		if ( is_object( $layers ) ) {
			$layers = json_decode( wp_json_encode( $layers ), true );
		}
		if ( ! is_array( $layers ) ) {
			return null;
		}
		$v       = isset( $layers['v'] ) ? (int) $layers['v'] : 2;
		$panels  = isset( $layers['panels'] ) && is_array( $layers['panels'] ) ? $layers['panels'] : null;
		if ( null === $panels ) {
			return null;
		}
		$clean = [];
		foreach ( $panels as $key => $panel ) {
			if ( ! is_array( $panel ) ) {
				continue;
			}
			$objects = isset( $panel['objects'] ) && is_array( $panel['objects'] ) ? $panel['objects'] : [];
			/* Cap panels and object count for safety. */
			if ( count( $clean ) >= 50 ) {
				break;
			}
			$objects = array_slice( $objects, 0, 500 );
			$clean[ (string) (int) $key ] = [ 'objects' => $objects ];
		}
		$out = [
			'v'      => $v,
			'panels' => $clean,
		];

		return self::sanitize_tree_for_json_storage( $out );
	}

	/**
	 * Recursively normalize values so json_encode survives DB utf8/utf8mb4 round-trips (Fabric text, paths, etc.).
	 *
	 * @param mixed $data Design fragment.
	 * @return mixed
	 */
	private static function sanitize_tree_for_json_storage( $data ) {
		if ( is_string( $data ) ) {
			$s = wp_check_invalid_utf8( $data, true );
			return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s );
		}
		if ( is_float( $data ) ) {
			if ( is_nan( $data ) || is_infinite( $data ) ) {
				return null;
			}
			return $data;
		}
		if ( is_int( $data ) || is_bool( $data ) || null === $data ) {
			return $data;
		}
		if ( is_array( $data ) ) {
			$out = [];
			foreach ( $data as $k => $v ) {
				$nk       = is_string( $k ) ? wp_check_invalid_utf8( $k, true ) : $k;
				$out[ $nk ] = self::sanitize_tree_for_json_storage( $v );
			}
			return $out;
		}
		if ( is_object( $data ) ) {
			return null;
		}
		return $data;
	}

	/**
	 * Encode design for post meta: UTF-8 safe, verified JSON round-trip.
	 *
	 * @param array<string, mixed> $design Sanitized design.
	 * @return string|null JSON string or null on failure.
	 */
	public static function encode_design_json( array $design ): ?string {
		$design = self::sanitize_tree_for_json_storage( $design );
		if ( ! is_array( $design ) ) {
			return null;
		}

		$flags  = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		$depth  = 2048;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$flags |= JSON_INVALID_UTF8_SUBSTITUTE;
		}

		if ( defined( 'JSON_THROW_ON_ERROR' ) ) {
			try {
				$json = json_encode( $design, $flags | JSON_THROW_ON_ERROR, $depth );
			} catch ( \Throwable $e ) {
				return null;
			}
		} else {
			$json = json_encode( $design, $flags, $depth );
			if ( false === $json ) {
				return null;
			}
		}

		$jf = 0;
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			$jf |= JSON_INVALID_UTF8_SUBSTITUTE;
		}
		$verify = json_decode( $json, true, $depth, $jf );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $verify ) ) {
			return null;
		}

		return $json;
	}
}

