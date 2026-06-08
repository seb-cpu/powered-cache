<?php
/**
 * Settings Page Template — Clean single-page interface
 *
 * Uses native WordPress admin CSS classes only (.wrap, .form-table,
 * .widefat, .button-primary). No external frameworks.
 *
 * @package SwiftPress
 * @since   3.8
 */

namespace SwiftPress\Admin\Partials\SettingsPage;

use function SwiftPress\Utils\can_configure_htaccess;
use function SwiftPress\Utils\get_timeout_with_interval;

// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = \SwiftPress\Utils\get_settings();

// Cache timeout conversion for display
list( $cache_timeout_value, $cache_timeout_interval ) = get_timeout_with_interval( $settings['cache_timeout'] );

?>
<div class="wrap">

	<!-- ═══════════════════════════════════════════════════════ HEADER -->
	<div class="sp-header">
		<h1>
			<?php esc_html_e( 'SwiftPress', 'swiftpress' ); ?>
			<span class="sp-version"><?php echo esc_html( SWIFTPRESS_VERSION ); ?></span>
		</h1>
		<div class="sp-header-actions">
			<button type="button" id="sp-clear-cache" class="button button-primary">
				<?php esc_html_e( 'Clear All Cache', 'swiftpress' ); ?>
			</button>
			<button type="button" id="sp-clear-font-cache" class="button button-secondary">
				<?php esc_html_e( 'Clear Font Cache', 'swiftpress' ); ?>
			</button>
		</div>
	</div>

	<p class="sp-description">
		<?php esc_html_e( 'Lightweight WordPress performance optimization', 'swiftpress' ); ?>
	</p>

	<?php \SwiftPress\Utils\settings_errors( 'swiftpress', false, true ); ?>

	<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'swiftpress_update_settings', 'swiftpress_settings_nonce' ); ?>
		<input type="hidden" name="swiftpress_form_action" value="save_settings" />

		<!-- ═══════════════════════════════════════ SECTION 1: PAGE CACHE -->
		<div class="sp-section" data-section="page-cache">
			<h2 class="sp-section-header">
				<span><?php esc_html_e( 'Page Cache', 'swiftpress' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<div class="sp-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Page Cache', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_page_cache" value="1"
									<?php checked( $settings['enable_page_cache'] ); ?> />
								<?php esc_html_e( 'Cache pages for faster load times', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache Expiration', 'swiftpress' ); ?></th>
						<td>
							<input type="number" name="cache_timeout" id="cache_timeout"
								value="<?php echo esc_attr( $cache_timeout_value ); ?>"
								min="0" step="1" class="small-text" />
							<select name="cache_timeout_interval" id="cache_timeout_interval">
								<option value="MINUTE" <?php selected( $cache_timeout_interval, 'MINUTE' ); ?>>
									<?php esc_html_e( 'Minutes', 'swiftpress' ); ?>
								</option>
								<option value="HOUR" <?php selected( $cache_timeout_interval, 'HOUR' ); ?>>
									<?php esc_html_e( 'Hours', 'swiftpress' ); ?>
								</option>
								<option value="DAY" <?php selected( $cache_timeout_interval, 'DAY' ); ?>>
									<?php esc_html_e( 'Days', 'swiftpress' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Set to 0 for no automatic expiration.', 'swiftpress' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Gzip Compression', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="gzip_compression" value="1"
									<?php checked( $settings['gzip_compression'] ); ?> />
								<?php esc_html_e( 'Create gzip compressed cache files', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mobile Cache', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cache_mobile" value="1"
									<?php checked( $settings['cache_mobile'] ); ?> />
								<?php esc_html_e( 'Serve cached pages to mobile visitors', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="cache_mobile_separate_file" value="1"
									<?php checked( $settings['cache_mobile_separate_file'] ); ?> />
								<?php esc_html_e( 'Create separate cache files for mobile', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logged-in Users', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="loggedin_user_cache" value="1"
									<?php checked( $settings['loggedin_user_cache'] ); ?> />
								<?php esc_html_e( 'Serve cached pages to logged-in users', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<?php if ( can_configure_htaccess() ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-configure .htaccess', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="auto_configure_htaccess" value="1"
									<?php checked( $settings['auto_configure_htaccess'] ); ?> />
								<?php esc_html_e( 'Automatically update .htaccess rules', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache Footprint', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cache_footprint" value="1"
									<?php checked( $settings['cache_footprint'] ); ?> />
								<?php esc_html_e( 'Add an HTML comment showing the page was served from cache', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>
		</div><!-- /page-cache -->

		<!-- ═══════════════════════════════════ SECTION 2: FILE OPTIMIZATION -->
		<div class="sp-section" data-section="file-optimization">
			<h2 class="sp-section-header">
				<span><?php esc_html_e( 'File Optimization', 'swiftpress' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<div class="sp-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Minify HTML', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="minify_html" value="1"
									id="minify_html"
									data-master-toggle="#sp-sub-html"
									<?php checked( $settings['minify_html'] ); ?> />
								<?php esc_html_e( 'Remove whitespace and comments from HTML output', 'swiftpress' ); ?>
							</label>
							<div id="sp-sub-html" class="sp-sub-options">
								<label>
									<input type="checkbox" name="minify_html_dom_optimization" value="1"
										<?php checked( $settings['minify_html_dom_optimization'] ); ?> />
									<?php esc_html_e( 'DOM optimization', 'swiftpress' ); ?>
								</label>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'CSS', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="minify_css" value="1"
									<?php checked( $settings['minify_css'] ); ?> />
								<?php esc_html_e( 'Minify CSS files', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="combine_css" value="1"
									<?php checked( $settings['combine_css'] ); ?> />
								<?php esc_html_e( 'Combine CSS files', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="critical_css" value="1"
									<?php checked( $settings['critical_css'] ); ?> />
								<?php esc_html_e( 'Optimize CSS delivery (Critical CSS)', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="remove_unused_css" value="1"
									<?php checked( $settings['remove_unused_css'] ); ?> />
								<?php esc_html_e( 'Remove unused CSS', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'JavaScript', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="minify_js" value="1"
									<?php checked( $settings['minify_js'] ); ?> />
								<?php esc_html_e( 'Minify JS files', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="combine_js" value="1"
									<?php checked( $settings['combine_js'] ); ?> />
								<?php esc_html_e( 'Combine JS files', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="js_defer" value="1"
									<?php checked( $settings['js_defer'] ); ?> />
								<?php esc_html_e( 'Defer JavaScript', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="js_delay" value="1"
									<?php checked( $settings['js_delay'] ); ?> />
								<?php esc_html_e( 'Delay JavaScript execution', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Excluded CSS Files', 'swiftpress' ); ?></th>
						<td>
							<textarea name="excluded_css_files" rows="4" class="large-text code"
								placeholder="<?php esc_attr_e( 'One file per line', 'swiftpress' ); ?>"
							><?php echo esc_textarea( $settings['excluded_css_files'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Excluded JS Files', 'swiftpress' ); ?></th>
						<td>
							<textarea name="excluded_js_files" rows="4" class="large-text code"
								placeholder="<?php esc_attr_e( 'One file per line', 'swiftpress' ); ?>"
							><?php echo esc_textarea( $settings['excluded_js_files'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'JS Defer Exclusions', 'swiftpress' ); ?></th>
						<td>
							<textarea name="js_defer_exclusions" rows="3" class="large-text code"
								placeholder="<?php esc_attr_e( 'One file per line', 'swiftpress' ); ?>"
							><?php echo esc_textarea( $settings['js_defer_exclusions'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'JS Delay Exclusions', 'swiftpress' ); ?></th>
						<td>
							<textarea name="js_delay_exclusions" rows="3" class="large-text code"
								placeholder="<?php esc_attr_e( 'One file per line', 'swiftpress' ); ?>"
							><?php echo esc_textarea( $settings['js_delay_exclusions'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Media', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="add_missing_image_dimensions" value="1"
									<?php checked( $settings['add_missing_image_dimensions'] ); ?> />
								<?php esc_html_e( 'Add missing image dimensions', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="disable_wp_embeds" value="1"
									<?php checked( $settings['disable_wp_embeds'] ); ?> />
								<?php esc_html_e( 'Disable WordPress embeds', 'swiftpress' ); ?>
							</label>
							<br />
							<label>
								<input type="checkbox" name="disable_emoji_scripts" value="1"
									<?php checked( $settings['disable_emoji_scripts'] ); ?> />
								<?php esc_html_e( 'Disable emoji scripts', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>
		</div><!-- /file-optimization -->

		<!-- ═══════════════════════════════════ SECTION 3: FONT OPTIMIZATION -->
		<div class="sp-section" data-section="font-optimization">
			<h2 class="sp-section-header">
				<span><?php esc_html_e( 'Font Optimization', 'swiftpress' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<div class="sp-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Font Optimization', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_font_optimization" value="1"
									id="enable_font_optimization"
									data-master-toggle="#sp-sub-fonts"
									<?php checked( $settings['enable_font_optimization'] ); ?> />
								<?php esc_html_e( 'Optimize Google Fonts loading', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<div id="sp-sub-fonts" class="sp-sub-options">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Self-host Google Fonts', 'swiftpress' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="self_host_google_fonts" value="1"
										<?php checked( $settings['self_host_google_fonts'] ); ?> />
									<?php esc_html_e( 'Download and serve Google Fonts locally', 'swiftpress' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Preload Fonts', 'swiftpress' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="font_preload" value="1"
										<?php checked( $settings['font_preload'] ); ?> />
									<?php esc_html_e( 'Preload above-the-fold font files', 'swiftpress' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Font Display Swap', 'swiftpress' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="font_display_swap" value="1"
										<?php checked( $settings['font_display_swap'] ); ?> />
									<?php esc_html_e( 'Force font-display: swap on all @font-face declarations', 'swiftpress' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div><!-- /font-optimization -->

		<!-- ═══════════════════════════════════ SECTION 4: CACHE PRELOADER -->
		<div class="sp-section" data-section="cache-preloader">
			<h2 class="sp-section-header">
				<span><?php esc_html_e( 'Cache Preloader', 'swiftpress' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<div class="sp-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Cache Preloader', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_cache_preload" value="1"
									id="enable_cache_preload"
									data-master-toggle="#sp-sub-preloader"
									<?php checked( $settings['enable_cache_preload'] ); ?> />
								<?php esc_html_e( 'Automatically preload pages into the cache', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<div id="sp-sub-preloader" class="sp-sub-options">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Preload Options', 'swiftpress' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="preload_homepage" value="1"
										<?php checked( $settings['preload_homepage'] ); ?> />
									<?php esc_html_e( 'Preload homepage', 'swiftpress' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="preload_public_posts" value="1"
										<?php checked( $settings['preload_public_posts'] ); ?> />
									<?php esc_html_e( 'Preload posts/pages', 'swiftpress' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="preload_public_tax" value="1"
										<?php checked( $settings['preload_public_tax'] ); ?> />
									<?php esc_html_e( 'Preload taxonomies', 'swiftpress' ); ?>
								</label>
								<br />
								<label>
									<input type="checkbox" name="enable_sitemap_preload" value="1"
										id="enable_sitemap_preload"
										<?php checked( $settings['enable_sitemap_preload'] ); ?> />
									<?php esc_html_e( 'Preload from sitemap', 'swiftpress' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="preload_sitemap"><?php esc_html_e( 'Sitemap URL', 'swiftpress' ); ?></label>
							</th>
							<td>
								<input type="url" name="preload_sitemap" id="preload_sitemap"
									value="<?php echo esc_attr( $settings['preload_sitemap'] ); ?>"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'Auto-detected (leave blank)', 'swiftpress' ); ?>" />
								<button type="button" id="sp-refresh-sitemap" class="button button-secondary">
									<?php esc_html_e( 'Refresh Sitemap', 'swiftpress' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Leave empty for automatic detection (robots.txt, wp-sitemap.xml, sitemap_index.xml, sitemap.xml).', 'swiftpress' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="preload_crawl_interval"><?php esc_html_e( 'Crawl Interval', 'swiftpress' ); ?></label>
							</th>
							<td>
								<input type="number" name="preload_crawl_interval" id="preload_crawl_interval"
									value="<?php echo esc_attr( $settings['preload_crawl_interval'] ); ?>"
									min="10" step="1" class="small-text" />
								<?php esc_html_e( 'seconds between batch processing', 'swiftpress' ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="preload_request_interval"><?php esc_html_e( 'Request Interval', 'swiftpress' ); ?></label>
							</th>
							<td>
								<input type="number" name="preload_request_interval" id="preload_request_interval"
									value="<?php echo esc_attr( $settings['preload_request_interval'] ); ?>"
									min="0" step="1" class="small-text" />
								<?php esc_html_e( 'seconds between individual preload requests', 'swiftpress' ); ?>
							</td>
						</tr>
					</table>

					<!-- Preload Progress Display -->
					<div class="sp-progress-wrap">
						<h4 style="margin-top:0;">
							<?php esc_html_e( 'Preload Progress', 'swiftpress' ); ?>
						</h4>
						<div class="sp-progress-bar-outer">
							<div class="sp-progress-bar-inner" id="sp-progress-bar" style="width:0%"></div>
						</div>
						<div style="text-align:right;font-size:12px;color:#646970;margin-bottom:8px;">
							<span id="sp-progress-pct">0%</span>
						</div>
						<div class="sp-progress-stats">
							<span>
								<?php esc_html_e( 'Total:', 'swiftpress' ); ?>
								<strong id="sp-stat-total">0</strong>
							</span>
							<span>
								<?php esc_html_e( 'Completed:', 'swiftpress' ); ?>
								<strong id="sp-stat-completed">0</strong>
							</span>
							<span>
								<?php esc_html_e( 'Pending:', 'swiftpress' ); ?>
								<strong id="sp-stat-pending">0</strong>
							</span>
							<span class="sp-stat-failed">
								<?php esc_html_e( 'Failed:', 'swiftpress' ); ?>
								<strong id="sp-stat-failed">0</strong>
							</span>
							<span>
								<?php esc_html_e( 'Last run:', 'swiftpress' ); ?>
								<strong id="sp-stat-last-run">&mdash;</strong>
							</span>
						</div>
						<div style="margin-top:8px;font-size:12px;color:#646970;">
							<?php esc_html_e( 'Detected sitemap:', 'swiftpress' ); ?>
							<strong id="sp-stat-sitemap">&mdash;</strong>
						</div>
					</div>
				</div>
			</div>
		</div><!-- /cache-preloader -->

		<!-- ═══════════════════════════════════════ SECTION 5: ADVANCED -->
		<div class="sp-section" data-section="advanced">
			<h2 class="sp-section-header">
				<span><?php esc_html_e( 'Advanced', 'swiftpress' ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</h2>
			<div class="sp-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="prefetch_dns"><?php esc_html_e( 'DNS Prefetch', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="prefetch_dns" id="prefetch_dns" rows="4" class="large-text code"
								placeholder="<?php esc_attr_e( 'One domain per line, e.g. //fonts.googleapis.com', 'swiftpress' ); ?>"
							><?php echo esc_textarea( $settings['prefetch_dns'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="preconnect_resource"><?php esc_html_e( 'Preconnect', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="preconnect_resource" id="preconnect_resource" rows="4" class="large-text code"
								placeholder="<?php esc_attr_e( 'One domain per line, e.g. https://cdn.example.com', 'swiftpress' ); ?>"
							><?php echo esc_textarea( $settings['preconnect_resource'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="rejected_uri"><?php esc_html_e( 'Cache Exclusions', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="rejected_uri" id="rejected_uri" rows="4" class="large-text code"
								placeholder="<?php esc_attr_e( 'URL patterns to never cache, one per line', 'swiftpress' ); ?>"
							><?php echo esc_textarea( $settings['rejected_uri'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="rejected_user_agents"><?php esc_html_e( 'Rejected User Agents', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="rejected_user_agents" id="rejected_user_agents" rows="3" class="large-text code"
							><?php echo esc_textarea( $settings['rejected_user_agents'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="rejected_cookies"><?php esc_html_e( 'Rejected Cookies', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="rejected_cookies" id="rejected_cookies" rows="3" class="large-text code"
							><?php echo esc_textarea( $settings['rejected_cookies'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="vary_cookies"><?php esc_html_e( 'Vary Cookies', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="vary_cookies" id="vary_cookies" rows="3" class="large-text code"
							><?php echo esc_textarea( $settings['vary_cookies'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ignored_query_strings"><?php esc_html_e( 'Ignored Query Strings', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="ignored_query_strings" id="ignored_query_strings" rows="3" class="large-text code"
							><?php echo esc_textarea( $settings['ignored_query_strings'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cache_query_strings"><?php esc_html_e( 'Cache Query Strings', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="cache_query_strings" id="cache_query_strings" rows="3" class="large-text code"
							><?php echo esc_textarea( $settings['cache_query_strings'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="purge_additional_pages"><?php esc_html_e( 'Purge Additional Pages', 'swiftpress' ); ?></label>
						</th>
						<td>
							<textarea name="purge_additional_pages" id="purge_additional_pages" rows="3" class="large-text code"
							><?php echo esc_textarea( $settings['purge_additional_pages'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Prefetch Links', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="prefetch_links" value="1"
									<?php checked( $settings['prefetch_links'] ); ?> />
								<?php esc_html_e( 'Prefetch links on hover', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Async Cache Cleaning', 'swiftpress' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="async_cache_cleaning" value="1"
									<?php checked( $settings['async_cache_cleaning'] ); ?> />
								<?php esc_html_e( 'Clean cache asynchronously in the background', 'swiftpress' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<!-- Import / Export -->
				<h3><?php esc_html_e( 'Import / Export Settings', 'swiftpress' ); ?></h3>
				<div class="sp-import-export">
					<div class="sp-export-group">
						<p class="description"><?php esc_html_e( 'Export your current settings as a JSON file.', 'swiftpress' ); ?></p>
						<button type="submit" name="swiftpress_form_action" value="export_settings" class="button button-secondary">
							<?php esc_html_e( 'Export Settings', 'swiftpress' ); ?>
						</button>
					</div>
					<div class="sp-import-group">
						<p class="description"><?php esc_html_e( 'Import settings from a previously exported JSON file.', 'swiftpress' ); ?></p>
						<input type="file" name="import_file" accept=".json" />
						<button type="submit" name="swiftpress_form_action" value="import_settings" class="button button-secondary">
							<?php esc_html_e( 'Import Settings', 'swiftpress' ); ?>
						</button>
					</div>
				</div>

				<hr />

				<p>
					<button type="submit" name="swiftpress_form_action" value="reset_settings"
						class="button button-link-delete"
						onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to reset all settings to defaults?', 'swiftpress' ); ?>');">
						<?php esc_html_e( 'Reset All Settings', 'swiftpress' ); ?>
					</button>
				</p>
			</div>
		</div><!-- /advanced -->

		<!-- ═══════════════════════════════════════════════ FOOTER -->
		<div class="sp-footer">
			<?php submit_button( __( 'Save Settings', 'swiftpress' ), 'primary', 'submit', false ); ?>
			&nbsp;
			<button type="submit" name="swiftpress_form_action" value="save_settings_and_clear_cache" class="button button-secondary">
				<?php esc_html_e( 'Save & Clear Cache', 'swiftpress' ); ?>
			</button>
		</div>

	</form>
</div><!-- /.wrap -->
