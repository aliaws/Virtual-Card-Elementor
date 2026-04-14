<?php
/**
 * Frontend: Card panel editor shell (toolbar + stage + filmstrip + preview modal).
 *
 * @package Virtual_Card_Elementor
 *
 * @var int                  $post_id
 * @var int                  $source_card_id
 * @var int[]                $ids
 * @var array                $panels_data Serialized panel payloads for JS.
 * @var array                $saved_layers Stored submission layer payloads.
 * @var string               $editor_font Selected font key.
 * @var array<string,string> $font_options Font key => label for toolbar select.
 */

defined( 'ABSPATH' ) || exit;

$json = wp_json_encode(
	$panels_data,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ( false === $json ) {
	$json = '[]';
}

$saved_json = wp_json_encode(
	$saved_layers ?? [],
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if ( false === $saved_json ) {
	$saved_json = '{}';
}
?>
<div
	class="vce-panel-editor"
	data-vce-panel-editor
	data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"
	data-source-card-id="<?php echo esc_attr( (string) $source_card_id ); ?>"
	data-panels="<?php echo esc_attr( $json ); ?>"
	data-saved-layers="<?php echo esc_attr( $saved_json ); ?>"
	data-default-font="<?php echo esc_attr( $editor_font ); ?>"
>
	<div class="vce-panel-editor__toolbar" role="toolbar" aria-label="<?php echo esc_attr__( 'Card editor tools', VCE_TEXT_DOMAIN ); ?>">
		<div class="vce-panel-editor__toolbar-main">
			<div class="vce-panel-editor__toolbar-row vce-panel-editor__toolbar-row--actions">
				<div class="vce-panel-editor__actions">
					<button type="button" class="button button-primary vce-panel-editor__btn vce-panel-editor__btn--review" data-vce-final-review>
						<?php esc_html_e( 'Final review', VCE_TEXT_DOMAIN ); ?>
					</button>
					<button type="button" class="button vce-panel-editor__btn vce-panel-editor__btn--ghost" data-vce-add-text>
						<?php esc_html_e( 'Add text', VCE_TEXT_DOMAIN ); ?>
					</button>
					<button type="button" class="button button-secondary vce-panel-editor__btn" data-vce-save-submission>
						<?php esc_html_e( 'Save submission', VCE_TEXT_DOMAIN ); ?>
					</button>
				</div>
				<button type="button" class="button vce-panel-editor__btn vce-panel-editor__btn--danger" data-vce-delete-layer disabled>
					<?php esc_html_e( 'Remove text', VCE_TEXT_DOMAIN ); ?>
				</button>
			</div>
			<div class="vce-panel-editor__toolbar-row vce-panel-editor__toolbar-row--type">
			<label class="vce-panel-editor__field vce-panel-editor__field--font">
				<span class="vce-panel-editor__field-label"><?php esc_html_e( 'Font', VCE_TEXT_DOMAIN ); ?></span>
				<select class="vce-panel-editor__select" data-vce-font-family aria-label="<?php echo esc_attr__( 'Text font', VCE_TEXT_DOMAIN ); ?>">
					<?php foreach ( $font_options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $key ); ?>"<?php selected( $editor_font, (string) $key ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="vce-panel-editor__field vce-panel-editor__field--size">
				<span class="vce-panel-editor__field-label"><?php esc_html_e( 'Size', VCE_TEXT_DOMAIN ); ?></span>
				<span class="vce-panel-editor__size-wrap">
					<input
						type="range"
						class="vce-panel-editor__range"
						id="vce-editor-font-size-<?php echo esc_attr( (string) $post_id ); ?>"
						data-vce-font-size
						min="12"
						max="96"
						value="28"
						aria-label="<?php echo esc_attr__( 'Text size', VCE_TEXT_DOMAIN ); ?>"
					/>
					<span class="vce-panel-editor__size-readout" data-vce-font-size-display aria-hidden="true">28</span>
				</span>
			</label>
			<label class="vce-panel-editor__field vce-panel-editor__field--color">
				<span class="vce-panel-editor__field-label"><?php esc_html_e( 'Color', VCE_TEXT_DOMAIN ); ?></span>
				<span class="vce-panel-editor__color-tool">
					<select
						class="vce-panel-editor__select vce-panel-editor__select--color"
						data-vce-color-preset
						aria-label="<?php echo esc_attr__( 'Preset text colors', VCE_TEXT_DOMAIN ); ?>"
					>
						<?php
						$vce_swatches = [
							'#1d2327' => __( 'Black', VCE_TEXT_DOMAIN ),
							'#ffffff' => __( 'White', VCE_TEXT_DOMAIN ),
							'#d63638' => __( 'Red', VCE_TEXT_DOMAIN ),
							'#00a32a' => __( 'Green', VCE_TEXT_DOMAIN ),
							'#2271b1' => __( 'Blue', VCE_TEXT_DOMAIN ),
							'#7c3aed' => __( 'Purple', VCE_TEXT_DOMAIN ),
							'#d97706' => __( 'Orange', VCE_TEXT_DOMAIN ),
							'#db2777' => __( 'Pink', VCE_TEXT_DOMAIN ),
						];
						foreach ( $vce_swatches as $vce_hex => $vce_label ) :
							?>
						<option value="<?php echo esc_attr( $vce_hex ); ?>"><?php echo esc_html( $vce_label ); ?></option>
						<?php endforeach; ?>
						<option value="__custom__"><?php esc_html_e( 'Custom…', VCE_TEXT_DOMAIN ); ?></option>
					</select>
					<label class="vce-panel-editor__color-hit" title="<?php echo esc_attr__( 'Pick any color', VCE_TEXT_DOMAIN ); ?>">
						<span class="vce-sr-only"><?php esc_html_e( 'Open color picker', VCE_TEXT_DOMAIN ); ?></span>
						<input
							type="color"
							class="vce-panel-editor__color-input"
							data-vce-text-color
							value="#1d2327"
							aria-label="<?php echo esc_attr__( 'Custom text color', VCE_TEXT_DOMAIN ); ?>"
						/>
					</label>
				</span>
			</label>
			<label class="vce-panel-editor__field vce-panel-editor__field--bg">
				<span class="vce-panel-editor__field-label"><?php esc_html_e( 'Text background', VCE_TEXT_DOMAIN ); ?></span>
				<span class="vce-panel-editor__color-tool">
					<label class="vce-panel-editor__color-hit" title="<?php echo esc_attr__( 'Pick text background color', VCE_TEXT_DOMAIN ); ?>">
						<span class="vce-sr-only"><?php esc_html_e( 'Text background color', VCE_TEXT_DOMAIN ); ?></span>
						<input
							type="color"
							class="vce-panel-editor__color-input"
							data-vce-text-bg-color
							value="#ffffff"
							aria-label="<?php echo esc_attr__( 'Text background color', VCE_TEXT_DOMAIN ); ?>"
						/>
					</label>
					<button
						type="button"
						class="vce-panel-editor__btn-clear-bg"
						data-vce-clear-text-bg
						disabled
						aria-label="<?php echo esc_attr__( 'Clear text background', VCE_TEXT_DOMAIN ); ?>"
					>
						<?php esc_html_e( 'Clear', VCE_TEXT_DOMAIN ); ?>
					</button>
				</span>
			</label>
			<div
				class="vce-panel-editor__format-group"
				role="group"
				aria-label="<?php echo esc_attr__( 'Text style', VCE_TEXT_DOMAIN ); ?>"
			>
				<button
					type="button"
					class="vce-panel-editor__fmt"
					data-vce-text-bold
					aria-pressed="false"
					disabled
					aria-label="<?php echo esc_attr__( 'Bold', VCE_TEXT_DOMAIN ); ?>"
				><span aria-hidden="true">B</span></button>
				<button
					type="button"
					class="vce-panel-editor__fmt vce-panel-editor__fmt--italic"
					data-vce-text-italic
					aria-pressed="false"
					disabled
					aria-label="<?php echo esc_attr__( 'Italic', VCE_TEXT_DOMAIN ); ?>"
				><span class="vce-panel-editor__italic-glyph" aria-hidden="true">I</span></button>
				<button
					type="button"
					class="vce-panel-editor__fmt vce-panel-editor__fmt--underline"
					data-vce-text-underline
					aria-pressed="false"
					disabled
					aria-label="<?php echo esc_attr__( 'Underline', VCE_TEXT_DOMAIN ); ?>"
				><span aria-hidden="true">U</span></button>
			</div>
			</div>
		</div>
		<div class="vce-panel-editor__toolbar-meta">
			<span class="vce-panel-editor__panel-label" data-vce-panel-label></span>
			<span class="vce-panel-editor__draft-note"><?php esc_html_e( 'Draft saved in this browser only.', VCE_TEXT_DOMAIN ); ?></span>
			<a class="vce-panel-editor__submission-link" data-vce-submission-link hidden></a>
		</div>
	</div>

	<div class="vce-panel-editor__stage-outer">
		<div class="vce-panel-editor__stage" data-vce-stage>
			<div class="vce-panel-editor__stage-inner" data-vce-stage-inner>
				<div class="vce-panel-editor__canvas-wrap" data-vce-canvas-wrap>
					<canvas class="vce-panel-editor__fabric" data-vce-fabric-canvas width="800" height="600"></canvas>
				</div>
			</div>
		</div>
	</div>

	<div class="vce-panel-editor__filmstrip-wrap">
		<p class="vce-panel-editor__filmstrip-title"><?php esc_html_e( 'Panels', VCE_TEXT_DOMAIN ); ?></p>
		<ul class="vce-panel-editor__filmstrip" data-vce-filmstrip>
			<?php foreach ( $panels_data as $index => $p ) : ?>
				<li>
					<button
						type="button"
						class="vce-panel-editor__thumb<?php echo 0 === (int) $index ? ' is-active' : ''; ?>"
						data-vce-thumb
						data-index="<?php echo esc_attr( (string) $index ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: panel number */ __( 'Panel %d', VCE_TEXT_DOMAIN ), (int) $index + 1 ) ); ?>"
						aria-pressed="<?php echo 0 === (int) $index ? 'true' : 'false'; ?>"
					>
						<?php if ( ! empty( $p['thumb'] ) ) : ?>
							<img src="<?php echo esc_url( $p['thumb'] ); ?>" alt="" width="80" height="80" loading="lazy" />
						<?php endif; ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="vce-preview-modal vce-preview-modal--fullpage" data-vce-preview-modal hidden>
		<div class="vce-preview-modal__backdrop" data-vce-preview-close tabindex="-1"></div>
		<div
			class="vce-preview-modal__dialog"
			role="dialog"
			aria-modal="true"
			aria-label="<?php echo esc_attr__( 'Panel preview', VCE_TEXT_DOMAIN ); ?>"
		>
			<div class="vce-preview-modal__header vce-preview-modal__header--minimal">
				<span class="vce-preview-modal__title vce-preview-modal__title--sr-only" data-vce-preview-title></span>
				<button
					type="button"
					class="vce-preview-modal__close"
					data-vce-preview-close
					aria-label="<?php echo esc_attr__( 'Close preview', VCE_TEXT_DOMAIN ); ?>"
				>
					<span class="vce-preview-modal__close-x" aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="vce-preview-modal__viewport">
				<div class="vce-preview-modal__loading" data-vce-preview-loading hidden>
					<?php esc_html_e( 'Building preview…', VCE_TEXT_DOMAIN ); ?>
				</div>
				<div class="vce-preview-modal__body" data-vce-preview-body hidden>
					<button type="button" class="vce-preview-modal__nav vce-preview-modal__nav--prev" data-vce-preview-prev aria-label="<?php echo esc_attr__( 'Previous panel', VCE_TEXT_DOMAIN ); ?>">‹</button>
					<div class="vce-preview-modal__stage">
						<div class="vce-preview-modal__frame" data-vce-preview-frame>
							<img src="" alt="" class="vce-preview-modal__main" data-vce-preview-main decoding="async" />
							<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" class="vce-preview-modal__overlay" data-vce-preview-overlay decoding="async" hidden />
						</div>
					</div>
					<button type="button" class="vce-preview-modal__nav vce-preview-modal__nav--next" data-vce-preview-next aria-label="<?php echo esc_attr__( 'Next panel', VCE_TEXT_DOMAIN ); ?>">›</button>
				</div>
			</div>
		</div>
	</div>
</div>
