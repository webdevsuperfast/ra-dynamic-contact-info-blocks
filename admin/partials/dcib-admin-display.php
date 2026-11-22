<?php
/**
 * Admin settings page markup.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$custom_fields = get_option( 'dcib_custom_fields', array() );
if ( is_string( $custom_fields ) ) {
	$custom_fields = maybe_unserialize( $custom_fields );
}
if ( ! is_array( $custom_fields ) ) {
	$custom_fields = array();
}

// Normalize legacy string values to typed format.
$normalized = array();
foreach ( $custom_fields as $key => $data ) {
	if ( is_array( $data ) && isset( $data['type'], $data['value'] ) ) {
		$normalized[ $key ] = $data;
	} elseif ( is_string( $data ) ) {
		$normalized[ $key ] = array(
			'type'  => 'custom',
			'value' => $data,
		);
	}
}
$custom_fields = $normalized;
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
			<?php foreach ( $custom_fields as $key => $entry ) : ?>
				<?php
				$field_type  = isset( $entry['type'] ) ? $entry['type'] : 'custom';
				$field_value = isset( $entry['value'] ) ? $entry['value'] : '';
				$key_clean   = trim( $key, ')' );
				?>
				<div class="dcib-custom-field">
					<input type="text" class="regular-text custom-key" name="dcib_custom_fields[key][]"
							value="<?php echo esc_attr( $key ); ?>"
							aria-label="<?php esc_attr_e( 'Field key, e.g. (STORE_HOURS)', 'dynamic-contact-info-blocks' ); ?>"
							data-enforce-parens="true" required />
					<select class="custom-type" name="dcib_custom_fields[type][]" aria-label="<?php esc_attr_e( 'Field type', 'dynamic-contact-info-blocks' ); ?>">
						<option value="phone"   <?php selected( $field_type, 'phone' ); ?>><?php echo esc_html__( 'Phone', 'dynamic-contact-info-blocks' ); ?></option>
						<option value="email"   <?php selected( $field_type, 'email' ); ?>><?php echo esc_html__( 'Email', 'dynamic-contact-info-blocks' ); ?></option>
						<option value="address" <?php selected( $field_type, 'address' ); ?>><?php echo esc_html__( 'Address', 'dynamic-contact-info-blocks' ); ?></option>
						<option value="custom"  <?php selected( $field_type, 'custom' ); ?>><?php echo esc_html__( 'Custom', 'dynamic-contact-info-blocks' ); ?></option>
					</select>
					<?php if ( 'address' === $field_type ) : ?>
						<textarea class="regular-text custom-value" name="dcib_custom_fields[value][]" rows="3" required aria-label="<?php esc_attr_e( 'Field value', 'dynamic-contact-info-blocks' ); ?>" data-type="<?php echo esc_attr( $field_type ); ?>"><?php echo esc_textarea( $field_value ); ?></textarea>
					<?php else : ?>
						<?php
						$input_type = 'custom' === $field_type ? 'text' : $field_type;
						?>
						<input type="<?php echo esc_attr( $input_type ); ?>" class="regular-text custom-value" name="dcib_custom_fields[value][]"
								value="<?php echo esc_attr( $field_value ); ?>" required aria-label="<?php esc_attr_e( 'Field value', 'dynamic-contact-info-blocks' ); ?>" data-type="<?php echo esc_attr( $field_type ); ?>" />
					<?php endif; ?>
			<div class="dcib-dropdown dcib-row-actions">
				<button type="button" class="button dcib-dropdown-toggle" aria-haspopup="true" aria-expanded="false"><?php echo esc_html__( 'Actions ▾', 'dynamic-contact-info-blocks' ); ?></button>
				<div class="dcib-dropdown-menu" role="menu">
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-action="copy-key"><?php echo esc_html( $key ); ?></div>
					<div class="dcib-dropdown-item" role="menuitem" tabindex="-1" data-action="copy-link" style="<?php echo ( 'phone' !== $field_type && 'email' !== $field_type && 'address' !== $field_type ) ? 'display:none' : ''; ?>"><?php echo esc_html( $key_clean . '_LINK)' ); ?></div>
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
