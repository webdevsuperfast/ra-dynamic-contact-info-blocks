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
				'copiedToClipboard' => __( 'Copied to clipboard', 'ra-dynamic-contact-info-blocks' ),
				'confirmRemove'     => __( 'Are you sure you want to remove this field?', 'ra-dynamic-contact-info-blocks' ),
				'copyKey'           => __( 'Copy Key', 'ra-dynamic-contact-info-blocks' ),
				'actions'           => __( 'Actions ▾', 'ra-dynamic-contact-info-blocks' ),
				'remove'            => __( 'Remove', 'ra-dynamic-contact-info-blocks' ),
				'typePhone'         => __( 'Phone', 'ra-dynamic-contact-info-blocks' ),
				'typeEmail'         => __( 'Email', 'ra-dynamic-contact-info-blocks' ),
				'typeAddress'       => __( 'Address', 'ra-dynamic-contact-info-blocks' ),
				'typeCustom'        => __( 'Custom', 'ra-dynamic-contact-info-blocks' ),
				'fieldKeyLabel'     => __( 'Field key, e.g. (STORE_HOURS)', 'ra-dynamic-contact-info-blocks' ),
				'fieldTypeLabel'    => __( 'Field type', 'ra-dynamic-contact-info-blocks' ),
				'fieldValueLabel'   => __( 'Field value', 'ra-dynamic-contact-info-blocks' ),
				'defaultCountry'    => get_option( 'dcib_default_country', 'US' ),
				'invalidNumber'     => __( 'Invalid phone number for selected country.', 'ra-dynamic-contact-info-blocks' ),
				'validNumber'       => __( 'Valid number', 'ra-dynamic-contact-info-blocks' ),
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
			__( 'Dynamic Contact Info Blocks', 'ra-dynamic-contact-info-blocks' ),
			__( 'Contact Info', 'ra-dynamic-contact-info-blocks' ),
			'manage_options',
			'dcib-settings',
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-phone',
			60
		);

		add_submenu_page(
			'dcib-settings',
			__( 'Import/Export', 'ra-dynamic-contact-info-blocks' ),
			__( 'Import/Export', 'ra-dynamic-contact-info-blocks' ),
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
			'<a href="' . esc_url( admin_url( 'admin.php?page=dcib-settings' ) ) . '">' . esc_html__( 'Settings', 'ra-dynamic-contact-info-blocks' ) . '</a>',
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
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_custom_fields' ),
				'default'           => array(),
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
			__( 'Contact Information', 'ra-dynamic-contact-info-blocks' ),
			'__return_empty_string',
			'dcib-settings'
		);

		add_settings_field(
			'dcib_default_country',
			__( 'Default Country', 'ra-dynamic-contact-info-blocks' ),
			array( $this, 'render_country_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_phone_number',
			__( 'Phone', 'ra-dynamic-contact-info-blocks' ),
			array( $this, 'render_phone_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_email_address',
			__( 'Email', 'ra-dynamic-contact-info-blocks' ),
			array( $this, 'render_email_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_address',
			__( 'Address', 'ra-dynamic-contact-info-blocks' ),
			array( $this, 'render_address_field' ),
			'dcib-settings',
			'dcib_main_section'
		);

		add_settings_field(
			'dcib_maps_url',
			__( 'Maps URL (optional)', 'ra-dynamic-contact-info-blocks' ),
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
		<p class="description"><?php esc_html_e( 'Used to format phone numbers for this site.', 'ra-dynamic-contact-info-blocks' ); ?></p>
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
		$value   = strtoupper( sanitize_text_field( $this->to_string( $value ) ) );
		$codes   = array_keys( DCIB_Phone_Utils::get_countries() );
		$default = 'US';

		if ( in_array( $value, $codes, true ) ) {
			return $value;
		}

		add_settings_error(
			'dcib_messages',
			'dcib_country_error',
			__( 'Invalid country selected.', 'ra-dynamic-contact-info-blocks' )
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
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php esc_html_e( 'Placeholders ▾', 'ra-dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(PHONE)">(PHONE)</div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(PHONE_LINK)">(PHONE_LINK)</div>
				</div>
			</div>
		</div>
		<p class="description">
			<?php esc_html_e( 'Use (PHONE) for display, (PHONE_LINK) for a tel: link.', 'ra-dynamic-contact-info-blocks' ); ?>
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
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php esc_html_e( 'Placeholders ▾', 'ra-dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(EMAIL)">(EMAIL)</div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(EMAIL_LINK)">(EMAIL_LINK)</div>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Use (EMAIL) anywhere to output this value.', 'ra-dynamic-contact-info-blocks' ); ?></p>
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
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php esc_html_e( 'Placeholders ▾', 'ra-dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(ADDRESS)">(ADDRESS)</div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-copy="(ADDRESS_LINK)">(ADDRESS_LINK)</div>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Use (ADDRESS) for text, (ADDRESS_LINK) for a map URL.', 'ra-dynamic-contact-info-blocks' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Leave empty to auto-generate a Google Maps link from the address above, or paste a custom URL (Google Business Profile, Apple Maps, etc.).', 'ra-dynamic-contact-info-blocks' ); ?></p>
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
		$value   = sanitize_text_field( $this->to_string( $value ) );
		$country = DCIB_Phone_Utils::get_default_country();
		$tel     = isset( $_POST['dcib_phone_number_tel'] ) && is_string( $_POST['dcib_phone_number_tel'] ) ? sanitize_text_field( wp_unslash( $_POST['dcib_phone_number_tel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $value ) ) {
			return '';
		}

		if ( ! DCIB_Phone_Utils::is_valid( $value, $country ) || ( $tel && ! DCIB_Phone_Utils::is_valid( $tel, $country ) ) ) {
			add_settings_error(
				'dcib_messages',
				'dcib_phone_invalid',
				sprintf(
					/* translators: %s: country name */
					__( 'Phone number does not appear to be valid for %s.', 'ra-dynamic-contact-info-blocks' ),
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
	 * Sanitize the custom fields option.
	 *
	 * The settings form submits parallel `key`, `type`, `value` (and optional
	 * `tel`) arrays, while options written programmatically (imports, code)
	 * use the stored `(KEY) => array( 'type', 'value' )` map. Both shapes are
	 * accepted and re-sanitized in place, so a save never discards existing
	 * fields and sanitizing an already sanitized value is harmless.
	 *
	 * @param mixed $value Raw option value.
	 *
	 * @return array
	 */
	public function sanitize_custom_fields( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$is_form = isset( $value['key'] ) && is_array( $value['key'] );
		$rows    = $is_form ? $this->custom_field_rows_from_form( $value ) : $this->custom_field_rows_from_store( $value );

		$valid_types = array( 'phone', 'email', 'address', 'custom' );
		$country     = DCIB_Phone_Utils::get_default_country();
		$sanitized   = array();
		$has_invalid = false;

		foreach ( $rows as $row ) {
			$key = '(' . strtoupper( trim( $row['key'], "() \t\n\r\0\x0B" ) ) . ')';

			if ( ! preg_match( '/^\([A-Z0-9_]+\)$/', $key ) ) {
				continue;
			}

			$type = in_array( $row['type'], $valid_types, true ) ? $row['type'] : 'custom';
			$raw  = $row['value'];

			if ( 'phone' === $type ) {
				if ( '' === $raw ) {
					continue;
				}

				$tel = DCIB_Phone_Utils::format_e164( '' !== $row['tel'] ? $row['tel'] : $raw, $country );

				// The form rejects numbers that cannot be validated for the
				// selected country. Stored values are kept as-is so imports
				// and programmatic writes never lose saved data.
				if ( $is_form && ( ! DCIB_Phone_Utils::is_valid( $raw, $country ) || ! DCIB_Phone_Utils::is_valid( $tel, $country ) ) ) {
					$has_invalid = true;
					continue;
				}

				$sanitized[ $key ] = array(
					'type'  => 'phone',
					'value' => sanitize_text_field( $raw ),
					'tel'   => $tel,
				);

				continue;
			}

			switch ( $type ) {
				case 'email':
					$clean = sanitize_email( $raw );
					break;
				case 'address':
					$clean = sanitize_textarea_field( $raw );
					break;
				default:
					$clean = sanitize_text_field( $raw );
					break;
			}

			if ( class_exists( 'DCIB_Public' ) ) {
				$public = new DCIB_Public( 'dcib', defined( 'DCIB_VERSION' ) ? DCIB_VERSION : '1.0' );
				$clean  = $public->filter_normalize_value( $clean );
			}

			$sanitized[ $key ] = array(
				'type'  => $type,
				'value' => $clean,
			);
		}

		if ( $has_invalid ) {
			add_settings_error(
				'dcib_messages',
				'dcib_phone_invalid',
				sprintf(
				/* translators: %s: country name */
					__( 'One or more phone numbers are not valid for %s and were not saved.', 'ra-dynamic-contact-info-blocks' ),
					DCIB_Phone_Utils::get_countries()[ $country ]
				)
			);
		}

		return $sanitized;
	}

	/**
	 * Turn the parallel arrays submitted by the settings form into rows.
	 *
	 * @param array $value Raw option value.
	 *
	 * @return array[]
	 */
	private function custom_field_rows_from_form( array $value ) {
		$types  = isset( $value['type'] ) && is_array( $value['type'] ) ? $value['type'] : array();
		$values = isset( $value['value'] ) && is_array( $value['value'] ) ? $value['value'] : array();
		$tels   = isset( $value['tel'] ) && is_array( $value['tel'] ) ? $value['tel'] : array();

		$rows      = array();
		$tel_index = 0;

		foreach ( $value['key'] as $index => $key ) {
			$type = isset( $types[ $index ] ) ? $this->to_string( $types[ $index ] ) : '';
			$tel  = '';

			if ( 'phone' === $type ) {
				$tel = isset( $tels[ $tel_index ] ) ? $this->to_string( $tels[ $tel_index ] ) : '';
				++$tel_index;
			}

			$rows[] = array(
				'key'   => $this->to_string( $key ),
				'type'  => $type,
				'value' => $this->to_string( isset( $values[ $index ] ) ? $values[ $index ] : '' ),
				'tel'   => $tel,
			);
		}

		return $rows;
	}

	/**
	 * Turn a stored `(KEY) => array( 'type', 'value' )` map into rows.
	 *
	 * Plain string entries from older versions are read as custom fields.
	 *
	 * @param array $value Stored option value.
	 *
	 * @return array[]
	 */
	private function custom_field_rows_from_store( array $value ) {
		$rows = array();

		foreach ( $value as $key => $entry ) {
			$stored = is_array( $entry ) ? $entry : array();

			$rows[] = array(
				'key'   => $this->to_string( $key ),
				'type'  => isset( $stored['type'] ) ? $this->to_string( $stored['type'] ) : '',
				'value' => $this->to_string( isset( $stored['value'] ) ? $stored['value'] : ( is_array( $entry ) ? '' : $entry ) ),
				'tel'   => isset( $stored['tel'] ) ? $this->to_string( $stored['tel'] ) : '',
			);
		}

		return $rows;
	}

	/**
	 * Coerce a submitted value to a string without raising on arrays.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private function to_string( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		}

		return is_scalar( $value ) ? (string) $value : '';
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
