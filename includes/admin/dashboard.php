<?php
/**
 * Dashboard Page
 *
 * @package SwiftPress
 */

namespace SwiftPress\Admin\Dashboard;

use SwiftPress\Async\CachePreloader;
use SwiftPress\Async\CachePurger;
use SwiftPress\Async\SitemapPreloader;
use SwiftPress\Config;
use SwiftPress\Preloader;
use function SwiftPress\Utils\is_dev_mode_active;
use const SwiftPress\Constants\ICON_BASE64;
use const SwiftPress\Constants\MENU_SLUG;
use const SwiftPress\Constants\PURGE_CACHE_CRON_NAME;
use const SwiftPress\Constants\PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT;
use const SwiftPress\Constants\SETTING_OPTION;
use function SwiftPress\Utils\can_configure_htaccess;
use function SwiftPress\Utils\can_control_all_settings;
use function SwiftPress\Utils\clean_site_cache_dir;
use function SwiftPress\Utils\get_cache_dir;
use function SwiftPress\Utils\get_timeout_with_interval;
use function SwiftPress\Utils\swiftpress_flush;
use function SwiftPress\Utils\remove_dir;
use function SwiftPress\Utils\sanitize_css;

// phpcs:disable WordPress.WhiteSpace.PrecisionAlignment.Found
// phpcs:disable Generic.WhiteSpace.DisallowSpaceIndent.SpacesUsed
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	if ( SWIFTPRESS_IS_NETWORK ) {
		add_action( 'network_admin_menu', __NAMESPACE__ . '\\admin_menu' );
		add_action( 'network_admin_notices', __NAMESPACE__ . '\\maybe_display_message' );
	} else {
		add_action( 'admin_menu', __NAMESPACE__ . '\\admin_menu' );
	}

	add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_display_message' );

	add_action( 'admin_init', __NAMESPACE__ . '\\process_form_submit' );
	add_filter( 'admin_body_class', __NAMESPACE__ . '\\add_sui_admin_body_class' );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\admin_bar_menu', 999 );
	add_action( 'admin_bar_menu', __NAMESPACE__ . '\\purge_all_admin_bar_menu' );
	add_action( 'admin_post_swiftpress_purge_all_cache', __NAMESPACE__ . '\\purge_all_cache_action' );
	add_action( 'admin_post_swiftpress_download_rewrite_settings', __NAMESPACE__ . '\\download_rewrite_config' );
	add_action( 'wp_ajax_swiftpress_run_diagnostic', __NAMESPACE__ . '\\run_diagnostic' );
	add_action( 'wp_ajax_swiftpress_clear_cache', __NAMESPACE__ . '\\ajax_clear_cache' );
	add_action( 'wp_ajax_swiftpress_clear_font_cache', __NAMESPACE__ . '\\ajax_clear_font_cache' );
	add_action( 'wp_ajax_swiftpress_refresh_sitemap', __NAMESPACE__ . '\\ajax_refresh_sitemap' );
	add_action( 'wp_ajax_swiftpress_preload_status', __NAMESPACE__ . '\\ajax_preload_status' );
	add_action( 'admin_post_deactivate_plugin', __NAMESPACE__ . '\\deactivate_plugin' );
	add_filter( 'plugin_action_links_' . plugin_basename( SWIFTPRESS_PLUGIN_FILE ), __NAMESPACE__ . '\\action_links' );
	add_filter( 'network_admin_plugin_action_links_' . plugin_basename( SWIFTPRESS_PLUGIN_FILE ), __NAMESPACE__ . '\\action_links' );
}

/**
 * Add admin body class for SwiftPress settings page
 *
 * @param string $classes css classes for admin area
 *
 * @return string
 */
function add_sui_admin_body_class( $classes ) {
	return $classes;
}

/**
 * Adds admin menu item
 *
 * @since 1.0
 */
function admin_menu() {
	global $swiftpress_settings_page;

	$capability = 'manage_options';

	if ( SWIFTPRESS_IS_NETWORK ) {
		$capability = 'manage_network';
	}

	$swiftpress_settings_page = add_menu_page(
		esc_html__( 'SwiftPress Settings', 'swiftpress' ),
		esc_html__( 'SwiftPress', 'swiftpress' ),
		$capability,
		MENU_SLUG,
		__NAMESPACE__ . '\settings_page',
		ICON_BASE64
	);

	/**
	 * Different name submenu item, url point same address with parent.
	 */
	add_submenu_page(
		MENU_SLUG,
		esc_html__( 'SwiftPress Settings', 'swiftpress' ),
		esc_html__( 'Settings', 'swiftpress' ),
		$capability,
		MENU_SLUG
	);
}

/**
 * Main settings page of the plugin
 */
