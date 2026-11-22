<?php
/**
 * Main plugin bootstrap class.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DCIB class.
 * Initializes the plugin and registers hooks.
 */
class DCIB {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->version     = DCIB_VERSION;
		$this->plugin_name = 'dynamic-contact-info-blocks';
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Get singleton plugin instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load required plugin files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once plugin_dir_path( __DIR__ ) . 'admin/class-dcib-admin.php';
		require_once plugin_dir_path( __DIR__ ) . 'public/class-dcib-public.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-dcib-wpforms.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-dcib-updater.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-dcib-import-export.php';
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-dcib-phone-utils.php';
	}

	/**
	 * Register admin-side hooks.
	 *
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin = new DCIB_Admin( $this->get_plugin_name(), $this->get_version() );
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( plugin_dir_path( __DIR__ ) . 'dynamic-contact-info-blocks.php' ),
			array( $plugin_admin, 'add_action_links' )
		);
	}

	/**
	 * Register public-side hooks.
	 *
	 * @return void
	 */
	private function define_public_hooks() {
		$plugin_public = new DCIB_Public( $this->get_plugin_name(), $this->get_version() );
		add_filter( 'the_content', array( $plugin_public, 'replace_contact_info' ) );
		add_filter( 'widget_text', array( $plugin_public, 'replace_contact_info' ) );
		add_filter( 'widget_text_content', array( $plugin_public, 'replace_contact_info' ) );

		// Elementor support - filter frontend content including headers/footers.
		// Use priority 10 for early processing, then 20 for widget-level content.
		add_filter( 'elementor/frontend/the_content', array( $plugin_public, 'replace_contact_info' ), 10 );
		add_filter( 'elementor/widget/render_content', array( $plugin_public, 'replace_contact_info' ), 20 );

		// Additional Elementor hooks for comprehensive coverage.
		add_filter( 'elementor/theme/the_content', array( $plugin_public, 'replace_contact_info' ), 10 );

		// Shortcode: [dcib key="PHONE"].
		add_shortcode( 'dcib', array( $plugin_public, 'shortcode' ) );

		// Debug shortcode: [dcib_debug key="PHONE"].
		add_shortcode( 'dcib_debug', array( $plugin_public, 'debug_shortcode' ) );

		// Register self-test hook in debug environments.
		if ( method_exists( 'DCIB_Public', 'register_self_test_filter' ) ) {
			DCIB_Public::register_self_test_filter();
		}

		// Elementor Dynamic Tags - only if Elementor is active.
		// Elementor Dynamic Tags - always register hook, it will only fire if Elementor loads.
		add_action( 'elementor/init', array( $this, 'register_elementor_dynamic_tags_init' ), 10 );

		// WPForms Smart Tags - register when WPForms loads (or as a fallback on plugins_loaded).
		// We don't check for WPForms here because WPForms may load after this plugin.
		// The DCIB_WPForms::register_wpforms_smart_tags_init() method will itself.
		// verify WPForms presence before attaching filters.
		add_action( 'wpforms_loaded', array( 'DCIB_WPForms', 'register_wpforms_smart_tags_init' ) );
		// Fallback for load orders where wpforms_loaded isn't fired in time.
		add_action( 'plugins_loaded', array( 'DCIB_WPForms', 'register_wpforms_smart_tags_init' ), 20 );
	}

	/**
	 * Clear cache when Elementor preview loads.
	 *
	 * @return void
	 */
	public function clear_cache_on_preview() {
		if ( class_exists( 'DCIB_Public' ) ) {
			DCIB_Public::clear_cache();
		}
	}

	/**
	 * Enqueue scripts for Elementor editor.
	 *
	 * @return void
	 */
	public function enqueue_elementor_editor_scripts() {
		wp_enqueue_script(
			'dcib-elementor-editor',
			DCIB_PLUGIN_URL . 'public/js/dcib-elementor-editor.js',
			array( 'jquery' ),
			$this->version,
			true
		);
	}

	/**
	 * Get plugin slug.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Check if Elementor Pro is active.
	 *
	 * @return bool
	 */
	private function is_elementor_pro_active() {
		return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin' );
	}

	/**
	 * Check if Elementor is active (core or Pro).
	 *
	 * @return bool
	 */
	private function is_elementor_active() {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Register Elementor Dynamic Tags and Groups.
	 *
	 * @param \Elementor\Core\DynamicTags\Manager $dynamic_tags_manager Dynamic tags manager instance.
	 *
	 * @return void
	 */
	public function register_elementor_dynamic_tags( $dynamic_tags_manager ) {
		// Register custom group.
		$dynamic_tags_manager->register_group(
			'dcib',
			array(
				'title' => esc_html__( 'Dynamic Contact Info', 'dynamic-contact-info-blocks' ),
			)
		);

		// Register the tag.
		require_once plugin_dir_path( __DIR__ ) . 'includes/class-dcib-elementor-dynamic-tag.php';
		$dynamic_tags_manager->register( new DCIB_Elementor_Dynamic_Tag() );
	}

	/**
	 * Register Elementor Dynamic Tags on init.
	 *
	 * @return void
	 */
	public function register_elementor_dynamic_tags_init() {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_elementor_dynamic_tags' ) );
	}
}
