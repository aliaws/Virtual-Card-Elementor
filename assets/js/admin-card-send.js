/**
 * Admin submission send modal.
 */
(function ($) {
	'use strict';

	var modal, backdrop, form, emailInput, senderInput, messageInput, status, submitBtn;
	var currentPostId = 0;

	function openModal() {
		modal.removeAttr('hidden');
		emailInput.trigger('focus');
	}

	function closeModal() {
		modal.attr('hidden', 'hidden');
		status.attr('hidden', 'hidden').removeClass('is-error is-success');
	}

	function init() {
		modal = $('#vce-send-modal');
		backdrop = $('#vce-send-modal-backdrop');
		form = $('#vce-send-form');
		emailInput = $('#vce-admin-email');
		senderInput = $('#vce-admin-sender');
		messageInput = $('#vce-admin-message');
		status = $('#vce-send-status');
		submitBtn = $('#vce-send-submit');

		if (!modal.length) {
			return;
		}

		$(document).on('click', '.vce-admin-send', function (e) {
			e.preventDefault();
			currentPostId = $(this).data('post-id');
			status.attr('hidden', 'hidden').removeClass('is-error is-success');
			form[0].reset();
			openModal();
		});

		$('#vce-send-cancel').on('click', closeModal);
		backdrop.on('click', closeModal);

		form.on('submit', function (e) {
			e.preventDefault();
			var email = emailInput.val().trim();
			if (!email) {
				status
					.text(vceAdminSend.i18n.requiredEmail)
					.addClass('is-error')
					.removeClass('is-success')
					.removeAttr('hidden');
				return;
			}

			submitBtn.prop('disabled', true).text(vceAdminSend.i18n.sending);
			status
				.text(vceAdminSend.i18n.sending)
				.removeClass('is-error is-success')
				.removeAttr('hidden');

			$.ajax({
				url: vceAdminSend.restUrl,
				method: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', vceAdminSend.nonce);
				},
				data: JSON.stringify({
					submissionId: currentPostId,
					recipientEmail: email,
					senderName: senderInput.val().trim(),
					message: messageInput.val().trim(),
				}),
				contentType: 'application/json',
				success: function (res) {
					if (res.success) {
						status
							.text(res.message || vceAdminSend.i18n.sent)
							.addClass('is-success')
							.removeClass('is-error')
							.removeAttr('hidden');
						setTimeout(function () {
							closeModal();
							location.reload();
						}, 1000);
					} else {
						status
							.text(res.message || vceAdminSend.i18n.failed)
							.addClass('is-error')
							.removeClass('is-success')
							.removeAttr('hidden');
					}
				},
				error: function () {
					status
						.text(vceAdminSend.i18n.error)
						.addClass('is-error')
						.removeClass('is-success')
						.removeAttr('hidden');
				},
				complete: function () {
					submitBtn.prop('disabled', false).text(vceAdminSend.i18n.send);
				},
			});
		});
	}

	$(init);
})(jQuery);
