<?php
/**
 * Forked from https://github.com/tlovett1/simple-cache/
 */

defined( 'ABSPATH' ) || exit;

$swiftpress_start_time = microtime( true );

// Don't cache robots.txt or htacesss
if ( strpos( $_SERVER['REQUEST_URI'], 'robots.txt' ) !== false || strpos( $_SERVER['REQUEST_URI'], '.htaccess' ) !== false ) {
	swiftpress_add_cache_miss_header( "Uncacheable file" );

	return;
}

// Don't cache non-GET requests
if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
	swiftpress_add_cache_miss_header( "Invalid request method" );

	return;
}

if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
	$_SERVER['HTTP_USER_AGENT'] = '';
}

// Don't cache wp-admin
if ( is_admin() ) {
	return;
}

$file_extension = $_SERVER['REQUEST_URI'];
$file_extension = preg_replace( '#^(.*?)\?.*$#', '$1', $file_extension );
$file_extension = trim( preg_replace( '#^.*\.(.*)$#', '$1', $file_extension ) );

// Don't cache disallowed extensions. Prevents wp-cron.php, xmlrpc.php, etc.
if ( ! preg_match( '#index\.php$#i', $_SERVER['REQUEST_URI'] ) && in_array( $file_extension, array( 'php', 'xml', 'xsl' ), true ) ) {
	swiftpress_add_cache_miss_header( "Disallowed file extension" );

	return;
}

if ( ! $GLOBALS['swiftpress_options']['enable_page_cache'] ) {
	swiftpress_add_cache_miss_header( "Page Caching is not enabled" );

	return;
}

if ( ! empty( $GLOBALS['swiftpress_options']['dev_mode'] ) ) {
	swiftpress_add_cache_miss_header( "Dev mode is enabled" );

	return;
}

if ( isset( $_GET['noswiftpress'] ) && $_GET['noswiftpress'] ) {
	swiftpress_add_cache_miss_header( "Passing noswiftpress with the query" );

	return;
}

// Don't cache page with these user agents
if ( isset( $swiftpress_rejected_user_agents ) && ! empty( $swiftpress_rejected_user_agents ) ) {
	$rejected_user_agents = implode( '|', $swiftpress_rejected_user_agents );
	if ( ! empty( $rejected_user_agents ) && isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '#(' . $rejected_user_agents . ')#', $_SERVER['HTTP_USER_AGENT'] ) ) {
		swiftpress_add_cache_miss_header( "Rejected user agent" );

		return;
	}
}

// dont cache mobile (when mobile caching is disabled, skip caching for mobile UA)
if ( empty( $GLOBALS['swiftpress_options']['cache_mobile'] ) ) {
	if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '#(Mobile|Android|Silk/|Kindle|BlackBerry|Opera Mini|Opera Mobi)#i', $_SERVER['HTTP_USER_AGENT'] ) ) {
		swiftpress_add_cache_miss_header( "Mobile request" );

		return;
	}
}

// Exclude caching based on HTTP_REFERER
if ( ! empty( $swiftpress_rejected_referrers ) && isset( $_SERVER['HTTP_REFERER'] ) && $_SERVER['HTTP_REFERER'] ) {
	foreach ( $swiftpress_rejected_referrers as $referrer_rule ) {
		$referrer_rule = trim( $referrer_rule );
		if ( $referrer_rule === '' ) {
			continue;
		}
		// If rule starts with ^ or contains regex special chars, treat as regex
		if ( preg_match( '/[\\^$.|?*+()\[\]]/', $referrer_rule ) ) {
			if ( @preg_match( '#' . $referrer_rule . '#i', $_SERVER['HTTP_REFERER'] ) ) {
				if ( preg_match( '#' . $referrer_rule . '#i', $_SERVER['HTTP_REFERER'] ) ) {
					swiftpress_add_cache_miss_header( "Rejected referer: $referrer_rule" );

					return;
				}
			}
		} else {
			// Plain string match
			if ( strpos( $_SERVER['HTTP_REFERER'], $referrer_rule ) !== false ) {
				swiftpress_add_cache_miss_header( "Rejected referer: $referrer_rule" );

				return;
			}
		}
	}
}