function settings_page() {
	if ( function_exists( '\\SwiftPress\\Admin\\App\\render' ) ) {
		\SwiftPress\Admin\App\render();
		return;
	}
	include __DIR__ . '/partials/settings-page.php';
}

/**
 * Process settings form action
 *
 * @since 2.0
 */
function process_form_submit() {

	if ( SWIFTPRESS_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$nonce = filter_input( INPUT_POST, 'swiftpress_settings_nonce', FILTER_SANITIZE_SPECIAL_CHARS );
	if ( wp_verify_nonce( $nonce, 'swiftpress_update_settings' ) ) {
		$action      = isset( $_POST['swiftpress_form_action'] ) ? sanitize_text_field( wp_unslash( $_POST['swiftpress_form_action'] ) ) : 'save_settings';
		$old_options = \SwiftPress\Utils\get_settings();
		$options     = sanitize_options( $_POST );

		switch ( $action ) {
			case 'reset_settings':
				if ( SWIFTPRESS_IS_NETWORK ) {
					delete_site_option( SETTING_OPTION );
				} else {
					delete_option( SETTING_OPTION );
				}

				$options = \SwiftPress\Utils\get_settings();

				break;
			case 'export_settings':
				$filename = sprintf( 'swiftpress-settings-%s-%s.json', gmdate( 'Y-m-d' ), uniqid() );
				if ( SWIFTPRESS_IS_NETWORK ) {
					$options = get_site_option( SETTING_OPTION );
				} else {
					$options = get_option( SETTING_OPTION );
				}

				$sensitive_options = [
					'cloudflare_email', // PII data
					'cloudflare_api_key',
					'cloudflare_api_token',
					// AI module secrets — never export, even if a future refactor merges them into settings.
					'openrouter_key',
					'openrouter_key_cipher',
					'ai_openrouter_key',
					'psi_key_cipher',
				];

				foreach ( $sensitive_options as $option_key ) {
					if ( isset( $options[ $option_key ] ) ) {
						$options[ $option_key ] = '';
					}
				}

				$options = wp_json_encode( $options, JSON_PRETTY_PRINT );

				nocache_headers();
				// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
				@header( 'Content-Type: application/json' );
				@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
				@header( 'Content-Transfer-Encoding: binary' );
				@header( 'Content-Length: ' . strlen( $options ) );
				@header( 'Connection: close' );
				// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
				echo $options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			case 'import_settings':
				// Hardened import: require a successfully-uploaded file, cap its size,
				// and require the decoded JSON to be an array before trusting it. On any
				// failure we bail and keep the already-sanitized $options from $_POST.
				if (
					! isset( $_FILES['import_file'] )
					|| ! is_array( $_FILES['import_file'] )
					|| ! isset( $_FILES['import_file']['error'] )
					|| UPLOAD_ERR_OK !== (int) $_FILES['import_file']['error']
					|| empty( $_FILES['import_file']['tmp_name'] )
				) {
					break;
				}

				// Sane size cap (1 MB) — a settings JSON export is a few KB at most.
				$max_import_bytes = 1024 * 1024;
				if ( isset( $_FILES['import_file']['size'] ) && (int) $_FILES['import_file']['size'] > $max_import_bytes ) {
					break;
				}

				$import_file = sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) );
				if ( ! is_uploaded_file( $import_file ) ) {
					break;
				}

				$import_data     = file_get_contents( $import_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$import_settings = json_decode( $import_data, true );

				// Bail unless the payload decoded to an array.
				if ( ! is_array( $import_settings ) ) {
					break;
				}

				$options = sanitize_options( $import_settings );

				// Strip any AI / secret keys so an imported JSON can never seed a key.
				$import_secret_keys = [
					'openrouter_key',
					'openrouter_key_cipher',
					'ai_openrouter_key',
					'psi_key_cipher',
				];
				foreach ( $import_secret_keys as $import_secret_key ) {
					unset( $options[ $import_secret_key ] );
				}
				break;
			case 'enable_dev_mode':
				$options['dev_mode'] = true;
				break;
			case 'disable_dev_mode':
				$options['dev_mode'] = false;
				break;
			case 'save_settings_and_clear_cache':
				purge_all_cache( $options );
				break;
			case 'save_settings':
			default:
				break;
		}

		if ( SWIFTPRESS_IS_NETWORK ) {
			update_site_option( SETTING_OPTION, $options );
		} else {
			update_option( SETTING_OPTION, $options );
		}

		Config::factory()->save_configuration( $options, SWIFTPRESS_IS_NETWORK );

		// Flush cache when Dev Mode is turned OFF
		if ( ! empty( $old_options['dev_mode'] ) && empty( $options['dev_mode'] ) ) {
			wp_cache_flush();
		}

		// maybe cancel preloading process when it turned off
		if ( $old_options['enable_cache_preload'] && ! $options['enable_cache_preload'] ) {
			cancel_preloading();
		}

		// start the preloading process when it is turned on
		if ( ! $old_options['enable_cache_preload'] && $options['enable_cache_preload'] ) {
			start_preloading();
		}

		if ( $old_options['async_cache_cleaning'] && ! $options['async_cache_cleaning'] ) {
			cancel_async_cache_cleaning();
		}

		// cleanup existing cache on toggling cache option
		if ( $old_options['enable_page_cache'] && ! $options['enable_page_cache'] ) {
			clean_site_cache_dir();
		}

		// cleanup existing cache due to optimized URL changes
		if ( $old_options['rewrite_file_optimizer'] && ! $options['rewrite_file_optimizer'] ) {
			clean_site_cache_dir();
		}

		if ( $old_options['cache_timeout'] !== $options['cache_timeout'] ) {
			$timestamp = wp_next_scheduled( PURGE_CACHE_CRON_NAME );

			wp_unschedule_event( $timestamp, PURGE_CACHE_CRON_NAME );
		}

		/**
		 * Fires after saving configurations.
		 *
		 * @hook  swiftpress_settings_saved
		 *
		 * @param {array} $old_options Old settings.
		 * @param {array} $options New settings.
		 *
		 * @since 1.0
		 */
		do_action( 'swiftpress_settings_saved', $old_options, $options );

		$redirect_url = wp_get_referer();

		if ( empty( $redirect_url ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$redirect_url = wp_unslash( $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$redirect_url = add_query_arg(
			[
				'sp_action' => $action,
			],
			$redirect_url
		);

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}
}

/**
 * Sanitize options
 *
 * @param array $options Raw input, most likely $_POST request
 *
 * @return array|mixed Sanitized options
 */
function sanitize_options( $options ) {
	$sanitized_options = [];

	$sanitized_options['enable_page_cache']                = ! empty( $options['enable_page_cache'] );
	$sanitized_options['cache_mobile']                     = ! empty( $options['cache_mobile'] );
	$sanitized_options['cache_mobile_separate_file']       = ! empty( $options['cache_mobile_separate_file'] );
	$sanitized_options['loggedin_user_cache']              = ! empty( $options['loggedin_user_cache'] );
	$sanitized_options['gzip_compression']                 = ! empty( $options['gzip_compression'] );
	$sanitized_options['cache_timeout']                    = absint( $options['cache_timeout'] ?? 0 );
	$sanitized_options['auto_configure_htaccess']          = ! empty( $options['auto_configure_htaccess'] );
	$sanitized_options['rewrite_file_optimizer']           = ! empty( $options['rewrite_file_optimizer'] );
	$sanitized_options['rejected_user_agents']             = sanitize_textarea_field( $options['rejected_user_agents'] ?? '' );
	$sanitized_options['rejected_cookies']                 = sanitize_textarea_field( $options['rejected_cookies'] ?? '' );
	$sanitized_options['rejected_referrers']               = sanitize_textarea_field( $options['rejected_referrers'] ?? '' );
	$sanitized_options['vary_cookies']                     = sanitize_textarea_field( $options['vary_cookies'] ?? '' );
	$sanitized_options['rejected_uri']                     = sanitize_textarea_field( $options['rejected_uri'] ?? '' );
	$sanitized_options['cache_query_strings']              = sanitize_textarea_field( $options['cache_query_strings'] ?? '' );
	$sanitized_options['ignored_query_strings']            = sanitize_textarea_field( $options['ignored_query_strings'] ?? '' );
	$sanitized_options['purge_additional_pages']           = sanitize_textarea_field( $options['purge_additional_pages'] ?? '' );
	$sanitized_options['minify_html']                      = ! empty( $options['minify_html'] );
	$sanitized_options['minify_html_dom_optimization']     = ! empty( $options['minify_html_dom_optimization'] );
	$sanitized_options['enable_font_optimization']         = ! empty( $options['enable_font_optimization'] );
	$sanitized_options['self_host_google_fonts']            = ! empty( $options['self_host_google_fonts'] );
	$sanitized_options['font_preload']                     = ! empty( $options['font_preload'] );
	$sanitized_options['font_display_swap']                = ! empty( $options['font_display_swap'] );
	$sanitized_options['combine_google_fonts']             = ! empty( $options['combine_google_fonts'] );
	$sanitized_options['use_bunny_fonts']                  = ! empty( $options['use_bunny_fonts'] );
	$sanitized_options['swap_google_fonts_display']        = ! empty( $options['swap_google_fonts_display'] );
	$sanitized_options['minify_css']                       = ! empty( $options['minify_css'] );
	$sanitized_options['combine_css']                      = ! empty( $options['combine_css'] );
	$sanitized_options['critical_css']                     = ! empty( $options['critical_css'] );
	$sanitized_options['critical_css_additional_files']    = sanitize_textarea_field( $options['critical_css_additional_files'] ?? '' );
	$sanitized_options['critical_css_excluded_files']      = sanitize_textarea_field( $options['critical_css_excluded_files'] ?? '' );
	$sanitized_options['excluded_css_files']               = sanitize_textarea_field( $options['excluded_css_files'] ?? '' );
	$sanitized_options['remove_unused_css']                = ! empty( $options['remove_unused_css'] );
	$sanitized_options['ucss_safelist']                    = sanitize_textarea_field( $options['ucss_safelist'] ?? '' );
	$sanitized_options['ucss_excluded_files']              = sanitize_textarea_field( $options['ucss_excluded_files'] ?? '' );
	$sanitized_options['minify_js']                        = ! empty( $options['minify_js'] );
	$sanitized_options['combine_js']                       = ! empty( $options['combine_js'] );
	$sanitized_options['excluded_js_files']                = sanitize_textarea_field( $options['excluded_js_files'] ?? '' );
	$sanitized_options['js_defer']                         = ! empty( $options['js_defer'] );
	$sanitized_options['js_defer_exclusions']              = sanitize_textarea_field( $options['js_defer_exclusions'] ?? '' );
	$sanitized_options['js_delay']                         = ! empty( $options['js_delay'] );
	$sanitized_options['js_delay_exclusions']              = sanitize_textarea_field( $options['js_delay_exclusions'] ?? '' );
	$sanitized_options['js_delay_timeout']                 = absint( $options['js_delay_timeout'] ?? 0 );
	$sanitized_options['enable_image_optimization']        = ! empty( $options['enable_image_optimization'] );
	$sanitized_options['image_optimizer_preferred_format'] = isset( $options['image_optimizer_preferred_format'] ) ? sanitize_text_field( wp_unslash( $options['image_optimizer_preferred_format'] ) ) : '';
	$sanitized_options['add_missing_image_dimensions']     = ! empty( $options['add_missing_image_dimensions'] );
	$sanitized_options['disable_wp_embeds']                = ! empty( $options['disable_wp_embeds'] );
	$sanitized_options['disable_emoji_scripts']            = ! empty( $options['disable_emoji_scripts'] );

	// convert TTL in minute
	if ( ( $options['cache_timeout'] ?? 0 ) > 0 && isset( $options['cache_timeout_interval'] ) ) {
		switch ( $options['cache_timeout_interval'] ) {
			case 'DAY':
				$sanitized_options['cache_timeout'] = $options['cache_timeout'] * 1440;
				break;
			case 'HOUR':
				$sanitized_options['cache_timeout'] = $options['cache_timeout'] * 60;
				break;
			case 'MINUTE':
			default:
				$sanitized_options['cache_timeout'] = $options['cache_timeout'] * 1;
		}
	}

	$sanitized_options['enable_cache_preload']           = ! empty( $options['enable_cache_preload'] );
	$sanitized_options['preload_homepage']               = ! empty( $options['preload_homepage'] );
	$sanitized_options['preload_public_posts']           = ! empty( $options['preload_public_posts'] );
	$sanitized_options['preload_public_tax']             = ! empty( $options['preload_public_tax'] );
	$sanitized_options['enable_sitemap_preload']         = ! empty( $options['enable_sitemap_preload'] );
	$sanitized_options['preload_request_interval']       = absint( $options['preload_request_interval'] ?? 0 );
	$sanitized_options['preload_crawl_interval']         = max( 10, absint( isset( $options['preload_crawl_interval'] ) ? $options['preload_crawl_interval'] : 60 ) );
	$sanitized_options['preload_sitemap']                = isset( $options['preload_sitemap'] ) ? esc_url_raw( trim( $options['preload_sitemap'] ) ) : '';
	$sanitized_options['prefetch_dns']                   = sanitize_textarea_field( $options['prefetch_dns'] ?? '' );
	$sanitized_options['preconnect_resource']            = sanitize_textarea_field( $options['preconnect_resource'] ?? '' );
	$sanitized_options['enable_lcp_optimization']        = ! empty( $options['enable_lcp_optimization'] );
	$sanitized_options['prefetch_links']                 = ! empty( $options['prefetch_links'] );
	$sanitized_options['enable_cloudflare']              = ! empty( $options['enable_cloudflare'] );
	$sanitized_options['cloudflare_email']               = sanitize_email( $options['cloudflare_email'] ?? '' );
	$sanitized_options['cloudflare_api_key']             = sanitize_text_field( $options['cloudflare_api_key'] ?? '' );
	$sanitized_options['cloudflare_api_token']           = sanitize_text_field( $options['cloudflare_api_token'] ?? '' );
	$sanitized_options['cloudflare_zone']                = sanitize_text_field( $options['cloudflare_zone'] ?? '' );
	$sanitized_options['enable_heartbeat']               = ! empty( $options['enable_heartbeat'] );
	$sanitized_options['heartbeat_dashboard_status']     = sanitize_text_field( $options['heartbeat_dashboard_status'] ?? '' );
	$sanitized_options['heartbeat_editor_status']        = sanitize_text_field( $options['heartbeat_editor_status'] ?? '' );
	$sanitized_options['heartbeat_frontend_status']      = sanitize_text_field( $options['heartbeat_frontend_status'] ?? '' );
	$sanitized_options['heartbeat_dashboard_interval']   = absint( $options['heartbeat_dashboard_interval'] ?? 0 );
	$sanitized_options['heartbeat_editor_interval']      = absint( $options['heartbeat_editor_interval'] ?? 0 );
	$sanitized_options['heartbeat_frontend_interval']    = absint( $options['heartbeat_frontend_interval'] ?? 0 );
	$sanitized_options['enable_varnish']                 = ! empty( $options['enable_varnish'] );
	$sanitized_options['varnish_ip']                     = sanitize_text_field( $options['varnish_ip'] ?? '' );
	$sanitized_options['cache_footprint']                = ! empty( $options['cache_footprint'] );
	$sanitized_options['async_cache_cleaning']           = ! empty( $options['async_cache_cleaning'] );
	$sanitized_options['dev_mode']                       = ! empty( $options['dev_mode'] );
	$sanitized_options['enable_google_tracking']         = ! empty( $options['enable_google_tracking'] );
	$sanitized_options['enable_fb_tracking']             = ! empty( $options['enable_fb_tracking'] );

	if ( isset( $options['critical_css_appended_content'] ) ) {
		$sanitized_options['critical_css_appended_content'] = sanitize_css( $options['critical_css_appended_content'] );
	}

	if ( isset( $options['critical_css_fallback'] ) ) {
		$sanitized_options['critical_css_fallback'] = sanitize_css( $options['critical_css_fallback'] );
	}

	/**
	 * Filters sanitized options.
	 *
	 * @hook   swiftpress_sanitized_options
	 *
	 * @param  {array} $sanitized_options Sanitized options.
	 * @param  {array} $options raw input.
	 *
	 * @return {array} New value.
	 *
	 * @since  2.0
	 */
	return apply_filters( 'swiftpress_sanitized_options', $sanitized_options, $options );
}

/**
 * Add base admin bar menu
 *
 * @param object $wp_admin_bar Admin bar object
 *
 * @since 2.0
 */
function admin_bar_menu( $wp_admin_bar ) {
	$href = admin_url( 'admin.php?page=swiftpress' );

	if ( SWIFTPRESS_IS_NETWORK && current_user_can( 'manage_network' ) ) {
		$href = network_admin_url( 'admin.php?page=swiftpress' );
	}

	if ( SWIFTPRESS_IS_NETWORK && ! current_user_can( 'manage_network' ) ) {
		$href = '#';
	}

	if ( current_user_can( 'manage_options' ) ) {
		$wp_admin_bar->add_menu(
			array(
				'id'    => MENU_SLUG,
				'title' => __( 'SwiftPress', 'swiftpress' ),
				'href'  => $href,
			)
		);
	}
}

/**
 * Maybe display feedback messages when certain action is taken
 *
 * @since 2.0
 */
function maybe_display_message() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['sp_action'] ) ) {
		return;
	}

	/**
	 * Dont display multiple message when saving the options while having the query_params
	 */
	if ( ! empty( $_POST ) ) {
		return;
	}

	$screen = get_current_screen();

	$success_messages = [
		'purge_image_optimizer_cache'   => esc_html__( 'Image optimizer cache purged successfully!', 'swiftpress' ),
		'flush_page_cache_network'      => esc_html__( 'Page cache deleted for all websites!', 'swiftpress' ),
		'flush_page_cache'              => esc_html__( 'Page cache deleted successfully!', 'swiftpress' ),
		'flush_all_cache'               => esc_html__( 'All cached items flushed successfully!', 'swiftpress' ),
		'start_preload'                 => esc_html__( 'The cache preloading has been initialized!', 'swiftpress' ),
		'generate_critical'             => esc_html__( 'The Critical CSS generation process has been initialized!', 'swiftpress' ),
		'generate_critical_network'     => esc_html__( 'The Critical CSS generation process has been initialized for all sites! This might take a while, depending on the network size.', 'swiftpress' ),
		'generate_ucss'                 => esc_html__( 'The UCSS generation process has been initialized!', 'swiftpress' ),
		'generate_ucss_network'         => esc_html__( 'The UCSS generation process has been initialized for all sites! This might take a while, depending on the network size.', 'swiftpress' ),
		'flush_cf_cache'                => esc_html__( 'Cloudflare cache flushed, it can take up to 30 seconds to delete all cache from Cloudflare!', 'swiftpress' ),
		'reset_settings'                => esc_html__( 'Settings have been reset!', 'swiftpress' ),
		'import_settings'               => esc_html__( 'Settings have been imported!', 'swiftpress' ),
		'save_settings_and_clear_cache' => esc_html__( 'Settings have been successfully saved, and all cache has been cleared.', 'swiftpress' ),
		'save_settings'                 => esc_html__( 'Settings saved.', 'swiftpress' ),
	];

	if ( isset( $_GET['language'] ) ) {
		$success_messages['flush_lang_cache'] = sprintf( esc_html__( 'Page cache for %s language has been deleted!', 'swiftpress' ), esc_attr( urldecode_deep( $_GET['language'] ) ) ); // phpcs:ignore
	}

	$err_messages = [
		'purge_image_optimizer_cache_failed'      => esc_html__( 'Could not purge image optimizer cache. Please try again later and ensure your license key is activated!', 'swiftpress' ),
		'generic_permission_err'                  => esc_html__( 'You don\'t have permission to perform this action!', 'swiftpress' ),
		'flush_page_cache_network_err_permission' => esc_html__( 'You don\'t have permission to perform this action!', 'swiftpress' ),
		'flush_page_cache_err_permission'         => esc_html__( 'You don\'t have permission to perform this action!', 'swiftpress' ),
		'flush_all_cache_err_permission'          => esc_html__( 'You don\'t have permission to perform this action!', 'swiftpress' ),
		'start_preload_err_permission'            => esc_html__( 'You don\'t have permission to perform this action!', 'swiftpress' ),
		'start_critical_err_permission'           => esc_html__( 'You don\'t have permission to perform this action!', 'swiftpress' ),
		'start_ucss_err_permission'               => esc_html__( 'You don\'t have permission to perform this action!', 'swiftpress' ),
		'start_critical_err_license'              => esc_html__( 'Your license key does not seem valid. A valid license is required for the Critical CSS!', 'swiftpress' ),
		'start_ucss_err_license'                  => esc_html__( 'Your license key does not seem valid. A valid license is required for removing unused CSS!', 'swiftpress' ),
		'flush_cf_cache_failed'                   => esc_html__( 'Could not flush Cloudflare cache. Please make sure you entered the correct credentials and zone id!', 'swiftpress' ),
	];

	if ( isset( $success_messages[ $_GET['sp_action'] ] ) ) {
		if ( MENU_SLUG === $screen->parent_base ) { // display with shared-ui on plugin page
			add_settings_error( $screen->parent_file, MENU_SLUG, $success_messages[ $_GET['sp_action'] ], 'success' ); // phpcs:ignore

			return;
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', $success_messages[ $_GET['sp_action'] ] ); // phpcs:ignore
	}

	if ( isset( $err_messages[ $_GET['sp_action'] ] ) ) {
		if ( MENU_SLUG === $screen->parent_base ) { // display with shared-ui on plugin page
			add_settings_error( $screen->parent_file, MENU_SLUG, $err_messages[ $_GET['sp_action'] ], 'error' ); // phpcs:ignore

			return;
		}

		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', $err_messages[ $_GET['sp_action'] ] ); // phpcs:ignore
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}


/**
 * Adds `Purge All Cache` menu bar item
 *
 * @param object $wp_admin_bar Admin bar object
 *
 * @since 1.1
 */
function purge_all_admin_bar_menu( $wp_admin_bar ) {
	// Only available for the network admins on multisite.
	if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
		return;
	}

	$wp_admin_bar->add_menu(
		array(
			'id'     => 'all-cache-purge',
			'title'  => __( 'Purge All Cache', 'swiftpress' ),
			'href'   => wp_nonce_url( admin_url( 'admin-post.php?action=swiftpress_purge_all_cache' ), 'swiftpress_purge_all_cache' ),
			'parent' => 'swiftpress',
		)
	);
}

/**
 * Purges all cache related things
 *
 * @since 1.1
 */
function purge_all_cache_action() {

	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'swiftpress_purge_all_cache' ) ) { // phpcs:ignore
		wp_nonce_ays( '' );
	}

	if ( is_multisite() && ! current_user_can( 'manage_network' ) ) {
		$redirect_url = add_query_arg( 'sp_action', 'flush_all_cache_err_permission', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		$redirect_url = add_query_arg( 'sp_action', 'flush_all_cache_err_permission', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	purge_all_cache();

	$redirect_url = add_query_arg( 'sp_action', 'flush_all_cache', wp_get_referer() );

	wp_safe_redirect( esc_url_raw( $redirect_url ) );
	exit;
}


/**
 * Purge all cache
 *
 * @param array $settings plugin settings
 *
 * @return void
 */
function purge_all_cache( $settings = array() ) {
	$cache_purger = CachePurger::factory();
	if ( empty( $settings ) ) {
		$settings = \SwiftPress\Utils\get_settings();
	}

	if ( $settings['async_cache_cleaning'] ) {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		$cache_purger->push_to_queue( [ 'call' => 'swiftpress_flush' ] );
		$cache_purger->save()->dispatch();
	} else {
		swiftpress_flush();// cleans object cache + page cache dir
	}

	/**
	 * Fires after purging all cache
	 *
	 * @hook  swiftpress_purge_all_cache
	 * @since 1.1
	 */
	do_action( 'swiftpress_purge_all_cache' );

	if ( SWIFTPRESS_IS_NETWORK ) {
		delete_site_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );
	} else {
		delete_transient( PURGE_CACHE_PLUGIN_NOTICE_TRANSIENT );
	}
}


/**
 * Downloads proper configuration file
 *
 * @since 1.1
 */
function download_rewrite_config() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'swiftpress_download_rewrite' ) ) { // phpcs:ignore
		wp_nonce_ays( '' );
	}

	if ( ! can_control_all_settings() ) {
		$redirect_url = add_query_arg( 'sp_action', 'generic_permission_err', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( ! empty( $_GET['server'] ) ) {
		$server = sanitize_text_field( wp_unslash( $_GET['server'] ) );
		Config::factory()->download_rewrite_rules( $server );
	}

	wp_safe_redirect( wp_get_referer() );
	die();
}

/**
 * Cancel preloading process on toggling preload option
 */
function cancel_preloading() {
	\SwiftPress\Utils\log( 'Cancel preload process - Settings toggle' );
	$cache_preloader = CachePreloader::factory();
	$cache_preloader->cancel_process();
	$cache_preloader->delete_all();
}

/**
 * Kick-start preloading process
 *
 * @return void
 */
function start_preloading() {
	\SwiftPress\Utils\log( 'Enable Preloader - Settings toggle' );
	Preloader::factory()->setup_preload_queue();
	// kickstart the preloading process
	Preloader::factory()->dispatch_preload_queue();
}

/**
 * Cancel async cache purging processes
 *
 * @since 2.3
 */
function cancel_async_cache_cleaning() {
	\SwiftPress\Utils\log( 'Cancel CachePurger process' );
	$cache_preloader = CachePurger::factory();
	$cache_preloader->cancel_process();
}


// ──────────────────────────────────────────────────────────────
//  Phase 6 – AJAX Endpoints for Settings Page
// ──────────────────────────────────────────────────────────────

/**
 * AJAX: Clear all cache
 *
 * @since 3.8
 */
function ajax_clear_cache() {
	check_ajax_referer( 'swiftpress_settings_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'swiftpress' ) ] );
	}

	purge_all_cache();

	wp_send_json_success( [ 'message' => esc_html__( 'All cache cleared successfully.', 'swiftpress' ) ] );
}

/**
 * AJAX: Clear font cache
 *
 * @since 3.8
 */
function ajax_clear_font_cache() {
	check_ajax_referer( 'swiftpress_settings_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'swiftpress' ) ] );
	}

	$font_cache_dir = get_cache_dir() . 'swiftpress/fonts/';

	if ( is_dir( $font_cache_dir ) ) {
		remove_dir( $font_cache_dir );
		\SwiftPress\Utils\log( 'Font cache directory cleared via AJAX.' );
	}

	wp_send_json_success( [ 'message' => esc_html__( 'Font cache cleared successfully.', 'swiftpress' ) ] );
}

/**
 * AJAX: Refresh sitemap (re-detect + re-parse)
 *
 * @since 3.8
 */
function ajax_refresh_sitemap() {
	check_ajax_referer( 'swiftpress_settings_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'swiftpress' ) ] );
	}

	$stats = SitemapPreloader::factory()->manual_refresh();

	wp_send_json_success( $stats );
}

/**
 * AJAX: Get preload status (for progress bar auto-refresh)
 *
 * @since 3.8
 */
function ajax_preload_status() {
	check_ajax_referer( 'swiftpress_settings_ajax', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => esc_html__( 'Permission denied.', 'swiftpress' ) ] );
	}

	$stats = SitemapPreloader::factory()->get_preload_stats();

	wp_send_json_success( $stats );
}


