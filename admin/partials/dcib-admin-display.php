<?php
/**
 * Admin settings page markup.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dcib_custom_fields = get_option( 'dcib_custom_fields', array() );
if ( is_string( $dcib_custom_fields ) ) {
	$dcib_custom_fields = maybe_unserialize( $dcib_custom_fields );
}
if ( ! is_array( $dcib_custom_fields ) ) {
	$dcib_custom_fields = array();
}

// Normalize legacy string values to typed format.
$dcib_normalized = array();
foreach ( $dcib_custom_fields as $dcib_key => $dcib_data ) {
	if ( is_array( $dcib_data ) && isset( $dcib_data['type'], $dcib_data['value'] ) ) {
		$dcib_normalized[ $dcib_key ] = $dcib_data;
	} elseif ( is_string( $dcib_data ) ) {
		$dcib_normalized[ $dcib_key ] = array(
			'type'  => 'custom',
			'value' => $dcib_data,
		);
	}
}
$dcib_custom_fields = $dcib_normalized;
?>

<div class="wrap">
	<?php settings_errors( 'dcib_messages' ); ?>
	<h1 class="wp-heading-inline"><?php echo esc_html__( 'Dynamic Contact Info Blocks Settings', 'dynamic-contact-info-blocks' ); ?></h1>
	<hr class="wp-header-end">
	<form method="post" id="dcib-form" action="options.php">
		<?php
		settings_fields( 'dcib_settings_group' );
		do_settings_sections( 'dcib-settings' );
		?>

	<h2 class="title"><?php echo esc_html__( 'Custom Fields', 'dynamic-contact-info-blocks' ); ?></h2>
		<div id="custom-fields" class="custom-fields-wrapper">
			<?php foreach ( $dcib_custom_fields as $dcib_key => $dcib_entry ) : ?>
				<?php
				$dcib_field_type  = isset( $dcib_entry['type'] ) ? $dcib_entry['type'] : 'custom';
				$dcib_field_value = isset( $dcib_entry['value'] ) ? $dcib_entry['value'] : '';
				$dcib_key_clean   = trim( $dcib_key, ')' );
				?>
				<div class="dcib-custom-field">
					<input type="text" class="regular-text custom-key" name="dcib_custom_fields[key][]"
							value="<?php echo esc_attr( $dcib_key ); ?>"
							aria-label="<?php esc_attr_e( 'Field key, e.g. (STORE_HOURS)', 'dynamic-contact-info-blocks' ); ?>"
							data-enforce-parens="true" required />
					<select class="custom-type" name="dcib_custom_fields[type][]" aria-label="<?php esc_attr_e( 'Field type', 'dynamic-contact-info-blocks' ); ?>">
						<option value="phone"   <?php selected( $dcib_field_type, 'phone' ); ?>><?php echo esc_html__( 'Phone', 'dynamic-contact-info-blocks' ); ?></option>
						<option value="email"   <?php selected( $dcib_field_type, 'email' ); ?>><?php echo esc_html__( 'Email', 'dynamic-contact-info-blocks' ); ?></option>
						<option value="address" <?php selected( $dcib_field_type, 'address' ); ?>><?php echo esc_html__( 'Address', 'dynamic-contact-info-blocks' ); ?></option>
						<option value="custom"  <?php selected( $dcib_field_type, 'custom' ); ?>><?php echo esc_html__( 'Custom', 'dynamic-contact-info-blocks' ); ?></option>
					</select>
					<?php if ( 'address' === $dcib_field_type ) : ?>
						<textarea class="regular-text custom-value" name="dcib_custom_fields[value][]" rows="3" required aria-label="<?php esc_attr_e( 'Field value', 'dynamic-contact-info-blocks' ); ?>" data-type="<?php echo esc_attr( $dcib_field_type ); ?>"><?php echo esc_textarea( $dcib_field_value ); ?></textarea>
					<?php else : ?>
						<?php
						$dcib_input_type = 'custom' === $dcib_field_type ? 'text' : $dcib_field_type;
						?>
						<input type="<?php echo esc_attr( $dcib_input_type ); ?>" class="regular-text custom-value" name="dcib_custom_fields[value][]"
								value="<?php echo esc_attr( $dcib_field_value ); ?>" required aria-label="<?php esc_attr_e( 'Field value', 'dynamic-contact-info-blocks' ); ?>" data-type="<?php echo esc_attr( $dcib_field_type ); ?>" />
					<?php endif; ?>
			<div class="dcib-dropdown dcib-row-actions">
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php echo esc_html__( 'Actions ▾', 'dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-action="copy-key"><?php echo esc_html( $dcib_key ); ?></div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-action="copy-link" style="<?php echo ( 'phone' !== $dcib_field_type && 'email' !== $dcib_field_type && 'address' !== $dcib_field_type ) ? 'display:none' : ''; ?>"><?php echo esc_html( $dcib_key_clean . '_LINK)' ); ?></div>
					<div class="dcib-dropdown-separator"></div>
					<div class="dcib-dropdown-item danger" role="menuitem" tabindex="-1" data-action="remove"><?php echo esc_html__( 'Remove', 'dynamic-contact-info-blocks' ); ?></div>
				</div>
			</div>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="submit">
		<button type="button" class="button" data-action="add-field"><?php echo esc_html__( 'Add Custom Field', 'dynamic-contact-info-blocks' ); ?></button>
		<?php submit_button( '', 'primary', 'submit', false ); ?>
		</p>
	</form>
</div>
