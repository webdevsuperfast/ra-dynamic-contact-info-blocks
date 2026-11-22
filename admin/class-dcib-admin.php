<?php
/**
 * Admin functionality for Dynamic Contact Info Blocks.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DCIB_Admin class.
 * Handles all admin-specific functionality for the plugin.
 */
class DCIB_Admin {

	/**
	 * Bypass flag for import — prevents sanitize_custom_fields from
	 * wiping data that's already in processed array format.
	 *
	 * @var bool
	 */
	public static $skip_custom_fields_sanitize = false;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize admin hooks.
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'check_version_and_migrate' ) );
		add_filter( 'pre_update_option_dcib_custom_fields', array( $this, 'sanitize_custom_fields' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'clear_cache_on_option_update' ), 10, 3 );
		add_action( 'admin_post_dcib_export', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'handle_import' ) );
	}

	/**
	 * Enqueue admin styles on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_styles( $hook_suffix ) {
		if ( 'toplevel_page_dcib-settings' !== $hook_suffix && 'contact-info_page_dcib-import-export' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/dcib-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue admin scripts on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'toplevel_page_dcib-settings' !== $hook_suffix && 'contact-info_page_dcib-import-export' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'dcib-libphonenumber',
			plugin_dir_url( __FILE__ ) . 'js/libphonenumber-js.min.js',
			array(),
			'1.13.17',
			true
		);

		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/dcib-admin.js',
			array( 'dcib-libphonenumber' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name,
			'dcibAdmin',
			array(
				'copiedToClipboard' => __( 'Copied to clipboard', 'dynamic-contact-info-blocks' ),
				'confirmRemove'     => __( 'Are you sure you want to remove this field?', 'dynamic-contact-info-blocks' ),
				'copyKey'           => __( 'Copy Key', 'dynamic-contact-info-blocks' ),
				'actions'           => __( 'Actions ▾', 'dynamic-contact-info-blocks' ),
				'remove'            => __( 'Remove', 'dynamic-contact-info-blocks' ),
				'typePhone'         => __( 'Phone', 'dynamic-contact-info-blocks' ),
				'typeEmail'         => __( 'Email', 'dynamic-contact-info-blocks' ),
				'typeAddress'       => __( 'Address', 'dynamic-contact-info-blocks' ),
				'typeCustom'        => __( 'Custom', 'dynamic-contact-info-blocks' ),
				'fieldKeyLabel'     => __( 'Field key, e.g. (STORE_HOURS)', 'dynamic-contact-info-blocks' ),
				'fieldTypeLabel'    => __( 'Field type', 'dynamic-contact-info-blocks' ),
				'fieldValueLabel'   => __( 'Field value', 'dynamic-contact-info-blocks' ),
				'defaultCountry'    => get_option( 'dcib_default_country', 'US' ),
				'invalidNumber'     => __( 'Invalid phone number for selected country.', 'dynamic-contact-info-blocks' ),
				'validNumber'       => __( 'Valid number', 'dynamic-contact-info-blocks' ),
			)
		);
	}

	/**
	 * Register plugin admin menu pages.
	 *
	 * @return void
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Dynamic Contact Info Blocks', 'dynamic-contact-info-blocks' ),
			__( 'Contact Info', 'dynamic-contact-info-blocks' ),
			'manage_options',
			'dcib-settings',
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-phone',
			60
		);

		add_submenu_page(
			'dcib-settings',
			__( 'Import/Export', 'dynamic-contact-info-blocks' ),
			__( 'Import/Export', 'dynamic-contact-info-blocks' ),
			'manage_options',
			'dcib-import-export',
			array( $this, 'display_import_export_page' )
		);
	}

	/**
	 * Add settings link to plugin row actions.
	 *
	 * @param array $links Existing action links.
	 *
	 * @return array
	 */
	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=dcib-settings' ) ) . '">' . esc_html__( 'Settings', 'dynamic-contact-info-blocks' ) . '</a>',
		);
		return array_merge( $settings_link, $links );
	}

	/**
	 * Render the main plugin admin page.
	 *
	 * @return void
	 */
	public function display_plugin_admin_page() {
		include_once 'partials/dcib-admin-display.php';
	}

	/**
	 * Render the import/export admin page.
	 *
	 * @return void
	 */
	public function display_import_export_page() {
		if ( ! class_exists( 'DCIB_Import_Export' ) ) {
			return;
		}
		$import_export = new DCIB_Import_Export( $this->plugin_name, $this->version );
		$import_export->display_import_export_page();
	}

	/**
	 * Register settings, sections, and fields via WordPress Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'dcib_settings_group',
			'dcib_phone_number',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_phone_number' ),
				'default'           => '',
			)
		);

		register_setting(
			'dcib_settings_group',
			'dcib_email_address',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_email_address' ),
				'default'           => '',
			)
		);

		register_setting(
			'dcib_settings_group',
			'dcib_address',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'default'           => '',
			)
		);

		register_setting(
			'dcib_settings_group',
			'dcib_maps_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_maps_url' ),
				'default'           => '',
			)
		);

		register_setting(
			'dcib_settings_group',
			'dcib_custom_fields',
			array(
				'type'    => 'array',
				'default' => array(),
			)
		);

		register_setting(
			'dcib_settings_group',
			'dcib_default_country',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_country' ),
				'default'           => 'US',
			)
		);

		add_settings_section(
			'dcib_main_section',
			__( 'Contact Information', 'dynamic-contact-info-blocks' ),
			'__return_empty_string',
			'dcib-settings'
		);

		add_settings_field(
			'dcib_default_country',
			__( 'Default Country', 'dynamic-contact-info-blocks' ),
			array( $this, 'render_country_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_phone_number',
			__( 'Phone', 'dynamic-contact-info-blocks' ),
			array( $this, 'render_phone_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_email_address',
			__( 'Email', 'dynamic-contact-info-blocks' ),
			array( $this, 'render_email_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_address',
			__( 'Address', 'dynamic-contact-info-blocks' ),
			array( $this, 'render_address_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_maps_url',
			__( 'Maps URL (optional)', 'dynamic-contact-info-blocks' ),
			array( $this, 'render_maps_url_field' ),
			'dcib-settings',
			'dcib_main_section'
		);
	}

	/**
	 * Render the country selector field.
	 *
	 * @return void
	 */
	public function render_country_field() {
		$country   = get_option( 'dcib_default_country', 'US' );
		$countries = DCIB_Phone_Utils::get_countries();
		?>
		<select id="dcib_default_country" class="regular-text" name="dcib_default_country">
			<?php foreach ( $countries as $code => $name ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $country, $code ); ?>><?php echo esc_html( $name ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Used to format phone numbers for this site.', 'dynamic-contact-info-blocks' ); ?></p>
		<?php
	}

	/**
	 * Sanitize country code.
	 *
	 * @param string $value Raw country code.
	 *
	 * @return string
	 */
	public function sanitize_country( $value ) {
		$value   = strtoupper( sanitize_text_field( $value ) );
		$codes   = array_keys( DCIB_Phone_Utils::get_countries() );
		$default = 'US';

		if ( in_array( $value, $codes, true ) ) {
			return $value;
		}

		add_settings_error(
			'dcib_messages',
			'dcib_country_error',
			__( 'Invalid country selected.', 'dynamic-contact-info-blocks' )
		);

		return $default;
	}

	/**
	 * Render the phone number field.
	 *
	 * @return void
	 */
	public function render_phone_field() {
		$option = get_option( 'dcib_phone_number', '' );

		if ( is_string( $option ) ) {
			$parsed = maybe_unserialize( $option );
			if ( is_array( $parsed ) ) {
				$option = $parsed;
			}
		}

		if ( is_array( $option ) && isset( $option['value'] ) ) {
			$value   = $option['value'];
			$has_tel = ! empty( $option['tel'] );
		} else {
			$value   = is_string( $option ) ? $option : '';
			$has_tel = false;
		}
		?>
		<div class="dcib-field-group">
			<input type="tel" id="dcib_phone_number" class="regular-text" name="dcib_phone_number" value="<?php echo esc_attr( $value ); ?>" required />
			<div class="dcib-dropdown">
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php esc_html_e( 'Placeholders ▾', 'dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(PHONE)">(PHONE)</div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(PHONE_LINK)">(PHONE_LINK)</div>
				</div>
			</div>
		</div>
		<p class="description">
			<?php esc_html_e( 'Use (PHONE) for display, (PHONE_LINK) for a tel: link.', 'dynamic-contact-info-blocks' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the email field.
	 *
	 * @return void
	 */
	public function render_email_field() {
		$value = get_option( 'dcib_email_address', '' );
		?>
		<div class="dcib-field-group">
			<input type="email" id="dcib_email_address" class="regular-text" name="dcib_email_address" value="<?php echo esc_attr( $value ); ?>" required />
			<div class="dcib-dropdown">
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php esc_html_e( 'Placeholders ▾', 'dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(EMAIL)">(EMAIL)</div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(EMAIL_LINK)">(EMAIL_LINK)</div>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Use (EMAIL) anywhere to output this value.', 'dynamic-contact-info-blocks' ); ?></p>
		<?php
	}

	/**
	 * Render the address field.
	 *
	 * @return void
	 */
	public function render_address_field() {
		$value = get_option( 'dcib_address', '' );
		?>
		<div class="dcib-field-group">
			<textarea id="dcib_address" class="regular-text" name="dcib_address" rows="3" required><?php echo esc_textarea( $value ); ?></textarea>
			<div class="dcib-dropdown">
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php esc_html_e( 'Placeholders ▾', 'dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(ADDRESS)">(ADDRESS)</div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(ADDRESS_LINK)">(ADDRESS_LINK)</div>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Use (ADDRESS) for text, (ADDRESS_LINK) for a map URL.', 'dynamic-contact-info-blocks' ); ?></p>
		<?php
	}

	/**
	 * Render the Maps URL field.
	 *
	 * @return void
	 */
	public function render_maps_url_field() {
		$value = get_option( 'dcib_maps_url', '' );
		?>
		<input type="url" id="dcib_maps_url" class="regular-text" name="dcib_maps_url" value="<?php echo esc_url( $value ); ?>" placeholder="https://www.google.com/maps/..." />
		<p class="description"><?php esc_html_e( 'Leave empty to auto-generate a Google Maps link from the address above, or paste a custom URL (Google Business Profile, Apple Maps, etc.).', 'dynamic-contact-info-blocks' ); ?></p>
		<?php
	}

	/**
	 * Sanitize the stored email address.
	 *
	 * The sanitize_email() and esc_url_raw() functions call strlen()/ltrim() on
	 * their input, so a request that submits an array (dcib_email_address[]=x)
	 * would raise a TypeError. Scalar-only input keeps the option save
	 * fail-closed.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return string
	 */
	public function sanitize_email_address( $value ) {
		return sanitize_email( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Sanitize the stored maps URL.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return string
	 */
	public function sanitize_maps_url( $value ) {
		return esc_url_raw( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Validate and format phone number on save.
	 *
	 * @param string $value Raw phone input.
	 *
	 * @return string Serialized array with 'value' and 'tel' keys.
	 */
	public function sanitize_phone_number( $value ) {
		$value   = sanitize_text_field( $value );
		$country = DCIB_Phone_Utils::get_default_country();
		$tel     = isset( $_POST['dcib_phone_number_tel'] ) ? sanitize_text_field( wp_unslash( $_POST['dcib_phone_number_tel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $value ) ) {
			return '';
		}

		if ( ! DCIB_Phone_Utils::is_valid( $value, $country ) || ( $tel && ! DCIB_Phone_Utils::is_valid( $tel, $country ) ) ) {
			add_settings_error(
				'dcib_messages',
				'dcib_phone_invalid',
				sprintf(
					/* translators: %s: country name */
					__( 'Phone number does not appear to be valid for %s.', 'dynamic-contact-info-blocks' ),
					DCIB_Phone_Utils::get_countries()[ $country ]
				)
			);
			return $value;
		}

		return maybe_serialize(
			array(
				'value' => DCIB_Phone_Utils::format( $value, $country ),
				'tel'   => $tel ? DCIB_Phone_Utils::format_e164( $tel, $country ) : DCIB_Phone_Utils::format_e164( $value, $country ),
			)
		);
	}

	/**
	 * Run migrations when plugin version changes.
	 *
	 * @return void
	 */
	public function check_version_and_migrate() {
		$stored_version = get_option( 'dcib_version', '0' );

		if ( version_compare( $stored_version, '1.3.0', '<' ) ) {
			$this->migrate_to_130();
		}

		if ( version_compare( $stored_version, '1.3.3', '<' ) ) {
			$this->normalize_phone_tel();
		}

		if ( version_compare( $stored_version, DCIB_VERSION, '<' ) ) {
			update_option( 'dcib_version', DCIB_VERSION );
		}
	}

	/**
	 * Normalize stored phone 'tel' values to E.164 (add missing country code).
	 *
	 * @return void
	 */
	private function normalize_phone_tel() {
		$country = DCIB_Phone_Utils::get_default_country();

		$phone = get_option( 'dcib_phone_number', '' );
		if ( is_string( $phone ) ) {
			$phone = maybe_unserialize( $phone );
		}
		if ( is_array( $phone ) && isset( $phone['value'] ) ) {
			$tel_src    = ! empty( $phone['tel'] ) ? $phone['tel'] : $phone['value'];
			$normalized = DCIB_Phone_Utils::format_e164( $tel_src, $country );
			if ( empty( $phone['tel'] ) || $normalized !== $phone['tel'] ) {
				$phone['tel'] = $normalized;
				update_option( 'dcib_phone_number', maybe_serialize( $phone ) );
			}
		}

		$custom = get_option( 'dcib_custom_fields', array() );
		if ( is_string( $custom ) ) {
			$custom = maybe_unserialize( $custom );
		}
		if ( ! is_array( $custom ) ) {
			return;
		}

		$dirty = false;
		foreach ( $custom as $key => $field ) {
			if ( is_array( $field ) && isset( $field['type'] ) && 'phone' === $field['type'] ) {
				$tel_src    = ! empty( $field['tel'] ) ? $field['tel'] : ( isset( $field['value'] ) ? $field['value'] : '' );
				$normalized = DCIB_Phone_Utils::format_e164( $tel_src, $country );
				if ( empty( $field['tel'] ) || $normalized !== $field['tel'] ) {
					$custom[ $key ]['tel'] = $normalized;
					$dirty                 = true;
				}
			}
		}

		if ( $dirty ) {
			update_option( 'dcib_custom_fields', $custom );
		}
	}

	/**
	 * Migration to v1.3.0: add 'tel' key to phone fields.
	 *
	 * @return void
	 */
	private function migrate_to_130() {
		$country = DCIB_Phone_Utils::get_default_country();

		$phone = get_option( 'dcib_phone_number', '' );
		if ( is_string( $phone ) && '' !== $phone ) {
			update_option(
				'dcib_phone_number',
				maybe_serialize(
					array(
						'value' => $phone,
						'tel'   => DCIB_Phone_Utils::format_e164( $phone, $country ),
					)
				)
			);
		}

		$custom = get_option( 'dcib_custom_fields', array() );
		if ( is_string( $custom ) ) {
			$custom = maybe_unserialize( $custom );
		}
		if ( ! is_array( $custom ) ) {
			return;
		}
		$dirty = false;

		foreach ( $custom as $key => $field ) {
			if ( isset( $field['type'] ) && 'phone' === $field['type'] && ! isset( $field['tel'] ) ) {
				$value                 = isset( $field['value'] ) ? $field['value'] : '';
				$custom[ $key ]['tel'] = DCIB_Phone_Utils::format_e164( $value, $country );
				$dirty                 = true;
			}
		}

		if ( $dirty ) {
			update_option( 'dcib_custom_fields', $custom );
		}
	}

	/**
	 * Sanitize custom fields: normalize keys to (KEY) format, validate, strip empty.
	 *
	 * @param array $value    Raw option value with 'key' and 'value' sub-arrays.
	 * @param array $old_value Previous option value (unused).
	 *
	 * @return array
	 */
	public function sanitize_custom_fields( $value, $old_value = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( self::$skip_custom_fields_sanitize ) {
			self::$skip_custom_fields_sanitize = false;
			return $value;
		}

		if ( ! is_array( $value ) || ! isset( $value['key'] ) || ! isset( $value['type'] ) || ! isset( $value['value'] ) ) {
			return array();
		}

		$keys       = array_map( 'sanitize_text_field', wp_unslash( $value['key'] ) );
		$types      = array_map( 'sanitize_text_field', wp_unslash( $value['type'] ) );
		$raw_values = wp_unslash( $value['value'] );
		$tel_values = isset( $value['tel'] ) ? wp_unslash( $value['tel'] ) : array();

		$valid_types = array( 'phone', 'email', 'address', 'custom' );

		$country     = DCIB_Phone_Utils::get_default_country();
		$sanitized   = array();
		$has_invalid = false;
		$tel_index   = 0;

		foreach ( $keys as $index => $key ) {
			$raw_key        = isset( $keys[ $index ] ) ? $keys[ $index ] : '';
			$raw_type       = isset( $types[ $index ] ) && in_array( $types[ $index ], $valid_types, true ) ? $types[ $index ] : 'custom';
			$raw_val        = isset( $raw_values[ $index ] ) ? $raw_values[ $index ] : '';
			$normalized_key = '(' . trim( $raw_key, "() \t\n\r\0\x0B" ) . ')';
			$normalized_key = strtoupper( $normalized_key );

			if ( ! preg_match( '/^\([A-Z0-9_]+\)$/', $normalized_key ) ) {
				continue;
			}

			if ( 'phone' === $raw_type ) {
				if ( empty( $raw_val ) ) {
					continue;
				}
				$tel_value = isset( $tel_values[ $tel_index ] ) ? sanitize_text_field( $tel_values[ $tel_index ] ) : '';
				++$tel_index;
				if ( '' === $tel_value ) {
					$tel_value = DCIB_Phone_Utils::format_e164( $raw_val, $country );
				} else {
					$tel_value = DCIB_Phone_Utils::format_e164( $tel_value, $country );
				}
				if ( ! DCIB_Phone_Utils::is_valid( $raw_val, $country ) || ( $tel_value && ! DCIB_Phone_Utils::is_valid( $tel_value, $country ) ) ) {
					$has_invalid = true;
					continue;
				}
				$sanitized[ $normalized_key ] = array(
					'type'  => 'phone',
					'value' => sanitize_text_field( $raw_val ),
					'tel'   => $tel_value,
				);
				continue;
			}

			switch ( $raw_type ) {
				case 'email':
					$clean_val = sanitize_email( $raw_val );
					break;
				case 'address':
					$clean_val = sanitize_textarea_field( $raw_val );
					break;
				case 'custom':
				default:
					$clean_val = sanitize_text_field( $raw_val );
					break;
			}

			if ( class_exists( 'DCIB_Public' ) ) {
				$public_inst = new DCIB_Public( 'dcib', defined( 'DCIB_VERSION' ) ? DCIB_VERSION : '1.0' );
				$clean_val   = $public_inst->filter_normalize_value( $clean_val );
			}

			$sanitized[ $normalized_key ] = array(
				'type'  => $raw_type,
				'value' => $clean_val,
			);
		}

		if ( $has_invalid ) {
			add_settings_error(
				'dcib_messages',
				'dcib_phone_invalid',
				sprintf(
				/* translators: %s: country name */
					__( 'One or more phone numbers are not valid for %s and were not saved.', 'dynamic-contact-info-blocks' ),
					DCIB_Phone_Utils::get_countries()[ $country ]
				)
			);
		}

		return $sanitized;
	}

	/**
	 * Clear DCIB_Public cache when any dcib_ option is updated.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $old_value   Old value (unused).
	 * @param mixed  $new_value   New value (unused).
	 *
	 * @return void
	 */
	public function clear_cache_on_option_update( $option_name, $old_value, $new_value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( str_starts_with( $option_name, 'dcib_' ) && class_exists( 'DCIB_Public' ) ) {
			DCIB_Public::clear_cache();
		}
	}

	/**
	 * Handle export action callback.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! class_exists( 'DCIB_Import_Export' ) ) {
			return;
		}
		$import_export = new DCIB_Import_Export( $this->plugin_name, $this->version );
		$import_export->export_data();
	}

	/**
	 * Handle import action callback.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! isset( $_POST['dcib_import_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcib_import_nonce'] ) ), 'dcib_import_settings' ) ) {
			return;
		}

		if ( ! class_exists( 'DCIB_Import_Export' ) ) {
			return;
		}
		$import_export = new DCIB_Import_Export( $this->plugin_name, $this->version );
		$import_export->import_data();
	}
}