/**
 * Perform diagnostic checks
 */
function run_diagnostic() {
	global $is_apache;

	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

	if ( wp_verify_nonce( $nonce, 'swiftpress_run_diagnostic' ) ) {
		$settings = \SwiftPress\Utils\get_settings();
		$checks   = array();

		// check config file
		$config_file        = Config::factory()->find_wp_config_file();
		$config_file_status = is_writeable( $config_file );

		if ( $config_file_status ) {
			$config_file_desc = esc_html__( 'wp-config.php is writable.', 'swiftpress' );
		} else {
			$config_file_desc = sprintf( __( 'wp-config.php is not writable. Please make sure the file writable or you can manually define %s constant.', 'swiftpress' ), '<code>WP_CACHE</code>' );
		}

		$checks[] = array(
			'check'       => 'config',
			'status'      => $config_file_status,
			'description' => $config_file_desc,
		);

		// check cache directory
		$cache_dir        = get_cache_dir();
		$cache_dir_status = false;
		if ( ! file_exists( $cache_dir ) ) {
			$cache_dir_desc = sprintf( __( 'Cache directory %s is not exist!', 'swiftpress' ), '<code>' . $cache_dir . '</code>' );
		} elseif ( ! is_writeable( $cache_dir ) ) {
			$cache_dir_desc = sprintf( __( 'Cache directory %s is not writeable!', 'swiftpress' ), '<code>' . $cache_dir . '</code>' );
		} else {
			$cache_dir_status = true;
			$cache_dir_desc   = sprintf( __( 'Cache directory %s exist and writable!', 'swiftpress' ), '<code>' . $cache_dir . '</code>' );
		}

		$checks[] = array(
			'check'       => 'cache-dir',
			'status'      => $cache_dir_status,
			'description' => $cache_dir_desc,
		);

		// check .htaccess file
		if ( $is_apache && $settings['auto_configure_htaccess'] ) {
			$htaccess_file        = get_home_path() . '.htaccess';
			$htaccess_file_status = false;
			if ( ! file_exists( $htaccess_file ) ) {
				$htaccess_file_desc = sprintf( __( '.htaccess file %s is not exist!', 'swiftpress' ), '<code>' . $htaccess_file . '</code>' );
			} elseif ( ! is_writeable( $htaccess_file ) ) {
				$htaccess_file_desc = sprintf( __( '.htaccess file %s is not writeable!', 'swiftpress' ), '<code>' . $htaccess_file . '</code>' );
			} else {
				$htaccess_file_status = true;
				$htaccess_file_desc   = sprintf( __( '.htaccess file %s exist and writable!', 'swiftpress' ), '<code>' . $htaccess_file . '</code>' );
			}

			$checks[] = array(
				'check'       => 'htaccess',
				'status'      => $htaccess_file_status,
				'description' => $htaccess_file_desc,
			);
		}

		// check page cache
		if ( $settings['enable_page_cache'] ) {
			$advanced_cache_file        = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
			$advanced_cache_file_status = false;
			if ( ! file_exists( $advanced_cache_file ) ) {
				$advanced_cache_file_desc = sprintf( __( 'Required file for the page caching %s is not exist!', 'swiftpress' ), '<code>' . $advanced_cache_file . '</code>' );
			} elseif ( ! is_writeable( $advanced_cache_file ) ) {
				$advanced_cache_file_desc = sprintf( __( 'Required file for the page caching %s is not writeable!', 'swiftpress' ), '<code>' . $advanced_cache_file . '</code>' );
			} else {
				$advanced_cache_file_status = true;
				$advanced_cache_file_desc   = sprintf( __( 'Required file for the page caching %s exist and writable!', 'swiftpress' ), '<code>' . $advanced_cache_file . '</code>' );
			}

			$checks[] = array(
				'check'       => 'advanced-cache',
				'status'      => $advanced_cache_file_status,
				'description' => $advanced_cache_file_desc,
			);
		}

		wp_send_json_success( $checks );
	}

	wp_send_json_error( [ esc_html__( 'Invalid request', 'swiftpress' ) ] );
}



/**
 * Deactivate incompatible plugins
 *
 * @since 1.0
 */
function deactivate_plugin() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'deactivate_plugin' ) ) { // phpcs:ignore
		wp_nonce_ays( '' );
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		$redirect_url = add_query_arg( 'sp_action', 'generic_permission_err', wp_get_referer() );
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	if ( isset( $_GET['plugin'] ) ) {
		$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );
		deactivate_plugins( $plugin );
	}

	wp_safe_redirect( wp_get_referer() );
	die();
}

/**
 * Adds settings link to plugin actions
 *
 * @param array $actions Plugin actions.
 *
 * @return array
 * @since  1.0
 */
function action_links( $actions ) {

	$settings_url      = SWIFTPRESS_IS_NETWORK ? network_admin_url( 'admin.php?page=swiftpress' ) : admin_url( 'admin.php?page=swiftpress' );
	$swiftpress_url = 'https://swiftpress.dev/?utm_source=wp_admin&utm_medium=plugin&utm_campaign=plugin_action_links';

	$actions['powered_settings'] = sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Settings', 'swiftpress' ) );

	return array_reverse( $actions );
}

