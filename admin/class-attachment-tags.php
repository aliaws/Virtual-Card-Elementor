<?php
/**
 * Media attachment tags (Tagify) in admin.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a Tags field under File URL, saves to post meta, AJAX autocomplete.
 */
class Attachment_Tags {

	public const META_KEY = '_vce_attachment_tags';

	private const TAGIFY_VERSION = '4.35.1';

	private const TAG_POOL_TRANSIENT = 'vce_attachment_tag_pool';

	/**
	 * Hook callbacks.
	 */
	public function register_hooks(): void {
		add_filter( 'attachment_fields_to_edit', [ $this, 'add_field_after_url' ], 20, 2 );
		add_filter( 'attachment_fields_to_save', [ $this, 'save_field' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_vce_suggest_attachment_tags', [ $this, 'ajax_suggest_tags' ] );
		add_action( 'updated_post_meta', [ $this, 'invalidate_tag_pool_on_meta_change' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'invalidate_tag_pool_on_meta_change' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'invalidate_tag_pool_on_delete' ], 10, 4 );
	}

	/**
	 * Screens that load the media library / modal.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 */
	private function screen_needs_tags( string $hook_suffix ): bool {
		return in_array( $hook_suffix, [ 'post.php', 'post-new.php', 'upload.php' ], true );
	}

	/**
	 * Register Tagify (CDN) and integration script.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->screen_needs_tags( $hook_suffix ) ) {
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		wp_enqueue_media();

		$tagify_css = 'https://cdn.jsdelivr.net/npm/@yaireo/tagify@' . self::TAGIFY_VERSION . '/dist/tagify.css';
		$tagify_js  = 'https://cdn.jsdelivr.net/npm/@yaireo/tagify@' . self::TAGIFY_VERSION . '/dist/tagify.min.js';

		wp_register_style(
			'vce-tagify',
			$tagify_css,
			[],
			self::TAGIFY_VERSION
		);

		wp_register_script(
			'vce-tagify',
			$tagify_js,
			[],
			self::TAGIFY_VERSION,
			true
		);

		wp_enqueue_style( 'vce-tagify' );
		wp_enqueue_style(
			'vce-admin-attachment-tags',
			VCE_PLUGIN_URL . 'assets/css/admin-attachment-tags.css',
			[ 'vce-tagify' ],
			vce_asset_version( 'assets/css/admin-attachment-tags.css' )
		);

		wp_enqueue_script(
			'vce-admin-attachment-tags',
			VCE_PLUGIN_URL . 'assets/js/admin-attachment-tags.js',
			[ 'jquery', 'vce-tagify', 'underscore', 'media-views' ],
			vce_asset_version( 'assets/js/admin-attachment-tags.js' ),
			true
		);

		wp_localize_script(
			'vce-admin-attachment-tags',
			'vceAttachmentTags',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vce_attachment_tags' ),
				'i18n'    => [
					'tagsLabel' => __( 'Tags', VCE_TEXT_DOMAIN ),
				],
			]
		);
	}

	/**
	 * Insert Tags field immediately after the URL field.
	 *
	 * @param array    $form_fields Associative form fields.
	 * @param \WP_Post $post        Attachment post.
	 * @return array
	 */
	public function add_field_after_url( array $form_fields, $post ): array {
		if ( ! $post instanceof \WP_Post || 'attachment' !== $post->post_type ) {
			return $form_fields;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $form_fields;
		}

		$raw   = get_post_meta( $post->ID, self::META_KEY, true );
		$value = is_string( $raw ) ? $raw : '';

		$field = [
			'label' => __( 'Tags', VCE_TEXT_DOMAIN ),
			'input' => 'html',
			'html'  => sprintf(
				'<input type="text" class="vce-attachment-tags-input" id="attachments-%1$d-vce_tags" name="attachments[%1$d][vce_tags]" value="%2$s" autocomplete="off" />',
				(int) $post->ID,
				esc_attr( $value )
			),
			'helps' => __( 'Type to search existing tags or add new ones. Separate with comma or Enter.', VCE_TEXT_DOMAIN ),
		];

		if ( ! isset( $form_fields['url'] ) ) {
			$form_fields['vce_tags'] = $field;
			return $form_fields;
		}

		$ordered = [];
		foreach ( $form_fields as $key => $def ) {
			$ordered[ $key ] = $def;
			if ( 'url' === $key ) {
				$ordered['vce_tags'] = $field;
			}
		}
		return $ordered;
	}

	/**
	 * Persist tags from the attachment compat form.
	 *
	 * @param array $post        Attachment post data (includes ID).
	 * @param array $attachment Submitted fields for this attachment.
	 * @return array
	 */
	public function save_field( array $post, array $attachment ): array {
		if ( empty( $post['ID'] ) || ! isset( $attachment['vce_tags'] ) ) {
			return $post;
		}

		$post_id = (int) $post['ID'];
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return $post;
		}

		$stored = $this->normalize_tags_string( $attachment['vce_tags'] );

		if ( '' === $stored ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, $stored );
		}

		return $post;
	}

