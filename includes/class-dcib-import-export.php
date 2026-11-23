<?php
/**
 * Import/export support for plugin settings.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles settings import/export features.
 */
class DCIB_Import_Export {

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
	 * Constructor.
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Export contact info data as JSON.
	 *
	 * @return void
	 */
	public function export_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ra-dynamic-contact-info-blocks' ) );
		}

		if ( ! isset( $_REQUEST['dcib_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['dcib_export_nonce'] ) ), 'dcib_export_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'ra-dynamic-contact-info-blocks' ) );
		}

		$data = array(
			'phone_number'  => get_option( 'dcib_phone_number', '' ),
			'email_address' => get_option( 'dcib_email_address', '' ),
			'address'       => get_option( 'dcib_address', '' ),
			'custom_fields' => get_option( 'dcib_custom_fields', array() ),
			'maps_url'      => get_option( 'dcib_maps_url', '' ),
			'exported_at'   => current_time( 'mysql' ),
			'version'       => $this->version,
		);

		$filename = 'dcib-backup-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import contact info data from JSON.
	 *
	 * @return void
	 */
	public function import_data() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ra-dynamic-contact-info-blocks' ) );
		}

		if ( ! isset( $_POST['dcib_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dcib_import_nonce'] ) ), 'dcib_import_settings' ) ) {
			add_settings_error( 'dcib_import_messages', 'dcib_import_nonce_error', __( 'Security check failed.', 'ra-dynamic-contact-info-blocks' ), 'error' );
			return;
		}

		if (
			! isset( $_FILES['dcib_import_file'] )
			|| ! isset( $_FILES['dcib_import_file']['error'] )
			|| UPLOAD_ERR_OK !== (int) $_FILES['dcib_import_file']['error']
		) {
			add_settings_error( 'dcib_import_messages', 'dcib_import_file_error', __( 'Please select a valid backup file.', 'ra-dynamic-contact-info-blocks' ), 'error' );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Superglobal is only read for upload metadata; every member used below is type-checked and validated.
		$file = $_FILES['dcib_import_file'];

		// Check file extension manually since JSON is not in WordPress default allowed types.
		$file_name      = isset( $file['name'] ) && is_string( $file['name'] ) ? $file['name'] : '';
		$file_extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( 'json' !== $file_extension ) {
			add_settings_error( 'dcib_import_messages', 'dcib_import_file_type_error', __( 'Invalid file type. Please upload a JSON file.', 'ra-dynamic-contact-info-blocks' ), 'error' );
			return;
		}

		if ( ! isset( $file['size'] ) || (int) $file['size'] > 2 * MB_IN_BYTES ) {
			add_settings_error( 'dcib_import_messages', 'dcib_import_file_size_error', __( 'Backup file is too large. Maximum size is 2 MB.', 'ra-dynamic-contact-info-blocks' ), 'error' );
			return;
		}

		if ( empty( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			add_settings_error( 'dcib_import_messages', 'dcib_import_file_error', __( 'Please select a valid backup file.', 'ra-dynamic-contact-info-blocks' ), 'error' );
			return;
		}

		$content = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data    = json_decode( $content, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			add_settings_error( 'dcib_import_messages', 'dcib_import_json_error', __( 'Invalid JSON file.', 'ra-dynamic-contact-info-blocks' ), 'error' );
			return;
		}

		$this->apply_import( $data );
	}

	/**
	 * Validate, sanitize and store an imported backup.
	 *
	 * Every value is coerced to a string before it reaches a WordPress
	 * sanitizer: the JSON types are attacker-controlled and sanitize_email()
	 * and friends raise a TypeError on arrays. Kept separate from the upload
	 * handling so it can be exercised without a real file upload.
	 *
	 * @param array $data Decoded backup file.
	 *
	 * @return void
	 */
	private function apply_import( $data ) {
		// Validate data structure.
		if ( ! isset( $data['phone_number'] ) || ! isset( $data['email_address'] ) || ! isset( $data['address'] ) || ! isset( $data['custom_fields'] ) ) {
			add_settings_error( 'dcib_import_messages', 'dcib_import_data_error', __( 'Invalid backup file structure.', 'ra-dynamic-contact-info-blocks' ), 'error' );
			return;
		}

		// Sanitize and save data.
		$phone         = $this->import_phone( $data['phone_number'] );
		$email         = sanitize_email( self::to_string( $data['email_address'] ) );
		$address       = sanitize_textarea_field( self::to_string( $data['address'] ) );
		$custom_fields = array();
		$valid_types   = array( 'phone', 'email', 'address', 'custom' );

		if ( is_array( $data['custom_fields'] ) ) {
			foreach ( $data['custom_fields'] as $key => $value ) {
				$clean_key      = sanitize_text_field( (string) $key );
				$normalized_key = '(' . trim( strtoupper( $clean_key ), '()' ) . ')';

				if ( ! preg_match( '/^\([A-Z0-9_]+\)$/', $normalized_key ) ) {
					continue;
				}

				if ( is_array( $value ) && isset( $value['type'], $value['value'] ) && is_string( $value['type'] ) ) {
					$raw_type = in_array( $value['type'], $valid_types, true ) ? $value['type'] : 'custom';

					$entry = array(
						'type'  => $raw_type,
						'value' => '',
					);

					switch ( $raw_type ) {
						case 'phone':
							$country = DCIB_Phone_Utils::get_default_country();
							$raw_val = self::to_string( $value['value'] );
							$raw_tel = isset( $value['tel'] ) ? self::to_string( $value['tel'] ) : '';
							if ( DCIB_Phone_Utils::is_valid( $raw_val, $country ) ) {
								$entry['value'] = sanitize_text_field( $raw_val );
								$entry['tel']   = DCIB_Phone_Utils::format_e164( $raw_tel, $country );
							} else {
								$entry['value'] = sanitize_text_field( $raw_val );
							}
							break;
						case 'email':
							$entry['value'] = sanitize_email( self::to_string( $value['value'] ) );
							break;
						case 'address':
							$entry['value'] = sanitize_textarea_field( self::to_string( $value['value'] ) );
							break;
						default:
							$entry['value'] = sanitize_text_field( self::to_string( $value['value'] ) );
							break;
					}
				} else {
					$raw_type = 'custom';
					$entry    = array(
						'type'  => $raw_type,
						'value' => sanitize_text_field( self::to_string( $value ) ),
					);
				}

				if ( class_exists( 'DCIB_Public' ) ) {
					$public_inst    = new DCIB_Public( $this->plugin_name, $this->version );
					$entry['value'] = $public_inst->filter_normalize_value( $entry['value'] );
				}

				$custom_fields[ $normalized_key ] = $entry;
			}
		}

		update_option( 'dcib_phone_number', $phone );
		update_option( 'dcib_email_address', $email );
		update_option( 'dcib_address', $address );

		if ( array_key_exists( 'maps_url', $data ) ) {
			update_option( 'dcib_maps_url', esc_url_raw( self::to_string( $data['maps_url'] ) ) );
		}

		// Bypass pre_update_option filter — import data already sanitized.
		// The filter expects form-format arrays; import passes processed format.
		if ( class_exists( 'DCIB_Admin' ) ) {
			DCIB_Admin::$skip_custom_fields_sanitize = true;
		}
		update_option( 'dcib_custom_fields', $custom_fields );

		// Reset the bypass so later writes in this request stay sanitized when
		// update_option() short-circuits and the filter never runs.
		if ( class_exists( 'DCIB_Admin' ) ) {
			DCIB_Admin::$skip_custom_fields_sanitize = false;
		}

		// Clear the public cache.
		if ( class_exists( 'DCIB_Public' ) ) {
			DCIB_Public::clear_cache();
		}

		add_settings_error( 'dcib_import_messages', 'dcib_import_success', __( 'Settings imported successfully.', 'ra-dynamic-contact-info-blocks' ), 'updated' );
	}

	/**
	 * Coerce a JSON value to a string.
	 *
	 * WordPress sanitizers call strlen()/ltrim()/trim() on their input, which
	 * raises a TypeError for arrays and objects on PHP 8. JSON types are
	 * attacker-controlled, so anything non-scalar collapses to an empty string.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private static function to_string( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Restore the phone number in the serialized {value, tel} format.
	 *
	 * Accepts both the current export shape (object with value/tel) and the
	 * pre-1.3 plain string, so older backups still import.
	 *
	 * @param mixed $value Imported phone number.
	 *
	 * @return string Serialized array, a legacy string, or an empty string.
	 */
	private function import_phone( $value ) {
		$country = DCIB_Phone_Utils::get_default_country();

		if ( is_array( $value ) ) {
			$raw = self::to_string( isset( $value['value'] ) ? $value['value'] : '' );
			$tel = self::to_string( isset( $value['tel'] ) ? $value['tel'] : '' );
		} else {
			$raw = self::to_string( $value );
			$tel = '';
		}

		if ( '' === $raw ) {
			return '';
		}

		if ( ! DCIB_Phone_Utils::is_valid( $raw, $country ) ) {
			// Keep the raw value, matching sanitize_phone_number()'s failure mode.
			return $raw;
		}

		return maybe_serialize(
			array(
				'value' => DCIB_Phone_Utils::format( $raw, $country ),
				'tel'   => ( '' !== $tel && DCIB_Phone_Utils::is_valid( $tel, $country ) ) ? DCIB_Phone_Utils::format_e164( $tel, $country ) : DCIB_Phone_Utils::format_e164( $raw, $country ),
			)
		);
	}

	/**
	 * Display the import/export page.
	 *
	 * @return void
	 */
	public function display_import_export_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ra-dynamic-contact-info-blocks' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Import/Export', 'ra-dynamic-contact-info-blocks' ); ?></h1>
			<?php settings_errors( 'dcib_import_messages' ); ?>

			<div class="dcib-import-export-section">
				<h2><?php echo esc_html__( 'Export Settings', 'ra-dynamic-contact-info-blocks' ); ?></h2>
				<p><?php echo esc_html__( 'Download a backup of your contact information settings.', 'ra-dynamic-contact-info-blocks' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'dcib_export_settings', 'dcib_export_nonce' ); ?>
					<input type="hidden" name="action" value="dcib_export">
					<p>
						<button type="submit" class="button button-primary"><?php echo esc_html__( 'Download Backup', 'ra-dynamic-contact-info-blocks' ); ?></button>
					</p>
				</form>
			</div>

			<div class="dcib-import-export-section">
				<h2><?php echo esc_html__( 'Import Settings', 'ra-dynamic-contact-info-blocks' ); ?></h2>
				<p><?php echo esc_html__( 'Upload a backup file to restore your contact information settings.', 'ra-dynamic-contact-info-blocks' ); ?></p>
				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'dcib_import_settings', 'dcib_import_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Backup File', 'ra-dynamic-contact-info-blocks' ); ?></th>
							<td>
								<input type="file" name="dcib_import_file" accept=".json" required>
								<p class="description"><?php echo esc_html__( 'Select the JSON backup file to import.', 'ra-dynamic-contact-info-blocks' ); ?></p>
							</td>
						</tr>
					</table>
					<p>
						<button type="submit" class="button button-primary"><?php echo esc_html__( 'Import Settings', 'ra-dynamic-contact-info-blocks' ); ?></button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}
}
