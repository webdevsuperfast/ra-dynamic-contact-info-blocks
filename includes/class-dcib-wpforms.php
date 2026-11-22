<?php
/**
 * WPForms integration for dynamic contact placeholders.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DCIB WPForms Integration
 *
 * Handles WPForms smart tags integration for DCIB plugin.
 */
class DCIB_WPForms {

	/**
	 * Whether WPForms filters have been registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Check if WPForms is active.
	 *
	 * @return bool
	 */
	public static function is_wpforms_active() {
		return class_exists( 'WPForms' );
	}

	/**
	 * Register WPForms Smart Tags on init.
	 */
	public static function register_wpforms_smart_tags_init() {
		if ( self::$registered ) {
			return;
		}

		if ( ! self::is_wpforms_active() ) {
			return;
		}

		// Register the smart tags and processor. WPForms may call the processor
		// with 2 or 3 parameters depending on version, so accept 3 and ignore extras.
		add_filter( 'wpforms_smart_tags', array( __CLASS__, 'register_wpforms_smart_tags' ) );
		add_filter( 'wpforms_smart_tag_process', array( __CLASS__, 'process_wpforms_smart_tag' ), 10, 3 );

		self::$registered = true;
	}

	/**
	 * Register WPForms Smart Tags.
	 *
	 * @param array $tags Registered smart tags.
	 *
	 * @return array
	 */
	public static function register_wpforms_smart_tags( $tags ) {
		$tags['dcib_phone']        = 'DCIB Phone';
		$tags['dcib_email']        = 'DCIB Email';
		$tags['dcib_address']      = 'DCIB Address';
		$tags['dcib_address_link'] = 'DCIB Address (map link)';
		$tags['dcib_email_link']   = 'DCIB Email (mailto: link)';

		// Check for phone link variant.
		$phone_raw = get_option( 'dcib_phone_number', '' );
		if ( is_string( $phone_raw ) ) {
			$phone_parsed = maybe_unserialize( $phone_raw );
			if ( is_array( $phone_parsed ) ) {
				$phone_raw = $phone_parsed;
			}
		}
		if ( is_array( $phone_raw ) && ! empty( $phone_raw['tel'] ) ) {
			$tags['dcib_phone_link'] = 'DCIB Phone (tel: link)';
		}

		// Add custom fields.
		if ( function_exists( 'get_option' ) ) {
			$custom_fields = get_option( 'dcib_custom_fields', array() );
			if ( is_array( $custom_fields ) ) {
				foreach ( $custom_fields as $key => $value ) {
					if ( ! is_array( $value ) ) {
						continue;
					}
					$clean_key = strtolower( trim( $key, '()' ) );
					$label     = 'DCIB ' . ucwords( str_replace( '_', ' ', $clean_key ) );

					if ( isset( $value['tel'] ) && ! empty( $value['tel'] ) ) {
						$tags[ 'dcib_' . $clean_key ]           = $label;
						$tags[ 'dcib_' . $clean_key . '_link' ] = $label . ' (tel: link)';
					} elseif ( isset( $value['type'] ) && 'email' === $value['type'] && ! empty( $value['value'] ) ) {
						$tags[ 'dcib_' . $clean_key ]           = $label;
						$tags[ 'dcib_' . $clean_key . '_link' ] = $label . ' (mailto: link)';
					} elseif ( isset( $value['type'] ) && 'address' === $value['type'] && ! empty( $value['value'] ) ) {
						$tags[ 'dcib_' . $clean_key ]           = $label;
						$tags[ 'dcib_' . $clean_key . '_link' ] = $label . ' (map link)';
					} else {
						$tags[ 'dcib_' . $clean_key ] = $label;
					}
				}
			}
		}

		return $tags;
	}

	/**
	 * Process WPForms Smart Tags.
	 *
	 * @param string     $content   Content being processed.
	 * @param string     $tag       Current smart-tag key.
	 * @param array|null $form_data Current form data.
	 *
	 * @return string
	 */
	public static function process_wpforms_smart_tag( $content, $tag, $form_data = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! class_exists( 'DCIB_Public' ) ) {
			return $content;
		}

		$dcib_tags = array(
			'dcib_phone'        => 'PHONE',
			'dcib_email'        => 'EMAIL',
			'dcib_address'      => 'ADDRESS',
			'dcib_address_link' => 'ADDRESS_LINK',
			'dcib_email_link'   => 'EMAIL_LINK',
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
			$dcib_tags['dcib_phone_link'] = 'PHONE_LINK';
		}

		// Add custom fields mapping.
		if ( function_exists( 'get_option' ) ) {
			$custom_fields = get_option( 'dcib_custom_fields', array() );
			if ( is_array( $custom_fields ) ) {
				foreach ( $custom_fields as $key => $value ) {
					if ( ! is_array( $value ) ) {
						continue;
					}
					$clean_key = strtolower( trim( $key, '()' ) );

					if ( isset( $value['tel'] ) && ! empty( $value['tel'] ) ) {
						$dcib_tags[ 'dcib_' . $clean_key ]           = trim( $key, '()' );
						$dcib_tags[ 'dcib_' . $clean_key . '_link' ] = trim( $key, '()' ) . '_LINK';
					} elseif ( isset( $value['type'] ) && 'email' === $value['type'] && ! empty( $value['value'] ) ) {
						$dcib_tags[ 'dcib_' . $clean_key ]           = trim( $key, '()' );
						$dcib_tags[ 'dcib_' . $clean_key . '_link' ] = trim( $key, '()' ) . '_LINK';
					} elseif ( isset( $value['type'] ) && 'address' === $value['type'] && ! empty( $value['value'] ) ) {
						$dcib_tags[ 'dcib_' . $clean_key ]           = trim( $key, '()' );
						$dcib_tags[ 'dcib_' . $clean_key . '_link' ] = trim( $key, '()' ) . '_LINK';
					} else {
						$dcib_tags[ 'dcib_' . $clean_key ] = trim( $key, '()' );
					}
				}
			}
		}

		if ( isset( $dcib_tags[ $tag ] ) ) {
			$placeholder = '(' . $dcib_tags[ $tag ] . ')';
			$public      = new DCIB_Public( 'dcib', defined( 'DCIB_VERSION' ) ? DCIB_VERSION : '1.0' );
			$map         = $public->get_map();
			if ( isset( $map[ $placeholder ] ) ) {
				$value = $map[ $placeholder ];
				if ( false !== strpos( $tag, '_link' ) ) {
					$value = esc_url( $value );
				} else {
					$value = esc_html( $value );
				}
				$content = str_replace( '{' . $tag . '}', $value, $content );
			}
		}

		return $content;
	}
}
