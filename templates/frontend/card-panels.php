<?php
/**
 * Frontend: Virtual Card panel grid (Elementor widget output).
 *
 * @package Virtual_Card_Elementor
 *
 * @var int[] $ids
 * @var int   $columns
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="virtual-card-panels vce-card-panels" style="<?php echo esc_attr( sprintf( '--vce-columns:%d;', $columns ) ); ?>">
	<?php foreach ( $ids as $id ) : ?>
		<div class="virtual-card-panel-item vce-card-panels__item">
			<?php
			echo wp_get_attachment_image(
				(int) $id,
				'large',
				false,
				[
					'class'   => 'virtual-card-panel-image vce-card-panels__image',
					'loading' => 'lazy',
					'alt'     => esc_attr( get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) ),
				]
			);
			?>
		</div>
	<?php endforeach; ?>
</div>
