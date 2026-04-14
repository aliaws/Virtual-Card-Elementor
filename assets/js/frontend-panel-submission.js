/**
 * Submission final viewer: preview-like one-card carousel (non-editable).
 */
(function () {
	'use strict';

	function parseJsonAttr(el, attr, fallback) {
		try {
			var raw = el.getAttribute(attr) || '';
			if (!raw) {
				return fallback;
			}
			var data = JSON.parse(raw);
			return data == null ? fallback : data;
		} catch (e) {
			return fallback;
		}
	}

	function mountModal(modal) {
		if (!modal || modal.parentNode === document.body) {
			return;
		}
		document.body.appendChild(modal);
	}

	function isUsableImageUrl(url) {
		if (!url || typeof url !== 'string') {
			return false;
		}
		var trimmed = url.trim();
		return /^https?:\/\//i.test(trimmed) || /^data:image\//i.test(trimmed) || trimmed.charAt(0) === '/';
	}

	function isUsableOverlayUrl(url) {
		if (!url || typeof url !== 'string') {
			return false;
		}
		var trimmed = url.trim();
		return /^data:image\//i.test(trimmed) && trimmed.length > 32;
	}

	/** Valid minimal image so <img> never has missing src (avoids broken-icon overlay). */
	var TRANSPARENT_PIXEL =
		'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

	function setSlide(slides, idx, frame, main, overlay, title, prev, next) {
		var max = Math.max(0, slides.length - 1);
		var i = Math.max(0, Math.min(idx, max));
		var slide = slides[i] || {};
		if (frame) {
			var w = parseInt(slide.width, 10) || 0;
			var h = parseInt(slide.height, 10) || 0;
			if (w > 0 && h > 0) {
				frame.style.width = String(w) + 'px';
				frame.style.height = String(h) + 'px';
			} else {
				frame.style.width = '';
				frame.style.height = '';
			}
		}
		if (main) {
			if (isUsableImageUrl(slide.baseUrl)) {
				main.src = String(slide.baseUrl).trim();
			} else {
				main.removeAttribute('src');
			}
		}
		if (overlay) {
			if (isUsableOverlayUrl(slide.overlayUrl)) {
				overlay.src = String(slide.overlayUrl).trim();
				overlay.removeAttribute('hidden');
			} else {
				overlay.src = TRANSPARENT_PIXEL;
				overlay.setAttribute('hidden', 'hidden');
			}
		}
		if (title) {
			title.textContent = 'Panel preview · ' + (i + 1) + ' / ' + slides.length;
		}
		if (prev) {
			prev.disabled = i <= 0;
		}
		if (next) {
			next.disabled = i >= max;
		}
		return i;
	}

	function init(root) {
		var panels = parseJsonAttr(root, 'data-panels', []);
		var layers = parseJsonAttr(root, 'data-layers', {});
		if (!Array.isArray(panels) || !panels.length) {
			return;
		}

		var modal = root.querySelector('[data-vce-submission-modal]');
		var loading = root.querySelector('[data-vce-submission-loading]');
		var body = root.querySelector('[data-vce-submission-body]');
		var stage = root.querySelector('.vce-preview-modal__stage');
		var frame = root.querySelector('[data-vce-submission-frame]');
		var main = root.querySelector('[data-vce-submission-main]');
		var overlay = root.querySelector('[data-vce-submission-overlay]');
		var title = root.querySelector('[data-vce-submission-title]');
		var prev = root.querySelector('[data-vce-submission-prev]');
		var next = root.querySelector('[data-vce-submission-next]');
		var close = root.querySelectorAll('[data-vce-submission-close]');
		if (!modal || !main) {
			return;
		}
		main.addEventListener('error', function () {
			main.removeAttribute('src');
		});
		if (overlay) {
			overlay.addEventListener('error', function () {
				overlay.src = TRANSPARENT_PIXEL;
				overlay.setAttribute('hidden', 'hidden');
			});
		}

		mountModal(modal);
		document.body.style.overflow = 'hidden';

		function getRenderSize() {
			var stageW = stage ? stage.clientWidth : 0;
			var stageH = stage ? stage.clientHeight : 0;
			var maxW = stageW > 0 ? stageW : Math.max(320, window.innerWidth - 32);
			var maxH = stageH > 0 ? stageH : Math.round(window.innerHeight * 0.84);
			maxW = Math.max(320, Math.round(maxW - 8));
			maxH = Math.max(420, Math.min(maxH, 1100));
			return { maxW: maxW, maxH: maxH };
		}

		var current = 0;
		var slides = [];
		if (!window.vcePanelRenderer || typeof window.vcePanelRenderer.buildPreviewSlides !== 'function') {
			return;
		}
		var size = getRenderSize();
		window.vcePanelRenderer.buildPreviewSlides(panels, layers, size.maxW, size.maxH, function (built) {
			slides = built;
			if (loading) {
				loading.setAttribute('hidden', 'hidden');
			}
			if (body) {
				body.removeAttribute('hidden');
			}
			current = setSlide(slides, 0, frame, main, overlay, title, prev, next);
		});

		if (prev) {
			prev.addEventListener('click', function () {
				current = setSlide(slides, current - 1, frame, main, overlay, title, prev, next);
			});
		}
		if (next) {
			next.addEventListener('click', function () {
				current = setSlide(slides, current + 1, frame, main, overlay, title, prev, next);
			});
		}
		function exitViewer() {
			var ref = document.referrer || '';
			if (ref && ref !== window.location.href) {
				window.location.href = ref;
				return;
			}
			if (window.history.length > 1) {
				window.history.back();
				return;
			}
			window.location.href = '/';
		}
		close.forEach(function (el) {
			el.addEventListener('click', function () {
				exitViewer();
			});
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				exitViewer();
			}
		});
	}

	function boot() {
		document.querySelectorAll('[data-vce-submission-panels]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
