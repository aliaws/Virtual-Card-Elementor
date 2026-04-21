<?php
/**
 * Frontend: submission final view carousel (preview-like).
 *
 * @package Virtual_Card_Elementor
 *
 * @var array $panels_data
 * @var array $saved_layers
 */

defined( 'ABSPATH' ) || exit;

$panels_json = wp_json_encode( $panels_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
if ( false === $panels_json ) {
	$panels_json = '[]';
}
$layers_json = wp_json_encode( $saved_layers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
if ( false === $layers_json ) {
	$layers_json = '{}';
}
?>
<div
	class="vce-submission-viewer"
	data-vce-submission-panels
	data-panels="<?php echo esc_attr( $panels_json ); ?>"
	data-layers="<?php echo esc_attr( $layers_json ); ?>"
>
	<div class="vce-preview-modal vce-preview-modal--fullpage" data-vce-submission-modal>
		<div class="vce-preview-modal__backdrop" data-vce-submission-close tabindex="-1"></div>
		<div class="vce-preview-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Panel preview', VCE_TEXT_DOMAIN ); ?>">
			<div class="vce-preview-modal__header vce-preview-modal__header--minimal">
				<span class="vce-preview-modal__title vce-preview-modal__title--sr-only" data-vce-submission-title></span>
				<button type="button" class="vce-preview-modal__close" data-vce-submission-close aria-label="<?php echo esc_attr__( 'Close preview', VCE_TEXT_DOMAIN ); ?>">
					<span class="vce-preview-modal__close-x" aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="vce-preview-modal__viewport">
				<div class="vce-preview-modal__loading" data-vce-submission-loading>
					<?php esc_html_e( 'Building preview…', VCE_TEXT_DOMAIN ); ?>
				</div>
				<div class="vce-preview-modal__body" data-vce-submission-body hidden>
					<button type="button" class="vce-preview-modal__nav vce-preview-modal__nav--prev" data-vce-submission-prev aria-label="<?php echo esc_attr__( 'Previous panel', VCE_TEXT_DOMAIN ); ?>">‹</button>
					<div class="vce-preview-modal__stage">
						<div class="vce-preview-modal__frame" data-vce-submission-frame>
							<img src="" alt="" class="vce-preview-modal__main" data-vce-submission-main decoding="async" />
							<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" class="vce-preview-modal__overlay" data-vce-submission-overlay decoding="async" hidden />
						</div>
					</div>
					<button type="button" class="vce-preview-modal__nav vce-preview-modal__nav--next" data-vce-submission-next aria-label="<?php echo esc_attr__( 'Next panel', VCE_TEXT_DOMAIN ); ?>">›</button>
				</div>
			</div>
		</div>
	</div>
</div>
