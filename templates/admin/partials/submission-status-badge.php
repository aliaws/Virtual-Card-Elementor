<?php
/**
 * Admin: submission status pill (list table + meta box).
 *
 * @package Virtual_Card_Elementor
 *
 * @var string $status       Status slug.
 * @var string $status_label Translated label.
 * @var string $status_color Hex background color.
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="vce-status-badge" style="background:<?php echo esc_attr( $status_color ); ?>;">
	<?php echo esc_html( $status_label ); ?>
</span>
