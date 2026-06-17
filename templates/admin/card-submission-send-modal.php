<?php
/**
 * Admin: send virtual card modal (card submission list).
 *
 * @package Virtual_Card_Elementor
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vce-send-modal" id="vce-send-modal" hidden>
	<div class="vce-send-modal__backdrop" id="vce-send-modal-backdrop" tabindex="-1"></div>
	<div class="vce-send-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="vce-send-modal-title">
		<h3 class="vce-send-modal__title" id="vce-send-modal-title"><?php esc_html_e( 'Send Virtual Card', VCE_TEXT_DOMAIN ); ?></h3>
		<form id="vce-send-form" class="vce-send-modal__form">
			<p class="vce-send-modal__field">
				<label for="vce-admin-email"><?php esc_html_e( 'Recipient Email', VCE_TEXT_DOMAIN ); ?> *</label>
				<input type="email" id="vce-admin-email" class="widefat" required />
			</p>
			<p class="vce-send-modal__field">
				<label for="vce-admin-sender"><?php esc_html_e( 'Sender Name', VCE_TEXT_DOMAIN ); ?></label>
				<input type="text" id="vce-admin-sender" class="widefat" />
			</p>
			<p class="vce-send-modal__field">
				<label for="vce-admin-message"><?php esc_html_e( 'Message', VCE_TEXT_DOMAIN ); ?></label>
				<textarea id="vce-admin-message" class="widefat" rows="3"></textarea>
			</p>
			<div class="vce-send-modal__actions">
				<button type="button" class="button" id="vce-send-cancel"><?php esc_html_e( 'Cancel', VCE_TEXT_DOMAIN ); ?></button>
				<button type="submit" class="button button-primary" id="vce-send-submit"><?php esc_html_e( 'Send', VCE_TEXT_DOMAIN ); ?></button>
			</div>
			<p class="vce-send-modal__status" id="vce-send-status" hidden></p>
		</form>
	</div>
</div>
