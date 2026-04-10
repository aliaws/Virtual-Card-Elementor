<?php
/**
 * Admin: Card Panels meta box markup.
 *
 * @package Virtual_Card_Elementor
 *
 * @var \WP_Post $post
 * @var int[]    $ids
 */

defined( 'ABSPATH' ) || exit;

use Virtual_Card_Elementor\Admin\Panel_Meta_Box;

wp_nonce_field( Panel_Meta_Box::NONCE_ACTION, Panel_Meta_Box::NONCE_FIELD );
?>
<div class="vce-panel-meta">
	<ul id="virtual_card_panel_list" class="vce-panel-meta__list">
		<?php foreach ( $ids as $id ) : ?>
			<li class="vce-panel-meta__item" data-id="<?php echo esc_attr( (string) $id ); ?>">
				<?php echo wp_get_attachment_image( (int) $id, 'thumbnail' ); ?>
				<a href="#" class="vce-panel-meta__remove remove" aria-label="<?php echo esc_attr( __( 'Remove image', VCE_TEXT_DOMAIN ) ); ?>">×</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<input
		type="hidden"
		name="<?php echo esc_attr( Panel_Meta_Box::IDS_FIELD ); ?>"
		id="virtual_card_panel_ids"
		value="<?php echo esc_attr( implode( ',', $ids ) ); ?>"
	/>

	<button type="button" class="button" id="virtual_card_add_panels">
		<?php esc_html_e( 'Add images', VCE_TEXT_DOMAIN ); ?>
	</button>
</div>
