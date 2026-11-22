<?php
/**
 * In-house GitHub updater for DCIB.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles updates from the private GitHub repository.
 */
class DCIB_Updater {

	/** Updater singleton.
	 *
	 * @var DCIB_Updater|null
	 */
	private static $instance = null;

	/** Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/** Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug = 'dynamic-contact-info-blocks';

	/** Latest release response.
	 *
	 * @var object|null
	 */
	private $release;

	/**
	 * Initialize updater.
	 *
	 * @return void
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->plugin_basename = plugin_basename( DCIB_PLUGIN_PATH . 'dynamic-contact-info-blocks.php' );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ), 100 );
		add_filter( 'plugins_api', array( $this, 'get_plugin_info' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 10, 2 );
	}

	/**
	 * Check GitHub for an update.
	 *
	 * @param object $transient Plugin update transient.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $transient;
		}

		$version = ltrim( (string) $release->tag_name, 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+){1,2}$/', $version ) ) {
			return $transient;
		}

		if ( version_compare( $version, DCIB_VERSION, '<=' ) ) {
			return $transient;
		}

		// Never advertise an update we cannot download from the expected host.
		$package = $this->download_url( $release );
		if ( '' === $package ) {
			return $transient;
		}

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'slug'        => $this->plugin_slug,
			'plugin'      => $this->plugin_basename,
			'new_version' => $version,
			'url'         => $this->repository_url(),
			'package'     => $package,
		);

		return $transient;
	}

	/**
	 * Provide plugin information on update details screen.
	 *
	 * @param false|object $data Existing response.
	 * @param string       $action API action.
	 * @param object       $args API arguments.
	 * @return false|object
	 */
	public function get_plugin_info( $data, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $data;
		}

		$release = $this->get_release();
		if ( ! $release ) {
			return $data;
		}

