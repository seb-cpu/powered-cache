<?php
/**
 * Utils
 *
 * @package SwiftPress
 */

namespace SwiftPress\Utils;

use const SwiftPress\Constants\SETTING_OPTION;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Is plugin activated network wide?
 *
 * @param string $plugin_file file path
 *
 * @return bool
 * @since 2.0
 */
function is_network_wide( $plugin_file ) {
	if ( ! is_multisite() ) {
		return false;
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
	}

	return is_plugin_active_for_network( plugin_basename( $plugin_file ) );
}

/**
 * Get settings with defaults
 *
 * @param bool $force_network_wide Whether getting settings for network or not.
 *                                 The function respects `SWIFTPRESS_IS_NETWORK` by default.
 *                                 However, `SWIFTPRESS_IS_NETWORK` is not functional on
 *                                 (de)activation hooks.
 *
 * @return array
 * @since  2.0
 */
function get_settings( $force_network_wide = false ) {
	global $is_apache;

	$settings = [
		// basic options
		'enable_page_cache'                => true,
		'cache_mobile'                     => true,
		'cache_mobile_separate_file'       => false,
		'loggedin_user_cache'              => false,
		'ssl_cache'                        => true, // deprecated
		'gzip_compression'                 => false,
		'cache_timeout'                    => 1440,
		// advanced options
		'auto_configure_htaccess'          => $is_apache,
		'rejected_user_agents'             => '',
		'rejected_cookies'                 => '',
		'rejected_referrers'               => '',
		'vary_cookies'                     => '',
		'rejected_uri'                     => '',
		'ignored_query_strings'            => '',
		'cache_query_strings'              => '',
		'purge_additional_pages'           => '',
		// file optimization
		'minify_html'                      => false,
		'minify_html_dom_optimization'     => false,
		'minify_css'                       => false,
		'combine_css'                      => false,
		'critical_css'                     => false,
		'critical_css_additional_files'    => '',
		'critical_css_excluded_files'      => '',
		'critical_css_appended_content'    => '',
		'critical_css_fallback'            => '',
		'excluded_css_files'               => '',
		'remove_unused_css'                => false,
		'ucss_safelist'                    => '',
		'ucss_excluded_files'              => '',
		'minify_js'                        => false,
		'combine_js'                       => false,
		'excluded_js_files'                => '',
		'js_execution_method'              => 'blocking', // deprecated @since 3.2
		'js_defer'                         => false,
		'js_defer_exclusions'              => '',
		'js_delay'                         => false,
		'js_delay_exclusions'              => '',
		'js_delay_timeout'                 => 0,
		'js_execution_optimized_only'      => true,   // deprecated @since 3.2
		'rewrite_file_optimizer'           => $is_apache,
		// media optimization
		'enable_image_optimization'        => false,
		'image_optimizer_preferred_format' => '',
		'add_missing_image_dimensions'     => false,
		'disable_wp_embeds'                => false,
		'disable_emoji_scripts'            => false,
		// cdn (kept for backward compatibility with htaccess/nginx config)
		'enable_cdn'                       => false,
		// font optimization
		'enable_font_optimization'         => false,
		'font_preload'                     => true,
		'font_display_swap'                => true,
		'self_host_google_fonts'           => true,
		// preload
		'enable_cache_preload'             => false,
		'preload_homepage'                 => true,
		'preload_public_posts'             => true,
		'preload_public_tax'               => true,
		'enable_sitemap_preload'           => true,
		'preload_request_interval'         => 2, // in seconds
		'preload_crawl_interval'           => 60, // seconds between sitemap batch cron runs
		'preload_sitemap'                  => '',
		'prefetch_dns'                     => '',
		'preconnect_resource'              => '',
		'prefetch_links'                   => true,
		'enable_lcp_optimization'          => false,
		// add-ons
		'enable_cloudflare'                => false,
		'cloudflare_api_token'             => '',
		'cloudflare_email'                 => '',
		'cloudflare_api_key'               => '',
		'cloudflare_zone'                  => '',
		'enable_heartbeat'                 => false, // extension status
		'heartbeat_dashboard_status'       => 'enable', // enable,disable,modify
		'heartbeat_dashboard_interval'     => 60, // default interval in seconds
		'heartbeat_editor_status'          => 'enable', // enable,disable,modify
		'heartbeat_editor_interval'        => 15, // default interval in seconds
		'heartbeat_frontend_status'        => 'enable', // enable,disable,modify
		'heartbeat_frontend_interval'      => 60, // default interval in seconds
		'enable_varnish'                   => false,
		'varnish_ip'                       => '',
		// misc
		'cache_footprint'                  => true,
		'async_cache_cleaning'             => false,
		'dev_mode'                         => false,
		// new options needs to migrate from extensions
		'enable_google_tracking'           => false,
		'enable_fb_tracking'               => false,
	];

	/**
	 * Filter default settings.
	 *
	 * @hook   swiftpress_default_settings
	 *
	 * @param  {array} $settings Default settings.
	 *
	 * @return {array} New value
	 * @since  2.0
	 */
	$default_settings = apply_filters( 'swiftpress_default_settings', $settings );

	if ( SWIFTPRESS_IS_NETWORK || $force_network_wide ) {
		$settings = get_site_option( SETTING_OPTION, [] );
	} else {
		$settings = get_option( SETTING_OPTION, [] );
	}

	$settings = wp_parse_args( $settings, $default_settings );

	return $settings;
}


