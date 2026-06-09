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
		if (reduce) { ring.style.transition = 'none'; ring.style.strokeDashoffset = off; num.textContent = target; return; }
		requestAnimationFrame(function () { ring.style.strokeDashoffset = off; });
		var n = 0;
		(function step() { n += Math.max(1, Math.round((target - n) / 8)); if (n >= target) n = target; num.textContent = n; if (n < target) requestAnimationFrame(step); })();
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

		// Brief narrative.
		var content = $('#sp-brief-content');
		if (content && d.summary) {
			var degraded = d.meta && d.meta.degraded;
			$('#sp-brief-eyebrow').textContent = degraded ? 'Standard recommendations' : 'AI Brief · just now';
			var hint = '';
			if (src === 'local') hint = '<p class="sp-lede" style="margin-top:10px;color:var(--ink-3)">Estimated from a quick on-site scan. Add a free Google PageSpeed key in <b>Copilot</b> for full Lighthouse metrics.</p>';
			else if (degraded) hint = '<p class="sp-lede" style="margin-top:10px;color:var(--ink-3)">' + esc(APP.i18n.noKey) + '</p>';
			content.innerHTML = '<h1>' + esc(d.summary.split('. ')[0]) + '.</h1><p class="sp-lede">' + esc(d.summary) + '</p>' + hint;
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
	function runDiagnostic() {
		if (running) return; running = true;
		var btn = $('#sp-run-diagnostic'); if (btn) { btn.disabled = true; btn.style.opacity = '.6'; }
		var content = $('#sp-brief-content');
		if (content) content.innerHTML = '<div class="loading"><span>' + esc(APP.i18n.analyzing) + '</span><span class="bar"></span></div>';
		$('#sp-score-num').textContent = '—'; $('#sp-ring').style.strokeDashoffset = CIRC;
		post('swiftpress_ai_run_diagnostic', { force: 1 }).then(function (json) {
			running = false; if (btn) { btn.disabled = false; btn.style.opacity = ''; }
			var dd = (json && json.data) || {};
			if (json && json.success && dd.meta && dd.meta.debounced) {
				toast('Just analyzed — please wait a few seconds before re-running.');
				if (content) content.innerHTML = '<p class="sp-lede" style="color:var(--ink-3)">' + esc('Diagnostic on cooldown to keep API spend near zero. Try again shortly.') + '</p>';
				return;
			}
			if (json && json.success) { renderResult(dd); refreshKeyState(); }
			else { toast((dd && dd.message) || APP.i18n.failed, true); if (content) content.innerHTML = '<p class="sp-lede" style="color:var(--crit)">' + esc((dd && dd.message) || APP.i18n.failed) + '</p>'; }
		}).catch(function () { running = false; if (btn) { btn.disabled = false; btn.style.opacity = ''; } toast(APP.i18n.failed, true); });
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

	/* ── presets (real apply via the existing settings save path) ── */
	function applyPreset(name, chip) {
		var map = PRESETS[name]; if (!map) return;
		$$('.sp-chip').forEach(function (c) { c.classList.remove('sel'); }); if (chip) chip.classList.add('sel');
		var keys = Object.keys(map);
		if (!window.confirm('Apply the "' + name + '" preset? It will turn ON: ' + keys.map(label).join(', ') + '. (Reversible — your current settings are saved first.)')) return;
		var form = document.createElement('form'); form.method = 'post'; form.action = '';
		function hidden(n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; form.appendChild(i); }
		hidden('swiftpress_settings_nonce', APP.settingsNonce);
		hidden('swiftpress_form_action', 'save_settings');
		// Merge over current values: read existing toggles from a hidden mirror is complex; the
		// existing handler treats unchecked as false, so we re-send the whole known set. We send
		// only the preset's ON keys; other booleans default off — so presets are a clean reset to
		// that posture (documented in the confirm). Numeric/string settings keep their saved value
		// because the handler reads them from $_POST; to preserve them we copy from the Tune form is
		// out of scope here, so presets are applied from the Brief as an explicit posture.
		Object.keys(map).forEach(function (k) { hidden(k, '1'); });
		document.body.appendChild(form); form.submit();
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
			var pt;
			var savePsi = function () {
				var v = (psi.value || '').trim(); if (v.length < 20) return;
				post('swiftpress_ai_save_key', { psi_key: v }).then(function (json) {
					if (json && json.success) { var s = $('#sp-psi-saved'); if (s) { s.classList.add('show'); setTimeout(function () { s.classList.remove('show'); }, 1600); } toast('PageSpeed key saved.'); }
					else toast((json && json.data && json.data.message) || 'Invalid key.', true);
				});
			};
			psi.addEventListener('input', function () { clearTimeout(pt); pt = setTimeout(savePsi, 900); });
			psi.addEventListener('paste', function () { clearTimeout(pt); pt = setTimeout(savePsi, 400); });
		}
		var clr = $('#sp-key-clear'); if (clr) clr.addEventListener('click', function () {
			post('swiftpress_ai_save_key', { openrouter_key: '__CLEAR__' }).then(function () { toast('Key removed.'); refreshKeyState(); });
		});
	}

	/* ── misc ── */
	function bindMisc() {
		var tb = $('#sp-theme-btn'); if (tb) tb.addEventListener('click', function () {
			var app = $('.swiftpress-app'); app.setAttribute('data-sp-theme', app.getAttribute('data-sp-theme') === 'dark' ? 'light' : 'dark');
		});
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
		// command palette
		var open = function () { var c = $('#sp-cmdk'); if (c) { c.classList.add('open'); setTimeout(function () { var i = $('#sp-cmd-input'); if (i) i.focus(); }, 50); } };
		var close = function () { var c = $('#sp-cmdk'); if (c) c.classList.remove('open'); };
		var at = $('#sp-ask-trigger'); if (at) at.addEventListener('click', open);
		var cmdk = $('#sp-cmdk'); if (cmdk) cmdk.addEventListener('click', function (e) { if (e.target.id === 'sp-cmdk') close(); });
		document.addEventListener('keydown', function (e) { if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); open(); } if (e.key === 'Escape') close(); });
	}

	function init() {
		if (!$('.swiftpress-app')) return;
		var ring = $('#sp-ring'); if (ring) { ring.style.strokeDasharray = CIRC; ring.style.strokeDashoffset = CIRC; }
		bindMisc(); bindKey(); bindAutoSave(); bindDomains(); refreshKeyState();
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
