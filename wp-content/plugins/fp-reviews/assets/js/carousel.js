/**
 * FirmPilot carousel: scroll sync, optional autoplay, pause on hover / tab hidden, dots, arrows, keyboard.
 */
(function () {
	'use strict';

	function qs(root, sel) {
		return root.querySelector(sel);
	}

	function qsa(root, sel) {
		return Array.prototype.slice.call(root.querySelectorAll(sel));
	}

	function prefersReducedMotion() {
		return (
			typeof window.matchMedia === 'function' &&
			window.matchMedia('(prefers-reduced-motion: reduce)').matches
		);
	}

	function initCarousel(root) {
		if (root.getAttribute('data-fp-carousel-initialized') === '1') {
			return;
		}
		var track = qs(root, '.fp-carousel__slides');
		if (!track) return;

		var slides = Array.prototype.filter.call(track.children, function (el) {
			return el.tagName === 'LI';
		});
		if (!slides.length) return;

		root.setAttribute('data-fp-carousel-initialized', '1');

		var reduceMotion = prefersReducedMotion();
		var autoplayMs = reduceMotion ? 0 : parseInt(root.getAttribute('data-autoplay-ms'), 10) || 0;
		var pauseOnHover = root.getAttribute('data-pause-on-hover') !== '0';
		var dotsWrap = qs(root, '[data-fp-carousel-dots]');
		var prevBtn = qs(root, '[data-fp-carousel-prev]');
		var nextBtn = qs(root, '[data-fp-carousel-next]');

		var timer = null;
		var hovered = false;
		var hidden = typeof document.hidden !== 'undefined' && document.hidden;

		function scrollBehavior() {
			return reduceMotion ? 'auto' : 'smooth';
		}

		function currentIndex() {
			var x = track.scrollLeft;
			var best = 0;
			var bestDelta = Infinity;
			slides.forEach(function (slide, i) {
				var d = Math.abs(slide.offsetLeft - x);
				if (d < bestDelta) {
					bestDelta = d;
					best = i;
				}
			});
			return best;
		}

		function syncSlideVisibility(activeIdx) {
			slides.forEach(function (slide, i) {
				var isActive = i === activeIdx;
				slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
				if ('inert' in slide) {
					slide.inert = !isActive;
				}
			});
		}

		function syncDots(activeIdx) {
			if (!dotsWrap) return;
			var dots = qsa(dotsWrap, '.fp-carousel__dot');
			dots.forEach(function (dot, i) {
				var on = i === activeIdx;
				dot.classList.toggle('fp-carousel__dot--active', on);
				dot.setAttribute('aria-selected', on ? 'true' : 'false');
				dot.setAttribute('tabindex', on ? '0' : '-1');
			});
		}

		function goTo(i) {
			var max = slides.length - 1;
			var idx = Math.max(0, Math.min(max, i));
			var left = slides[idx] ? slides[idx].offsetLeft : 0;
			track.scrollTo({ left: left, behavior: scrollBehavior() });
			syncDots(idx);
			syncSlideVisibility(idx);
		}

		function buildDots() {
			if (!dotsWrap || slides.length < 2) return;
			var existing = qsa(dotsWrap, '.fp-carousel__dot');
			if (existing.length !== slides.length) {
				dotsWrap.innerHTML = '';
				slides.forEach(function (slide, i) {
					var id = slide.id || root.id + '-slide-' + i;
					if (!slide.id) slide.id = id;
					var btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'fp-carousel__dot';
					btn.setAttribute('role', 'tab');
					btn.setAttribute('aria-label', 'Slide ' + (i + 1) + ' of ' + slides.length);
					btn.setAttribute('aria-controls', id);
					btn.setAttribute('data-slide-index', String(i));
					dotsWrap.appendChild(btn);
				});
			} else {
				slides.forEach(function (slide, i) {
					var id = slide.id || root.id + '-slide-' + i;
					if (!slide.id) slide.id = id;
					if (existing[i]) {
						existing[i].setAttribute('aria-controls', id);
					}
				});
			}
			if (dotsWrap.getAttribute('data-fp-carousel-dots-delegation') !== '1') {
				dotsWrap.setAttribute('data-fp-carousel-dots-delegation', '1');
				dotsWrap.addEventListener('click', function (e) {
					var btn = e.target.closest('.fp-carousel__dot');
					if (!btn || !dotsWrap.contains(btn)) return;
					var si = parseInt(btn.getAttribute('data-slide-index'), 10);
					if (!isNaN(si)) goTo(si);
				});
				dotsWrap.addEventListener('keydown', function (e) {
					var dots = qsa(dotsWrap, '.fp-carousel__dot');
					if (!dots.length) return;
					var idx = currentIndex();
					if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
						e.preventDefault();
						var next =
							e.key === 'ArrowRight'
								? Math.min(slides.length - 1, idx + 1)
								: Math.max(0, idx - 1);
						goTo(next);
						if (dots[next]) dots[next].focus();
					}
				});
			}
			syncDots(currentIndex());
		}

		function stopAutoplay() {
			if (timer) {
				clearInterval(timer);
				timer = null;
			}
		}

		function startAutoplay() {
			stopAutoplay();
			if (autoplayMs < 500 || slides.length < 2 || reduceMotion) return;
			if (hidden || (pauseOnHover && hovered)) return;
			timer = setInterval(function () {
				var next = (currentIndex() + 1) % slides.length;
				goTo(next);
			}, autoplayMs);
		}

		function onScroll() {
			var idx = currentIndex();
			syncDots(idx);
			syncSlideVisibility(idx);
		}

		track.addEventListener('scroll', onScroll, { passive: true });

		if (prevBtn) {
			prevBtn.addEventListener('click', function () {
				goTo(currentIndex() - 1);
			});
		}
		if (nextBtn) {
			nextBtn.addEventListener('click', function () {
				goTo(currentIndex() + 1);
			});
		}

		root.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft') {
				e.preventDefault();
				goTo(currentIndex() - 1);
			} else if (e.key === 'ArrowRight') {
				e.preventDefault();
				goTo(currentIndex() + 1);
			} else if (e.key === 'Home') {
				e.preventDefault();
				goTo(0);
			} else if (e.key === 'End') {
				e.preventDefault();
				goTo(slides.length - 1);
			}
		});

		if (pauseOnHover) {
			root.addEventListener('mouseenter', function () {
				hovered = true;
				stopAutoplay();
			});
			root.addEventListener('mouseleave', function () {
				hovered = false;
				startAutoplay();
			});
		}

		document.addEventListener('visibilitychange', function () {
			hidden = document.hidden;
			if (hidden) stopAutoplay();
			else startAutoplay();
		});

		slides.forEach(function (slide, i) {
			if (!slide.id) {
				slide.id = root.id + '-slide-' + i;
			}
			if (!slide.getAttribute('role')) {
				slide.setAttribute('role', 'group');
			}
			if (!slide.getAttribute('aria-roledescription')) {
				slide.setAttribute('aria-roledescription', 'slide');
			}
		});

		buildDots();
		syncSlideVisibility(currentIndex());
		startAutoplay();

		window.addEventListener(
			'resize',
			function () {
				goTo(currentIndex());
			},
			{ passive: true }
		);
	}

	function initAll() {
		qsa(document, '[data-fp-carousel]').forEach(initCarousel);
	}

	var debounceTimer = null;
	function scheduleInitAll() {
		if (debounceTimer) clearTimeout(debounceTimer);
		debounceTimer = setTimeout(function () {
			debounceTimer = null;
			initAll();
		}, 100);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}

	if (typeof MutationObserver !== 'undefined') {
		new MutationObserver(scheduleInitAll).observe(document.documentElement, {
			childList: true,
			subtree: true
		});
	}
})();
