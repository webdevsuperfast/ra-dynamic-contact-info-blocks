<?php
/**
 * Uninstall cleanup routine.
 *
 * @package DCIB
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options.
delete_option( 'dcib_phone_number' );
delete_option( 'dcib_email_address' );
delete_option( 'dcib_address' );
delete_option( 'dcib_custom_fields' );
delete_option( 'dcib_default_country' );
delete_option( 'dcib_maps_url' );
// Left behind by versions that shipped a migration routine.
delete_option( 'dcib_version' );
