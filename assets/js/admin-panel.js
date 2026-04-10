/**
 * Virtual Card Elementor — admin panel picker (wp.media + list UI)
 */
(function ($) {
	'use strict';

	function escapeAttr(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;');
	}

	$(function () {
		var $list = $('#virtual_card_panel_list');
		var $hidden = $('#virtual_card_panel_ids');
		var $addBtn = $('#virtual_card_add_panels');

		if (!$list.length || !$hidden.length || !$addBtn.length) {
			return;
		}

		var frame;

		$addBtn.on('click', function (e) {
			e.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			var i18n = window.vceAdminPanel || {};

			frame = wp.media({
				title: i18n.frameTitle || 'Select images',
				multiple: true,
			});

			frame.on('select', function () {
				var selection = frame.state().get('selection').toJSON();
				var ids = $hidden.val() ? $hidden.val().split(',') : [];

				selection.forEach(function (img) {
					if (!img || !img.id) {
						return;
					}
					ids.push(String(img.id));

					var thumb =
						img.sizes && img.sizes.thumbnail && img.sizes.thumbnail.url
							? img.sizes.thumbnail.url
							: img.url || '';

					var removeLabel = i18n.removeLabel || 'Remove image';
					$list.append(
						'<li class="vce-panel-meta__item" data-id="' +
							img.id +
							'">' +
							(thumb ? '<img src="' + thumb + '" alt="" />' : '') +
							'<a href="#" class="vce-panel-meta__remove remove" aria-label="' +
							escapeAttr(removeLabel) +
							'">×</a>' +
							'</li>'
					);
				});

				$hidden.val(ids.filter(Boolean).join(','));
			});

			frame.open();
		});

		$(document).on('click', '.vce-panel-meta .remove', function (e) {
			e.preventDefault();
			var $li = $(this).closest('li');
			var id = String($li.data('id'));
			var ids = $hidden.val() ? $hidden.val().split(',') : [];

			ids = ids.filter(function (v) {
				return v && v !== id;
			});
			$hidden.val(ids.join(','));
			$li.remove();
		});
	});
})(jQuery);
