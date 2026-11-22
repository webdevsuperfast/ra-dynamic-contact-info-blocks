<?php
/**
 * Public-facing placeholder replacement logic.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DCIB_Public class.
 * Handles all public-facing functionality for the plugin, including placeholder replacement and shortcodes.
 */
class DCIB_Public {
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
	 * Cached placeholder map.
	 *
	 * @var array|null
	 */
	private static $cached_map = null;

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
	 * Clear cached placeholder map.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		self::$cached_map = null;
	}

	/**
	 * Build the replacement map from options.
	 *
	 * @return array
	 */
	private function get_placeholder_map() {
		if ( is_array( self::$cached_map ) ) {
			return self::$cached_map;
		}
		$phone_raw     = get_option( 'dcib_phone_number', '(123) 456-7890' );
		$email_address = get_option( 'dcib_email_address', 'example@example.com' );
		$address       = get_option( 'dcib_address', '123 Main St, City, Country' );
		$custom_fields = get_option( 'dcib_custom_fields', array() );

		if ( is_string( $phone_raw ) ) {
			$phone_parsed = maybe_unserialize( $phone_raw );
			if ( is_array( $phone_parsed ) ) {
				$phone_raw = $phone_parsed;
			}
		}

		if ( is_string( $custom_fields ) ) {
			$custom_fields = maybe_unserialize( $custom_fields );
		}

		$phone_value = is_array( $phone_raw ) && isset( $phone_raw['value'] ) ? $phone_raw['value'] : $phone_raw;
		$phone_tel   = is_array( $phone_raw ) && isset( $phone_raw['tel'] ) ? $phone_raw['tel'] : '';

		$map = array(
			'(PHONE)'   => $phone_value,
			'(EMAIL)'   => $email_address,
			'(ADDRESS)' => $address,
		);

		if ( '' !== $phone_tel ) {
			$map['(PHONE_LINK)'] = 'tel:' . $phone_tel;
		}

		if ( '' !== $email_address ) {
			$map['(EMAIL_LINK)'] = 'mailto:' . $email_address;
		}

		if ( '' !== $address ) {
			$maps_url = get_option( 'dcib_maps_url', '' );
			if ( '' !== $maps_url ) {
				$map['(ADDRESS_LINK)'] = $maps_url;
			} else {
				$map['(ADDRESS_LINK)'] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address );
			}
		}

		if ( is_array( $custom_fields ) ) {
			foreach ( $custom_fields as $code => $value ) {
				if ( is_array( $value ) && isset( $value['value'] ) ) {
					$display = $value['value'];
					$tel     = isset( $value['tel'] ) ? $value['tel'] : '';
					$type    = isset( $value['type'] ) ? $value['type'] : '';
					if ( 'phone' === $type && class_exists( 'DCIB_Phone_Utils' ) ) {
						$tel = DCIB_Phone_Utils::format_e164( $tel, DCIB_Phone_Utils::get_default_country() );
					}

					if ( is_string( $display ) && is_string( $code ) && preg_match( '/^\([A-Z0-9_]+\)$/', $code ) ) {
						$map[ $code ] = $display;
					}

					if ( '' !== $tel ) {
						$link_key = substr( $code, 0, -1 ) . '_LINK)';
						if ( preg_match( '/^\([A-Z0-9_]+\)$/', $link_key ) ) {
							$map[ $link_key ] = 'tel:' . $tel;
						}
					}

					if ( 'email' === $type && '' !== $display ) {
						$link_key = substr( $code, 0, -1 ) . '_LINK)';
						if ( preg_match( '/^\([A-Z0-9_]+\)$/', $link_key ) ) {
							$map[ $link_key ] = 'mailto:' . $display;
						}
					}

					if ( 'address' === $type && '' !== $display ) {
						$link_key = substr( $code, 0, -1 ) . '_LINK)';
						if ( preg_match( '/^\([A-Z0-9_]+\)$/', $link_key ) ) {
							$custom_maps_url = get_option( 'dcib_maps_url', '' );
							if ( '' !== $custom_maps_url ) {
								$map[ $link_key ] = $custom_maps_url;
							} else {
								$map[ $link_key ] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $display );
							}
						}
					}
				} elseif ( is_string( $code ) && is_string( $value ) && preg_match( '/^\([A-Z0-9_]+\)$/', $code ) ) {
						$map[ $code ] = $value;
				}
			}
		}

		// Normalize URL-like values to avoid malformed outputs like "http://https//example.com".
		$map = $this->normalize_placeholder_map( $map );

		/**
		 * Filter the DCIB replacement map.
		 *
		 * @param array $map Associative array of placeholder => value.
		 */
		self::$cached_map = apply_filters( 'dcib_placeholder_map', $map );
		return self::$cached_map;
	}

	/**
	 * Normalize URL-like values inside the placeholder map.
	 * - Fix missing colon after scheme (https// -> https://)
	 * - Remove duplicate/stacked schemes (http://https:// -> https://)
	 * - Add https:// if value looks like a bare domain
	 *
	 * Only attempts normalization for strings that look URL-ish to avoid
	 * altering regular text, phone numbers, or emails.
	 *
	 * @param array $map Associative array of placeholder => value.
	 * @return array
	 */
	private function normalize_placeholder_map( $map ) {
		foreach ( $map as $k => $v ) {
			if ( ! is_string( $v ) ) {
				continue;
			}
			$normalized = $this->maybe_normalize_url( $v );
			if ( null !== $normalized ) {
				$map[ $k ] = $normalized;
			}
		}
		return $map;
	}

	/**
	 * If the given value appears to be a URL, try to normalize it and return a sanitized URL string.
	 * Returns null if the value does not appear to be a URL (so callers can keep the original).
	 *
	 * @param string $value Input value to check and normalize if URL-like.
	 * @return string|null
	 */
	private function maybe_normalize_url( $value ) {
		$orig = trim( $value );
		if ( '' === $orig ) {
			return null;
		}
		if ( preg_match( '#^(mailto|tel):#i', $orig ) ) {
			return $orig;
		}
		// Strip scheme Elementor/WP prefixes onto scheme-less placeholders
		// (e.g. esc_url( '(PHONE_LINK)' ) => http://(PHONE_LINK), which then
		// resolves to http://tel:+... after placeholder replacement).
		if ( preg_match( '#^https?://(tel|mailto):#i', $orig ) ) {
			return preg_replace( '#^https?://#i', '', $orig );
		}

		// Quick heuristic: Starts with http/https, or www., or looks like a bare domain.
		$looks_url = preg_match( '#^(https?:)?//#i', $orig )
			|| preg_match( '#^https?[:/]#i', $orig )
			|| preg_match( '#^www\.#i', $orig )
			|| preg_match( '#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $orig );

		if ( ! $looks_url ) {
			return null; // Don't touch non-URL-ish values.
		}

		$v = $orig;

		// Fix missing colon after scheme (e.g., https//example.com or http//example.com).
		$v = preg_replace( '#^https//#i', 'https://', $v );
		$v = preg_replace( '#^http//#i', 'http://', $v );

		// Remove stacked/duplicate schemes.
		// http://https://example.com -> https://example.com.
		$v = preg_replace( '#^http://https://#i', 'https://', $v );
		// https://http://example.com -> http://example.com.
		$v = preg_replace( '#^https://http://#i', 'http://', $v );
		// Generic collapse of two schemes e.g. http://http://example.com -> http://example.com.
		$v = preg_replace( '#^(https?://)(https?://)#i', '$2', $v );

		// If no scheme provided but looks like a domain (or starts with // or www.), default to https://.
		if ( ! preg_match( '#^https?://#i', $v ) ) {
			if ( preg_match( '#^//#', $v ) ) {
				$v = 'https:' . $v; // Protocol-relative -> https by default.
			} elseif ( preg_match( '#^www\.#i', $v ) || preg_match( '#^[a-z0-9.-]+\.[a-z]{2,}(/|$)#i', $v ) ) {
				$v = 'https://' . $v;
			}
		}

		// Final sanitation/validation.
		$san = esc_url_raw( $v );
		if ( $san && function_exists( 'wp_http_validate_url' ) ) {
			if ( wp_http_validate_url( $san ) ) {
				return $san;
			}
		}

		// If sanitized string is empty but original looked like URL, return best-effort $v.
		// This is still better than malformed values with a missing colon.
		if ( ! empty( $san ) ) {
			return $san;
		}

		return $v;
	}

	/**
	 * Encode email address for mailto links (anti-spam).
	 * Converts @ to &#64; and . to &#46; inside mailto URIs.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	public static function encode_email( $email ) {
		return str_replace(
			array( '@', '.' ),
			array( '&#64;', '&#46;' ),
			$email
		);
	}

	/**
	 * Encode email addresses inside mailto: href attributes in HTML.
	 * Runs after esc_html to avoid double-encoding.
	 *
	 * @param string $html HTML content.
	 * @return string
	 */
	private function encode_mailto_links( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		return preg_replace_callback(
			'/\bhref\s*=\s*(["\'])\s*mailto:([^"\']+)\1/i',
			function ( $m ) {
				$encoded = self::encode_email( $m[2] );
				return 'href=' . $m[1] . 'mailto:' . $encoded . $m[1];
			},
			$html
		);
	}

	/**
	 * Filter callback to normalize a single value. If the value is URL-like, returns normalized URL; otherwise returns original.
	 *
	 * @param mixed $value Input value to normalize if it's a URL.
	 * @return mixed
	 */
	public function filter_normalize_value( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}
		$norm = $this->maybe_normalize_url( $value );

		if ( null !== $norm ) {
			return $norm;
		}

		return $value;
	}

	/**
	 * Replace placeholders with mapped values in content.
	 *
	 * @param string $content Content to process.
	 *
	 * @return string
	 */
	public function replace_contact_info( $content ) {
		$map = $this->get_placeholder_map();

		if ( empty( $map ) || ! is_string( $content ) ) {
			return $content;
		}

		// Use strtr for efficient replacements.
		// Escape as plain text to prevent HTML injection while preserving URL punctuation.
		$safe_map = array();
		foreach ( $map as $placeholder => $value ) {
			// esc_html keeps characters like ':' and '/' intact.
			$safe_map[ $placeholder ] = esc_html( $value );
		}

		$content = strtr( $content, $safe_map );

		// After replacements, fix href/src attributes that may have been auto-prefixed by builders.
		// This mitigates sequences like http://https//example.com.
		$content = $this->normalize_links_in_html( $content );

		// Anti-spam: encode email addresses inside mailto: hrefs.
		$content = $this->encode_mailto_links( $content );

		return $content;
	}

	/**
	 * Shortcode callback for fetching one key.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'key' => '',
			),
			$atts,
			'dcib'
		);

		$key = trim( $atts['key'] );
		if ( '' === $key ) {
			return '';
		}

		// Normalize to (KEY).
		if ( '(' !== $key[0] ) {
			$key = '(' . $key . ')';
		}
		if ( ')' !== substr( $key, -1 ) ) {
			$key .= ')';
		}
		$key = strtoupper( $key );

		$map = $this->get_placeholder_map();
		if ( isset( $map[ $key ] ) ) {
			$value = $map[ $key ];

			// Escape as text so URLs remain intact (no stripping of ':').
			return esc_html( $value );
		}

		return '';
	}

	/**
	 * Debug shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function debug_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'key' => '',
			),
			$atts,
			'dcib_debug'
		);

		$key = trim( $atts['key'] );
		if ( '' === $key ) {
			return '';
		}

		// Normalize to (KEY).
		if ( '(' !== $key[0] ) {
			$key = '(' . $key . ')';
		}
		if ( ')' !== substr( $key, -1 ) ) {
			$key .= ')';
		}
		$key = strtoupper( $key );

		$map = $this->get_placeholder_map();
		if ( isset( $map[ $key ] ) ) {
			$value = $map[ $key ];
			// Return raw value for debugging.
			return '<pre>Raw value: ' . esc_html( $value ) . '</pre><br>Sanitized: ' . esc_html( sanitize_text_field( $value ) );
		}

		return 'Key not found: ' . esc_html( $key );
	}

	/**
	 * Lightweight self-check for URL normalization, runs only when WP_DEBUG is true.
	 * Hooks a one-time filter 'dcib_run_self_test' which returns an array of test results.
	 *
	 * @return void
	 */
	public static function register_self_test_filter() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		add_filter(
			'dcib_run_self_test',
			function ( $results = array() ) {
				$inst  = new self( 'dcib', defined( 'DCIB_VERSION' ) ? DCIB_VERSION : '1.0' );
				$cases = array(
					'https_missing_colon' => 'https//google.com',
					'http_missing_colon'  => 'http//example.com',
					'double_schemes_a'    => 'http://https://example.com',
					'double_schemes_b'    => 'https://http://example.com',
					'bare_domain'         => 'google.com',
					'www_prefix'          => 'www.example.com/path',
					'already_ok_https'    => 'https://valid.com',
					'already_ok_http'     => 'http://valid.com',
					'not_url_text'        => 'Call us at (555) 123-4567',
				);
				foreach ( $cases as $label => $input ) {
					$norm              = $inst->maybe_normalize_url( $input );
					$results[ $label ] = array(
						'input'  => $input,
						'output' => $norm,
					);
				}
				return $results;
			}
		);
	}

	/**
	 * Normalize URLs found inside href/src attributes within an HTML string.
	 * This mitigates cases where a page builder auto-adds `http://` and our
	 * placeholder injects an `https//` (missing colon) which would combine into
	 * `http://https//...`.
	 *
	 * @param string $html Input HTML content to scan and normalize links within.
	 * @return string
	 */
	private function normalize_links_in_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		$cb = function ( $m ) {
			$attr       = $m[1];
			$quote      = $m[2];
			$url        = $m[3];
			$normalized = $this->maybe_normalize_url( $url );
			if ( null === $normalized ) {
				// Not a URL we touch.
				return $m[0];
			}
			// Escape as a URL for attribute context.
			$safe = esc_url( $normalized );
			return $attr . '=' . $quote . $safe . $quote;
		};

		// Match href/src attributes with single or double quotes.
		$pattern = '/\b(href|src)\s*=\s*(["\'])(.*?)\2/si';
		return preg_replace_callback( $pattern, $cb, $html );
	}

	/**
	 * Get the placeholder map (public accessor).
	 *
	 * @return array
	 */
	public function get_map() {
		return $this->get_placeholder_map();
	}
}
