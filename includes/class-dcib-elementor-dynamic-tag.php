<?php
/**
 * Elementor dynamic tag integration.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
	return;
}

/**
 * DCIB Dynamic Tag for Elementor.
 *
 * Allows selecting and displaying DCIB contact info fields in Elementor controls.
 */
class DCIB_Elementor_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {

	/**
	 * Get the dynamic tag identifier.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'dcib-contact-info';
	}

	/**
	 * Get the dynamic tag title.
	 *
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'DCIB Contact Info', 'ra-dynamic-contact-info-blocks' );
	}

	/**
	 * Get the dynamic tag group.
	 *
	 * @return string
	 */
	public function get_group() {
		return 'dcib';
	}

	/**
	 * Get the dynamic tag categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array(
			'text',
			'url',
		);
	}

	/**
	 * Register Elementor controls for this tag.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->add_control(
			'dcib_field',
			array(
				'label'   => esc_html__( 'Field', 'ra-dynamic-contact-info-blocks' ),
				'type'    => 'select',
				'options' => $this->get_field_options(),
				'default' => 'PHONE',
			)
		);
	}

	/**
	 * Render the selected field value.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! class_exists( 'DCIB_Public' ) ) {
			return;
		}

		$field = $this->get_settings( 'dcib_field' );
		if ( ! $field ) {
			return;
		}

		// Normalize to (FIELD).
		$placeholder = '(' . strtoupper( $field ) . ')';

		// Get the map and output the value.
		$public = new DCIB_Public( 'dcib', defined( 'DCIB_VERSION' ) ? DCIB_VERSION : '1.0' );
		$map    = $public->get_map();
		if ( isset( $map[ $placeholder ] ) ) {
			echo esc_html( $map[ $placeholder ] );
		}
	}

	/**
	 * Build selectable field options.
	 *
	 * @return array
	 */
	private function get_field_options() {
		$options = array(
			'PHONE'   => esc_html__( 'Phone', 'ra-dynamic-contact-info-blocks' ),
			'EMAIL'   => esc_html__( 'Email', 'ra-dynamic-contact-info-blocks' ),
			'ADDRESS' => esc_html__( 'Address', 'ra-dynamic-contact-info-blocks' ),
		);

		// Check for phone link variant.
		$phone_raw = get_option( 'dcib_phone_number', '' );
		if ( is_string( $phone_raw ) ) {
			$phone_parsed = maybe_unserialize( $phone_raw );
			if ( is_array( $phone_parsed ) ) {
				$phone_raw = $phone_parsed;
			}
		}
		if ( is_array( $phone_raw ) && ! empty( $phone_raw['tel'] ) ) {
			$options['PHONE_LINK'] = esc_html__( 'Phone (tel: link)', 'ra-dynamic-contact-info-blocks' );
		}

		// Email link always available.
		$options['EMAIL_LINK'] = esc_html__( 'Email (mailto: link)', 'ra-dynamic-contact-info-blocks' );

		// Address link always available.
		$options['ADDRESS_LINK'] = esc_html__( 'Address (map link)', 'ra-dynamic-contact-info-blocks' );

		// Add custom fields.
		if ( function_exists( 'get_option' ) ) {
			$custom_fields = get_option( 'dcib_custom_fields', array() );
			if ( is_string( $custom_fields ) ) {
				$custom_fields = maybe_unserialize( $custom_fields );
			}
			if ( is_array( $custom_fields ) ) {
				foreach ( $custom_fields as $key => $value ) {
					$clean_key = trim( $key, '()' );
					$label     = $clean_key;

					if ( is_array( $value ) ) {
						if ( isset( $value['type'] ) && 'phone' === $value['type'] && ! empty( $value['value'] ) ) {
							$options[ $clean_key ]           = esc_html( $label );
							$options[ $clean_key . '_LINK' ] = esc_html( $label . ' (tel: link)' );
						} elseif ( isset( $value['type'] ) && 'email' === $value['type'] && ! empty( $value['value'] ) ) {
							$options[ $clean_key ]           = esc_html( $label );
							$options[ $clean_key . '_LINK' ] = esc_html( $label . ' (mailto: link)' );
						} elseif ( isset( $value['type'] ) && 'address' === $value['type'] && ! empty( $value['value'] ) ) {
							$options[ $clean_key ]           = esc_html( $label );
							$options[ $clean_key . '_LINK' ] = esc_html( $label . ' (map link)' );
						} else {
							$options[ $clean_key ] = esc_html( $label );
						}
					} else {
						$options[ $clean_key ] = esc_html( $label );
					}
				}
			}
		}

		return $options;
	}
}
