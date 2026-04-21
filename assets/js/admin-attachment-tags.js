/**
 * Tagify on attachment detail fields (media modal + attachment edit screen).
 */
(function ($) {
	'use strict';

	var mediaPatched = false;

	function cfg() {
		return window.vceAttachmentTags || {};
	}

	function destroyTagify(input) {
		if (!input) {
			return;
		}
		var t = input._vceTagifyInstance;
		if (t && typeof t.destroy === 'function') {
			try {
				t.destroy();
			} catch (e) {}
		}
		input._vceTagifyInstance = null;
		input._vceTagifyReady = false;
		$(input).removeData('vceTagify');
	}

	function bindInput(input) {
		if (!input || typeof Tagify === 'undefined') {
			return;
		}
		if (input._vceTagifyReady) {
			return;
		}

		var c = cfg();
		var suggestTimer;

		var tagify = new Tagify(input, {
			maxTags: 50,
			dropdown: {
				enabled: 0,
				maxItems: 20,
				closeOnSelect: false,
				highlightFirst: true,
				classname: 'vce-tagify-dropdown',
				fuzzySearch: false,
				searchKeys: ['value'],
			},
			originalInputValueFormat: function (valuesArr) {
				return valuesArr
					.map(function (item) {
						return item.value;
					})
					.join(',');
			},
		});

		tagify.on('input', function (e) {
			var value = e.detail.value;
			clearTimeout(suggestTimer);
			if (!c.ajaxurl || !c.nonce) {
				return;
			}
			suggestTimer = setTimeout(function () {
				if (typeof tagify.loading === 'function') {
					tagify.loading(true);
				}
				$.get(c.ajaxurl, {
					action: 'vce_suggest_attachment_tags',
					nonce: c.nonce,
					q: value,
				})
					.done(function (resp) {
						var list = [];
						if (resp && resp.success && Array.isArray(resp.data)) {
							list = resp.data.map(function (t) {
								return typeof t === 'string' ? { value: t } : t;
							});
						}
						tagify.whitelist = list;
						if (typeof tagify.loading === 'function') {
							tagify.loading(false);
						}
						try {
							if (tagify.dropdown && typeof tagify.dropdown.show === 'function') {
								tagify.dropdown.show(value);
							}
						} catch (err) {}
					})
					.fail(function () {
						if (typeof tagify.loading === 'function') {
							tagify.loading(false);
						}
					});
			}, 220);
		});

		input._vceTagifyInstance = tagify;
		input._vceTagifyReady = true;
		$(input).data('vceTagify', tagify);
	}

	function bindInRoot($root) {
		$root.find('input.vce-attachment-tags-input').each(function () {
			bindInput(this);
		});
	}

	function patchAttachmentDetailsViews() {
		if (mediaPatched || typeof wp === 'undefined' || !wp.media || !wp.media.view || !wp.media.view.Attachment) {
			return;
		}
		var Details = wp.media.view.Attachment.Details;
		if (!Details) {
			return;
		}
		var TwoColumn = Details.TwoColumn;

		var wrapRender = function (protoRender) {
			return function () {
				var self = this;
				this.$el.find('input.vce-attachment-tags-input').each(function () {
					destroyTagify(this);
				});
				var out = protoRender.apply(this, arguments);
				_.defer(function () {
					bindInRoot(self.$el);
				});
				return out;
			};
		};

		var NewDetails = Details.extend({
			render: wrapRender(Details.prototype.render),
		});

		if (TwoColumn) {
			NewDetails.TwoColumn = TwoColumn.extend({
				render: wrapRender(TwoColumn.prototype.render),
			});
		}

		wp.media.view.Attachment.Details = NewDetails;
		mediaPatched = true;
	}

	$(function () {
		patchAttachmentDetailsViews();
		bindInRoot($(document));
	});
})(jQuery);
