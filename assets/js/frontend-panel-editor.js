/**
 * Front-end card panel editor: Fabric.js, global fonts, final review carousel.
 */
(function () {
	'use strict';

	var STORAGE_VERSION = 2;
	var STORAGE_PREFIX = 'vce_panel_editor_';
	var PREVIEW_MAX_W = 1400;
	var PREVIEW_MAX_H = 960;

	function parsePanels(root) {
		try {
			var raw = root.getAttribute('data-panels') || '[]';
			var data = JSON.parse(raw);
			return Array.isArray(data) ? data : [];
		} catch (e) {
			return [];
		}
	}

	function clamp(n, min, max) {
		return Math.max(min, Math.min(max, n));
	}

	function fillToHex(fill) {
		if (!fill || fill === '') {
			return '#1d2327';
		}
		if (typeof fill === 'string' && fill.charAt(0) === '#') {
			return fill.length >= 7 ? fill.slice(0, 7) : fill;
		}
		if (typeof fill === 'object' && fill !== null && typeof fill.toHex === 'function') {
			return fill.toHex();
		}
		var m = String(fill).match(/\d+/g);
		if (m && m.length >= 3) {
			return (
				'#' +
				('0' + parseInt(m[0], 10).toString(16)).slice(-2) +
				('0' + parseInt(m[1], 10).toString(16)).slice(-2) +
				('0' + parseInt(m[2], 10).toString(16)).slice(-2)
			);
		}
		return '#1d2327';
	}

	function isTextObject(obj) {
		if (!obj) {
			return false;
		}
		var t = obj.type;
		return t === 'i-text' || t === 'textbox' || t === 'text';
	}

	function normalizeHex(h) {
		var x = fillToHex(h || '#000000').toLowerCase();
		if (x.length === 4 && x.charAt(0) === '#') {
			return (
				'#' +
				x.charAt(1) +
				x.charAt(1) +
				x.charAt(2) +
				x.charAt(2) +
				x.charAt(3) +
				x.charAt(3)
			);
		}
		return x;
	}

	function isBoldVal(w) {
		return w === 'bold' || w === 700 || String(w) === '700';
	}

	function isItalicVal(s) {
		return s === 'italic' || s === 'oblique';
	}

	/**
	 * Canvas 2D textBaseline is "alphabetic", not "alphabetical". Invalid values trigger Chromium warnings in Fabric._setTextStyles.
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

	function init(root) {
		if (typeof fabric === 'undefined') {
			return;
		}

		var panels = parsePanels(root);
		if (!panels.length) {
			return;
		}

		var postId = root.getAttribute('data-post-id') || '0';
		var storageKey = STORAGE_PREFIX + postId;
		var cfg = typeof vcePanelEditor !== 'undefined' ? vcePanelEditor : {};
		var i18n = cfg.i18n || {};

		if (cfg.vceDiag && typeof console !== 'undefined' && console.info) {
			console.info('[VCE panel editor]', cfg.vceDiag);
		}
		var defaultText = i18n.defaultText || 'Your text';
		var fontStacks = cfg.fontStacks || {};
		var defaultFontKey = root.getAttribute('data-default-font') || cfg.defaultFont || 'system';

		var stageLabel = root.querySelector('[data-vce-panel-label]');
		var canvasEl = root.querySelector('[data-vce-fabric-canvas]');
		var wrapEl = root.querySelector('[data-vce-canvas-wrap]');
		var btnAdd = root.querySelector('[data-vce-add-text]');
		var btnReview = root.querySelector('[data-vce-final-review]');
		var btnDel = root.querySelector('[data-vce-delete-layer]');
		var inputSize = root.querySelector('[data-vce-font-size]');
		var inputColor = root.querySelector('[data-vce-text-color]');
		var selectFont = root.querySelector('[data-vce-font-family]');
		var btnBold = root.querySelector('[data-vce-text-bold]');
		var btnItalic = root.querySelector('[data-vce-text-italic]');
		var btnUnderline = root.querySelector('[data-vce-text-underline]');
		var selectColorPreset = root.querySelector('[data-vce-color-preset]');
		var sizeReadout = root.querySelector('[data-vce-font-size-display]');
		var thumbs = root.querySelectorAll('[data-vce-thumb]');

		var modal = root.querySelector('[data-vce-preview-modal]');
		var modalLoading = root.querySelector('[data-vce-preview-loading]');
		var modalBody = root.querySelector('[data-vce-preview-body]');
		var modalTitle = root.querySelector('[data-vce-preview-title]');
		var modalMain = root.querySelector('[data-vce-preview-main]');
		var btnPrev = root.querySelector('[data-vce-preview-prev]');
		var btnNext = root.querySelector('[data-vce-preview-next]');

		if (!canvasEl || !wrapEl) {
			return;
		}

		if (selectFont && defaultFontKey) {
			selectFont.value = defaultFontKey;
		}

		function getFontStack() {
			var key = selectFont ? selectFont.value : defaultFontKey;
			if (fontStacks[key]) {
				return fontStacks[key];
			}
			return fontStacks.system || '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
		}

		var fabricCanvas = new fabric.Canvas(canvasEl, {
			preserveObjectStacking: true,
			selection: true,
		});

		var activeIndex = 0;
		var layersByPanel = {};
		/** True while local draft has text layers (browser-only; warn on leave). */
		var draftDirty = false;
		var loadingPanel = false;
		var previewUrls = [];
		var previewIndex = 0;
		var previewScrollLocked = false;
		var savedBodyOverflow = '';
		var savedHtmlOverflow = '';
		var previewModalParent = null;
		var previewModalNext = null;

		/**
		 * Move preview to document.body so position:fixed is viewport-wide (Elementor columns use transform).
		 */
		function mountPreviewOnBody() {
			if (!modal || modal.parentNode === document.body) {
				return;
			}
			previewModalParent = modal.parentNode;
			previewModalNext = modal.nextSibling;
			document.body.appendChild(modal);
		}

		function unmountPreviewFromBody() {
			if (!modal || !previewModalParent) {
				return;
			}
			try {
				if (previewModalNext && previewModalNext.parentNode === previewModalParent) {
					previewModalParent.insertBefore(modal, previewModalNext);
				} else {
					previewModalParent.appendChild(modal);
				}
			} catch (e) {
				previewModalParent.appendChild(modal);
			}
			previewModalParent = null;
			previewModalNext = null;
		}

		function lockFullPagePreviewScroll() {
			if (previewScrollLocked) {
				return;
			}
			savedBodyOverflow = document.body.style.overflow;
			savedHtmlOverflow = document.documentElement.style.overflow;
			document.body.style.overflow = 'hidden';
			document.documentElement.style.overflow = 'hidden';
			previewScrollLocked = true;
		}

		function unlockFullPagePreviewScroll() {
			if (!previewScrollLocked) {
				return;
			}
			document.body.style.overflow = savedBodyOverflow;
			document.documentElement.style.overflow = savedHtmlOverflow;
			previewScrollLocked = false;
		}

		function loadDraft() {
			try {
				var raw = localStorage.getItem(storageKey);
				if (!raw) {
					return;
				}
				var data = JSON.parse(raw);
				if (!data || typeof data.panels !== 'object') {
					return;
				}
				if (data.v !== STORAGE_VERSION) {
					return;
				}
				layersByPanel = data.panels;
			} catch (e) {
				layersByPanel = {};
			}
		}

		function saveDraft() {
			try {
				localStorage.setItem(
					storageKey,
					JSON.stringify({
						v: STORAGE_VERSION,
						panels: layersByPanel,
					})
				);
			} catch (e) {
				/* quota */
			}
		}

		function hasAnyLayerContent() {
			var k;
			for (k in layersByPanel) {
				if (!Object.prototype.hasOwnProperty.call(layersByPanel, k)) {
					continue;
				}
				var st = layersByPanel[k];
				if (st && Array.isArray(st.objects) && st.objects.length > 0) {
					return true;
				}
			}
			return false;
		}

		function updateDraftDirtyFlag() {
			draftDirty = hasAnyLayerContent();
		}

		function getPanelState(index) {
			var k = String(index);
			var st = layersByPanel[k];
			if (st && Array.isArray(st.objects)) {
				return st.objects;
			}
			return [];
		}

		function saveCurrentPanelObjects() {
			if (loadingPanel) {
				return;
			}
			var objs = fabricCanvas.getObjects().map(function (o) {
				return o.toObject();
			});
			fixCanvasTextBaselineInPayloads(objs);
			layersByPanel[String(activeIndex)] = { objects: objs };
			saveDraft();
			updateDraftDirtyFlag();
		}

		function applyPanelContents(targetCanvas, index, maxW, maxH, onDone) {
			var url = panels[index].url;
			if (!url) {
				onDone(false);
				return;
			}
			var objects = getPanelState(index);

			fabric.Image.fromURL(url, function (bg, isError) {
				if (isError || !bg || !bg.width) {
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

		function getTextStyleSnapshot(obj) {
			if (!isTextObject(obj)) {
				return null;
			}
			var textLen = (obj.text && obj.text.length) || 0;
			if (obj.isEditing && textLen === 0) {
				return {
					fontWeight: obj.fontWeight,
					fontStyle: obj.fontStyle,
					underline: !!obj.underline,
					fill: obj.fill,
				};
			}
			var fw;
			var fs;
			var ul;
			var fl;
			if (obj.isEditing) {
				var a = Math.min(obj.selectionStart, obj.selectionEnd);
				var b = Math.max(obj.selectionStart, obj.selectionEnd);
				if (a === b && textLen > 0) {
					if (a >= textLen) {
						a = textLen - 1;
						b = textLen;
					} else {
						b = Math.min(a + 1, textLen);
					}
				}
				var st = {};
				if (typeof obj.getSelectionStyles === 'function' && textLen > 0) {
					try {
						var raw = obj.getSelectionStyles(a, b);
						if (Array.isArray(raw) && raw.length) {
							st = raw[0] || {};
						} else if (raw && typeof raw === 'object') {
							st = raw;
						}
					} catch (e) {
						st = {};
					}
				}
				fw = st.fontWeight !== undefined && st.fontWeight !== '' ? st.fontWeight : obj.fontWeight;
				fs = st.fontStyle !== undefined && st.fontStyle !== '' ? st.fontStyle : obj.fontStyle;
				ul = st.underline !== undefined ? st.underline : obj.underline;
				fl = st.fill !== undefined && st.fill !== null && st.fill !== '' ? st.fill : obj.fill;
			} else {
				fw = obj.fontWeight;
				fs = obj.fontStyle;
				ul = obj.underline;
				fl = obj.fill;
			}
			return {
				fontWeight: fw,
				fontStyle: fs,
				underline: !!ul,
				fill: fl,
			};
		}

		function syncColorPresetSelect(hex) {
			if (!selectColorPreset) {
				return;
			}
			var n = '';
			if (hex != null && hex !== '') {
				try {
					n = normalizeHex(hex);
				} catch (e) {
					n = '';
				}
			} else if (inputColor && inputColor.value) {
				try {
					n = normalizeHex(inputColor.value);
				} catch (e2) {
					n = '';
				}
			}
			var found = false;
			var opts = selectColorPreset.querySelectorAll('option');
			Array.prototype.forEach.call(opts, function (opt) {
				if (!opt.value || opt.value === '__custom__') {
					return;
				}
				if (normalizeHex(opt.value) === n) {
					found = true;
					selectColorPreset.value = opt.value;
				}
			});
			if (!found) {
				selectColorPreset.value = '__custom__';
			}
		}

		function updateSizeReadout() {
			if (sizeReadout && inputSize) {
				sizeReadout.textContent = String(inputSize.value);
			}
		}

		function setFormatButtonsEnabled(on) {
			[btnBold, btnItalic, btnUnderline].forEach(function (b) {
				if (!b) {
					return;
				}
				b.disabled = !on;
				if (!on) {
					b.setAttribute('aria-pressed', 'false');
					b.classList.remove('is-active');
				}
			});
		}

		function syncToolbarFromSelection() {
			var obj = fabricCanvas.getActiveObject();
			if (isTextObject(obj)) {
				if (inputSize) {
					inputSize.value = String(clamp(Math.round(obj.fontSize || 28), 12, 96));
				}
				updateSizeReadout();
				var snap = getTextStyleSnapshot(obj);
				if (inputColor && snap) {
					inputColor.value = fillToHex(snap.fill);
				}
				if (snap) {
					syncColorPresetSelect(snap.fill);
				}
				if (btnDel) {
					btnDel.disabled = false;
				}
				setFormatButtonsEnabled(true);
				if (btnBold && snap) {
					var bOn = isBoldVal(snap.fontWeight);
					btnBold.setAttribute('aria-pressed', bOn ? 'true' : 'false');
					btnBold.classList.toggle('is-active', bOn);
				}
				if (btnItalic && snap) {
					var iOn = isItalicVal(snap.fontStyle);
					btnItalic.setAttribute('aria-pressed', iOn ? 'true' : 'false');
					btnItalic.classList.toggle('is-active', iOn);
				}
				if (btnUnderline && snap) {
					var uOn = snap.underline;
					btnUnderline.setAttribute('aria-pressed', uOn ? 'true' : 'false');
					btnUnderline.classList.toggle('is-active', uOn);
				}
			} else {
				if (btnDel) {
					btnDel.disabled = true;
				}
				setFormatButtonsEnabled(false);
				syncColorPresetSelect(inputColor ? inputColor.value : '');
			}
		}

		function applyTextStyleProp(props) {
			var t = fabricCanvas.getActiveObject();
			if (!isTextObject(t)) {
				return;
			}
			var hasRange = t.isEditing && t.selectionStart !== t.selectionEnd;
			if (hasRange) {
				t.setSelectionStyles(props);
			} else {
				t.set(props);
			}
			t.dirty = true;
			fabricCanvas.requestRenderAll();
			saveCurrentPanelObjects();
			syncToolbarFromSelection();
		}

		function toggleBold() {
			var t = fabricCanvas.getActiveObject();
			if (!isTextObject(t)) {
				return;
			}
			var snap = getTextStyleSnapshot(t);
			applyTextStyleProp({ fontWeight: isBoldVal(snap.fontWeight) ? 'normal' : 'bold' });
		}

		function toggleItalic() {
			var t = fabricCanvas.getActiveObject();
			if (!isTextObject(t)) {
				return;
			}
			var snap = getTextStyleSnapshot(t);
			applyTextStyleProp({ fontStyle: isItalicVal(snap.fontStyle) ? 'normal' : 'italic' });
		}

		function toggleUnderline() {
			var t = fabricCanvas.getActiveObject();
			if (!isTextObject(t)) {
				return;
			}
			var snap = getTextStyleSnapshot(t);
			applyTextStyleProp({ underline: !snap.underline });
		}

		function updatePanelLabel() {
			if (stageLabel) {
				stageLabel.textContent = 'Panel ' + (activeIndex + 1) + ' / ' + panels.length;
			}
		}

		function updateThumbs() {
			thumbs.forEach(function (btn) {
				var i = parseInt(btn.getAttribute('data-index'), 10);
				var on = i === activeIndex;
				btn.classList.toggle('is-active', on);
				btn.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
		}

		function getMaxStageSize() {
			var maxW = wrapEl.clientWidth || root.clientWidth || 800;
			maxW = Math.min(maxW, 920);
			var maxH = Math.min(Math.round(window.innerHeight * 0.65), 640);
			return { maxW: maxW, maxH: maxH };
		}

		function loadPanel(index, skipSave) {
			if (index < 0 || index >= panels.length) {
				return;
			}
			if (!skipSave) {
				saveCurrentPanelObjects();
			}
			loadingPanel = true;
			var sizes = getMaxStageSize();
			applyPanelContents(fabricCanvas, index, sizes.maxW, sizes.maxH, function () {
				activeIndex = index;
				updatePanelLabel();
				updateThumbs();
				loadingPanel = false;
				syncToolbarFromSelection();
				updateDraftDirtyFlag();
			});
		}

		function addText() {
			var cw = fabricCanvas.getWidth() || 400;
			var ch = fabricCanvas.getHeight() || 300;
			var fs = inputSize ? parseInt(inputSize.value, 10) || 28 : 28;
			var col = inputColor ? inputColor.value : '#1d2327';

			var text = new fabric.IText(defaultText, {
				left: cw / 2,
				top: ch / 2,
				originX: 'center',
				originY: 'center',
				fontSize: fs,
				fill: col,
				fontFamily: getFontStack(),
			});

			fabricCanvas.add(text);
			fabricCanvas.setActiveObject(text);
			fabricCanvas.requestRenderAll();
			syncToolbarFromSelection();
			saveCurrentPanelObjects();
			text.enterEditing();
			text.selectAll();
		}

		function applyFontToSelection() {
			var obj = fabricCanvas.getActiveObject();
			if (!isTextObject(obj)) {
				return;
			}
			obj.set('fontFamily', getFontStack());
			fabricCanvas.requestRenderAll();
			saveCurrentPanelObjects();
		}

		function deleteSelected() {
			var obj = fabricCanvas.getActiveObject();
			if (!obj) {
				return;
			}
			fabricCanvas.remove(obj);
			fabricCanvas.discardActiveObject();
			fabricCanvas.requestRenderAll();
			syncToolbarFromSelection();
			saveCurrentPanelObjects();
		}

		function applySize() {
			var obj = fabricCanvas.getActiveObject();
			if (!isTextObject(obj) || !inputSize) {
				return;
			}
			obj.set('fontSize', clamp(parseInt(inputSize.value, 10) || 28, 12, 96));
			fabricCanvas.requestRenderAll();
			saveCurrentPanelObjects();
		}

		function applyColor() {
			if (!inputColor) {
				return;
			}
			var hex = inputColor.value;
			syncColorPresetSelect(hex);
			var obj = fabricCanvas.getActiveObject();
			if (!isTextObject(obj)) {
				return;
			}
			var hasRange = obj.isEditing && obj.selectionStart !== obj.selectionEnd;
			if (hasRange) {
				obj.setSelectionStyles({ fill: hex });
			} else {
				obj.set('fill', hex);
			}
			obj.dirty = true;
			fabricCanvas.requestRenderAll();
			saveCurrentPanelObjects();
			syncToolbarFromSelection();
		}

		function renderPanelStaticToDataURL(panelIndex, cb) {
			var el = document.createElement('canvas');
			var sc = new fabric.StaticCanvas(el);
			applyPanelContents(sc, panelIndex, PREVIEW_MAX_W, PREVIEW_MAX_H, function (ok) {
				if (!ok) {
					try {
						sc.dispose();
					} catch (e) {}
					cb('');
					return;
				}
				var url = '';
				try {
					url = sc.toDataURL({ format: 'png', quality: 1, multiplier: 1 });
				} catch (e) {
					url = '';
				}
				try {
					sc.dispose();
				} catch (e2) {}
				cb(url);
			});
		}

		function buildAllPreviewUrls(done) {
			saveCurrentPanelObjects();
			var urls = [];
			var i = 0;
			function next() {
				if (i >= panels.length) {
					done(urls);
					return;
				}
				renderPanelStaticToDataURL(i, function (dataUrl) {
					urls.push(dataUrl);
					i++;
					next();
				});
			}
			next();
		}

		function setPreviewSlide(idx) {
			previewIndex = clamp(idx, 0, Math.max(0, previewUrls.length - 1));
			var url = previewUrls[previewIndex];
			if (modalMain) {
				modalMain.src = url || '';
			}
			var label =
				(i18n.panelPreview || 'Card preview') +
				' · ' +
				(previewIndex + 1) +
				' / ' +
				previewUrls.length;
			if (modalTitle) {
				modalTitle.textContent = label;
			}
			if (modal) {
				var dlg = modal.querySelector('[role="dialog"]');
				if (dlg) {
					dlg.setAttribute('aria-label', label);
				}
			}
			if (btnPrev) {
				btnPrev.disabled = previewIndex <= 0;
			}
			if (btnNext) {
				btnNext.disabled = previewIndex >= previewUrls.length - 1;
			}
		}

		function openPreviewModal() {
			if (!modal) {
				return;
			}
			mountPreviewOnBody();
			lockFullPagePreviewScroll();
			modal.removeAttribute('hidden');
			if (modalLoading) {
				modalLoading.removeAttribute('hidden');
			}
			if (modalBody) {
				modalBody.setAttribute('hidden', 'hidden');
			}

			buildAllPreviewUrls(function (urls) {
				previewUrls = urls;
				previewIndex = 0;
				if (modalLoading) {
					modalLoading.setAttribute('hidden', 'hidden');
				}
				if (modalBody) {
					modalBody.removeAttribute('hidden');
				}
				/* Recipient-style preview: no thumbnail strip (prev/next + close only). */
				setPreviewSlide(0);
			});
		}

		function closePreviewModal() {
			if (!modal) {
				return;
			}
			modal.setAttribute('hidden', 'hidden');
			unlockFullPagePreviewScroll();
			unmountPreviewFromBody();
			if (modalMain) {
				modalMain.src = '';
			}
			previewUrls = [];
		}

		fabricCanvas.on('selection:created', syncToolbarFromSelection);
		fabricCanvas.on('selection:updated', syncToolbarFromSelection);
		fabricCanvas.on('selection:cleared', syncToolbarFromSelection);

		fabricCanvas.on('object:modified', saveCurrentPanelObjects);
		fabricCanvas.on('text:changed', saveCurrentPanelObjects);
		fabricCanvas.on('text:selection:changed', syncToolbarFromSelection);

		if (btnAdd) {
			btnAdd.addEventListener('click', addText);
		}
		if (btnReview) {
			btnReview.addEventListener('click', openPreviewModal);
		}
		if (btnDel) {
			btnDel.addEventListener('click', deleteSelected);
		}
		if (inputSize) {
			inputSize.addEventListener('input', function () {
				updateSizeReadout();
				applySize();
			});
		}
		if (inputColor) {
			inputColor.addEventListener('input', applyColor);
		}
		if (selectColorPreset) {
			selectColorPreset.addEventListener('change', function () {
				var v = selectColorPreset.value;
				if (v === '__custom__') {
					if (inputColor) {
						inputColor.click();
					}
					return;
				}
				if (v && inputColor) {
					inputColor.value = normalizeHex(v);
					applyColor();
				}
			});
		}
		updateSizeReadout();
		syncColorPresetSelect(inputColor ? inputColor.value : '');
		if (btnBold) {
			btnBold.addEventListener('click', toggleBold);
		}
		if (btnItalic) {
			btnItalic.addEventListener('click', toggleItalic);
		}
		if (btnUnderline) {
			btnUnderline.addEventListener('click', toggleUnderline);
		}
		if (selectFont) {
			selectFont.addEventListener('change', function () {
				applyFontToSelection();
			});
		}

		if (btnPrev) {
			btnPrev.addEventListener('click', function () {
				setPreviewSlide(previewIndex - 1);
			});
		}
		if (btnNext) {
			btnNext.addEventListener('click', function () {
				setPreviewSlide(previewIndex + 1);
			});
		}

		root.querySelectorAll('[data-vce-preview-close]').forEach(function (el) {
			el.addEventListener('click', function (e) {
				e.preventDefault();
				closePreviewModal();
			});
		});

		document.addEventListener('keydown', function (e) {
			if (!modal || modal.hasAttribute('hidden')) {
				return;
			}
			if (e.key === 'Escape') {
				closePreviewModal();
				return;
			}
			if (e.key === 'ArrowLeft' && btnPrev && !btnPrev.disabled) {
				e.preventDefault();
				setPreviewSlide(previewIndex - 1);
			}
			if (e.key === 'ArrowRight' && btnNext && !btnNext.disabled) {
				e.preventDefault();
				setPreviewSlide(previewIndex + 1);
			}
		});

		thumbs.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var i = parseInt(btn.getAttribute('data-index'), 10);
				loadPanel(i, false);
			});
		});

		window.addEventListener(
			'resize',
			debounce(function () {
				saveCurrentPanelObjects();
				loadPanel(activeIndex, true);
			}, 250)
		);

		function debounce(fn, ms) {
			var t;
			return function () {
				clearTimeout(t);
				var args = arguments;
				t = setTimeout(function () {
					fn.apply(null, args);
				}, ms);
			};
		}

		loadDraft();
		updateDraftDirtyFlag();
		loadPanel(0, true);

		window.addEventListener('beforeunload', function (e) {
			saveCurrentPanelObjects();
			if (!draftDirty) {
				return;
			}
			var msg = i18n.leaveUnsavedDraft || '';
			if (!msg) {
				return;
			}
			e.preventDefault();
			e.returnValue = msg;
			return msg;
		});
	}

	function boot() {
		document.querySelectorAll('[data-vce-panel-editor]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
