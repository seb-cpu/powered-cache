/**
 * SwiftPress Settings Page JavaScript
 *
 * Handles: collapsible sections, AJAX cache clearing, sitemap refresh,
 * preload progress bar auto-refresh, master toggle sub-options.
 *
 * @package SwiftPress
 * @since   3.8
 */

/* global jQuery, swiftpressSettings */
(function ($) {
	'use strict';

	var refreshTimer = null;

	/**
	 * Collapsible sections — toggle on header click,
	 * remember state in localStorage.
	 */
	function initCollapsibleSections() {
		var storageKey = 'swiftpress_collapsed_sections';
		var collapsed  = {};

		try {
			collapsed = JSON.parse(localStorage.getItem(storageKey)) || {};
		} catch (e) {
			collapsed = {};
		}

		$('.sp-section').each(function () {
			var id = $(this).data('section');
			if (id && collapsed[id]) {
				$(this).addClass('collapsed');
			}
		});

		$('.sp-section-header').on('click', function () {
			var $section = $(this).closest('.sp-section');
			var id       = $section.data('section');

			$section.toggleClass('collapsed');

			if ($section.hasClass('collapsed')) {
				collapsed[id] = true;
			} else {
				delete collapsed[id];
			}

			try {
				localStorage.setItem(storageKey, JSON.stringify(collapsed));
			} catch (e) {
				// localStorage may be unavailable
			}
		});
	}

	/**
	 * Master toggle — enable/disable sub-options.
	 */
	function initMasterToggles() {
		$('[data-master-toggle]').each(function () {
			var $master = $(this);
			var target  = $master.data('master-toggle');
			var $subs   = $(target);

			function updateState() {
				if ($master.is(':checked')) {
					$subs.removeClass('disabled');
				} else {
					$subs.addClass('disabled');
				}
			}

			updateState();
			$master.on('change', updateState);
		});
	}

	/**
	 * AJAX: Clear All Cache
	 */
	function initClearCache() {
		$('#sp-clear-cache').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text(swiftpressSettings.i18n.clearing);

			$.post(swiftpressSettings.ajaxUrl, {
				action: 'swiftpress_clear_cache',
				nonce:  swiftpressSettings.nonce
			}, function (resp) {
				if (resp.success) {
					showNotice('success', resp.data.message || swiftpressSettings.i18n.cacheCleared);
				} else {
					showNotice('error', resp.data.message || swiftpressSettings.i18n.error);
				}
			}).fail(function () {
				showNotice('error', swiftpressSettings.i18n.error);
			}).always(function () {
				$btn.prop('disabled', false).text(swiftpressSettings.i18n.clearAllCache);
			});
		});
	}

	/**
	 * AJAX: Clear Font Cache
	 */
	function initClearFontCache() {
		$('#sp-clear-font-cache').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true);

			$.post(swiftpressSettings.ajaxUrl, {
				action: 'swiftpress_clear_font_cache',
				nonce:  swiftpressSettings.nonce
			}, function (resp) {
				if (resp.success) {
					showNotice('success', resp.data.message || swiftpressSettings.i18n.fontCacheCleared);
				} else {
					showNotice('error', resp.data.message || swiftpressSettings.i18n.error);
				}
			}).fail(function () {
				showNotice('error', swiftpressSettings.i18n.error);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});
	}

	/**
	 * AJAX: Refresh Sitemap
	 */
	function initRefreshSitemap() {
		$('#sp-refresh-sitemap').on('click', function (e) {
			e.preventDefault();
			var $btn = $(this);
			$btn.prop('disabled', true).text(swiftpressSettings.i18n.refreshing);

			$.post(swiftpressSettings.ajaxUrl, {
				action: 'swiftpress_refresh_sitemap',
				nonce:  swiftpressSettings.nonce
			}, function (resp) {
				if (resp.success && resp.data) {
					updatePreloadStats(resp.data);
					showNotice('success', swiftpressSettings.i18n.sitemapRefreshed);
				} else {
					showNotice('error', resp.data.message || swiftpressSettings.i18n.error);
				}
			}).fail(function () {
				showNotice('error', swiftpressSettings.i18n.error);
			}).always(function () {
				$btn.prop('disabled', false).text(swiftpressSettings.i18n.refreshSitemap);
			});
		});
	}

	/**
	 * Preload Status — auto-refresh every 10 seconds when preloader is active.
	 */
	function initPreloadStatus() {
		// Initial load
		fetchPreloadStatus();

		// Check the preloader toggle to start/stop auto-refresh
		$('#enable_cache_preload, #enable_sitemap_preload').on('change', function () {
			toggleAutoRefresh();
		});

		toggleAutoRefresh();
	}

	function toggleAutoRefresh() {
		var preloadOn = $('#enable_cache_preload').is(':checked');
		var sitemapOn = $('#enable_sitemap_preload').is(':checked');

		if (preloadOn && sitemapOn) {
			if (!refreshTimer) {
				refreshTimer = setInterval(fetchPreloadStatus, 10000);
			}
		} else {
			if (refreshTimer) {
				clearInterval(refreshTimer);
				refreshTimer = null;
			}
		}
	}

	function fetchPreloadStatus() {
		$.post(swiftpressSettings.ajaxUrl, {
			action: 'swiftpress_preload_status',
			nonce:  swiftpressSettings.nonce
		}, function (resp) {
			if (resp.success && resp.data) {
				updatePreloadStats(resp.data);
			}
		});
	}

	function updatePreloadStats(data) {
		var total     = parseInt(data.total_urls, 10) || 0;
		var completed = parseInt(data.completed_count, 10) || 0;
		var pending   = parseInt(data.pending_count, 10) || 0;
		var failed    = parseInt(data.failed_count, 10) || 0;
		var pct       = total > 0 ? Math.round((completed / total) * 100) : 0;

		$('#sp-stat-total').text(total);
		$('#sp-stat-completed').text(completed);
		$('#sp-stat-pending').text(pending);
		$('#sp-stat-failed').text(failed);
		$('#sp-stat-last-run').text(data.last_run_timestamp || '—');
		$('#sp-stat-sitemap').text(data.detected_sitemap_url || '—');
		$('#sp-progress-bar').css('width', pct + '%');
		$('#sp-progress-pct').text(pct + '%');
	}

	/**
	 * Show a temporary admin notice.
	 */
	function showNotice(type, message) {
		var cssClass = type === 'success' ? 'notice-success' : 'notice-error';
		var $notice  = $(
			'<div class="notice ' + cssClass + ' is-dismissible">' +
			'<p>' + message + '</p>' +
			'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>' +
			'</div>'
		);

		$('.sp-header').after($notice);

		$notice.find('.notice-dismiss').on('click', function () {
			$notice.fadeOut(200, function () { $notice.remove(); });
		});

		setTimeout(function () {
			$notice.fadeOut(400, function () { $notice.remove(); });
		}, 5000);
	}

	/**
	 * Cache timeout interval conversion helper.
	 */
	function initTimeoutInterval() {
		$('#cache_timeout_interval').on('change', function () {
			// visual only — the actual conversion is done server-side in sanitize_options()
		});
	}

	/**
	 * Initialize everything on DOM ready.
	 */
	$(function () {
		initCollapsibleSections();
		initMasterToggles();
		initClearCache();
		initClearFontCache();
		initRefreshSitemap();
		initPreloadStatus();
		initTimeoutInterval();
	});

})(jQuery);
