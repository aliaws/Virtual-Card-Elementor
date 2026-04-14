/**
 * Shared panel renderer used by editor preview and submission final view.
 */
(function () {
	'use strict';

	function fixTextBaseline(list) {
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
				fixTextBaseline(item.objects);
			}
		});
	}

	function scaleObjectsForTarget(objects, baseW, baseH, targetW, targetH) {
		if (!Array.isArray(objects) || !objects.length) {
			return objects;
		}
		if (!baseW || !baseH || baseW <= 0 || baseH <= 0) {
			return objects;
		}
		if (!targetW || !targetH || targetW <= 0 || targetH <= 0) {
			return objects;
		}
		var ratioX = targetW / baseW;
		var ratioY = targetH / baseH;
		if (Math.abs(ratioX - 1) < 0.0001 && Math.abs(ratioY - 1) < 0.0001) {
			return objects;
		}
		return objects.map(function (obj) {
			if (!obj || typeof obj !== 'object') {
				return obj;
			}
			var copy = JSON.parse(JSON.stringify(obj));
			if (typeof copy.left === 'number') {
				copy.left = copy.left * ratioX;
			}
			if (typeof copy.top === 'number') {
				copy.top = copy.top * ratioY;
			}
			if (typeof copy.scaleX === 'number') {
				copy.scaleX = copy.scaleX * ratioX;
			}
			if (typeof copy.scaleY === 'number') {
				copy.scaleY = copy.scaleY * ratioY;
			}
			if (typeof copy.fontSize === 'number') {
				copy.fontSize = copy.fontSize * ratioY;
			}
			if (typeof copy.strokeWidth === 'number') {
				copy.strokeWidth = copy.strokeWidth * ratioY;
			}
			if (typeof copy.width === 'number' && !copy.path) {
				copy.width = copy.width * ratioX;
			}
			if (typeof copy.height === 'number' && !copy.path) {
				copy.height = copy.height * ratioY;
			}
			return copy;
		});
	}

	function renderOne(panel, objects, baseW, baseH, maxW, maxH, cb) {
		if (!panel || !panel.url || typeof fabric === 'undefined') {
			cb('');
			return;
		}

		var el = document.createElement('canvas');
		var sc = new fabric.StaticCanvas(el);

		fabric.Image.fromURL(panel.url, function (bg, isError) {
			if (isError || !bg || !bg.width) {
				try {
					sc.dispose();
				} catch (e) {}
				cb('');
				return;
			}

			var iw = bg.width || 1;
			var ih = bg.height || 1;
			var scale = Math.min(maxW / iw, maxH / ih, 1);
			var w = Math.round(iw * scale);
			var h = Math.round(ih * scale);
			sc.setDimensions({ width: w, height: h });
			bg.set({ selectable: false, evented: false, originX: 'left', originY: 'top', left: 0, top: 0 });
			bg.scaleX = w / iw;
			bg.scaleY = h / ih;

			sc.setBackgroundImage(bg, function () {
				if (!Array.isArray(objects) || !objects.length) {
					try {
						cb(sc.toDataURL({ format: 'png', quality: 1, multiplier: 1 }));
					} catch (e) {
						cb(panel.url);
					}
					try {
						sc.dispose();
					} catch (e2) {}
					return;
				}

				var scaledObjects = scaleObjectsForTarget(objects, baseW, baseH, w, h);
				fixTextBaseline(scaledObjects);
				fabric.util.enlivenObjects(scaledObjects, function (enlived) {
					enlived.forEach(function (o) {
						sc.add(o);
					});
					sc.renderAll();
					try {
						cb(sc.toDataURL({ format: 'png', quality: 1, multiplier: 1 }));
					} catch (e) {
						cb(panel.url);
					}
					try {
						sc.dispose();
					} catch (e2) {}
				});
			});
		});
	}

	function buildPreviewUrls(panels, layersByPanel, maxW, maxH, done) {
		if (!Array.isArray(panels) || !panels.length) {
			done([]);
			return;
		}
		var urls = [];
		var i = 0;
		function next() {
			if (i >= panels.length) {
				done(urls);
				return;
			}
			var state = layersByPanel && layersByPanel[String(i)];
			var objects = state && Array.isArray(state.objects) ? state.objects : [];
			var baseW = state && typeof state.baseW === 'number' ? state.baseW : 0;
			var baseH = state && typeof state.baseH === 'number' ? state.baseH : 0;
			renderOne(panels[i], objects, baseW, baseH, maxW, maxH, function (url) {
				urls.push(url || (panels[i] && panels[i].url) || '');
				i += 1;
				next();
			});
		}
		next();
	}

	function renderOverlayForPanel(panel, objects, baseW, baseH, maxW, maxH, cb) {
		if (!panel || !panel.url || typeof fabric === 'undefined') {
			cb({ baseUrl: '', overlayUrl: '', width: 0, height: 0 });
			return;
		}

		fabric.Image.fromURL(panel.url, function (bg, isError) {
			if (isError || !bg || !bg.width) {
				cb({ baseUrl: panel.url || '', overlayUrl: '', width: 0, height: 0 });
				return;
			}

			var iw = bg.width || 1;
			var ih = bg.height || 1;
			var scale = Math.min(maxW / iw, maxH / ih, 1);
			var w = Math.max(1, Math.round(iw * scale));
			var h = Math.max(1, Math.round(ih * scale));

			if (!Array.isArray(objects) || !objects.length) {
				cb({ baseUrl: panel.url, overlayUrl: '', width: w, height: h });
				return;
			}

			var el = document.createElement('canvas');
			var sc = new fabric.StaticCanvas(el);
			sc.setDimensions({ width: w, height: h });
			sc.setBackgroundColor('rgba(0,0,0,0)', function () {});

			var scaledObjects = scaleObjectsForTarget(objects, baseW, baseH, w, h);
			fixTextBaseline(scaledObjects);
			fabric.util.enlivenObjects(scaledObjects, function (enlived) {
				enlived.forEach(function (o) {
					sc.add(o);
				});
				sc.renderAll();
				var overlayUrl = '';
				try {
					overlayUrl = sc.toDataURL({ format: 'png', quality: 1, multiplier: 1 });
				} catch (e) {
					overlayUrl = '';
				}
				try {
					sc.dispose();
				} catch (e2) {}
				cb({ baseUrl: panel.url, overlayUrl: overlayUrl, width: w, height: h });
			});
		});
	}

	function buildPreviewSlides(panels, layersByPanel, maxW, maxH, done) {
		if (!Array.isArray(panels) || !panels.length) {
			done([]);
			return;
		}

		var slides = [];
		var i = 0;

		function fitSize(panel) {
			var iw = panel && typeof panel.w === 'number' ? panel.w : 0;
			var ih = panel && typeof panel.h === 'number' ? panel.h : 0;
			if (iw <= 0 || ih <= 0) {
				return { width: 0, height: 0 };
			}
			var scale = Math.min(maxW / iw, maxH / ih, 1);
			return {
				width: Math.max(1, Math.round(iw * scale)),
				height: Math.max(1, Math.round(ih * scale)),
			};
		}

		function next() {
			if (i >= panels.length) {
				done(slides);
				return;
			}
			var panel = panels[i] || {};
			var state = layersByPanel && layersByPanel[String(i)];
			var objects = state && Array.isArray(state.objects) ? state.objects : [];
			var baseW = state && typeof state.baseW === 'number' ? state.baseW : 0;
			var baseH = state && typeof state.baseH === 'number' ? state.baseH : 0;
			renderOverlayForPanel(panel, objects, baseW, baseH, maxW, maxH, function (slide) {
				var fallback = fitSize(panel);
				var normalized = slide || {};
				slides.push({
					baseUrl: normalized.baseUrl || panel.url || '',
					overlayUrl: normalized.overlayUrl || '',
					width: normalized.width || fallback.width || 0,
					height: normalized.height || fallback.height || 0,
				});
				i += 1;
				next();
			});
		}
		next();
	}

	window.vcePanelRenderer = {
		buildPreviewUrls: buildPreviewUrls,
		buildPreviewSlides: buildPreviewSlides,
	};
})();