		$version = ltrim( $release->tag_name, 'vV' );
		return (object) array(
			'name'          => 'Dynamic Contact Info Blocks',
			'slug'          => $this->plugin_slug,
			'version'       => $version,
			'author'        => '<a href="https://currier.marketing">Currier Marketing</a>',
			'homepage'      => $this->repository_url(),
			'requires'      => '6.0',
			'tested'        => get_bloginfo( 'version' ),
			'last_updated'  => isset( $release->published_at ) ? $release->published_at : '',
			'download_link' => $this->download_url( $release ),
			'sections'      => array(
				'description' => ! empty( $release->body ) ? wp_kses_post( $release->body ) : __( 'Centralized business contact information for WordPress.', 'dynamic-contact-info-blocks' ),
			),
		);
	}

	/**
	 * Normalize destination after package extraction.
	 *
	 * @param bool  $response Upgrade response.
	 * @param array $hook_extra Upgrade context.
	 * @param array $result Installation result.
	 * @return array
	 */
	public function upgrader_post_install( $response, $hook_extra, $result ) {
		if ( empty( $hook_extra['plugin'] ) || $this->plugin_basename !== $hook_extra['plugin'] || empty( $result['destination'] ) ) {
			return $result;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return $result;
		}

		$destination = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug;
		if ( $result['destination'] !== $destination ) {
			$wp_filesystem->move( $result['destination'], $destination );
			$result['destination'] = $destination;
		}

		return $result;
	}

	/**
	 * Fetch latest release, cached for six hours.
	 *
	 * @return object|null
	 */
	private function get_release() {
		if ( null !== $this->release ) {
			return $this->release;
		}

		$cached = get_site_transient( 'dcib_github_latest_release' );
		if ( ! $this->overrule_transients() && is_object( $cached ) ) {
			$this->release = $this->sanitize_release( $cached );
			if ( null !== $this->release ) {
				return $this->release;
			}
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/currier-marketing/dynamic-contact-info-blocks/releases/latest',
			array(
				'timeout' => 10,
				'headers' => $this->headers(),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$release = $this->sanitize_release( json_decode( wp_remote_retrieve_body( $response ) ) );
		if ( null === $release ) {
			return null;
		}

		set_site_transient( 'dcib_github_latest_release', $release, 6 * HOUR_IN_SECONDS );
		$this->release = $release;
		return $release;
	}

	/**
	 * Validate a release payload before it is trusted.
	 *
	 * The release object is stored in a site transient, which a shared object
	 * cache, another plugin, or a tampered API response can populate. Anything
	 * that does not look like a release of this repository is discarded.
	 *
	 * @param mixed $release Decoded release payload.
	 * @return object|null Validated release, or null.
	 */
	private function sanitize_release( $release ) {
		if ( ! is_object( $release ) || ! isset( $release->tag_name ) || ! is_string( $release->tag_name ) ) {
			return null;
		}

		if ( ! preg_match( '/^v?\d+(\.\d+){1,2}$/', $release->tag_name ) ) {
			return null;
		}

		if ( isset( $release->zipball_url ) && ! $this->is_repository_url( $release->zipball_url ) ) {
			unset( $release->zipball_url );
		}

		return $release;
	}

	/**
	 * Build an authenticated download URL.
	 *
	 * Only returns a URL when it points at this plugin's repository over https,
	 * so a tampered release payload (shared object cache, third-party filter)
	 * cannot redirect the upgrader at an arbitrary package.
	 *
	 * @param object $release GitHub release.
	 * @return string Download URL, or an empty string when untrusted.
	 */
	private function download_url( $release ) {
		if ( empty( $release->zipball_url ) || ! is_string( $release->zipball_url ) ) {
			return '';
		}

		if ( ! $this->is_repository_url( $release->zipball_url ) ) {
			return '';
		}

		return esc_url_raw( $release->zipball_url );
	}

	/**
	 * Add authentication to GitHub package downloads.
	 *
	 * @param array  $args HTTP arguments.
	 * @param string $url Request URL.
	 * @return array
	 */
	public function http_request_args( $args, $url ) {
		if ( ! $this->is_repository_url( $url ) ) {
			return $args;
		}

		$args['headers'] = array_merge( isset( $args['headers'] ) ? $args['headers'] : array(), $this->headers() );

		return $args;
	}

	/**
	 * Whether a URL belongs to this plugin's GitHub repository.
	 *
	 * Parses the URL and matches the host and path prefix explicitly. A plain
	 * substring match would send the Authorization header to any host that can
	 * place the repository path in a URL (oEmbed discovery, webhooks, etc.).
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	private function is_repository_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		$hosts = array( 'github.com', 'api.github.com', 'codeload.github.com' );
		if ( ! in_array( strtolower( $parts['host'] ), $hosts, true ) ) {
			return false;
		}

		$repo     = '/currier-marketing/dynamic-contact-info-blocks';
		$path     = isset( $parts['path'] ) ? $parts['path'] : '';
		$patterns = array(
			'#^' . preg_quote( $repo, '#' ) . '(/|$)#',
			'#^/repos' . preg_quote( $repo, '#' ) . '(/|$)#',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * GitHub request headers.
	 *
	 * @return array
	 */
	private function headers() {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'Dynamic-Contact-Info-Blocks/' . DCIB_VERSION,
		);
		$token   = apply_filters( 'dcib_updater_token', defined( 'DCIB_GITHUB_TOKEN' ) ? DCIB_GITHUB_TOKEN : '' );
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		return $headers;
	}

	/**
	 * Repository URL.
	 *
	 * @return string
	 */
	private function repository_url() {
		return 'https://github.com/currier-marketing/dynamic-contact-info-blocks';
	}

	/**
	 * Whether update cache should be bypassed.
	 *
	 * @return bool
	 */
	private function overrule_transients() {
		global $pagenow;
		return 'update-core.php' === $pagenow && isset( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}

add_action( 'plugins_loaded', array( 'DCIB_Updater', 'init' ), 20 );
