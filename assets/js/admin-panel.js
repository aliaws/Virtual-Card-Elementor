/**
 * Virtual Card Elementor — Card Panels: filmstrip, stage preview, sortable, modal.
 */
(function ($) {
	'use strict';

	var frame;

	function escapeAttr(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;');
	}

	function getI18n() {
		return window.vceAdminPanel || {};
	}

	function formatPanelCount(n) {
		var i18n = getI18n();
		if (n === 0) {
			return i18n.panelsCountEmpty || '';
		}
		if (n === 1) {
			return i18n.panelsCountOne || '1 panel';
		}
		var tpl = i18n.panelsCountMany || '%d panels';
		return tpl.replace('%d', String(n));
	}

	function formatPanelOf(index, total) {
		var i18n = getI18n();
		var tpl = i18n.panelOfMany || 'Panel %1$s of %2$s';
		return tpl.replace('%1$s', String(index)).replace('%2$s', String(total));
	}

	function syncIdsFromList($wrap) {
		var ids = [];
		$wrap.find('.vce-panel-meta__list .vce-panel-meta__item').each(function () {
			var id = $(this).data('id');
			if (id) {
				ids.push(String(id));
			}
		});
		$wrap.find('.vce-panel-meta__ids').val(ids.join(','));
	}

	function updateCount($wrap) {
		var n = $wrap.find('.vce-panel-meta__list .vce-panel-meta__item').length;
		$wrap.find('[data-vce-panel-count]').text(formatPanelCount(n));
	}



	function updateStage($wrap) {
		var $items = $wrap.find('.vce-panel-meta__list .vce-panel-meta__item');
		var $empty = $wrap.find('[data-vce-panel-stage-empty]');

		var $img = $wrap.find('[data-vce-panel-stage-img]');
		var $label = $wrap.find('[data-vce-panel-stage-label]');

		if (!$items.length) {
			$empty.removeAttr('hidden');

			$label.attr('hidden', 'hidden').text('');
			$img.attr('src', '');
			return;
		}

		var $sel = $items.filter('.is-selected').first();
		if (!$sel.length) {
			$sel = $items.first();
			$sel.addClass('is-selected');
		}

		var url = String($sel.data('preview-url') || '');
		var idx = $items.index($sel) + 1;
		var total = $items.length;

		$empty.attr('hidden', 'hidden');

		$label.removeAttr('hidden').text(formatPanelOf(idx, total));

		if (url) {
			$img.removeAttr('hidden');
			$img.attr('src', url);
		} else {
			$img.attr('src', '');
		}
	}

	function initSortable($wrap) {
		const $list = jQuery($wrap.find('.vce-panel-meta__list'));

		if (!$list.length || typeof $list.sortable !== 'function') {
			return;
		}
		if ($list.data('vce-sortable-init')) {
			$list.sortable('destroy');
		}


		$list.sortable({
			update: function () {
				syncIdsFromList($wrap);
				updateStage($wrap);
			},
		});
		// jQuery( $list ).disableSelection();
		$list.data('vce-sortable-init', true);
	}

	function selectItem($wrap, $li) {
		$wrap.find('.vce-panel-meta__item').removeClass('is-selected');
		$li.addClass('is-selected');
		updateStage($wrap);
	}


	function buildListItem(img, i18n) {
		var thumb =
			img.sizes && img.sizes.thumbnail && img.sizes.thumbnail.url
				? img.sizes.thumbnail.url
				: img.url || '';
		var preview =
			img.sizes && img.sizes.large && img.sizes.large.url
				? img.sizes.large.url
				: img.url || '';
		var removeLabel = i18n.removeLabel || 'Remove image';
		var dragLabel = i18n.dragHandleLabel || 'Drag to reorder';

		return (
			'<li class="vce-panel-meta__item" data-id="' +
			img.id +
			'" data-preview-url="' +
			escapeAttr(preview) +
			'">' +
			'<button type="button" class="vce-panel-meta__drag" aria-label="' +
			escapeAttr(dragLabel) +
			'" title="' +
			escapeAttr(dragLabel) +
			'">⋮⋮</button>' +
			'<span class="vce-panel-meta__thumb">' +
			(thumb ? '<img src="' + escapeAttr(thumb) + '" alt="" />' : '') +
			'</span>' +
			'<a href="#" class="vce-panel-meta__remove remove" aria-label="' +
			escapeAttr(removeLabel) +
			'">×</a>' +
			'</li>'
		);
	}

	function initWrap($wrap) {
		if (!$wrap.length) {
			return;
		}
		updateCount($wrap);
		updateStage($wrap);
		initSortable($wrap);
	}

	function init() {

		jQuery('.vce-panel-meta').each(function () {
			initWrap(jQuery(this));
		});


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

			var i18n = getI18n();

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
					var i18nInner = getI18n();

					selection.forEach(function (img) {
						if (!img || !img.id) {
							return;
						}
						$l.append(buildListItem(img, i18nInner));
					});

					syncIdsFromList($w);
					updateCount($w);
					var $last = $l.find('.vce-panel-meta__item').last();
					if ($last.length) {
						selectItem($w, $last);
					} else {
						updateStage($w);
					}
					initSortable($w);
				});
			}

			frame.vcePanelWrap = $wrap;
			frame.open();
		});

		$(document).on('click', 'li.vce-panel-meta__item', function (e) {
			const $li = $(this);
			const $wrap = jQuery('.vce-panel-meta');
			selectItem($wrap, $li);
		});

		$(document).on('click', '.vce-panel-meta .remove', function (e) {
			e.preventDefault();
			var $wrap = $(this).closest('.vce-panel-meta');
			var $hidden = $wrap.find('.vce-panel-meta__ids');
			var $li = $(this).closest('li');
			var wasSelected = $li.hasClass('is-selected');
			var id = String($li.data('id'));
			var ids = $hidden.val() ? $hidden.val().split(',') : [];

			ids = ids.filter(function (v) {
				return v && v !== id;
			});
			$hidden.val(ids.join(','));
			$li.remove();

			updateCount($wrap);
			if (wasSelected) {
				var $next = $wrap.find('.vce-panel-meta__item').first();
				if ($next.length) {
					selectItem($wrap, $next);
				} else {
					$wrap.find('.vce-panel-meta__item').removeClass('is-selected');
					updateStage($wrap);
				}
			} else {
				updateStage($wrap);
			}
			initSortable($wrap);
		});
	}

	$(init);
})(jQuery);
