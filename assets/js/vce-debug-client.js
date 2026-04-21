/**
 * Sends window errors and unhandled rejections to VCE REST log (admins, VCE_DEBUG only).
 */
(function () {
	'use strict';

	var cfg = typeof vceDebugClient !== 'undefined' ? vceDebugClient : null;
	if (!cfg || !cfg.enabled || !cfg.restUrl || !cfg.nonce) {
		return;
	}

	var queue = [];
	var maxQueue = 80;

	function push(msg) {
		if (typeof msg !== 'string' || !msg) {
			return;
		}
		if (queue.length >= maxQueue) {
			queue.shift();
		}
		queue.push(msg.slice(0, 2000));
	}

	function flush() {
		if (!queue.length) {
			return;
		}
		var batch = queue.splice(0, 35);
		try {
			fetch(cfg.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				body: JSON.stringify({ lines: batch }),
				keepalive: true,
			}).catch(function () {});
		} catch (e) {}
	}

	window.addEventListener('error', function (ev) {
		var m = ev.message || 'error';
		if (ev.filename) {
			m += ' @' + ev.filename + ':' + (ev.lineno || 0);
		}
		push('[window.error] ' + m);
		flush();
	});

	window.addEventListener('unhandledrejection', function (ev) {
		var r = ev.reason;
		var s = r && r.stack ? r.stack : String(r);
		push('[unhandledrejection] ' + s);
		flush();
	});

	window.addEventListener('beforeunload', flush);
	setInterval(flush, 8000);
})();
