<?php
/**
 * Admin labels meta box for Virtual Card.
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor\Admin;

use Virtual_Card_Elementor\Panel_Meta;
use Virtual_Card_Elementor\Post_Type;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card Labels meta box and save handler.
 */
class Card_Labels_Meta_Box {

	/**
	 * Hook callbacks.
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post_' . Post_Type::POST_TYPE, [ $this, 'save_labels' ], 12, 3 );
	}

	/**
	 * Register meta boxes on the Virtual Card edit screen.
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'virtual_card_labels',
			__( 'Labels & Status', VCE_TEXT_DOMAIN ),
			[ $this, 'render_meta_box' ],
			Post_Type::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render labels meta box HTML.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_meta_box( $post ): void {
		wp_nonce_field( 'vce_labels_save', 'vce_labels_nonce_field' );

		$is_favorite = get_post_meta( $post->ID, Panel_Meta::IS_FAVORITE_META_KEY, true );
		$first_label = get_post_meta( $post->ID, Panel_Meta::FIRST_LEVEL_LABEL_META_KEY, true );
		$second_label = get_post_meta( $post->ID, Panel_Meta::SECOND_LEVEL_LABEL_META_KEY, true );
		?>
		<p>
			<label for="vce_is_favorite">
				<input type="checkbox" id="vce_is_favorite" name="vce_is_favorite" value="1" <?php checked( $is_favorite, '1' ); ?> />
				<?php esc_html_e( 'Is Favorite', VCE_TEXT_DOMAIN ); ?>
			</label>
		</p>
		<p>
			<label for="vce_first_level_label">
				<?php esc_html_e( 'First Level Label', VCE_TEXT_DOMAIN ); ?><br />
				<input type="text" id="vce_first_level_label" name="vce_first_level_label" value="<?php echo esc_attr( $first_label ); ?>" class="widefat" />
			</label>
		</p>
		<p>
			<label for="vce_second_level_label">
				<?php esc_html_e( 'Second Level Label', VCE_TEXT_DOMAIN ); ?><br />
				<input type="text" id="vce_second_level_label" name="vce_second_level_label" value="<?php echo esc_attr( $second_label ); ?>" class="widefat" />
			</label>
		</p>
		<?php
	}

	/**
	 * Persist labels meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update.
	 */
	public function save_labels( int $post_id, $post, bool $update ): void {
		if ( ! isset( $_POST['vce_labels_nonce_field'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vce_labels_nonce_field'] ) ), 'vce_labels_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_favorite = isset( $_POST['vce_is_favorite'] ) ? '1' : '0';
		update_post_meta( $post_id, Panel_Meta::IS_FAVORITE_META_KEY, $is_favorite );

		$first_label = isset( $_POST['vce_first_level_label'] ) ? sanitize_text_field( wp_unslash( $_POST['vce_first_level_label'] ) ) : '';
		if ( '' === $first_label ) {
			delete_post_meta( $post_id, Panel_Meta::FIRST_LEVEL_LABEL_META_KEY );
		} else {
			update_post_meta( $post_id, Panel_Meta::FIRST_LEVEL_LABEL_META_KEY, $first_label );
		}

		$second_label = isset( $_POST['vce_second_level_label'] ) ? sanitize_text_field( wp_unslash( $_POST['vce_second_level_label'] ) ) : '';
		if ( '' === $second_label ) {
			delete_post_meta( $post_id, Panel_Meta::SECOND_LEVEL_LABEL_META_KEY );
		} else {
			update_post_meta( $post_id, Panel_Meta::SECOND_LEVEL_LABEL_META_KEY, $second_label );
		}
	}
}