	/**
	 * Normalize raw Tagify / text input into a comma-separated unique list.
	 *
	 * @param mixed $raw Posted value.
	 */
	private function normalize_tags_string( $raw ): string {
		if ( is_array( $raw ) ) {
			$raw = implode( ',', $raw );
		}
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$raw = trim( wp_unslash( $raw ) );
		if ( '' === $raw ) {
			return '';
		}

		$tags = [];

		if ( '[' === $raw[0] ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $item ) {
					if ( is_array( $item ) && isset( $item['value'] ) ) {
						$tags[] = sanitize_text_field( (string) $item['value'] );
					} elseif ( is_string( $item ) ) {
						$tags[] = sanitize_text_field( $item );
					}
				}
			}
		}

		if ( empty( $tags ) ) {
			$parts = preg_split( '/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY );
			if ( is_array( $parts ) ) {
				foreach ( $parts as $p ) {
					$tags[] = sanitize_text_field( $p );
				}
			}
		}

		$tags = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $t ) {
							$t = trim( (string) $t );
							if ( '' === $t ) {
								return '';
							}
							if ( function_exists( 'mb_substr' ) ) {
								$t = mb_substr( $t, 0, 100 );
							} else {
								$t = substr( $t, 0, 100 );
							}
							return $t;
						},
						$tags
					)
				)
			)
		);

		$tags = array_slice( $tags, 0, 50 );

		return implode( ',', $tags );
	}

	/**
	 * AJAX: tag suggestions for Tagify whitelist.
	 */
	public function ajax_suggest_tags(): void {
		check_ajax_referer( 'vce_attachment_tags', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( null, 403 );
		}

		$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$q = strtolower( $q );

		$pool = $this->get_tag_pool();
		if ( '' !== $q ) {
			$pool = array_values(
				array_filter(
					$pool,
					static function ( $tag ) use ( $q ) {
						return strpos( strtolower( $tag ), $q ) !== false;
					}
				)
			);
		}

		sort( $pool, SORT_NATURAL | SORT_FLAG_CASE );
		$pool = array_slice( $pool, 0, 40 );

		wp_send_json_success( $pool );
	}

	/**
	 * Cached flat list of tags used across attachments.
	 *
	 * @return string[]
	 */
	private function get_tag_pool(): array {
		$cached = get_transient( self::TAG_POOL_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> %s LIMIT 1500",
				self::META_KEY,
				''
			)
		);

		$set = [];
		foreach ( $values as $row ) {
			if ( ! is_string( $row ) || '' === $row ) {
				continue;
			}
			$parts = preg_split( '/\s*,\s*/', $row, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $parts ) ) {
				continue;
			}
			foreach ( $parts as $t ) {
				$t = trim( $t );
				if ( '' === $t ) {
					continue;
				}
				$set[ strtolower( $t ) ] = $t;
			}
		}

		$pool = array_values( $set );
		set_transient( self::TAG_POOL_TRANSIENT, $pool, 15 * MINUTE_IN_SECONDS );

		return $pool;
	}

	/**
	 * Drop tag pool cache when attachment tag meta changes.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function invalidate_tag_pool_on_meta_change( $meta_id, $object_id, $meta_key, $meta_value ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( self::META_KEY !== $meta_key ) {
			return;
		}
		delete_transient( self::TAG_POOL_TRANSIENT );
	}

	/**
	 * @param int[]|int $meta_ids   Deleted meta row id(s); shape varies by WP version.
	 * @param int       $object_id  Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Former meta value.
	 */
	public function invalidate_tag_pool_on_delete( $meta_ids, $object_id, $meta_key, $meta_value ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( self::META_KEY !== $meta_key ) {
			return;
		}
		delete_transient( self::TAG_POOL_TRANSIENT );
	}
}
