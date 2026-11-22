<?php
/**
 * Phone number storage utilities.
 *
 * @package DCIB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides storage-safe checks for values formatted by libphonenumber-js.
 */
class DCIB_Phone_Utils {

	/**
	 * Check basic shape of a client-formatted phone number.
	 *
	 * @param string $phone   Raw phone input.
	 * @param string $country ISO 3166-1 alpha-2 code.
	 *
	 * @return bool
	 */
	public static function is_valid( $phone, $country = 'US' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$phone = trim( $phone );
		return 1 === preg_match( '/^[+()0-9][0-9\s().+-]{6,24}$/', $phone ) && 7 <= strlen( preg_replace( '/\D/', '', $phone ) );
	}

	/**
	 * Keep client-formatted display value unchanged.
	 *
	 * @param string $phone   Raw phone input.
	 * @param string $country ISO 3166-1 alpha-2 code.
	 *
	 * @return string
	 */
	public static function format( $phone, $country = 'US' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return sanitize_text_field( $phone );
	}

	/**
	 * Normalize client-formatted E.164 value.
	 *
	 * @param string $phone   Raw phone input.
	 * @param string $country ISO 3166-1 alpha-2 code.
	 *
	 * @return string
	 */
	public static function format_e164( $phone, $country = 'US' ) {
		$phone = preg_replace( '/[^0-9+]/', '', sanitize_text_field( $phone ) );
		if ( '' === $phone || '+' === $phone ) {
			return '';
		}
		if ( 0 === strpos( $phone, '+' ) ) {
			return $phone;
		}
		$code = self::get_dialing_code( $country );
		if ( '' === $code ) {
			return $phone;
		}
		// Strip national trunk prefix (e.g. UK 020... -> 20...) before prepending.
		if ( 0 === strpos( $phone, '0' ) ) {
			$phone = substr( $phone, 1 );
		}
		return '+' . $code . $phone;
	}

	/**
	 * Country calling code for E.164 fallback.
	 *
	 * @param string $country ISO 3166-1 alpha-2 code.
	 *
	 * @return string
	 */
	public static function get_dialing_code( $country = 'US' ) {
		$codes = array(
			'US' => '1',
			'CA' => '1',
			'GB' => '44',
			'AU' => '61',
			'DE' => '49',
			'FR' => '33',
			'IT' => '39',
			'ES' => '34',
			'NL' => '31',
			'IE' => '353',
			'NZ' => '64',
			'IN' => '91',
			'BR' => '55',
			'MX' => '52',
		);
		return isset( $codes[ $country ] ) ? $codes[ $country ] : '';
	}

	/**
	 * Get example phone number for a country.
	 *
	 * @param string $country ISO 3166-1 alpha-2 code.
	 *
	 * @return string
	 */
	public static function get_example( $country = 'US' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return '';
	}

	/**
	 * Get default country from settings.
	 *
	 * @return string
	 */
	public static function get_default_country() {
		$country = get_option( 'dcib_default_country', 'US' );
		if ( empty( $country ) ) {
			return 'US';
		}
		return $country;
	}

	/**
	 * Get country list. Prefer WooCommerce if available.
	 *
	 * @return array ISO code => country name.
	 */
	public static function get_countries() {
		if ( function_exists( 'WC' ) && is_a( WC()->countries, 'WC_Countries' ) ) {
			return WC()->countries->get_countries();
		}

		return array(
			'AF' => 'Afghanistan',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BA' => 'Bosnia and Herzegovina',
			'BW' => 'Botswana',
			'BR' => 'Brazil',
			'BN' => 'Brunei',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CR' => 'Costa Rica',
			'CI' => 'Côte d\'Ivoire',
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DO' => 'Dominican Republic',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'SZ' => 'Eswatini',
			'ET' => 'Ethiopia',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GR' => 'Greece',
			'GT' => 'Guatemala',
			'GN' => 'Guinea',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Laos',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'MX' => 'Mexico',
			'MD' => 'Moldova',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'KP' => 'North Korea',
			'MK' => 'North Macedonia',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'QA' => 'Qatar',
			'RO' => 'Romania',
			'RU' => 'Russia',
			'RW' => 'Rwanda',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'KR' => 'South Korea',
			'SS' => 'South Sudan',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syria',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TH' => 'Thailand',
			'TL' => 'Timor-Leste',
			'TG' => 'Togo',
			'TT' => 'Trinidad and Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VE' => 'Venezuela',
			'VN' => 'Vietnam',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);
	}
}
