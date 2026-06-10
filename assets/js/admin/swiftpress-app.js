/**
 * SwiftPress — Editorial Console controller (vanilla, no build step).
 * Wires the Brief to the AI engine (admin-ajax), the Score instrument,
 * presets, the command palette, the nginx copy button, and key management.
 *
 * @package SwiftPress
 * @since   2.0
 */
(function () {
	'use strict';

	var APP = window.swiftpressApp || {};
	var AI = window.swiftpressAI || { hasKey: false, keySource: 'none' };
	var $ = function (s, r) { return (r || document).querySelector(s); };
	var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
	var reduce = window.matchMedia('(prefers-reduced-motion:reduce)').matches;

	var CIRC = 2 * Math.PI * 52;
	var appliedSnapshot = null;
	var lastChanges = [];

	// Human labels for setting keys (fallback to the key itself).
	var LABELS = {
		enable_page_cache: 'Enable page cache', gzip_compression: 'Gzip compression', cache_mobile: 'Mobile cache',
		cache_timeout: 'Cache expiration', minify_css: 'Minify CSS', combine_css: 'Combine CSS',
		critical_css: 'Optimize CSS delivery', remove_unused_css: 'Remove unused CSS', minify_js: 'Minify JS',
		combine_js: 'Combine JS', js_defer: 'Defer JavaScript', js_delay: 'Delay JavaScript',
		js_delay_timeout: 'JS delay timeout', enable_font_optimization: 'Font optimization',
		self_host_google_fonts: 'Self-host Google Fonts', font_preload: 'Preload fonts', font_display_swap: 'font-display: swap',
		enable_cache_preload: 'Cache preloading', enable_sitemap_preload: 'Sitemap preload', prefetch_links: 'Prefetch links on hover',
		enable_lcp_optimization: 'LCP optimization', enable_image_optimization: 'Image optimization',
		image_optimizer_preferred_format: 'Preferred image format', add_missing_image_dimensions: 'Add image dimensions',
		disable_emoji_scripts: 'Disable emoji scripts', disable_wp_embeds: 'Disable WP embeds', minify_html: 'Minify HTML'
	};
	function label(k) { return LABELS[k] || k; }
	function valueText(v) { return v === true ? 'on' : v === false ? 'off' : String(v); }

	// Preset maps (explicit — never a mystery bundle).
	var PRESETS = {
		safe: { minify_css: true, minify_js: true, font_display_swap: true, enable_font_optimization: true, add_missing_image_dimensions: true },
		balanced: { minify_css: true, minify_js: true, js_defer: true, enable_font_optimization: true, self_host_google_fonts: true, font_display_swap: true, font_preload: true, add_missing_image_dimensions: true, enable_lcp_optimization: true, disable_emoji_scripts: true },
		aggressive: { minify_css: true, combine_css: true, minify_js: true, js_defer: true, js_delay: true, enable_font_optimization: true, self_host_google_fonts: true, font_display_swap: true, font_preload: true, add_missing_image_dimensions: true, enable_lcp_optimization: true, prefetch_links: true, disable_emoji_scripts: true, disable_wp_embeds: true },
		woocommerce: { minify_css: true, minify_js: true, js_defer: true, enable_font_optimization: true, self_host_google_fonts: true, font_display_swap: true, add_missing_image_dimensions: true, enable_lcp_optimization: true }
	};

	/* ── AJAX helper ── */
	function post(action, data, nonceKey) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', APP[nonceKey || 'aiNonce']);
		Object.keys(data || {}).forEach(function (k) {
			body.append(k, typeof data[k] === 'object' ? JSON.stringify(data[k]) : data[k]);
		});
		return fetch(APP.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
			.then(function (r) { return r.json(); });
	}

	/* ── toast ── */
	function toast(msg, err) {
		var wrap = $('#sp-toasts'); if (!wrap) return;
		var t = document.createElement('div');
		t.className = 'sp-toast' + (err ? ' err' : '');
		t.textContent = msg;
		wrap.appendChild(t);
		setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .4s'; setTimeout(function () { t.remove(); }, 400); }, 3800);
	}

	/* ── Score instrument ── */
	function band(v) { return v >= 90 ? 'var(--score-hi)' : v >= 50 ? 'var(--score-mid)' : 'var(--score-lo)'; }
	function drawScore(target, srcText) {
		var ring = $('#sp-ring'), num = $('#sp-score-num'); if (!ring || !num) return;
		ring.style.stroke = band(target);
		if (srcText) { var s = $('#sp-score-src'); if (s) s.textContent = srcText; }
		var off = CIRC * (1 - target / 100);
		var meter = $('.sp-score'); if (meter) meter.setAttribute('aria-valuenow', target);
		// Reduced-motion or a backgrounded tab (rAF is throttled/paused when hidden,
		// which would freeze the count-up on its first frame) → set the final value.
		if (reduce || document.hidden) { ring.style.transition = 'none'; ring.style.strokeDashoffset = off; num.textContent = target; return; }
		requestAnimationFrame(function () { ring.style.strokeDashoffset = off; });
		var n = 0;
		(function step() { n += Math.max(1, Math.round((target - n) / 8)); if (n >= target) n = target; num.textContent = n; if (n < target && !document.hidden) requestAnimationFrame(step); else num.textContent = target; })();
	}

	/* ── pulse strip from metrics ── */
	function setPulse(metrics) {
		if (!metrics) return;
		var lab = metrics.lab || {}, field = metrics.field || {};
		function ms(v) { return v == null ? '—' : (v >= 1000 ? (v / 1000).toFixed(1) + 's' : Math.round(v) + 'ms'); }
		function put(id, dotId, val, dot) { var e = $('#' + id); if (e) e.innerHTML = val; var d = $('#' + dotId); if (d) d.className = 'd ' + dot; }
		var score = metrics.perf_score;
		put('sp-pv-score', 'sp-pd-score', score != null ? score + '<small>/100</small>' : '—', score >= 90 ? 'good' : score >= 50 ? 'warn' : 'crit');
		var lcp = field.LCP_ms || lab.LCP_ms;
		put('sp-pv-lcp', 'sp-pd-lcp', lcp != null ? ms(lcp) : '—', lcp == null ? '' : lcp <= 2500 ? 'good' : lcp <= 4000 ? 'warn' : 'crit');
		var cls = field.CLS != null ? field.CLS : lab.CLS;
		put('sp-pv-cls', 'sp-pd-cls', cls != null ? (+cls).toFixed(3) : '—', cls == null ? '' : cls <= 0.1 ? 'good' : cls <= 0.25 ? 'warn' : 'crit');
		var inp = field.INP_ms || lab.TBT_ms;
		put('sp-pv-inp', 'sp-pd-inp', inp != null ? ms(inp) : '—', inp == null ? '' : inp <= 200 ? 'good' : inp <= 500 ? 'warn' : 'crit');
		var ttfb = lab.TTFB_ms;
		put('sp-pv-ttfb', 'sp-pd-ttfb', ttfb != null ? ms(ttfb) : '—', ttfb == null ? '' : ttfb <= 800 ? 'good' : 'warn');
	}

	/* ── render a finding/action card ── */
	function changeCard(c, i) {
		var risk = c.risk || 'low';
		var sev = risk === 'high' ? 'critical' : risk === 'medium' ? 'medium' : 'low';
		var conf = c.confidence != null ? Math.round(c.confidence * 100) + '%' : '—';
		var div = document.createElement('article');
		div.className = 'sp-card sp-reveal';
		div.setAttribute('data-sev', sev);
		div.style.animationDelay = (0.05 + i * 0.06) + 's';
		div.innerHTML =
			'<div class="rank">' + (i + 1 < 10 ? '0' : '') + (i + 1) + '</div>' +
			'<div class="body"><h3>' + esc(c._title || label(c.setting_key)) + '</h3>' +
			'<span class="ev"><span class="mk">' + esc(label(c.setting_key)) + ' → ' + esc(valueText(c.to)) + '</span> · risk ' + esc(risk) + '</span>' +
			'<p>' + esc(c.why || '') + ' <code class="map">' + esc(c.setting_key) + '</code></p></div>' +
			'<div class="acts"><button type="button" class="sp-btn amber sp-fix">Apply</button>' +
			'<div class="conf"><b>confidence</b> ' + conf + '</div></div>';
		div.querySelector('.sp-fix').addEventListener('click', function () { applyChanges([c], div); });
		return div;
	}
	function suggestedCard(c, i) {
		var div = document.createElement('article');
		div.className = 'sp-card sp-reveal'; div.setAttribute('data-sev', 'info');
		div.innerHTML = '<div class="rank">' + (i + 1) + '</div><div class="body"><h3>' + esc(label(c.setting_key)) + '</h3>' +
			'<p>' + esc(c.why || c.reason || 'Recommended, but its engine is not yet available.') + '</p></div>' +
			'<div class="acts"><button type="button" class="sp-btn" disabled>Engine pending</button><div class="conf"><b>status</b> roadmap</div></div>';
		return div;
	}
	function findingCard(f, i) {
		var div = document.createElement('article');
		div.className = 'sp-card sp-reveal'; div.setAttribute('data-sev', f.severity || 'info');
		div.style.animationDelay = (0.05 + i * 0.06) + 's';
		div.innerHTML = '<div class="rank">' + (i + 1 < 10 ? '0' : '') + (i + 1) + '</div><div class="body"><h3>' + esc(f.issue || '') + '</h3>' +
			(f.evidence_metric ? '<span class="ev"><span class="mk">' + esc(f.evidence_metric) + '</span></span>' : '') +
			'<p>' + esc(f.plain_explanation || '') + '</p></div><div class="acts"><div class="conf"></div></div>';
		return div;
	}

	function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

	/* ── render diagnostic result ── */
	function renderResult(d) {
		var metrics = d.metrics || (d.meta && d.meta.metrics) || {};
		setPulse(metrics);
		var src = (d.meta && d.meta.metrics_source) || 'pagespeed';
		var score = metrics.perf_score != null ? metrics.perf_score : (d.meta && d.meta.perf_score);
		var srcLabel = (src === 'local') ? 'local scan · estimate' : ((d.meta && d.meta.degraded) ? 'standard rules' : 'PageSpeed · mobile');
		if (score != null) drawScore(score, srcLabel);

		// Brief narrative. Always replace the loading block, even when the model
		// returned no summary text (otherwise the spinner would spin forever while
		// the score + cards below it have already updated).
		var content = $('#sp-brief-content');
		if (content) {
			var degraded = d.meta && d.meta.degraded;
			var eb = $('#sp-brief-eyebrow'); if (eb) eb.textContent = degraded ? 'Standard recommendations' : 'AI Brief · just now';
			var hint = '';
			if (src === 'local') hint = '<p class="sp-lede" style="margin-top:10px;color:var(--ink-3)">Estimated from a quick on-site scan. Add a free Google PageSpeed key in <b>Copilot</b> for full Lighthouse metrics.</p>';
			else if (degraded) hint = '<p class="sp-lede" style="margin-top:10px;color:var(--ink-3)">' + esc(APP.i18n.noKey) + '</p>';
			var summary = d.summary || (((d.recommended_changes && d.recommended_changes.appliable) || []).length
				? 'I found a few optimizations you can apply below.'
				: 'No new issues found — your site is in good shape.');
			content.innerHTML = '<h1>' + esc(summary.split('. ')[0]) + '.</h1><p class="sp-lede">' + esc(summary) + '</p>' + hint;
		}

		// Cards: actionable changes first, then suggested, then context findings without a change.
		var wrap = $('#sp-findings'); wrap.innerHTML = '';
		var rc = d.recommended_changes || {};
		var appliable = rc.appliable || (Array.isArray(rc) ? rc : []) || [];
		var suggested = rc.suggested || [];
		lastChanges = appliable.slice();
		appliable.forEach(function (c, i) { wrap.appendChild(changeCard(c, i)); });
		suggested.forEach(function (c, i) { wrap.appendChild(suggestedCard(c, appliable.length + i)); });
		// If no actionable changes, show findings as context.
		if (!appliable.length && d.findings) { d.findings.forEach(function (f, i) { wrap.appendChild(findingCard(f, i)); }); }

		var head = $('#sp-findings-head');
		var n = appliable.length + suggested.length;
		head.style.display = n ? 'flex' : 'none';
		$('#sp-findings-count').textContent = appliable.length + ' one-click · ' + (d.findings ? d.findings.length : n) + ' findings';
		$('#sp-apply-all').style.display = appliable.length ? '' : 'none';
		if (d.meta && d.meta.cached) toast('Showing cached analysis (no API spend).');
	}

	/* ── run diagnostic ── */
	var running = false;
	function setRunBusy(busy) {
		var btn = $('#sp-run-diagnostic'); if (!btn) return;
		if (busy) {
			if (!btn.dataset.label) btn.dataset.label = btn.innerHTML;
			btn.disabled = true; btn.classList.add('busy');
			btn.innerHTML = '<span class="sp-spin" aria-hidden="true"></span> ' + esc(APP.i18n.diagBtn || 'Analyzing…');
		} else {
			btn.disabled = false; btn.classList.remove('busy');
			if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
		}
	}
	function runDiagnostic() {
		if (running) return;
		var content = $('#sp-brief-content');
		// The diagnostic's progress + results live on the Brief view. If the button
		// was clicked from another view (it sits in the global top bar), navigate to
		// the Brief and auto-run there so feedback is always visible.
		if (!content) {
			var u = new URL(location.href);
			u.searchParams.set('page', 'swiftpress'); u.searchParams.delete('sp_view'); u.searchParams.set('sp_run', '1');
			location.href = u.toString(); return;
		}
		running = true;
		setRunBusy(true);
		toast(APP.i18n.diagStart || 'Running diagnostic…');
		// Staged status so a multi-second (sometimes up to a minute) run feels alive.
		var stages = [APP.i18n.analyzing, APP.i18n.measuring, APP.i18n.writing].filter(Boolean);
		var si = 0;
		content.innerHTML = '<div class="loading"><span class="sp-spin big" aria-hidden="true"></span><span class="sp-load-msg">' + esc(stages[0] || 'Working…') + '</span><span class="bar"></span></div>';
		var iv = setInterval(function () { si = Math.min(si + 1, stages.length - 1); var m = content.querySelector('.sp-load-msg'); if (m) m.textContent = stages[si]; }, 3500);
		var num = $('#sp-score-num'); if (num) num.textContent = '—';
		var ring = $('#sp-ring'); if (ring) ring.style.strokeDashoffset = CIRC;
		var finish = function () { clearInterval(iv); running = false; setRunBusy(false); };
		post('swiftpress_ai_run_diagnostic', { force: 1 }).then(function (json) {
			finish();
			var dd = (json && json.data) || {};
			if (json && json.success && dd.meta && dd.meta.debounced) {
				toast('Just analyzed — please wait a few seconds before re-running.');
				content.innerHTML = '<p class="sp-lede" style="color:var(--ink-3)">' + esc('Diagnostic on cooldown to keep API spend near zero. Try again shortly.') + '</p>';
				return;
			}
			if (json && json.success) { renderResult(dd); refreshKeyState(); }
			else { toast((dd && dd.message) || APP.i18n.failed, true); content.innerHTML = '<p class="sp-lede" style="color:var(--crit)">' + esc((dd && dd.message) || APP.i18n.failed) + '</p>'; }
		}).catch(function () { finish(); toast(APP.i18n.failed, true); content.innerHTML = '<p class="sp-lede" style="color:var(--crit)">' + esc(APP.i18n.failed) + '</p>'; });
	}

	/* ── apply changes ── */
	function applyChanges(changes, cardEl) {
		var btns = cardEl ? cardEl.querySelectorAll('.sp-fix') : [];
		Array.prototype.forEach.call(btns, function (b) { b.textContent = APP.i18n.applying; });
		post('swiftpress_ai_apply', { changes: changes.map(function (c) { return { setting_key: c.setting_key, to: c.to }; }) }).then(function (json) {
			if (json && json.success) {
				if (cardEl) { cardEl.classList.add('done'); Array.prototype.forEach.call(btns, function (b) { b.textContent = '✓ ' + APP.i18n.applied; }); }
				appliedSnapshot = (json.data && json.data.snapshot_id) || true;
				showUndo();
				var delta = $('#sp-score-delta'); if (delta) { delta.textContent = '▲ re-audit to see the gain'; delta.style.opacity = '1'; }
				toast(APP.i18n.applied + ' — ' + changes.map(function (c) { return label(c.setting_key); }).join(', '));
			} else { toast((json && json.data && json.data.message) || APP.i18n.failed, true); Array.prototype.forEach.call(btns, function (b) { b.textContent = 'Apply'; }); }
		}).catch(function () { toast(APP.i18n.failed, true); Array.prototype.forEach.call(btns, function (b) { b.textContent = 'Apply'; }); });
	}
	function showUndo() {
		if ($('#sp-undo')) return;
		var head = $('#sp-findings-head'); if (!head) return;
		var b = document.createElement('button'); b.id = 'sp-undo'; b.type = 'button'; b.className = 'sp-btn'; b.textContent = '↶ Undo last AI change';
		b.style.marginLeft = '8px';
		b.addEventListener('click', function () {
			post('swiftpress_ai_undo', {}).then(function (json) {
				if (json && json.success) { toast('Reverted to the previous settings.'); b.remove(); appliedSnapshot = null; }
				else toast((json && json.data && json.data.message) || APP.i18n.failed, true);
			});
		});
		head.appendChild(b);
	}

	/* ── presets (additive apply via the safe per-key save path) ── */
	function applyPreset(name, chip) {
		var map = PRESETS[name]; if (!map) return;
		$$('.sp-chip').forEach(function (c) { c.classList.remove('sel'); }); if (chip) chip.classList.add('sel');
		var keys = Object.keys(map);
		if (!window.confirm('Apply the "' + name + '" preset? It turns ON: ' + keys.map(label).join(', ') +
			'. Each switch saves on its own through the normal save path and stays visible, so you can adjust any of them afterwards. Page caching, credentials and exclusion lists are left untouched.')) return;
		if (chip) chip.classList.add('busy');
		// Save each key through the allowlisted read-merge-write endpoint (the same
		// path the individual toggles use) — never the whole-form POST, which would
		// reset every setting the Brief doesn't render.
		Promise.all(keys.map(function (k) {
			var cb = document.querySelector('input[type="checkbox"][name="' + k + '"]');
			if (cb && !cb.disabled) cb.checked = true;
			return post('swiftpress_app_save_setting', { key: k, value: '1' }, 'ajaxNonce');
		})).then(function (results) {
			if (chip) chip.classList.remove('busy');
			var failed = results.filter(function (r) { return !(r && r.success); }).length;
			if (failed) toast(failed + ' of ' + keys.length + ' switches could not be saved.', true);
			else toast('"' + name + '" preset applied — ' + keys.length + ' switches on. Adjust any of them in Tune.');
		}).catch(function () { if (chip) chip.classList.remove('busy'); toast(APP.i18n.failed, true); });
	}

	/* ── key management ── */
	function refreshKeyState() {
		post('swiftpress_ai_status', {}).then(function (json) {
			var has = json && json.success && json.data && json.data.has_key;
			var src = (json && json.success && json.data && json.data.source) || AI.keySource || 'none';
			AI.hasKey = !!has; AI.keySource = src;
			var pill = $('#sp-key-pill'); if (pill) pill.style.display = has ? '' : 'none';
			var hint = $('#sp-key-hint');
			if (hint) hint.innerHTML = has ? '' : esc(APP.i18n.noKey);
			var st = $('#sp-key-state'), stt = $('#sp-key-state-text'), ks = $('#sp-key-source');
			if (st) { st.className = 'sp-keystate' + (has ? '' : ' none'); }
			if (stt) stt.textContent = has ? ('Key active' + (src === 'constant' ? ' · set in wp-config.php' : '')) : 'No key set — standard recommendations only';
			if (ks) ks.textContent = has && src === 'constant' ? 'set in wp-config.php (read-only)' : '';
			var keyrow = $('#sp-keyrow'), kconst = $('#sp-key-constant'), input = $('#sp-key-input');
			if (src === 'constant') { if (keyrow) keyrow.style.display = 'none'; if (kconst) kconst.style.display = 'flex'; }
			else { if (keyrow) keyrow.style.display = 'flex'; if (kconst) kconst.style.display = 'none'; if (input) input.disabled = false; }
			// Initialize the model + budget controls from their saved values.
			var d = (json && json.data) || {};
			var cap = $('#sp-ai-cap'); if (cap && typeof d.monthly_cap !== 'undefined' && cap !== document.activeElement) cap.value = d.monthly_cap;
			var model = $('#sp-ai-model'); if (model && d.model) model.value = d.model;
			// PageSpeed key state (mirrors the OpenRouter key state row).
			var hasPsi = !!d.has_psi_key;
			var ps = $('#sp-psi-state'), pst = $('#sp-psi-state-text');
			if (ps) ps.className = 'sp-keystate' + (hasPsi ? '' : ' none');
			if (pst) pst.textContent = hasPsi
				? 'PageSpeed key active — every diagnostic uses full Lighthouse metrics'
				: 'No PageSpeed key — quick on-site scan is used';
		}).catch(function () {});
	}

	/* ── auto-save (toggles/selects/inputs save instantly) ── */
	function flashSaved(row) {
		var s = row && row.querySelector('.sp-saved'); if (!s) return;
		s.classList.add('show'); setTimeout(function () { s.classList.remove('show'); }, 1600);
	}
	function autoSave(key, value, row) {
		return post('swiftpress_app_save_setting', { key: key, value: value }, 'ajaxNonce').then(function (json) {
			if (json && json.success) { flashSaved(row); }
			else { toast((json && json.data && json.data.message) || APP.i18n.failed, true); }
		}).catch(function () { toast(APP.i18n.failed, true); });
	}
	function bindAutoSave() {
		var form = $('.swiftpress-app form'); if (!form) return;
		$$('input[type=checkbox][name]', form).forEach(function (cb) {
			if (cb.disabled) return;
			cb.addEventListener('change', function () { autoSave(cb.name, cb.checked ? '1' : '0', cb.closest('.sp-row')); });
		});
		$$('select[name]', form).forEach(function (sel) {
			if (sel.name === 'cache_timeout_interval') return;
			sel.addEventListener('change', function () { autoSave(sel.name, sel.value, sel.closest('.sp-row')); });
		});
		var num = form.querySelector('input[name=cache_timeout]'), intv = form.querySelector('select[name=cache_timeout_interval]');
		function saveTimeout() { if (!num) return; var n = parseInt(num.value || '0', 10) || 0; var mult = (intv && intv.value === 'DAY') ? 1440 : ((intv && intv.value === 'HOUR') ? 60 : 1); autoSave('cache_timeout', String(n * mult), num.closest('.sp-row')); }
		if (num) num.addEventListener('change', saveTimeout);
		if (intv) intv.addEventListener('change', saveTimeout);
		$$('textarea[name]', form).forEach(function (ta) { ta.addEventListener('blur', function () { autoSave(ta.name, ta.value, ta.closest('.sp-row')); }); });
	}

	/* ── DNS-prefetch domain detection ── */
	function currentDnsSet() {
		var box = $('#sp-domains'); var raw = (box && box.getAttribute('data-current')) || '';
		return raw.split(/[\r\n,]+/).map(function (s) { return s.trim().replace(/^\/\//, '').toLowerCase(); }).filter(Boolean);
	}
	function updateDns() {
		var box = $('#sp-domains'); if (!box) return;
		var hosts = [];
		$$('.dom', box).forEach(function (d) { var cb = d.querySelector('input'); if (cb && cb.checked) hosts.push('//' + d.getAttribute('data-host')); });
		var ta = document.querySelector('textarea[name=prefetch_dns]');
		var manual = ta ? ta.value.split(/[\r\n,]+/).map(function (s) { return s.trim(); }).filter(function (s) { return s && hosts.indexOf(s) < 0 && hosts.indexOf('//' + s.replace(/^\/\//, '')) < 0; }) : [];
		var all = hosts.concat(manual).join('\n');
		if (ta) ta.value = all;
		box.setAttribute('data-current', all);
		autoSave('prefetch_dns', all, box.closest('.sp-row'));
	}
	function renderDomains(domains, note) {
		var box = $('#sp-domains'); if (!box) return; box.innerHTML = '';
		if (!domains || !domains.length) { box.innerHTML = '<div class="empty">' + esc(note || 'No third-party domains detected on the homepage.') + '</div>'; return; }
		var current = currentDnsSet();
		domains.forEach(function (host) {
			var checked = current.indexOf(String(host).toLowerCase()) >= 0;
			var div = document.createElement('div'); div.className = 'dom'; div.setAttribute('data-host', host);
			div.innerHTML = '<label class="sp-toggle" style="transform:scale(.82);transform-origin:left"><input type="checkbox" ' + (checked ? 'checked' : '') + '><span class="track"></span><span class="knob"></span></label> <code>' + esc(host) + '</code><span class="src">DNS-prefetch</span>';
			div.querySelector('input').addEventListener('change', updateDns);
			box.appendChild(div);
		});
	}
	function bindDomains() {
		var btn = $('#sp-detect-domains'); if (!btn) return;
		btn.addEventListener('click', function () {
			btn.textContent = 'Scanning…'; btn.disabled = true;
			post('swiftpress_app_detect_domains', {}, 'ajaxNonce').then(function (json) {
				btn.textContent = 'Detect domains'; btn.disabled = false;
				var d = (json && json.data) || {};
				renderDomains(d.domains || [], d.note);
			}).catch(function () { btn.textContent = 'Detect domains'; btn.disabled = false; toast(APP.i18n.failed, true); });
		});
	}
	function bindImageOptimize() {
		var btn = $('#sp-optimize-images'); if (!btn) return;
		var status = $('#sp-imgopt-status');
		var orig = btn.textContent;
		btn.addEventListener('click', function () {
			if (btn.disabled) return;
			btn.disabled = true; btn.textContent = 'Optimizing…';
			var createdTotal = 0;
			function step(offset) {
				return post('swiftpress_app_optimize_images', { offset: offset }, 'ajaxNonce').then(function (json) {
					if (!json || !json.success) { throw new Error((json && json.data && json.data.message) || ''); }
					var d = json.data || {};
					createdTotal += d.created || 0;
					if (status) status.textContent = d.processed + ' / ' + d.total + ' images processed — ' + createdTotal + ' next-gen files created.';
					if (d.finished) {
						btn.disabled = false; btn.textContent = orig;
						toast('Image optimization finished: ' + createdTotal + ' files created.');
						return;
					}
					return step(d.nextOffset);
				});
			}
			step(0).catch(function (e) {
				btn.disabled = false; btn.textContent = orig;
				toast((e && e.message) || APP.i18n.failed, true);
			});
		});
	}
	function bindCloudflare() {
		$$('[data-cf]').forEach(function (el) {
			var save = function () {
				var v = (el.value || '').trim();
				// Empty = keep the stored value (never wipe a configured secret).
				if (v === '') return;
				autoSave(el.getAttribute('data-cf'), v, el.closest('.sp-row'));
				if (el.type === 'password') { el.value = ''; el.placeholder = '•••••• configured — leave blank to keep'; }
			};
			el.addEventListener('change', save);
		});
	}
	function bindKey() {
		var input = $('#sp-key-input');
		var busy = false;
		function doSave(k) {
			if (busy) return Promise.resolve(); busy = true;
			var state = $('#sp-key-state-text'); if (state) state.textContent = 'Validating key…';
			return post('swiftpress_ai_save_key', { openrouter_key: k }).then(function (json) {
				busy = false;
				if (json && json.success) { if (input) input.value = ''; toast('Key validated, encrypted & saved.'); refreshKeyState(); }
				else { toast((json && json.data && json.data.message) || 'Invalid key.', true); refreshKeyState(); }
			}).catch(function () { busy = false; toast(APP.i18n.failed, true); refreshKeyState(); });
		}
		var save = $('#sp-key-save'); if (save) save.addEventListener('click', function () {
			var k = ((input && input.value) || '').trim(); if (!k) { toast('Enter a key first.', true); return; }
			save.textContent = 'Validating…'; doSave(k).then(function () { save.textContent = 'Save key'; });
		});
		// Auto-validate + save shortly after a plausible key is pasted or typed.
		if (input) {
			var t;
			var go = function () { var k = (input.value || '').trim(); if (k.length < 20 || k.indexOf('sk-') !== 0) return; doSave(k); };
			input.addEventListener('input', function () { clearTimeout(t); t = setTimeout(go, 800); });
			input.addEventListener('paste', function () { clearTimeout(t); t = setTimeout(go, 350); });
		}
		var psi = $('#sp-psi-input');
		if (psi) {
			var pt, psiBusy = false;
			var savePsi = function () {
				var v = (psi.value || '').trim(); if (v.length < 20 || psiBusy) return;
				psiBusy = true;
				var pst = $('#sp-psi-state-text'); if (pst) pst.textContent = 'Validating PageSpeed key with Google…';
				post('swiftpress_ai_save_key', { psi_key: v }).then(function (json) {
					psiBusy = false;
					if (json && json.success) { psi.value = ''; toast('PageSpeed key validated & saved — diagnostics now use full Lighthouse metrics.'); refreshKeyState(); }
					else { toast((json && json.data && json.data.message) || 'Invalid key.', true); refreshKeyState(); }
				}).catch(function () { psiBusy = false; toast(APP.i18n.failed, true); refreshKeyState(); });
			};
			psi.addEventListener('input', function () { clearTimeout(pt); pt = setTimeout(savePsi, 900); });
			psi.addEventListener('paste', function () { clearTimeout(pt); pt = setTimeout(savePsi, 400); });
		}
		var psiClr = $('#sp-psi-clear'); if (psiClr) psiClr.addEventListener('click', function () {
			post('swiftpress_ai_save_key', { psi_key: (AI.clearToken || '__CLEAR__') }).then(function () { toast('PageSpeed key removed.'); refreshKeyState(); });
		});
		var clr = $('#sp-key-clear'); if (clr) clr.addEventListener('click', function () {
			post('swiftpress_ai_save_key', { openrouter_key: '__CLEAR__' }).then(function () { toast('Key removed.'); refreshKeyState(); });
		});
		// Model + monthly spend cap (saved through the same secure endpoint).
		var model = $('#sp-ai-model'); if (model) model.addEventListener('change', function () {
			post('swiftpress_ai_save_key', { ai_model: model.value }).then(function (j) { if (j && j.success) toast('Model saved.'); });
		});
		var cap = $('#sp-ai-cap');
		if (cap) {
			var ct;
			var saveCap = function () {
				var v = parseFloat(cap.value); if (isNaN(v) || v < 0) return;
				post('swiftpress_ai_save_key', { monthly_cap_usd: v }).then(function (j) { if (j && j.success) toast('Spend cap saved: $' + v + '/mo.'); });
			};
			cap.addEventListener('change', saveCap);
			cap.addEventListener('input', function () { clearTimeout(ct); ct = setTimeout(saveCap, 800); });
		}
	}

	/* ── misc ── */
	function bindMisc() {
		var rd = $('#sp-run-diagnostic'); if (rd) rd.addEventListener('click', runDiagnostic);
		var aa = $('#sp-apply-all'); if (aa) aa.addEventListener('click', function () { if (lastChanges.length) applyChanges(lastChanges.filter(function (c) { return (c.risk || 'low') !== 'high'; }), null); });
		$$('.sp-chip').forEach(function (c) { c.addEventListener('click', function () { applyPreset(c.getAttribute('data-preset'), c); }); });
		var copy = $('#sp-copy-nginx'); if (copy) copy.addEventListener('click', function () {
			var pre = $('#sp-nginx-pre'); if (!pre) return;
			navigator.clipboard.writeText(pre.textContent).then(function () { copy.textContent = '✓ Copied'; setTimeout(function () { copy.textContent = 'Copy'; }, 1600); });
		});
		var flush = $('#sp-flush-all'); if (flush) flush.addEventListener('click', function () {
			flush.textContent = '…'; post('swiftpress_clear_cache', {}, 'ajaxNonce').then(function (json) {
				flush.textContent = 'Flush all'; toast((json && json.data && json.data.message) || 'Cache cleared.');
			}).catch(function () { flush.textContent = 'Flush all'; });
		});
	}

	/* ── command palette (real commands + AI ask) ── */
	function cmdkCommands() {
		var U = APP.urls || {};
		var cmds = [
			{ label: 'Run Diagnostic', kw: 'run diagnostic analyze audit test scan', run: function (close) { close(); runDiagnostic(); } },
			{ label: 'Flush all cache', kw: 'flush clear purge cache empty', run: function (close) { close(); var f = $('#sp-flush-all'); if (f) { f.click(); } else { post('swiftpress_clear_cache', {}, 'ajaxNonce').then(function () { toast('Cache cleared.'); }); } } },
			{ label: 'Optimize images now (bulk WebP)', kw: 'optimize images webp convert media bulk', run: function (close) { var b = $('#sp-optimize-images'); if (b) { close(); b.click(); } else if (U.tune) { location.href = U.tune; } } },
			{ label: 'Open: The Brief', kw: 'open brief home dashboard score', run: function () { if (U.brief) location.href = U.brief; } },
			{ label: 'Open: Settings', kw: 'open settings tune options toggles', run: function () { if (U.tune) location.href = U.tune; } },
			{ label: 'Open: Server (nginx rules)', kw: 'open server nginx apache rules config htaccess', run: function () { if (U.server) location.href = U.server; } },
			{ label: 'Open: Copilot (AI keys & budget)', kw: 'open copilot ai key openrouter pagespeed budget model', run: function () { if (U.copilot) location.href = U.copilot; } }
		];
		var bs = APP.boolState || {};
		Object.keys(bs).forEach(function (k) {
			cmds.push({ label: '', setting: k, kw: 'toggle turn on off enable disable setting ' + k.replace(/_/g, ' ') + ' ' + label(k), run: function (close) {
				var on = !!bs[k];
				autoSave(k, on ? '0' : '1', null).then(function () { bs[k] = !on; toast(label(k) + (on ? ' disabled.' : ' enabled.')); });
				close();
			} });
		});
		return cmds;
	}
	function bindCmdk() {
		var cmdk = $('#sp-cmdk'); if (!cmdk) return;
		var input = $('#sp-cmd-input'), list = $('#sp-cmd-list'), answer = $('#sp-cmd-answer');
		if (!input || !list) return;
		var all = cmdkCommands(), filtered = [], sel = 0;
		var bs = APP.boolState || {};
		function labelFor(c) { return c.setting ? ((bs[c.setting] ? 'Turn OFF: ' : 'Turn ON: ') + label(c.setting)) : c.label; }
		function open() { cmdk.classList.add('open'); input.value = ''; render(''); setTimeout(function () { input.focus(); }, 40); }
		function close() { cmdk.classList.remove('open'); if (answer) { answer.hidden = true; answer.textContent = ''; } }
		function render(q) {
			if (answer) answer.hidden = true;
			var ql = (q || '').toLowerCase().trim();
			filtered = !ql ? all.slice(0, 8) : all.filter(function (c) { return (labelFor(c) + ' ' + c.kw).toLowerCase().indexOf(ql) > -1; }).slice(0, 8);
			sel = 0;
			var rows = filtered.map(function (c, i) { return '<div class="cmd-row' + (i === 0 ? ' sel' : '') + '" data-i="' + i + '"><span class="t">' + esc(labelFor(c)) + '</span><span class="go">↵</span></div>'; });
			if (ql.length > 7 && AI.hasKey) rows.push('<div class="cmd-row ask' + (!filtered.length ? ' sel' : '') + '" data-ask="1"><span class="t">✦ Ask AICache: “' + esc(q) + '”</span><span class="go">↵</span></div>');
			if (!rows.length) rows.push('<div class="cmd-empty">' + esc('Nothing matches — try “flush”, “images”, a setting name, or ask a question.') + '</div>');
			list.innerHTML = rows.join('');
		}
		function rowsEls() { return list.querySelectorAll('.cmd-row'); }
		function markSel() { Array.prototype.forEach.call(rowsEls(), function (el, i) { el.classList.toggle('sel', i === sel); }); }
		function runSel() {
			var els = rowsEls(); var el = els[sel] || els[0]; if (!el) return;
			if (el.getAttribute('data-ask')) { askAI(input.value); return; }
			var c = filtered[parseInt(el.getAttribute('data-i'), 10)];
			if (c) c.run(close);
		}
		function askAI(q) {
			if (!answer) return;
			answer.hidden = false; answer.textContent = 'Thinking…';
			post('swiftpress_ai_ask', { q: q }).then(function (j) {
				answer.textContent = (j && j.success && j.data && j.data.answer) ? j.data.answer : ((j && j.data && j.data.message) || APP.i18n.failed);
			}).catch(function () { answer.textContent = APP.i18n.failed; });
		}
		input.addEventListener('input', function () { render(input.value); });
		input.addEventListener('keydown', function (e) {
			var n = rowsEls().length;
			if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(sel + 1, n - 1); markSel(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(sel - 1, 0); markSel(); }
			else if (e.key === 'Enter') { e.preventDefault(); runSel(); }
		});
		list.addEventListener('click', function (e) {
			var r = e.target.closest('.cmd-row'); if (!r) return;
			sel = Array.prototype.indexOf.call(rowsEls(), r); runSel();
		});
		var at = $('#sp-ask-trigger');
		if (at) { at.addEventListener('click', open); at.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } }); }
		cmdk.addEventListener('click', function (e) { if (e.target.id === 'sp-cmdk') close(); });
		document.addEventListener('keydown', function (e) { if ((e.metaKey || e.ctrlKey) && String(e.key).toLowerCase() === 'k') { e.preventDefault(); open(); } if (e.key === 'Escape') close(); });
	}

	function init() {
		if (!$('.swiftpress-app')) return;
		// The AI config script can print after this bundle — re-resolve it now
		// (DOM ready) so lastResult/hasKey/clearToken are actually populated.
		AI = window.swiftpressAI || AI;
		var ring = $('#sp-ring'); if (ring) { ring.style.strokeDasharray = CIRC; ring.style.strokeDashoffset = CIRC; }
		bindMisc(); bindCmdk(); bindKey(); bindAutoSave(); bindDomains(); bindImageOptimize(); bindCloudflare(); refreshKeyState();
		// Auto-run when arriving from a "Run Diagnostic" click on another view;
		// otherwise re-render the last saved diagnostic so results survive navigation.
		try {
			var autoRun = new URLSearchParams(location.search).get('sp_run') === '1' && $('#sp-brief-content');
			if (autoRun) {
				if (window.history && history.replaceState) {
					var u = new URL(location.href); u.searchParams.delete('sp_run'); history.replaceState(null, '', u.toString());
				}
				setTimeout(runDiagnostic, 350);
			} else if (AI.lastResult && $('#sp-brief-content')) {
				renderResult(AI.lastResult);
				var eb = $('#sp-brief-eyebrow');
				if (eb && AI.lastResultAt) {
					var now = parseInt(AI.nowTs, 10) || Math.floor(Date.now() / 1000);
					var mins = Math.max(1, Math.round((now - parseInt(AI.lastResultAt, 10)) / 60));
					eb.textContent = 'AI Brief · ' + (mins < 60 ? (mins + ' min ago') : (Math.round(mins / 60) + ' h ago'));
				}
			}
		} catch (e) {}
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
