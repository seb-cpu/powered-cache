<?php
/**
 * Plugin Name:       AICache
 * Plugin URI:        https://webs.ie/aicache
 * Description:       AI-assisted WordPress performance &amp; caching — page cache, CSS/JS &amp; font optimization, intelligent preloading, and an AI diagnostic that explains and one-click-fixes what's slowing your site.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * Author:            Webs.ie
 * Author URI:        https://webs.ie
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       swiftpress
 * Domain Path:       /languages
 *
 * @package           SwiftPress
 */

namespace SwiftPress;

use SwiftPress\Extensions\Extensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Useful global constants.
define( 'SWIFTPRESS_VERSION', '1.0.0' );
define( 'SWIFTPRESS_DB_VERSION', '3.8' );
define( 'SWIFTPRESS_PLUGIN_FILE', __FILE__ );
define( 'SWIFTPRESS_URL', plugin_dir_url( __FILE__ ) );
define( 'SWIFTPRESS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SWIFTPRESS_INC', SWIFTPRESS_PATH . 'includes/' );
define( 'SWIFTPRESS_DROPIN_DIR', SWIFTPRESS_INC . 'dropins/' );
define( 'SWIFTPRESS_COMPAT_DIR', SWIFTPRESS_INC . 'compat/' );
define( 'SWIFTPRESS_PACKAGE_DIR', SWIFTPRESS_INC . 'package/' );

if ( ! defined( 'SWIFTPRESS_CACHE_DIR' ) ) {
	define( 'SWIFTPRESS_CACHE_DIR', WP_CONTENT_DIR . '/cache/' );
}

if ( ! defined( 'SWIFTPRESS_FO_CACHE_DIR' ) ) {
	define( 'SWIFTPRESS_FO_CACHE_DIR', SWIFTPRESS_CACHE_DIR . 'min/' );
}

// Require Composer autoloader if it exists.
if ( file_exists( SWIFTPRESS_PATH . 'vendor/autoload.php' ) ) {
	require_once SWIFTPRESS_PATH . 'vendor/autoload.php';
}

// load packages
require_once SWIFTPRESS_PACKAGE_DIR . 'deliciousbrains/wp-background-processing/classes/wp-async-request.php';
require_once SWIFTPRESS_PACKAGE_DIR . 'deliciousbrains/wp-background-processing/classes/wp-background-process.php';

/**
 * PSR-4-ish autoloading
 *
 * @since 2.0
 */
spl_autoload_register(
	function ( $class ) {
		// project-specific namespace prefix.
		$prefix = 'SwiftPress\\';

		// base directory for the namespace prefix.
		$base_dir = __DIR__ . '/includes/classes/';

		// does the class use the namespace prefix?
		$len = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// if the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Include files.
require_once SWIFTPRESS_INC . 'constants.php';
require_once SWIFTPRESS_INC . 'utils.php';
require_once SWIFTPRESS_INC . 'core.php';
require_once SWIFTPRESS_INC . 'admin/dashboard.php';
require_once SWIFTPRESS_INC . 'admin/app.php';
require_once SWIFTPRESS_INC . 'admin/notices.php';
require_once SWIFTPRESS_COMPAT_DIR . 'loader.php';

$network_activated = Utils\is_network_wide( SWIFTPRESS_PLUGIN_FILE );
if ( ! defined( 'SWIFTPRESS_IS_NETWORK' ) ) {
	define( 'SWIFTPRESS_IS_NETWORK', $network_activated );
}

if ( Utils\is_dev_mode_active() ) {
	$frontend_request = ! (
		( function_exists( 'is_admin' ) && is_admin() )
		|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'WP_CLI' ) && WP_CLI )
	);

	// don't run the plugin for frontend requests in dev mode
	if ( $frontend_request ) {
		return;
	}
}

if ( Utils\bypass_request() ) {
	return;
}


// Bootstrap.
Core\setup();
Admin\Dashboard\setup();
Admin\App\setup();
\SwiftPress\AI\AI::factory();
Updater::factory();
Admin\Notices\setup();
Install::factory();
AdvancedCache::factory();
Cron::factory();
Preloader::factory();
Async\SitemapPreloader::factory();
FileOptimizer::factory();
FontOptimizer::factory();
Extensions::factory();

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'swiftpress', CLI::class );
}

// Activation/Deactivation.
register_activation_hook( __FILE__, '\SwiftPress\Core\activate' );
register_deactivation_hook( __FILE__, '\SwiftPress\Core\deactivate' );