if ( ! empty( $_COOKIE ) ) {
	$wp_cookies      = [ 'wordpressuser_', 'wordpresspass_', 'wordpress_sec_', 'wordpress_logged_in_' ];
	$comment_cookies = [ 'comment_author_', 'comment_author_email_', 'comment_author_url_' ];

	// Check if logged-in caching is disabled or the user is not logged in
	if ( empty( $GLOBALS['swiftpress_options']['loggedin_user_cache'] ) || false === swiftpress_get_user_cookie() ) {
		// Standard check for logged-in status
		foreach ( $_COOKIE as $key => $value ) {
			foreach ( $wp_cookies as $cookie ) {
				if ( strpos( $key, $cookie ) !== false ) {
					swiftpress_add_cache_miss_header( "User logged-in" );
					return;
				}
			}

			// Check if the user has commented on the site
			foreach ( $comment_cookies as $cookie ) {
				if ( strpos( $key, $cookie ) !== false ) {
					swiftpress_add_cache_miss_header( "User left a comment" );
					return;
				}
			}
		}

		// Avoid caching pages with specific post comments from users
		// it's a bit different from $comment_cookies since we are checking the post path
		if ( ! empty( $_COOKIE['swiftpress_commented_posts'] ) ) {
			foreach ( $_COOKIE['swiftpress_commented_posts'] as $path ) {
				if ( rtrim( $path, '/' ) === rtrim( $_SERVER['REQUEST_URI'], '/' ) ) {
					swiftpress_add_cache_miss_header( "User commented" );
					return;
				}
			}
		}
	}

	// Skip caching if there are specific rejected cookies, regardless of logged-in status
	if ( ! empty( $swiftpress_rejected_cookies ) ) {
		$rejected_cookies = array_diff( $swiftpress_rejected_cookies, $wp_cookies, $comment_cookies, ['swiftpress_commented_posts'] );
		$rejected_cookies = implode( '|', $rejected_cookies );
		if ( preg_match( '#(' . $rejected_cookies . ')#', var_export( $_COOKIE, true ) ) ) {
			swiftpress_add_cache_miss_header( "Rejected cookie" );
			return;
		}
	}
}

// Don't cache rejected pages
if ( ! empty( $swiftpress_rejected_uri ) ) {
	foreach ( (array) $swiftpress_rejected_uri as $exception ) {
		if ( preg_match( '#^[\s]*$#', $exception ) ) {
			continue;
		}

		// full url exception
		if ( preg_match( '#^https?://#', $exception ) ) {
			$exception = parse_url( $exception, PHP_URL_PATH );
		}

		if ( empty( $exception ) ) {
			continue;
		}

		if ( preg_match( '#^(' . $exception . ')$#', $_SERVER['REQUEST_URI'] ) ) {
			swiftpress_add_cache_miss_header( "Rejected page" );

			return;
		}
	}
}


if ( ! empty( $_GET ) ) {
	if ( ! isset( $swiftpress_ignored_query_strings ) ) {
		$swiftpress_ignored_query_strings = [];
	}

	$query_params = array_diff_key( $_GET, array_flip( $swiftpress_ignored_query_strings ) );

	if ( ! isset( $swiftpress_cache_query_strings ) ) {
		$swiftpress_cache_query_strings = [];
	}

	// don't cache when there is not allowed query parameter exists
	if ( ! empty( $query_params ) && ! array_intersect_key( $_GET, array_flip( $swiftpress_cache_query_strings ) ) ) {
		swiftpress_add_cache_miss_header( "Disallowed query parameter exists" );

		return;
	}
}

swiftpress_serve_cache();

ob_start( 'swiftpress_page_buffer' );

/**
 * Cache output before it goes to the browser
 *
 * @param string $buffer
 * @param int    $flags
 *
 * @return string
 * @since  1.0
 */
