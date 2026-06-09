<?php
/**
 * GitHub-based plugin updater.
 *
 * Lets sites running this plugin receive update notifications and one-click
 * updates when a new tagged release is published on the configured GitHub
 * repository — the same flow as plugins hosted on wordpress.org.
 *
 * Self-contained (no third-party library). Public repos need no token; for a
 * private repo provide a token via the `SWIFTPRESS_GITHUB_TOKEN` constant or
 * the `swiftpress_github_token` filter. The repository is filterable via
 * `swiftpress_github_repo` (default: the repo this plugin ships from).
 *
 * Releases must be tagged with a semantic version (e.g. `v1.0.1`) higher than
 * the installed `Version:` header. Attach a built plugin ZIP as a release asset
 * for the cleanest update; otherwise the GitHub source archive is used and its
 * folder is normalized to the plugin slug on install.
 *
 * @package SwiftPress
 */

namespace SwiftPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Updater
 */
class Updater {

	/**
	 * Transient key caching the latest-release lookup.
	 *
	 * @var string
	 */
	const TRANSIENT = 'swiftpress_github_release';

	/**
	 * How long to cache the GitHub lookup (avoids API rate limits).
	 *
	 * @var int
	 */
	const CACHE_TTL = 21600; // 6 hours.

	/**
	 * Default GitHub repository (owner/name).
	 *
	 * @var string
	 */
	const DEFAULT_REPO = 'seb-cpu/powered-cache';

	/**
	 * Constructor (placeholder, mirrors codebase style).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return Updater
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Register update hooks.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'normalize_source_dir' ], 10, 4 );
		add_action( 'upgrader_process_complete', [ $this, 'flush_cache' ], 10, 0 );
	}

	/**
	 * Configured repository (owner/name).
	 *
	 * @return string
	 */
	private function repo() {
		/**
		 * Filters the GitHub repository (owner/name) used for updates.
		 *
		 * @hook  swiftpress_github_repo
		 * @since 1.0
		 */
		return (string) apply_filters( 'swiftpress_github_repo', self::DEFAULT_REPO );
	}

	/**
	 * Optional GitHub token (for private repos / higher rate limits).
	 *
	 * @return string
	 */
	private function token() {
		$token = defined( 'SWIFTPRESS_GITHUB_TOKEN' ) ? (string) SWIFTPRESS_GITHUB_TOKEN : '';

		/**
		 * Filters the GitHub token used for update requests.
		 *
		 * @hook  swiftpress_github_token
		 * @since 1.0
		 */
		return (string) apply_filters( 'swiftpress_github_token', $token );
	}

	/**
	 * Plugin basename (folder/file.php).
	 *
	 * @return string
	 */
	private function basename() {
		return plugin_basename( SWIFTPRESS_PLUGIN_FILE );
	}

	/**
	 * Plugin slug (install folder name).
	 *
	 * @return string
	 */
	private function slug() {
		return dirname( $this->basename() );
	}

	/**
	 * Fetch (and cache) the latest GitHub release.
	 *
	 * @return array {version, package, changelog, html_url} or ['error' => true].
	 */
	private function latest_release() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$args = [
			'timeout'   => 15,
			'sslverify' => true,
			'headers'   => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'AICache-Updater',
			],
		];

		$token = $this->token();
		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( 'https://api.github.com/repos/' . $this->repo() . '/releases/latest', $args );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$error = [ 'error' => true ];
			set_transient( self::TRANSIENT, $error, self::CACHE_TTL );
			return $error;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			$error = [ 'error' => true ];
			set_transient( self::TRANSIENT, $error, self::CACHE_TTL );
			return $error;
		}

		// Prefer a .zip release asset; fall back to the source archive.
		$package = '';
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
				if ( '.zip' === substr( $name, -4 ) && ! empty( $asset['browser_download_url'] ) ) {
					$package = (string) $asset['browser_download_url'];
					break;
				}
			}
		}
		if ( '' === $package ) {
			$package = isset( $body['zipball_url'] ) ? (string) $body['zipball_url'] : '';
		}

		$data = [
			'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
			'package'   => $package,
			'changelog' => isset( $body['body'] ) ? (string) $body['body'] : '',
			'html_url'  => isset( $body['html_url'] ) ? (string) $body['html_url'] : '',
		];

		set_transient( self::TRANSIENT, $data, self::CACHE_TTL );
		return $data;
	}

	/**
	 * Inject an available update into the update_plugins transient.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->latest_release();
		if ( ! empty( $release['error'] ) || empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], SWIFTPRESS_VERSION, '>' ) ) {
			$item = [
				'slug'        => $this->slug(),
				'plugin'      => $this->basename(),
				'new_version' => $release['version'],
				'url'         => $release['html_url'],
				'package'     => $release['package'],
			];

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = [];
			}
			$transient->response[ $this->basename() ] = (object) $item;
		}

		return $transient;
	}

	/**
	 * Provide the "View details" popup content.
	 *
	 * @param mixed  $result Default result.
	 * @param string $action API action.
	 * @param object $args   Request args.
	 * @return mixed
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug() ) {
			return $result;
		}

		$release = $this->latest_release();
		if ( ! empty( $release['error'] ) ) {
			return $result;
		}

		return (object) [
			'name'          => 'AICache',
			'slug'          => $this->slug(),
			'version'       => $release['version'],
			'author'        => '<a href="https://webs.ie">Webs.ie</a>',
			'homepage'      => $release['html_url'],
			'download_link' => $release['package'],
			'trytestreq'    => false,
			'sections'      => [
				'changelog' => wpautop( esc_html( $release['changelog'] ) ),
			],
		];
	}

	/**
	 * GitHub source archives extract to "owner-repo-<sha>/". Rename the extracted
	 * folder to the plugin slug so the update installs into the right directory.
	 *
	 * @param string $source        Extracted source dir.
	 * @param string $remote_source Parent temp dir.
	 * @param object $upgrader      Upgrader instance.
	 * @param array  $hook_extra    Hook context.
	 * @return string|\WP_Error
	 */
	public function normalize_source_dir( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		if ( empty( $hook_extra['plugin'] ) || $this->basename() !== $hook_extra['plugin'] ) {
			return $source;
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $this->slug();

		if ( untrailingslashit( $source ) === $desired ) {
			return $source;
		}

		if ( $wp_filesystem->move( untrailingslashit( $source ), $desired, true ) ) {
			return trailingslashit( $desired );
		}

		return $source;
	}

	/**
	 * Drop the cached release lookup after any upgrade so the next check is fresh.
	 *
	 * @return void
	 */
	public function flush_cache() {
		delete_transient( self::TRANSIENT );
	}
}
