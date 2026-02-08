<?php
/**
 * Configurator Class of the plugin
 *
 * @package SwiftPress
 */

namespace SwiftPress;

use function SwiftPress\Utils\can_configure_htaccess;
use function SwiftPress\Utils\get_cache_dir;
use function SwiftPress\Utils\permalink_structure_has_trailingslash;
use function SwiftPress\Utils\remove_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

/**
 * Class Config
 */
class Config {
	/**
	 * placeholder
	 *
	 * @since 1.0
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class
	 *
	 * @return Config
	 * @since 2.0 removed WP_Filesystem_Direct deps
	 * @since 1.1 initialize WP_Filesystem_Direct
	 * @since 1.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Generate advanced-cache.php and define WP_CACHE
	 *
	 * @param bool $status status of the page caching
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function setup_page_cache( $status ) {

		/**
		 * Forcing multisite settings always true
		 */
		if ( is_multisite() && ! SWIFTPRESS_IS_NETWORK ) {
			$status = true;
		}

		$this->generate_advanced_cache_file();
		$this->define_wp_cache( $status );

		if ( can_configure_htaccess() ) {
			$this->configure_htaccess( $status );
		}

		$this->protect_cache_dir();

		return true;
	}


	/**
	 * Generates advanced-cache.php
	 *
	 * @return bool
	 * @since 1.1 is_multisite control added
	 * @since 1.0
	 */
	public function generate_advanced_cache_file() {
		$file     = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
		$settings = \SwiftPress\Utils\get_settings();

		$file_string = '';

		/**
		 * multisite setups should always have `advanced-cache.php` file
		 */
		if ( true === $settings['enable_page_cache'] || is_multisite() ) {
			$file_string = $this->advanced_cache_file_content();
		}

		if ( ! file_put_contents( $file, $file_string ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Prepare advanced-cache.php contents
	 *
	 * @return mixed|void
	 * @since 1.1 supports `SWIFTPRESS_ADVANCED_CACHE_DROPIN`
	 * @since 1.0
	 */
	public function advanced_cache_file_content() {
		$string  = '<?php ' . PHP_EOL;
		$string .= "defined( 'ABSPATH' ) || exit;" . PHP_EOL;
		$string .= "define( 'SWIFTPRESS_PAGE_CACHING', true );" . PHP_EOL . PHP_EOL;
		// lookup order 1) network-wide , 2) subdomain specific (if any) 3) domain specific
		$string .= "\$config_locations[] = WP_CONTENT_DIR . '/sp-config/config-network.php';" . PHP_EOL;
		$string .= "\$host = isset( \$_SERVER['HTTP_HOST'] ) ? \$_SERVER['HTTP_HOST'] : '';" . PHP_EOL . PHP_EOL;
		$string .= "if ( is_multisite() && defined( 'SUBDOMAIN_INSTALL' ) && ! SUBDOMAIN_INSTALL ) {" . PHP_EOL;
		$string .= "\t" . "\$request_uri = explode( '/', ltrim( \$_SERVER['REQUEST_URI'], '/' ) );" . PHP_EOL;
		$string .= "\t" . 'if ( ! empty( $request_uri[0] ) ) {' . PHP_EOL;
		$string .= "\t" . "\t" . "\$config_locations[] = WP_CONTENT_DIR . '/sp-config/config-' . \$host . '-' . \$request_uri[0] . '.php';" . PHP_EOL;
		$string .= "\t" . '}' . PHP_EOL;
		$string .= '}' . PHP_EOL;

		$string .= "\$config_locations[] = WP_CONTENT_DIR . '/sp-config/config-' . \$host . '.php';" . PHP_EOL . PHP_EOL;

		$string .= 'foreach ( $config_locations as $config_file ) {' . PHP_EOL;
		$string .= "\t" . 'if ( @file_exists( $config_file ) ) {' . PHP_EOL;
		$string .= "\t" . "\t" . 'include( $config_file );' . PHP_EOL;
		$string .= "\t" . "\t" . 'break;' . PHP_EOL;
		$string .= "\t" . '}' . PHP_EOL;
		$string .= '}' . PHP_EOL . PHP_EOL;

		$string .= "if ( ! isset( \$GLOBALS['swiftpress_options'] ) ) {" . PHP_EOL;
		$string .= "\t" . 'return;' . PHP_EOL;
		$string .= '}' . PHP_EOL . PHP_EOL;

		$string .= 'if ( defined( \'SWIFTPRESS_ADVANCED_CACHE_DROPIN\') && @file_exists( SWIFTPRESS_ADVANCED_CACHE_DROPIN ) ) {' . PHP_EOL;
		$string .= "\t" . 'include( SWIFTPRESS_ADVANCED_CACHE_DROPIN );' . PHP_EOL;
		$string .= '} elseif ( @file_exists( \'' . SWIFTPRESS_DROPIN_DIR . 'page-cache.php' . '\' ) ) {' . PHP_EOL;
		$string .= "\t" . 'include( \'' . SWIFTPRESS_DROPIN_DIR . 'page-cache.php' . '\' );' . PHP_EOL;
		$string .= '} else {' . PHP_EOL;
		$string .= "\t" . 'define( \'SWIFTPRESS_PAGE_CACHING_HAS_PROBLEM\', true );' . PHP_EOL;
		$string .= '}';

		/**
		 * Filters advanced-cache.php file contents.
		 *
		 * @hook   swiftpress_advanced_cache_file_content
		 *
		 * @param  {string} $string The content of the advanced-cache.php file
		 *
		 * @return {string} New value.
		 *
		 * @since  1.0
		 */
		return apply_filters( 'swiftpress_advanced_cache_file_content', $string );
	}


	/**
	 * Define WP_CACHE constant
	 *
	 * @param bool $status The status of the caching
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function define_wp_cache( $status ) {
		$config_path = $this->find_wp_config_file();

		if ( ! $config_path ) {
			return false;
		}

		if ( defined( 'WP_CACHE' ) && WP_CACHE === $status ) {
			return true;
		}

		$config_file_string = file_get_contents( $config_path );

		// Config file is empty. Maybe couldn't read it?
		if ( empty( $config_file_string ) ) {
			return false;
		}

		$config_file = preg_split( "#(\r\n|\r|\n)#", $config_file_string );
		$line_key    = false;

		foreach ( $config_file as $key => $line ) {
			if ( ! preg_match( '/^\s*define\(\s*(\'|")([A-Z_]+)(\'|")(.*)/', $line, $match ) ) {
				continue;
			}

			if ( 'WP_CACHE' === $match[2] ) {
				$line_key = $key;
			}
		}

		if ( false !== $line_key ) {
			unset( $config_file[ $line_key ] );
		}

		$status_string = ( $status ) ? 'true' : 'false';

		array_shift( $config_file );
		array_unshift( $config_file, '<?php', "define( 'WP_CACHE', $status_string ); // SwiftPress" );

		if ( ! @file_put_contents( $config_path, implode( PHP_EOL, $config_file ) ) ) {  // phpcs:ignore
			return false;
		}

		return true;
	}

	/**
	 * seeking wp-config file
	 *
	 * @return bool|string
	 * @since 1.0
	 */
	public function find_wp_config_file() {
		$file = '/wp-config.php';

		for ( $i = 1; $i <= 3; $i ++ ) {
			if ( $i > 1 ) {
				$file = '/..' . $file;
			}

			if ( file_exists( untrailingslashit( ABSPATH ) . $file ) ) {
				$config_path = untrailingslashit( ABSPATH ) . $file;
				break;
			}
		}

		if ( ! isset( $config_path ) ) {
			return false;
		}

		return $config_path;
	}

	/**
	 * Create .htaccess file based on current setting preferences
	 *
	 * @param bool $enable Configure .htaccess automatically when it's true
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function configure_htaccess( $enable = true ) {
		if ( ! function_exists( '\get_home_path' ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
		}

		$htaccess_file = get_home_path() . '.htaccess';
		$settings      = \SwiftPress\Utils\get_settings();

		/**
		 * Filters whether automatically update or not update .htaccess file
		 *
		 * @hook   swiftpress_auto_htaccess_update
		 *
		 * @param  {boolean} true to automatic update.
		 *
		 * @return {boolean} New value.
		 *
		 * @since  1.1.1
		 */
		if ( true !== apply_filters( 'swiftpress_auto_htaccess_update', true ) ) {
			return false;
		}

		/**
		 * Apache users can control automatic configuration
		 *
		 * @since 1.2
		 */
		$automatic_configuration = $settings['auto_configure_htaccess'];

		if ( is_multisite() && ! SWIFTPRESS_IS_NETWORK ) {
			$automatic_configuration = false; // individual sites shouldn't use .htaccess on multisite
		}

		if ( ! $automatic_configuration ) {
			return false;
		}

		if ( is_writable( $htaccess_file ) ) {
			$contents = file_get_contents( $htaccess_file );

			// clean up
			$contents = preg_replace( '/# BEGIN SWIFTPRESS(.*)# END SWIFTPRESS\s*?/isU', '', $contents );

			if ( false === $enable ) {
				return file_put_contents( $htaccess_file, $contents );
			}

			$rules    = Htaccess::factory()->htaccess_rules();
			$contents = $rules . $contents;

			// Update the .htacces file
			if ( ! file_put_contents( $htaccess_file, $contents ) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Prepares .htaccess rules for the caching
	 *
	 * @return string $rules
	 * @since      1.1
	 * @deprecated 2.5.0 Use the {@see '\SwiftPress\Htaccess::factory()->htaccess_rules()'} instead.
	 */
	public function htaccess_rules() {
		$rules = Htaccess::factory()->htaccess_rules();

		return $rules;
	}

	/**
	 * Make sure directory listing disabled
	 *
	 * @return bool
	 * @since 1.2
	 */
	public function protect_cache_dir() {

		if ( ! file_exists( get_cache_dir() ) ) {
			mkdir( get_cache_dir() );
		}

		$file = get_cache_dir() . '.htaccess';

		$file_string  = '<IfModule mod_autoindex.c>' . PHP_EOL;
		$file_string .= ' Options -Indexes' . PHP_EOL;
		$file_string .= '</IfModule>' . PHP_EOL . PHP_EOL;
		$file_string .= '<FilesMatch "^.*-user_.*\.(html|html\.gz)$">' . PHP_EOL;
		$file_string .= ' Order Allow,Deny' . PHP_EOL;
		$file_string .= ' Deny from all' . PHP_EOL;
		$file_string .= '</FilesMatch>' . PHP_EOL;

		/**
		 * Filters the content of the .htaccess file in the cache directory.
		 *
		 * @param {string} $file_string Default configuration
		 *
		 * @hook  swiftpress_cache_dir_htaccess_file_content
		 * @since 3.5.3
		 */
		$file_string = apply_filters( 'swiftpress_cache_dir_htaccess_file_content', $file_string );

		if ( ! file_put_contents( $file, $file_string ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare config name
	 *
	 * @param bool $is_network Whether network-wide config name or not
	 *
	 * @return string configuration file name
	 * @since 2.0
	 */
	public function get_config_filename( $is_network ) {
		if ( $is_network ) {
			return 'config-network.php';
		} else {
			$url_parts   = wp_parse_url( home_url() );
			$config_name = 'config-' . $url_parts['host'];

			if ( is_multisite() && ! is_subdomain_install() ) {
				if ( ! is_main_site( get_current_blog_id() ) && ! empty( $url_parts['path'] ) ) {
					$subdir_name  = ltrim( $url_parts['path'], '/' );
					$config_name .= '-' . $subdir_name;
				}
			}

			$config_name .= '.php';

			return $config_name;
		}
	}

	/**
	 * Save settings to file
	 *
	 * @param array $configuration plugin settings
	 * @param bool  $network_wide  Whether network-wide configuration or not
	 *
	 * @return bool
	 * @since 1.0
	 */
	public function save_to_file( $configuration, $network_wide = false ) {
		$config_dir       = WP_CONTENT_DIR . '/sp-config';
		$config_file_name = $this->get_config_filename( $network_wide );

		if ( ! file_exists( $config_dir ) ) {
			mkdir( $config_dir );
		}

		$config_file = trailingslashit( $config_dir ) . $config_file_name;

		if ( ! file_exists( $config_file ) ) {
			touch( $config_file );
		}

		$configuration['cache_location'] = get_cache_dir();

		$config_file_string = '<?php' . PHP_EOL . "defined( 'ABSPATH' ) || exit;" . PHP_EOL . PHP_EOL;

		$config_file_string .= "\$GLOBALS['swiftpress_options'] = " . var_export( $configuration, true ) . ';' . PHP_EOL . PHP_EOL;

		// mobile cache variables
		$config_file_string .= '$swiftpress_mobile_browsers = ' . var_export( mobile_browsers(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_mobile_prefixes = ' . var_export( mobile_prefixes(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_rejected_user_agents = ' . var_export( AdvancedCache::get_rejected_user_agents(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_rejected_cookies = ' . var_export( AdvancedCache::get_rejected_cookies(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_rejected_referrers = ' . var_export( AdvancedCache::get_rejected_referrers(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_vary_cookies = ' . var_export( AdvancedCache::get_vary_cookies(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_rejected_uri = ' . var_export( AdvancedCache::get_rejected_uri(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_ignored_query_strings = ' . var_export( AdvancedCache::get_ignored_query_strings(), true ) . ';' . PHP_EOL;
		$config_file_string .= '$swiftpress_cache_query_strings = ' . var_export( AdvancedCache::get_cache_query_string(), true ) . ';' . PHP_EOL;

		if ( permalink_structure_has_trailingslash() ) {
			$config_file_string .= '$swiftpress_slash_check = true;' . PHP_EOL;
		} else {
			$config_file_string .= '$swiftpress_slash_check = false;' . PHP_EOL;
		}

		/**
		 * Fires before writing configuration file.
		 *
		 * @hook  swiftpress_create_config_file
		 *
		 * @param {string} $config_file The path of the configuration file.
		 * @param {string} $config_file_string The contents of the configurations.
		 * @param {bool} $network_wide Whether network-wide configuration or not.
		 *
		 * @since 2.0
		 */
		do_action( 'swiftpress_create_config_file', $config_file, $config_file_string, $network_wide );

		if ( ! file_put_contents( $config_file, $config_file_string ) ) {
			return false;
		}

		// OPCache invalidate
		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $config_file, true );
		}

		return true;
	}


	/**
	 * Prepares nginx configuration
	 *
	 * @return string $contents nginx rules
	 * @since 1.2 trailingslash rule added
	 *
	 * @since 1.1
	 */
	public function nginx_rules() {
		$settings = \SwiftPress\Utils\get_settings();

		$contents  = '';
		$contents .= '##### SWIFTPRESS CONF #####' . PHP_EOL;
		$contents .= 'set $cache_uri $request_uri;' . PHP_EOL;
		$contents .= 'set $pc_ssl "";' . PHP_EOL;
		$contents .= 'set $pc_enc "";' . PHP_EOL;
		$contents .= 'set $pc_ua "";' . PHP_EOL . PHP_EOL;

		// post
		$contents .= '# POST requests and urls with a query string should always go to PHP' . PHP_EOL;
		$contents .= 'if ($request_method = POST) {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$contents .= 'location = /favicon.ico { log_not_found off; access_log off; }' . PHP_EOL;
		$contents .= 'location = /robots.txt { try_files $uri $uri/ /index.php?$args; log_not_found off; access_log off; }' . PHP_EOL . PHP_EOL;

		// query string
		$contents .= 'if ($query_string != "") {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		// Add CORS rules
		if ( $settings['enable_cdn'] ) {
			$contents .= 'location ~* .(jpg|jpeg|png|gif|ico|css|js|svg|eot|woff|woff2|ttf|otf)$ {' . PHP_EOL;
			$contents .= 'add_header Access-Control-Allow-Origin *;' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;
		}

		/**
		 * Documented in htaccess config
		 */
		if ( apply_filters( 'swiftpress_browser_cache', true ) ) {
			$contents .= 'location ~* .(js|jpg|jpeg|gif|png|css|tgz|gz|rar|bz2|doc|pdf|ppt|tar|wav|bmp|rtf|swf|ico|flv|txt|woff|woff2|svg|webp|avif)$ {' . PHP_EOL;
			$contents .= '  expires 6M;' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;
		}

		// file optimizer rewrite rule
		if ( $settings['rewrite_file_optimizer'] ) {
			$file_optimizer_path = \SwiftPress\Optimizer\Helper::get_file_optimizer_relative_path();

			$contents .= 'location /_static/ {' . PHP_EOL;
			$contents .= '  fastcgi_pass unix:/var/run/fastcgi.sock;' . PHP_EOL;
			$contents .= '  include /etc/nginx/fastcgi_params;' . PHP_EOL;
			$contents .= '  fastcgi_param SCRIPT_FILENAME $document_root/' . $file_optimizer_path . ';' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;
		}

		// https
		$contents .= '# HTTPS' . PHP_EOL;
		$contents .= 'if ($https = "on") {' . PHP_EOL;
		$contents .= '  set $pc_ssl "-https";' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$contents .= '# Don\'t cache uris containing the following segments' . PHP_EOL;
		$contents .= 'if ($request_uri ~* "(/wp-admin/|/xmlrpc.php|/wp-(app|cron|login|register|mail).php|wp-.*.php|/feed/|index.php|wp-comments-popup.php|wp-links-opml.php|wp-locations.php|sitemap(_index)?.xml|[a-z0-9_-]+-sitemap([0-9]+)?.xml)") {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$rejected_user_agents = (array) AdvancedCache::get_rejected_user_agents();

		$contents .= '# Don\'t use the cache for rejected agents' . PHP_EOL;
		$contents .= 'if ($http_user_agent ~* "(' . implode( '|', $rejected_user_agents ) . ')") {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$rejected_cookies = (array) AdvancedCache::get_rejected_cookies();

		$contents .= '# Don\'t use the cache for logged in users or recent commenters' . PHP_EOL;
		$contents .= 'if ($http_cookie ~* "wordpress_[a-f0-9]+|' . implode( '|', $rejected_cookies ) . '") {' . PHP_EOL;
		$contents .= '  set $cache_uri \'null cache\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		$contents .= 'if ($http_x_wap_profile) {' . PHP_EOL;
		$contents .= '	set $pc_ua \'-mobile\';' . PHP_EOL;
		$contents .= '}' . PHP_EOL . PHP_EOL;

		if ( true === $settings['cache_mobile'] && true === $settings['cache_mobile_separate_file'] ) {
			$mobile_browsers = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', mobile_browsers() ) ), ' ' );
			$mobile_prefixes = addcslashes( implode( '|', preg_split( '/[\s*,\s*]*,+[\s*,\s*]*/', mobile_prefixes() ) ), ' ' );

			$contents .= 'if ($http_user_agent ~* (' . $mobile_browsers . ')) {' . PHP_EOL;
			$contents .= '	set $pc_ua \'-mobile\';' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;

			$contents .= 'if ($http_user_agent ~* (' . $mobile_prefixes . ')) {' . PHP_EOL;
			$contents .= '	set $pc_ua \'-mobile\';' . PHP_EOL;
			$contents .= '}' . PHP_EOL . PHP_EOL;
		}

		$cache_suffix = 'html';

		if ( true === $settings['gzip_compression'] ) {
			$cache_suffix .= '.gz';
		}

		$contents .= 'location / {' . PHP_EOL;
		/**
		 * Documented in htaccess config
		 */
		if ( apply_filters( 'swiftpress_mod_rewrite', true ) ) { // rewrite
			$contents .= '  add_header X-SwiftPress-Cache nginx;' . PHP_EOL;
			$contents .= '  try_files /wp-content/cache/swiftpress/$http_host/$cache_uri/index${pc_ssl}${pc_ua}.' . $cache_suffix . ' $uri $uri/ /index.php?$args;' . PHP_EOL;

		} else {
			$contents .= '  try_files $uri $uri/ /index.php?$args;' . PHP_EOL;
		}

		$contents .= '}' . PHP_EOL . PHP_EOL;

		if ( permalink_structure_has_trailingslash() ) {
			$contents .= '# add trailingslash rule' . PHP_EOL;
			$contents .= 'rewrite ^([^.]*[^/])$ $1/ permanent;' . PHP_EOL;
		}

		return $contents;
	}

	/**
	 * Downloads configuration files
	 *
	 * @param string $server type supports apache and nginx
	 *
	 * @since 1.1
	 */
	public function download_rewrite_rules( $server ) {

		$rules    = '';
		$filename = 'conf';

		if ( 'apache' === $server ) {
			$rules    = Htaccess::factory()->htaccess_rules();
			$filename = '.htaccess_swiftpress';
		}

		if ( 'nginx' === $server ) {
			$rules    = $this->nginx_rules();
			$filename = 'swiftpress.conf';
		}

		nocache_headers();
		@header( 'Content-Type: text/plain' );
		@header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		@header( 'Content-Transfer-Encoding: binary' );
		@header( 'Content-Length: ' . strlen( $rules ) );
		@header( 'Connection: close' );
		echo $rules; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Make the caching configurations with given options
	 *
	 * @param array $settings     Plugin settings
	 * @param bool  $network_wide Whether network-wide configuration or not
	 *
	 * @since 2.0
	 */
	public function save_configuration( $settings, $network_wide = false ) {

		$this->setup_page_cache( $settings['enable_page_cache'] );
		$private_settings = [ 'cloudflare_email', 'cloudflare_api_key', 'cloudflare_api_token', 'cloudflare_zone' ];

		foreach ( $private_settings as $setting_key ) {
			unset( $settings[ $setting_key ] );
		}

		$this->save_to_file( $settings, $network_wide );
	}

	/**
	 * Clean-up all the configurations and cache related footprints
	 */
	public function clean_up() {
		$advanced_cache_dropin = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';
		if ( file_exists( $advanced_cache_dropin ) ) {
			unlink( $advanced_cache_dropin );
		}

		$this->define_wp_cache( false );
		$this->configure_htaccess( false );
		$this->protect_cache_dir();

		if ( ! file_exists( get_cache_dir() ) ) {
			remove_dir( get_cache_dir() );
		}

		$config_dir = WP_CONTENT_DIR . '/sp-config';

		if ( is_multisite() ) {
			$config_file_name = $this->get_config_filename( SWIFTPRESS_IS_NETWORK );
			$config_file      = trailingslashit( $config_dir ) . $config_file_name;
			if ( file_exists( $config_file ) ) {
				unlink( $config_file );
			}
		} else { // remove entire configuration directory on single site setup
			remove_dir( $config_dir );
		}

		/**
		 * Fires after cleanup all configurations
		 *
		 * @hook  swiftpress_after_clean_up
		 *
		 * @since 2.0
		 */
		do_action( 'swiftpress_after_clean_up' );

	}

}