function swiftpress_page_buffer( $buffer, $flags ) {
	global $swiftpress_start_time, $post;

	if ( strlen( $buffer ) < 255 ) {
		return $buffer;
	}

	// maybe we shouldn't cache template file has this constant
	// dont check DONOTCACHEPAGE strictly some plugins define string instead bool flag
	if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
		swiftpress_add_cache_miss_header( "DONOTCACHEPAGE defined" );

		return $buffer;
	}

	if ( ! empty( $GLOBALS['swiftpress_options']['dev_mode'] ) ) {
		swiftpress_add_cache_miss_header( "Dev mode is enabled" );

		return $buffer;
	}

	// Don't cache password protected posts
	if ( ! empty( $post->post_password ) ) {
		swiftpress_add_cache_miss_header( "Password protected posts are not cached" );

		return $buffer;
	}

	// Don't cache 404 results
	if ( function_exists( 'is_404' ) && is_404() ) {
		swiftpress_add_cache_miss_header( "404 pages are not cached" );

		return $buffer;
	}

	// Don't cache search results
	if ( function_exists( 'is_search' ) && is_search() ) {
		swiftpress_add_cache_miss_header( "Search result page is not cached" );

		return $buffer;
	}

	/**
	 * Filter whether to enable page cache for this request
	 *
	 * @hook  swiftpress_page_cache_enable
	 *
	 * @param {boolean} $enable true for caching
	 *
	 * @since 1.0
	 */
	if ( true !== apply_filters( 'swiftpress_page_cache_enable', true ) ) {
		swiftpress_add_cache_miss_header( "Page Cache not enabled for this post" );

		return $buffer;
	}

	// only cache when http ok
	if ( 200 !== http_response_code() ) {
		swiftpress_add_cache_miss_header( "Response code is not 200" );

		return $buffer;
	}

	if ( ! function_exists( '\SwiftPress\Utils\get_cache_dir' ) ) {
		return $buffer;
	}

	// Make sure we can read/write files and that proper folders exist
	if ( ! file_exists( untrailingslashit( \SwiftPress\Utils\get_cache_dir() ) ) ) {
		if ( ! @mkdir( untrailingslashit( \SwiftPress\Utils\get_cache_dir() ) ) ) {
			// Can not cache!
			swiftpress_add_cache_miss_header( "The cache directory does not exist for storing cached output" );

			return $buffer;
		}
	}

	if ( ! file_exists( \SwiftPress\Utils\get_page_cache_dir() ) ) {
		if ( ! @mkdir( \SwiftPress\Utils\get_page_cache_dir() ) ) {
			// Can not cache!
			swiftpress_add_cache_miss_header( "The page cache directory does not exist for storing cached output" );

			return $buffer;
		}
	}

	/**
	 * Filters HTML buffer
	 *
	 * @hook   swiftpress_page_caching_buffer
	 *
	 * @param  {string} $buffer Output buffer.
	 *
	 * @return {string} New value.
	 * @since  1.0
	 */
	$buffer = apply_filters( 'swiftpress_page_caching_buffer', $buffer );

	$url_path = swiftpress_get_url_path();

	$dirs = explode( '/', $url_path );

	$path = untrailingslashit( \SwiftPress\Utils\get_page_cache_dir() );

	foreach ( $dirs as $dir ) {
		if ( ! empty( $dir ) ) {
			$path .= '/' . $dir;

			if ( ! file_exists( $path ) ) {
				if ( ! @mkdir( $path ) ) {
					// Can not cache!
					return $buffer;
				}
			}
		}
	}

	$modified_time   = time(); // Make sure modified time is consistent
	$generation_time = number_format( microtime( true ) - $swiftpress_start_time, 3 );

	$home_url = get_home_url();
	// prevent mixed content
	if ( ! is_ssl() && 'https' === strtolower( parse_url( $home_url, PHP_URL_SCHEME ) ) ) {
		$https_home_url = $home_url;
		$http_home_url  = str_replace( 'https://', 'http://', $https_home_url );
		$buffer         = str_replace( esc_url( $http_home_url ), esc_url( $https_home_url ), $buffer );
	}

	if ( array_key_exists( 'cache_footprint', $GLOBALS['swiftpress_options'] ) && true === $GLOBALS['swiftpress_options']['cache_footprint'] ) {
		if ( preg_match( '#</html>#i', $buffer ) ) {
			$buffer .= PHP_EOL;
			$buffer .= "<!-- Cache served by SwiftPress -->";
			$buffer .= PHP_EOL;
			$buffer .= "<!-- If you like fast websites like this, visit: https://swiftpress.dev -->";
			$buffer .= PHP_EOL;
			$buffer .= "<!-- Last modified: " . gmdate( 'D, d M Y H:i:s', $modified_time ) . " GMT -->";
			$buffer .= PHP_EOL;
			$buffer .= "<!-- Dynamic page generated in $generation_time -->";
			$buffer .= PHP_EOL;
			if ( false !== stripos( $_SERVER['HTTP_USER_AGENT'], 'SwiftPress Preloader' ) ) {
				$buffer .= "<!-- This page is preloaded by SwiftPress Preloader -->";
				$buffer .= PHP_EOL;
			}
		}
	}


	$meta_file_name = 'meta.php';
	$meta_file      = '<?php exit; ?>' . PHP_EOL;

	$meta_params = array(); // holds to metadata for cached file

	$response_headers = \SwiftPress\Utils\get_response_headers();

	foreach ( (array) $response_headers as $key => $value ) {
		$meta_params['headers'][ $key ] = "$key: $value";
	}

	/**
	 * Filters meta parameters.
	 *
	 * @hook   swiftpress_page_cache_meta_params
	 *
	 * @param  {array} $meta_params Meta parameters.
	 * @param  {array} $response_headers Supported response header list.
	 *
	 * @return {array} New value.
	 * @since  1.2
	 */
	$meta_params        = apply_filters( 'swiftpress_page_cache_meta_params', $meta_params, $response_headers );
	$meta_file_contents = $meta_file . json_encode( $meta_params );

	/**
	 * Filters meta file contents.
	 *
	 * @hook   swiftpress_page_cache_meta_info
	 *
	 * @param  {string} $meta_file_contents The content of the meta file.*
	 *
	 * @return {array} New value.
	 * @since  1.2
	 */
	$meta_file_contents = apply_filters( 'swiftpress_page_cache_meta_info', $meta_file_contents );

	file_put_contents( $path . '/' . $meta_file_name, $meta_file_contents );
	touch( $path . '/' . $meta_file_name, $modified_time );

	if ( ! empty( $meta_params['headers']['Content-Type'] ) ) {
		$index_name = swiftpress_index_file( $meta_params['headers']['Content-Type'] );
	} else {
		$index_name = swiftpress_index_file();
	}

	if ( $GLOBALS['swiftpress_options']['gzip_compression'] && function_exists( 'gzencode' ) ) {
		file_put_contents( $path . '/' . $index_name, gzencode( $buffer, 3 ) );
		touch( $path . '/' . $index_name, $modified_time );
	} else {
		file_put_contents( $path . '/' . $index_name, $buffer );
		touch( $path . '/' . $index_name, $modified_time );
	}

	/**
	 * Fires after caching a page.
	 *
	 * @hook  swiftpress_page_cached
	 *
	 * @param {string} $buffer HTML Output.
	 *
	 * @since 1.0
	 */
	do_action( 'swiftpress_page_cached', $buffer );

	header( 'Cache-Control: no-cache' ); // Check back every time to see if re-download is necessary

	header( 'X-SwiftPress-Cache: MISS' );

	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified_time ) . ' GMT' );

	if ( function_exists( 'ob_gzhandler' ) && $GLOBALS['swiftpress_options']['gzip_compression'] ) {
		return ob_gzhandler( $buffer, $flags );
	} else {
		return $buffer;
	}
}


