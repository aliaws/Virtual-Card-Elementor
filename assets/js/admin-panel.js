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

	var frame;

	function init() {
		$(document).on('click', '[data-vce-panel-add]', function (e) {
			e.preventDefault();

			if (typeof wp === 'undefined' || !wp.media) {
				return;
			}

			var $wrap = $(this).closest('.vce-panel-meta');
			var $list = $wrap.find('.vce-panel-meta__list');
			var $hidden = $wrap.find('.vce-panel-meta__ids');

			if (!$list.length || !$hidden.length) {
				return;
			}

			var i18n = window.vceAdminPanel || {};

			if (!frame) {
				frame = wp.media({
					title: i18n.frameTitle || 'Select images',
					multiple: true,
					library: { type: 'image' },
				});

				frame.on('select', function () {
					var $w = frame.vcePanelWrap;
					if (!$w || !$w.length) {
						return;
					}
					var $l = $w.find('.vce-panel-meta__list');
					var $h = $w.find('.vce-panel-meta__ids');
					if (!$l.length || !$h.length) {
						return;
					}

					var selection = frame.state().get('selection').toJSON();
					var ids = $h.val() ? $h.val().split(',') : [];

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
						$l.append(
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

					$h.val(ids.filter(Boolean).join(','));
				});
			}

			frame.vcePanelWrap = $wrap;
			frame.open();
		});

		$(document).on('click', '.vce-panel-meta .remove', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.vce-panel-meta');
			var $hidden = $wrap.find('.vce-panel-meta__ids');
			var $li = $(this).closest('li');
			var id = String($li.data('id'));
			var ids = $hidden.val() ? $hidden.val().split(',') : [];

			ids = ids.filter(function (v) {
				return v && v !== id;
			});
			$hidden.val(ids.join(','));
			$li.remove();
		});
	}

	$(init);
})(jQuery);
