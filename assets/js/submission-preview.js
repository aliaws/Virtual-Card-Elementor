/**
 * Standalone submission preview: render Fabric layers to images, simple carousel.
 */
(function () {
	'use strict';

	var PREVIEW_MAX_W = 1400;
	var PREVIEW_MAX_H = 960;

	function clamp(n, min, max) {
		return Math.max(min, Math.min(max, n));
	}

	function getPanelState(layersByPanel, index) {
		var k = String(index);
		var st = layersByPanel[k];
		if (st && Array.isArray(st.objects)) {
			return st.objects;
		}
		return [];
	}

	/**
	 * Canvas 2D textBaseline allows "alphabetic", not "alphabetical". Fabric/older data may use the typo;
	 * Chromium logs a console warning and may ignore the value.
	 */
	function fixCanvasTextBaselineInPayloads(list) {
		if (!Array.isArray(list)) {
			return;
		}
		list.forEach(function (item) {
			if (!item || typeof item !== 'object') {
				return;
			}
			if (item.textBaseline === 'alphabetical') {
				item.textBaseline = 'alphabetic';
			}
			if (Array.isArray(item.objects)) {
				fixCanvasTextBaselineInPayloads(item.objects);
			}
		});
	}

	function applyPanelContents(fabric, targetCanvas, panels, layersByPanel, index, maxW, maxH, onDone) {
		var panel = panels[index];
		var url = panel && panel.url ? panel.url : '';
		if (!url) {
			onDone(false);
			return;
		}
		var objects = getPanelState(layersByPanel, index);

		fabric.Image.fromURL(url, function (bg) {
			if (!bg || !bg.width) {
				onDone(false);
				return;
			}
			var iw = bg.width || 1;
			var ih = bg.height || 1;
			var scale = Math.min(maxW / iw, maxH / ih, 1);
			var cw = Math.round(iw * scale);
			var ch = Math.round(ih * scale);

			targetCanvas.clear();
			targetCanvas.setDimensions({ width: cw, height: ch });

			bg.set({
				selectable: false,
				evented: false,
				originX: 'left',
				originY: 'top',
				left: 0,
				top: 0,
			});
			bg.scaleX = cw / iw;
			bg.scaleY = ch / ih;

			targetCanvas.setBackgroundImage(bg, function () {
				if (!objects.length) {
					targetCanvas.renderAll();
					onDone(true);
					return;
				}
				fixCanvasTextBaselineInPayloads(objects);
				fabric.util.enlivenObjects(objects, function (enlived) {
					enlived.forEach(function (o) {
						if (typeof o.setCoords === 'function') {
							o.setCoords();
						}
						targetCanvas.add(o);
					});
					targetCanvas.renderAll();
					onDone(true);
				});
			});
		});
	}

	function boot() {
		var cfg = typeof vceSubmissionPreview !== 'undefined' ? vceSubmissionPreview : {};
		var panels = Array.isArray(cfg.panels) ? cfg.panels : [];
		var layersByPanel = cfg.layers && typeof cfg.layers === 'object' ? cfg.layers : {};
		var i18n = cfg.i18n || {};

		if (cfg.vceDiag && typeof console !== 'undefined' && console.info) {
			console.info('[VCE submission preview]', cfg.vceDiag);
		}

		if (typeof fabric === 'undefined' || !panels.length) {
			var loadEl = document.querySelector('[data-vce-spv-loading]');
			if (loadEl) {
				loadEl.textContent = i18n.error || 'Unable to load preview.';
			}
			return;
		}

		var root = document.querySelector('[data-vce-submission-preview]');
		var loadEl = document.querySelector('[data-vce-spv-loading]');
		var bodyEl = document.querySelector('[data-vce-spv-body]');
		var mainImg = document.querySelector('[data-vce-spv-main]');
		var titleEl = document.querySelector('[data-vce-spv-title]');
		var btnPrev = document.querySelector('[data-vce-spv-prev]');
		var btnNext = document.querySelector('[data-vce-spv-next]');
		var btnClose = document.querySelector('[data-vce-spv-close]');

		var previewUrls = [];
		var previewIndex = 0;

		function setSlide(idx) {
			previewIndex = clamp(idx, 0, Math.max(0, previewUrls.length - 1));
			var url = previewUrls[previewIndex];
			if (mainImg) {
				mainImg.src = url || '';
			}
			if (titleEl) {
				titleEl.textContent =
					(i18n.panelPreview || 'Panel') +
					' · ' +
					(previewIndex + 1) +
					' / ' +
					previewUrls.length;
			}
			if (btnPrev) {
				btnPrev.disabled = previewIndex <= 0;
			}
			if (btnNext) {
				btnNext.disabled = previewIndex >= previewUrls.length - 1;
			}
		}

		function buildAll(done) {
			var el = document.createElement('canvas');
			var sc = new fabric.StaticCanvas(el);
			var urls = [];
			var i = 0;

			function next() {
				if (i >= panels.length) {
					try {
						sc.dispose();
					} catch (e) {}
					done(urls);
					return;
				}
				applyPanelContents(fabric, sc, panels, layersByPanel, i, PREVIEW_MAX_W, PREVIEW_MAX_H, function (ok) {
					if (!ok) {
						urls.push('');
					} else {
						var u = '';
						try {
							u = sc.toDataURL({ format: 'png', quality: 1, multiplier: 1 });
						} catch (e2) {
							u = '';
						}
						urls.push(u);
					}
					i++;
					next();
				});
			}
			next();
		}

		buildAll(function (urls) {
			previewUrls = urls.filter(Boolean);
			if (!previewUrls.length) {
				if (loadEl) {
					loadEl.textContent = i18n.error || 'Nothing to display.';
				}
				return;
			}
			if (loadEl) {
				loadEl.setAttribute('hidden', 'hidden');
			}
			if (bodyEl) {
				bodyEl.removeAttribute('hidden');
			}
			setSlide(0);
		});

		if (btnPrev) {
			btnPrev.addEventListener('click', function () {
				setSlide(previewIndex - 1);
			});
		}
		if (btnNext) {
			btnNext.addEventListener('click', function () {
				setSlide(previewIndex + 1);
			});
		}
		if (btnClose) {
			btnClose.addEventListener('click', function () {
				if (window.history.length > 1) {
					window.history.back();
				} else {
					window.location.href = '/';
				}
			});
		}

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && btnClose) {
				btnClose.click();
				return;
			}
			if (e.key === 'ArrowLeft' && btnPrev && !btnPrev.disabled) {
				e.preventDefault();
				setSlide(previewIndex - 1);
			}
			if (e.key === 'ArrowRight' && btnNext && !btnNext.disabled) {
				e.preventDefault();
				setSlide(previewIndex + 1);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