/**
 * return base caching dir
 * use this function to get base caching directory instead of directly calling constant
 *
 * @return string path
 * @since 1.0
 */
function get_cache_dir() {
	if ( defined( 'SWIFTPRESS_CACHE_DIR' ) ) {
		return SWIFTPRESS_CACHE_DIR; // don't change unless have a particular reason
	}

	return WP_CONTENT_DIR . '/cache/';
}



/**
 * convert minutes to possible time format
 *
 * @param int $timeout_in_minutes TTL in minutes
 *
 * @return array
 * @since 1.1
 */
function get_timeout_with_interval( $timeout_in_minutes ) {
	$cache_timeout     = $timeout_in_minutes;
	$selected_interval = 'MINUTE';

	if ( $cache_timeout > 0 ) {
		if ( 0 === (int) ( $cache_timeout % 1440 ) ) {
			$cache_timeout     = $cache_timeout / 1440;
			$selected_interval = 'DAY';
		} elseif ( 0 === (int) ( $cache_timeout % 60 ) ) {
			$cache_timeout     = $cache_timeout / 60;
			$selected_interval = 'HOUR';
		}
	}

	return array(
		$cache_timeout,
		$selected_interval,
	);
}


/**
 * Determine whether display or not display htaccess configuration
 * .htaccess can affect the way of serving cached files.
 * Therefore it's only available for network admin on multisite
 *
 * @return bool
 */