/**
 * Optionally serve cache and exit
 *
 * @since 1.0
 */
function swiftpress_serve_cache() {
	global $swiftpress_slash_check;

	$path = rtrim( $GLOBALS['swiftpress_options']['cache_location'], '/' ) . '/swiftpress/' . rtrim( swiftpress_get_url_path(), '/' ) . '/';

	$meta_file = $path . '/meta.php';

	$header_params = [];
	$content_type  = 'text/html';

	if ( @file_exists( $meta_file ) ) {
		$meta_contents = trim( file_get_contents( $meta_file ) );
		$meta_contents = str_replace( '<?php exit; ?>', '', $meta_contents );
		$meta_params   = json_decode( trim( $meta_contents ), true );
		$header_params = $meta_params['headers'];
	}

	if ( ! empty( $header_params['Content-Type'] ) ) {
		$content_type = $header_params['Content-Type'];
	}


	$file_name = swiftpress_index_file( $content_type );
	$file_path = $path . $file_name;

	// check file exists?
	if ( ! file_exists( $file_path ) ) {
		return;
	}

	$modified_time = (int) @filemtime( $file_path );

	header( 'Cache-Control: no-cache' ); // Check back in an hour

	if ( ! empty( $modified_time ) && ! empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) === $modified_time ) {
		header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
		exit;
	}

	// trailingslash check
	if ( isset( $swiftpress_slash_check ) ) {
		$current_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
		if ( $swiftpress_slash_check && ! empty( $current_path ) && '/' !== substr( $current_path, - 1 ) ) {
			header( 'X-SwiftPress-Cache: Passing to WordPress' );

			return;
		}

		if ( ! $swiftpress_slash_check && ! empty( $current_path ) && '/' === substr( $current_path, - 1 ) ) {
			header( 'X-SwiftPress-Cache: Passing to WordPress' );

			return;
		}
	}

	if ( @file_exists( $file_path ) && @is_readable( $file_path ) ) {

		if ( ! empty( $header_params ) ) {
			foreach ( $header_params as $key => $response_header ) {
				header( $response_header );
			}
		}

		header( 'X-SwiftPress-Cache: PHP' );
		header( 'X-Cache-Enabled: true' );
		header( sprintf( "age: %d",  time() - filemtime( $file_path ) ) );

		if ( function_exists( 'gzencode' ) && $GLOBALS['swiftpress_options']['gzip_compression'] ) {
			header( 'Content-Encoding: gzip' );
		}

		@readfile( $file_path );

		exit;
	}
}

