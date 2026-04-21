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

$count = count( $ids );
?>
<div class="vce-panel-meta">
	<div class="vce-panel-meta__toolbar">
		<p class="vce-panel-meta__count">
			<span data-vce-panel-count>
				<?php
				if ( 0 === $count ) {
					esc_html_e( 'No panels yet.', VCE_TEXT_DOMAIN );
				} elseif ( 1 === $count ) {
					esc_html_e( '1 panel', VCE_TEXT_DOMAIN );
				} else {
					echo esc_html(
						sprintf(
							/* translators: %d: panel count */
							_n( '%d panel', '%d panels', $count, VCE_TEXT_DOMAIN ),
							$count
						)
					);
				}
				?>
			</span>
		</p>
		<p class="vce-panel-meta__hint description">
			<?php esc_html_e( 'Click a thumbnail to preview. Drag by the grip to reorder.', VCE_TEXT_DOMAIN ); ?>
		</p>
	</div>

	<div class="vce-panel-meta__stage-wrap">
		<div class="vce-panel-meta__stage" data-vce-panel-stage aria-live="polite">
			<div class="vce-panel-meta__stage-label" data-vce-panel-stage-label hidden></div>
			<div class="vce-panel-meta__stage-empty" data-vce-panel-stage-empty>
				<?php esc_html_e( 'No panels yet. Use “Add images” to start.', VCE_TEXT_DOMAIN ); ?>
			</div>
			<div class="vce-panel-meta__stage-frame">
				<img class="vce-panel-meta__stage-img" src="" alt="" data-vce-panel-stage-img />
			</div>
		</div>
	</div>

	<div class="vce-panel-meta__filmstrip-wrap">
		<p class="vce-panel-meta__filmstrip-label">
			<?php esc_html_e( 'Panel order', VCE_TEXT_DOMAIN ); ?>
		</p>
		<ul id="virtual_card_panel_list" class="vce-panel-meta__list vce-panel-meta__filmstrip" data-vce-panel-list>
			<?php foreach ( $ids as $id ) : ?>
				<?php
				$aid   = (int) $id;
				$large = $aid ? wp_get_attachment_image_src( $aid, 'large' ) : null;
				$prev  = ( $large && ! empty( $large[0] ) ) ? $large[0] : '';
				if ( '' === $prev && $aid ) {
					$prev = wp_get_attachment_url( $aid ) ?: '';
				}
				?>
				<li
					class="vce-panel-meta__item ui-state-default"
					data-id="<?php echo esc_attr( (string) $aid ); ?>"
					data-preview-url="<?php echo esc_url( $prev ); ?>"
				>
					<button
						type="button"
						class="vce-panel-meta__drag"
						aria-label="<?php echo esc_attr__( 'Drag to reorder', VCE_TEXT_DOMAIN ); ?>"
						title="<?php echo esc_attr__( 'Drag to reorder', VCE_TEXT_DOMAIN ); ?>"
					>⋮⋮</button>
					<span class="vce-panel-meta__thumb"><?php echo wp_get_attachment_image( $aid, 'thumbnail' ); ?></span>
					<a href="#" class="vce-panel-meta__remove remove" aria-label="<?php echo esc_attr__( 'Remove image', VCE_TEXT_DOMAIN ); ?>">×</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<input
		type="hidden"
		class="vce-panel-meta__ids"
		name="<?php echo esc_attr( Panel_Meta_Box::IDS_FIELD ); ?>"
		id="virtual_card_panel_ids"
		value="<?php echo esc_attr( implode( ',', $ids ) ); ?>"
	/>

	<p class="vce-panel-meta__actions">
		<button type="button" class="button button-primary vce-panel-meta__add" id="virtual_card_add_panels" data-vce-panel-add>
			<?php esc_html_e( 'Add images', VCE_TEXT_DOMAIN ); ?>
		</button>
	</p>

	<div class="vce-panel-modal" data-vce-panel-modal hidden>
		<div class="vce-panel-modal__backdrop" data-vce-panel-modal-close tabindex="-1"></div>
		<div
			class="vce-panel-modal__dialog"
			role="dialog"
			aria-modal="true"
			aria-label="<?php echo esc_attr__( 'Panel preview', VCE_TEXT_DOMAIN ); ?>"
		>
			<button type="button" class="vce-panel-modal__close" data-vce-panel-modal-close>
				<?php esc_html_e( 'Close', VCE_TEXT_DOMAIN ); ?>
			</button>
			<div class="vce-panel-modal__img-wrap">
				<img src="" alt="" class="vce-panel-modal__img" data-vce-panel-modal-img />
			</div>
		</div>
	</div>
</div>
