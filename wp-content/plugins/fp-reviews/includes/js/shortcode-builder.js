/**
 * Reviews for FirmPilot – Shortcode Builder: build [firmpilot_reviews …].
 */
(function () {
	'use strict';

	function escAttr(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function buildShortcode() {
		var cfg = window.fpReviewsShortcodeBuilder || {};
		var tag = cfg.tag || 'firmpilot_reviews';
		var form = document.getElementById('fp-reviews-shortcode-reviews');
		var out = document.getElementById('fp-reviews-shortcode-reviews-output');
		if (!form || !out) {
			return;
		}
		var els = form.querySelectorAll('[data-fp-shortcode-field]');
		var parts = [];
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			var k = el.getAttribute('data-fp-shortcode-field');
			if (!k) {
				continue;
			}
			var v;
			if (el.type === 'checkbox') {
				v = el.checked ? '1' : '0';
			} else {
				v = (el.value || '').trim();
			}
			if (v === '') {
				continue;
			}
			parts.push(k + '="' + escAttr(v) + '"');
		}
		out.value = parts.length ? '[' + tag + ' ' + parts.join(' ') + ']' : '[' + tag + ']';
	}

	function bind() {
		var gen = document.getElementById('fp-reviews-shortcode-reviews-generate');
		var copyBtn = document.getElementById('fp-reviews-shortcode-reviews-copy');
		var out = document.getElementById('fp-reviews-shortcode-reviews-output');
		var msg = document.querySelector('.fp-reviews-shortcode-builder--reviews .fp-reviews-shortcode-copy-msg');
		var cfg = window.fpReviewsShortcodeBuilder || {};

		if (gen) {
			gen.addEventListener('click', buildShortcode);
		}
		if (copyBtn && out) {
			copyBtn.addEventListener('click', function () {
				buildShortcode();
				out.focus();
				out.select();
				if (msg) {
					msg.hidden = false;
				}
				try {
					document.execCommand('copy');
					if (msg) {
						msg.textContent = cfg.copyDone || 'Copied.';
					}
				} catch (e) {
					if (msg) {
						msg.textContent = cfg.copyManual || '';
					}
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