/**
 * Get URL path for caching
 *
 * @return string
 * @since  1.0
 * @since  2.0 $request_uri without query string
 */
function swiftpress_get_url_path() {
	$host        = ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' );
	$request_uri = explode( '?', $_SERVER['REQUEST_URI'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$request_uri = reset( $request_uri );
	$request_uri = preg_replace( '/(\/+)/', '/', $request_uri );
	$request_uri = str_replace( '..', '', preg_replace( '/[ <>\'\"\r\n\t\(\)]/', '', $request_uri ) );

	return rtrim( $host, '/' ) . $request_uri;
}

function swiftpress_get_user_cookie() {
	if ( empty( $_COOKIE ) ) {
		return false;
	}

	foreach ( $_COOKIE as $c_key => $val ) {
		if ( false !== strpos( $c_key, 'wordpress_logged_in_' ) ) {
			return $val;
		}
	}

	return false;
}


/**
 * Determines cache file names
 *
 * @param string $content_type
 *
 * @return string
 * @since 1.2 `$content_type`
 * @since 1.0
 */
function swiftpress_index_file( $content_type = 'text/html' ) {
	global $swiftpress_vary_cookies, $swiftpress_cache_query_strings;

	$file_name = 'index';

	if ( is_ssl() ) {
		$file_name .= '-https';
	}

	// separate file for mobile cache
	if ( ! empty( $GLOBALS['swiftpress_options']['cache_mobile'] ) && ! empty( $GLOBALS['swiftpress_options']['cache_mobile_separate_file'] ) ) {
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && preg_match( '#(Mobile|Android|Silk/|Kindle|BlackBerry|Opera Mini|Opera Mobi)#i', $_SERVER['HTTP_USER_AGENT'] ) ) {
			$file_name .= '-mobile';
		}
	}

	if ( ! empty( $GLOBALS['swiftpress_options']['loggedin_user_cache'] ) ) {
		$usr_cookie = swiftpress_get_user_cookie();
		if ( false !== $usr_cookie ) {
			$cookie_info = explode( '|', $usr_cookie );

			// user specific cache index
			$file_name .= '-user_' . $cookie_info[0] . '-' . substr( sha1( $cookie_info[0] ), 0, 6 );
		}
	}

	// change filename based on vary cookies
	if ( ! empty( $swiftpress_vary_cookies ) ) {
		$cookie_file_name = '';
		foreach ( $swiftpress_vary_cookies as $key => $vary_cookie ) {
			if ( is_array( $vary_cookie ) ) {
				if ( ! empty( $_COOKIE[ $key ] ) ) {
					foreach ( $vary_cookie as $vary_sub_cookie ) {
						if ( isset( $_COOKIE[ $key ][ $vary_sub_cookie ] ) ) {
							$cookie_value     = preg_replace( '/[^A-Za-z0-9. -]/', '', $_COOKIE[ $key ][ $vary_sub_cookie ] );
							$cookie_file_name .= strtolower( $key . $vary_sub_cookie . $cookie_value );
						}
					}
				}

				continue;
			}

			if ( isset( $_COOKIE[ $vary_cookie ] ) && ! empty( $_COOKIE[ $vary_cookie ] ) ) {
				$cookie_value     = preg_replace( '/[^A-Za-z0-9. -]/', '', $_COOKIE[ $vary_cookie ] );
				$cookie_file_name .= strtolower( $vary_cookie . $cookie_value );
			}
		}

		if ( ! empty( $cookie_file_name ) ) {
			/**
			 * Hashing for no particular reason
			 * preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $cookie_file_name ) )
			 * can create a messy filename
			 */
			$file_name .= '-' . sha1( $cookie_file_name );
		}

	}

	// change filename for provided cache query string
	if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
		parse_str( $_SERVER['QUERY_STRING'], $query_string );
		$qs_variable = '';
		sort( $swiftpress_cache_query_strings );
		foreach ( $swiftpress_cache_query_strings as $query_parameter ) {
			if ( isset( $query_string[ $query_parameter ] ) ) {
				$qs_variable .= '_' . $query_parameter;
				$qs_variable .= is_array( $query_string[ $query_parameter ] ) ? implode( '|', $query_string[ $query_parameter ] ) : $query_string[ $query_parameter ];
			}
		}

		if ( ! empty( $qs_variable ) ) {
			$qs_variable = 'query_' . $qs_variable;
			$file_name   .= '_' . sha1( $qs_variable );
		}
	}


	/**
	 * Content-Type is not always text/html (like feed, wp-json etc..)
	 * Adding hash by simply escaping from rewrite matches in htaccess or nginx
	 * Different types of content should be serve via PHP, in order to restore header info
	 */
	if ( false === strpos( $content_type, 'text/html' ) ) {
		$file_name .= '-' . substr( sha1( $content_type ), 0, 6 );
	}


	$file_name .= '.html';

	if ( function_exists( 'gzencode' ) && $GLOBALS['swiftpress_options']['gzip_compression'] ) {
		$file_name .= '.gz';
	}

	return $file_name;
}

/**
 * Add cache miss header and reason
 *
 * @param string $reason Cache Miss info
 *
 * @since 2.2
 */
function swiftpress_add_cache_miss_header( $reason ) {
	if ( headers_sent() ) {
		return;
	}

	header( 'X-SwiftPress-Cache: MISS' );

	if ( ( defined( 'SWIFTPRESS_ENABLE_LOG' ) && SWIFTPRESS_ENABLE_LOG )
	     || ( defined( 'SWIFTPRESS_MISS_REASON' ) && SWIFTPRESS_MISS_REASON )
	) {
		header( "X-SwiftPress-Cache-Miss-Reason: $reason" );
	}
}
