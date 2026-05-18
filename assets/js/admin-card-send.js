/**
 * Admin submission send modal.
 */
(function ($) {
	'use strict';

	var modal, form, emailInput, senderInput, messageInput, status, submitBtn;
	var currentPostId = 0;

	function init() {
		modal    = $('#vce-send-modal');
		form     = $('#vce-send-form');
		emailInput   = $('#vce-admin-email');
		senderInput  = $('#vce-admin-sender');
		messageInput = $('#vce-admin-message');
		status   = $('#vce-send-status');
		submitBtn    = $('#vce-send-submit');

		if (!modal.length) {
			return;
		}

		$(document).on('click', '.vce-admin-send', function (e) {
			e.preventDefault();
			currentPostId = $(this).data('post-id');
			status.hide();
			form[0].reset();
			modal.addClass('open');
			emailInput.focus();
		});

		$('#vce-send-cancel').on('click', function () {
			modal.removeClass('open');
		});

		form.on('submit', function (e) {
			e.preventDefault();
			var email = emailInput.val().trim();
			if (!email) {
				status.text(vceAdminSend.i18n.requiredEmail).show();
				return;
			}

			submitBtn.prop('disabled', true).text(vceAdminSend.i18n.sending);
			status.text(vceAdminSend.i18n.sending).show();

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
						status.text(res.message || vceAdminSend.i18n.sent).css('color', 'green');
						setTimeout(function () {
							modal.removeClass('open');
							location.reload();
						}, 1000);
					} else {
						status.text(res.message || vceAdminSend.i18n.failed).css('color', 'red');
					}
				},
				error: function () {
					status.text(vceAdminSend.i18n.error).css('color', 'red').show();
				},
				complete: function () {
					submitBtn.prop('disabled', false).text(vceAdminSend.i18n.send);
				},
			});
		});
	}

	$(init);
})(jQuery);