function can_configure_htaccess() {
	global $is_apache;

	if ( ! $is_apache ) {
		return false;
	}

	if ( SWIFTPRESS_IS_NETWORK && current_user_can( 'manage_network' ) ) {
		return true;
	}

	if ( is_multisite() && ! SWIFTPRESS_IS_NETWORK ) {
		return false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return false;
}

/**
 * Whether current user capable to do any configuration changes
 *
 * @return bool
 */
function can_control_all_settings() {
	if ( is_multisite() ) {
		if ( current_user_can( 'manage_network' ) ) {
			return true;
		}

		return false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return false;
}



/**
 * Display settings errors using native WordPress admin notice markup.
 *
 * @param string $setting        Slug title of a specific setting
 * @param bool   $sanitize       Whether to re-sanitize the setting value before returning errors
 * @param bool   $hide_on_update Whether hide or not hide on update
 *
 * @see settings_errors
 */
function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {

	if ( $hide_on_update && ! empty( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$settings_errors = get_settings_errors( $setting, $sanitize );

	if ( empty( $settings_errors ) ) {
		return;
	}

	$output = '';

	foreach ( $settings_errors as $key => $details ) {
		if ( 'updated' === $details['type'] ) {
			$details['type'] = 'success';
		}

		$css_id = sprintf(
			'setting-error-%s',
			esc_attr( $details['code'] )
		);

		$css_class = sprintf(
			'notice notice-%s settings-error is-dismissible',
			esc_attr( $details['type'] )
		);

		$output .= "<div id='$css_id' class='$css_class'> \n";
		$output .= "<p>{$details['message']}</p>";
		$output .= "</div> \n";
	}

	echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * remove directories recursively
 * Adopted from W3TC Utility
 *
 * @param string $path    The target path
 * @param array  $exclude list of the files that will excluded
 *
 * @return void
 * @since 1.2.5
 */
function remove_dir( $path, $exclude = array() ) {
	// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
	$dir = @opendir( $path );

	if ( $dir ) {
		while ( ( $entry = @readdir( $dir ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			foreach ( $exclude as $mask ) {
				if ( fnmatch( $mask, basename( $entry ) ) ) {
					continue 2;
				}
			}

			$full_path = $path . DIRECTORY_SEPARATOR . $entry;

			if ( @is_dir( $full_path ) ) {
				remove_dir( $full_path, $exclude );
			} else {
				@unlink( $full_path );
			}
		}

		@closedir( $dir );
		@rmdir( $path );
	}
	// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * Get base caching directory of the site.
 *
 * @return mixed|void
 * @since 1.1
 */
function site_cache_dir() {
	$base_dir = get_page_cache_dir();

	// compatible with multisite
	$site_url = get_site_url();

	$site_url_parsed = wp_parse_url( $site_url );

	$site_path = $site_url_parsed['host'];

	if ( ! empty( $site_url_parsed['path'] ) ) {
		$site_path .= $site_url_parsed['path'];
	}

	$site_cache_dir = trailingslashit( $base_dir . $site_path );

	/**
	 * Filter get base caching directory of site
	 *
	 * @hook   swiftpress_site_cache_dir
	 *
	 * @param  {string} $site_cache_dir Site cache dir.
	 *
	 * @return {string} New value
	 * @since  1.1
	 */
	return apply_filters( 'swiftpress_site_cache_dir', $site_cache_dir );
}

/**
 * Page cache base directory.
 *
 * @return string
 * @since 1.1 $url parameter removed
 * @since 1.0
 */
function get_page_cache_dir() {
	$path = get_cache_dir() . 'swiftpress/';

	/**
	 * Filter page cache base directory.
	 *
	 * @hook   swiftpress_get_page_cache_dir
	 *
	 * @param  {string} $path Page cache dir
	 *
	 * @return {string} New value
	 * @since  1.0
	 */
	return apply_filters( 'swiftpress_get_page_cache_dir', $path );
}

/**
 * Clean up cache directory
 *
 * @return mixed
 * @since 1.0
 */
function clean_page_cache_dir() {
	remove_dir( get_page_cache_dir() );
}

/**
 * Clean cache base for the current site
 *
 * @return mixed
 * @since 1.1
 */
function clean_site_cache_dir() {
	$site_cache_dir = site_cache_dir();

	/**
	 * When deleting cache for the main site on multisite subdirectory setup
	 * Don't delete other site's cache
	 */
	if ( is_multisite() && ! is_subdomain_install() && is_main_site() ) {
		$base_dir    = get_page_cache_dir();
		$directories = glob( $site_cache_dir . '*', GLOB_ONLYDIR );
		$site_url    = get_site_url();

		$site_domain = wp_parse_url( $site_url, PHP_URL_HOST );
		foreach ( $directories as $directory ) {
			$dir_name  = str_replace( $base_dir . $site_domain, '', $directory );
			$site_info = get_site_by_path( $site_domain, $dir_name );
			if ( ! $site_info || is_main_site( $site_info->blog_id ) ) {
				remove_dir( $directory );
			}
		}
	} else {
		remove_dir( $site_cache_dir );
	}

	/**
	 * Fires after deleting site cache dir
	 *
	 * @hook  swiftpress_clean_site_cache_dir
	 *
	 * @param {string} $site_cache_dir The caching directory of the current site.
	 *
	 * @since 2.0
	 */
	do_action( 'swiftpress_clean_site_cache_dir', $site_cache_dir );
}




/**
 * Collect post related urls
 *
 * @param int $post_id Post ID
 *
 * @return array
 * @since 1.0
 * @since 1.1 swiftpress_post_related_urls filter added
 */
function get_post_related_urls( $post_id ) {
	// Valid post statuses that require cache purging.
	$valid_post_statuses = [ 'publish', 'private', 'trash', 'pending', 'draft' ];
	$post_status         = get_post_status( $post_id );
	$post                = get_post( $post_id );

	// Post types that should not have their cache purged.
	$excluded_post_types = [ 'nav_menu_item', 'revision' ];
	$post_type           = get_post_type( $post_id );
	$rest_api_route      = 'wp/v2';

	$related_urls = [];

	if ( false !== get_permalink( $post_id ) && in_array( $post_status, $valid_post_statuses, true ) && ! in_array( $post_type, $excluded_post_types, true ) ) {
		// Add the post URL.
		$related_urls[] = get_permalink( $post_id );

		// Add REST API URL if applicable.
		if ( $rest_api_route ) {
			$post_type_object = get_post_type_object( $post_type );
			if ( ! empty( $post_type_object->show_in_rest ) ) {
				$post_type_base = $post_type_object->rest_base ? $post_type_object->rest_base : $post_type_object->name;
				$related_urls[] = get_rest_url() . $rest_api_route . '/' . $post_type_base . '/' . $post_id . '/';
			} elseif ( in_array( $post_type, [ 'post', 'page' ], true ) ) {
				$related_urls[] = get_rest_url() . $rest_api_route . '/' . $post_type . 's/' . $post_id . '/';
			}
		}

		// Add AMP URL if AMP plugin is active.
		if ( function_exists( 'amp_get_permalink' ) ) {
			$related_urls[] = amp_get_permalink( $post_id );
		}

		// Regular AMP url for posts if ant of the following are active:
		// https://wordpress.org/plugins/accelerated-mobile-pages/
		if ( defined( 'AMPFORWP_AMP_QUERY_VAR' ) ) {
			$related_urls[] = get_permalink( $post_id ) . 'amp/';
		}

		// Handle trashed post URLs.
		if ( 'trash' === $post_status ) {
			$trash_permalink = str_replace( '__trashed', '', get_permalink( $post_id ) );
			$related_urls[]  = $trash_permalink;
			$related_urls[]  = $trash_permalink . 'feed/';
		}

		$taxonomies = get_object_taxonomies( get_post_type( $post_id ), 'objects' );

		// Purge terms associated with the post.
		foreach ( $taxonomies as $taxonomy ) {
			// Skip non-public taxonomies.
			if ( ! $taxonomy->public ) {
				continue;
			}

			$terms = get_the_terms( $post_id, $taxonomy->name );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$term_url = get_term_link( $term->slug, $taxonomy->name );
				if ( ! is_wp_error( $term_url ) ) {
					$related_urls[] = $term_url;
					if ( $taxonomy->show_in_rest ) {
						$taxonomy_base  = $taxonomy->rest_base ? $taxonomy->rest_base : $taxonomy->name;
						$related_urls[] = rest_url( "{$taxonomy->rest_namespace}/{$taxonomy_base}/{$term->term_id}/" ); // REST API URL for the term
					}
				}

				if ( ! is_taxonomy_hierarchical( $taxonomy->name ) ) {
					continue;
				}

				$ancestors = (array) get_ancestors( $term->term_id, $taxonomy->name );
				foreach ( $ancestors as $ancestor ) {
					$ancestor_object = get_term( $ancestor, $taxonomy->name );
					if ( ! is_a( $ancestor, '\WP_Term' ) ) {
						continue;
					}

					$ancestor_term_url = get_term_link( $ancestor_object->slug, $taxonomy->name );
					if ( ! is_wp_error( $ancestor_term_url ) ) {
						$related_urls[] = $ancestor_term_url;
						if ( $taxonomy->show_in_rest ) {
							$taxonomy_base  = $taxonomy->rest_base ? $taxonomy->rest_base : $taxonomy->name;
							$related_urls[] = rest_url( "{$taxonomy->rest_namespace}/{$taxonomy_base}/{$ancestor_object->term_id}/" ); // REST API URL for the ancestor term
						}
					}
				}
			}
		}

		// Purge author and feed URLs for posts.
		if ( 'post' === $post_type ) {
			$author_id      = get_post_field( 'post_author', $post_id );
			$related_urls[] = get_author_posts_url( $author_id );
			$related_urls[] = get_author_feed_link( $author_id );
			if ( $rest_api_route ) {
				$related_urls[] = get_rest_url() . $rest_api_route . '/users/' . $author_id . '/';
			}

			// Include various feed URLs.
			$feed_urls    = [
				get_bloginfo_rss( 'rdf_url' ),
				get_bloginfo_rss( 'rss_url' ),
				get_bloginfo_rss( 'rss2_url' ),
				get_bloginfo_rss( 'atom_url' ),
				get_bloginfo_rss( 'comments_rss2_url' ),
				get_post_comments_feed_link( $post_id ),
			];
			$related_urls = array_merge( $related_urls, $feed_urls );
		}

		// Purge archive pages if not excluded.
		if ( ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
			$related_urls[] = get_post_type_archive_link( $post_type );
			$related_urls[] = get_post_type_archive_feed_link( $post_type );
		}

		$post_date = strtotime( $post->post_date );

		if ( $post_date ) {
			// Generate the date archive URLs
			$year  = gmdate( 'Y', $post_date );
			$month = gmdate( 'm', $post_date );
			$day   = gmdate( 'd', $post_date );

			$related_urls[] = get_year_link( $year );
			$related_urls[] = get_month_link( $year, $month );
			$related_urls[] = get_day_link( $year, $month, $day );
		}

		// Always purge the home page.
		$related_urls[] = home_url( '/' );

		// Purge the posts page if it's set to a static page.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$posts_page_id = get_option( 'page_for_posts' );
			if ( $posts_page_id ) {
				$related_urls[] = get_permalink( $posts_page_id );
			}
		}
	}

	// Remove query strings and ensure unique URLs before purging.
	$related_urls = array_unique(
		array_map(
			function ( $url ) {
				return strtok( $url, '?' );
			},
			$related_urls
		)
	);

	/**
	 * Filters post related urls.
	 *
	 * @hook   swiftpress_post_related_urls
	 *
	 * @param  {array} $related_urls The list of the URLs that related with the post.
	 *
	 * @return {array} New value.
	 * @since  1.0
	 */
	$related_urls = apply_filters( 'swiftpress_post_related_urls', $related_urls );

	return $related_urls;
}


/**
 * Delete cache file
 *
 * @param string $url                   Target URL
 * @param bool   $delete_subdirectories Whether delete subdirectories or not
 *
 * @return bool  true when found cache dir, otherwise false
 * @since 1.0
 */
function delete_page_cache( $url, $delete_subdirectories = false ) {
	$dir = get_url_dir( trim( $url ) );

	if ( is_dir( $dir ) ) {
		$files = scandir( $dir );
		foreach ( $files as $file ) {
			/**
			 * Don't need to lookup for index-https, index-https-mobile etc..
			 * Just clean that directory's files only.
			 */
			if ( ! is_dir( $dir . $file ) && file_exists( $dir . $file ) && ! in_array( $file, array( '.', '..' ), true ) ) {
				unlink( $dir . $file );
			}
		}

		if ( file_exists( $dir ) && ( $delete_subdirectories || is_dir_empty( $dir ) ) ) {
			remove_dir( $dir );
		}

		return true;
	}

	return false;
}


/**
 * Get cache location of given url
 *
 * @param string $url The url to retrieve path
 *
 * @return mixed|void
 * @since 1.1
 */
function get_url_dir( $url ) {
	$url_info = wp_parse_url( $url );
	$sub_dir  = isset( $url_info['host'] ) ? $url_info['host'] : '';

	if ( ! empty( $url_info['path'] ) ) {
		$sub_dir .= $url_info['path'];
	}

	$path = trailingslashit( get_page_cache_dir() ) . ltrim( $sub_dir, '/' );
	$path = trailingslashit( $path );

	/**
	 * Filters the path of the given url in the cache directory.
	 *
	 * @hook  swiftpress_get_url_dir
	 *
	 * @param {string} $path The cache directory of the given URL.
	 *
	 * @since 1.1
	 */
	return apply_filters( 'swiftpress_get_url_dir', $path );
}


/**
 * Get list of expired files for given directory
 *
 * @param string $path     directory location
 * @param int    $lifespan lifespan in seconds
 *
 * @return array expired file list
 * @since 1.1
 */
function get_expired_files( $path, $lifespan = 0 ) {

	$current_time = time();

	$expired_files = array();

	// return immediately if the path is not exist!
	if ( ! file_exists( $path ) ) {
		return $expired_files;
	}

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

	foreach ( $files as $file ) {

		if ( $file->isDir() ) {
			continue;
		}

		$path = $file->getPathname();

		if ( @filemtime( $path ) + $lifespan <= $current_time ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$expired_files[] = $path;
		}
	}

	return $expired_files;
}


/**
 * Flush object cache and clean cache directory
 *
 * @since 1.0
 */
function swiftpress_flush() {
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}

	remove_dir( get_cache_dir() );

	/**
	 * Fires after cache flush.
	 *
	 * @hook   swiftpress_flushed
	 * @since  1.0
	 */
	do_action( 'swiftpress_flushed' );
}

/**
 * Log to stdout or a file
 *
 * @param string $message Log message
 *
 * @return bool
 */
function log( $message ) {
	if ( ! defined( 'SWIFTPRESS_ENABLE_LOG' ) ) {
		return false;
	}

	if ( ! SWIFTPRESS_ENABLE_LOG ) {
		return false;
	}

	$log_message = gmdate( 'H:i:s' ) . ' ' . getmypid() . ' ' . get_client_ip() . " {$message}" . PHP_EOL;

	/**
	 * Filters log message.
	 *
	 * @hook   swiftpress_log_message
	 *
	 * @param  {string} $log_message The log message.
	 *
	 * @return {string} New value.
	 * @since  2.0
	 */
	$log_message = apply_filters( 'swiftpress_log_message', $log_message );

	/**
	 * Filters log message type.
	 *
	 * @hook   swiftpress_log_message_type
	 *
	 * @param  {int} 0 default message type since 3.6
	 *
	 * @return {int} New value.
	 * @since  2.0
	 */
	$message_type = apply_filters( 'swiftpress_log_message_type', 0 );
	$destination  = null;

	if ( defined( 'SWIFTPRESS_LOG_FILE' ) ) {
		$destination  = SWIFTPRESS_LOG_FILE;
		$message_type = 3;
	}

	/**
	 * Filters destination of the log.
	 *
	 * @hook   swiftpress_log_destination
	 *
	 * @param  {null|string} $destination The destination of the log.
	 *
	 * @return {null|string} New value.
	 * @since  2.0
	 */
	$log_destination = apply_filters( 'swiftpress_log_destination', $destination );

	// don't log anything when it used for particular IP address
	if ( defined( 'SWIFTPRESS_LOG_IP' ) && SWIFTPRESS_LOG_IP !== get_client_ip() ) {
		return false;
	}

	$message_type = absint( $message_type );

	return error_log( $log_message, $message_type, $log_destination ); // phpcs:ignore
}

/**
 * Get client raw ip
 *
 * @return mixed
 */
function get_client_ip() {
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
		return wp_unslash( $_SERVER['REMOTE_ADDR'] );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}
}


/**
 * Fetches known headers, ported from WP Super Cache but not using apache_response_headers
 *
 * @return array|false
 * @since 1.2
 */
function get_response_headers() {
	static $known_headers = array(
		'Access-Control-Allow-Origin',
		'Accept-Ranges',
		'Age',
		'Allow',
		'Cache-Control',
		'Connection',
		'Content-Encoding',
		'Content-Language',
		'Content-Length',
		'Content-Location',
		'Content-MD5',
		'Content-Disposition',
		'Content-Range',
		'Content-Type',
		'Date',
		'ETag',
		'Expires',
		'Last-Modified',
		'Link',
		'Location',
		'P3P',
		'Pragma',
		'Proxy-Authenticate',
		'Referrer-Policy',
		'Refresh',
		'Retry-After',
		'Server',
		'Status',
		'Strict-Transport-Security',
		'Trailer',
		'Transfer-Encoding',
		'Upgrade',
		'Vary',
		'Via',
		'Warning',
		'WWW-Authenticate',
		'X-Frame-Options',
		'Public-Key-Pins',
		'X-XSS-Protection',
		'Content-Security-Policy',
		'X-Pingback',
		'X-Content-Security-Policy',
		'X-WebKit-CSP',
		'X-Content-Type-Options',
		'X-Powered-By',
		'X-UA-Compatible',
		'X-Robots-Tag',
	);

	/**
	 * Filters known headers.
	 *
	 * @hook   swiftpress_known_headers
	 *
	 * @param  {array} $known_headers The list of known HTTP headers.
	 *
	 * @return {array} New value.
	 * @since  1.2
	 */
	$known_headers = apply_filters( 'swiftpress_known_headers', $known_headers );

	if ( ! isset( $known_headers['age'] ) ) {
		$known_headers = array_map( 'strtolower', $known_headers );
	}

	$headers = array();

	if ( function_exists( 'headers_list' ) ) {
		$headers = array();
		foreach ( headers_list() as $hdr ) {
			$header_parts = explode( ':', $hdr, 2 );
			$header_name  = isset( $header_parts[0] ) ? trim( $header_parts[0] ) : '';
			$header_value = isset( $header_parts[1] ) ? trim( $header_parts[1] ) : '';

			$headers[ $header_name ] = $header_value;
		}
	}

	foreach ( $headers as $key => $value ) {
		if ( ! in_array( strtolower( $key ), $known_headers, true ) ) {
			unset( $headers[ $key ] );
		}
	}

	return $headers;
}


/**
 * Check if the given url exists in the cache
 *
 * @param string $url       URL
 * @param bool   $is_mobile Check mobile cache
 * @param bool   $is_gzip   check gzipped cache
 *
 * @return bool
 */
function is_url_cached( $url, $is_mobile = false, $is_gzip = false ) {
	$file_name = 'index';
	$url_parts = wp_parse_url( $url );

	if ( 'https' === $url_parts['scheme'] ) {
		$file_name .= '-https';
	}

	if ( $is_mobile ) {
		$file_name .= '-mobile';
	}

	$file_name .= '.html';

	if ( $is_gzip ) {
		$file_name .= '.gz';
	}

	$rel_path = $url_parts['host'];
	if ( ! empty( $url_parts['path'] ) ) {
		$rel_path .= $url_parts['path'];
	}

	$path = trailingslashit( get_page_cache_dir() . $rel_path );

	$cache_file = $path . $file_name;

	return file_exists( $cache_file );
}

/**
 * Check if the permalink structure of the site end with trailingslash
 *
 * @return bool
 * @since 2.0
 */
function permalink_structure_has_trailingslash() {
	if ( '/' === substr( get_option( 'permalink_structure' ), - 1 ) ) {
		return true;
	}

	return false;
}

/**
 * Check if the given directory empty
 *
 * @param string $dir Path
 *
 * @return bool
 */
function is_dir_empty( $dir ) {
	foreach ( new \DirectoryIterator( $dir ) as $file_info ) {
		if ( $file_info->isDot() ) {
			continue;
		}

		return false;
	}

	return true;
}

/**
 * Get the documentation url
 *
 * @param string $path     The path of documentation
 * @param string $fragment URL Fragment
 *
 * @return string final URL
 */
function get_doc_url( $path = null, $fragment = '' ) {
	$doc_site       = 'https://docs.swiftpress.dev/';
	$utm_parameters = '?utm_source=wp_admin&utm_medium=plugin&utm_campaign=settings_page';

	if ( ! empty( $path ) ) {
		$doc_site .= ltrim( $path, '/' );
	}

	$doc_url = trailingslashit( $doc_site ) . $utm_parameters;

	if ( ! empty( $fragment ) ) {
		$doc_url .= '#' . $fragment;
	}

	return $doc_url;
}

/**
 * Sanitize CSS
 *
 * @param string $css Input
 *
 * @return string|string[] $css
 * @since 2.1
 */
function sanitize_css( $css ) {
	$css = wp_strip_all_tags( $css );

	if ( false !== strpos( $css, '<' ) ) {
		$css = preg_replace( '#<(\/?\w+)#', '\00003C$1', $css );
	}

	return $css;
}



/**
 * If the site is a local site.
 *
 * @return bool
 * @since 2.2
 */
function is_local_site() {
	$site_url = site_url();

	// Check for localhost and sites using an IP only first.
	$is_local = $site_url && false === strpos( $site_url, '.' );

	// Use Core's environment check, if available. Added in 5.5.0 / 5.5.1 (for `local` return value).
	if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
		$is_local = true;
	}

	// Then check for usual usual domains used by local dev tools.
	$known_local = array(
		'#\.local$#i',
		'#\.localhost$#i',
		'#\.test$#i',
		'#\.docksal$#i',      // Docksal.
		'#\.docksal\.site$#i', // Docksal.
		'#\.dev\.cc$#i',       // ServerPress.
		'#\.lndo\.site$#i',    // Lando.
	);

	if ( ! $is_local ) {
		foreach ( $known_local as $url ) {
			if ( preg_match( $url, $site_url ) ) {
				$is_local = true;
				break;
			}
		}
	}

	/**
	 * Filters is_local_site check.
	 *
	 * @param bool $is_local If the current site is a local site.
	 *
	 * @since 2.2
	 */
	$is_local = apply_filters( 'swiftpress_is_local_site', $is_local );

	return $is_local;
}

/**
 * Check whether request for bypass or process normally
 *
 * @return bool
 * @since 3.0
 */
function bypass_request() {
	if ( isset( $_GET['noswiftpress'] ) && $_GET['noswiftpress'] ) { // phpcs:ignore
		return true;
	}

	return false;
}


/**
 * Check if the dev mode is active
 *
 * @return bool
 * @since 3.6
 */
function is_dev_mode_active() {
	// Check global config first (fast)
	if ( ! empty( $GLOBALS['swiftpress_options']['dev_mode'] ) ) {
		return true;
	}

	// Fallback to database option
	$settings = \SwiftPress\Utils\get_settings();

	return ! empty( $settings['dev_mode'] );
}
