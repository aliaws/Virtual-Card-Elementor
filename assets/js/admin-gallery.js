(function($){
	'use strict';

	var $modal  = $('#vce-gallery-modal');
	var $form   = $('#vce-gallery-edit-form');
	var $status = $('.vce-gallery-modal-status');
	var $postId = $('#vce-edit-post-id');
	var $title  = $('#vce-edit-title');
	var $order  = $('#vce-edit-order');

	function openModal(id, title, order) {
		$postId.val(id);
		$title.val(title);
		$order.val(order);
		$status.hide().text('');
		$modal.prop('hidden', false);
	}

	function closeModal() {
		$modal.prop('hidden', true);
	}

	$(document).on('click', '.vce-gallery-edit-btn', function(e) {
		e.preventDefault();
		e.stopPropagation();
		var $btn = $(this);
		openModal($btn.data('post-id'), $btn.data('title'), $btn.data('order'));
	});

	$(document).on('click', '.vce-gallery-modal-backdrop, .vce-gallery-modal-close, .vce-gallery-modal-cancel', function() {
		closeModal();
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape' && !$modal.prop('hidden')) {
			closeModal();
		}
	});

	$form.on('submit', function(e) {
		e.preventDefault();

		var id    = $postId.val();
		var title = $title.val().trim();
		var order = $order.val().trim();

		if (!title) {
			$status.text(vceGallery.i18n.requiredTitle).show();
			return;
		}

		$status.text(vceGallery.i18n.saving).show();

		$.ajax({
			url: vceGallery.ajaxUrl,
			method: 'POST',
			data: {
				_ajax_nonce: vceGallery.nonce,
				action: 'vce_gallery_update_card',
				post_id: id,
				title: title,
				order: order
			},
			success: function(resp) {
				if (resp.success) {
					$status.text(vceGallery.i18n.saved).css('color', '#46b450');
					var $item = $('.vce-gallery-grid-item[data-post-id="' + id + '"]');
					$item.find('.vce-gallery-grid-title').text(title);
					$item.find('.vce-gallery-grid-order').text(order === '' ? '0' : order);
					$item.find('.vce-gallery-edit-btn').data('title', title).data('order', order === '' ? 0 : parseInt(order, 10));
					setTimeout(closeModal, 800);
				} else {
					$status.text(resp.data && resp.data.message ? resp.data.message : vceGallery.i18n.error).css('color', '#dc3232');
				}
			},
			error: function() {
				$status.text(vceGallery.i18n.error).css('color', '#dc3232');
			}
		});
	});
})(jQuery);
