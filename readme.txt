=== Dynamic Contact Info Blocks ===

Contributors: frodobean
Tags: contact, shortcode, phone, email, address
Requires at least: 5.9.0
Tested up to: 7.1
Stable tag: 1.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Effortlessly manage your business details! Update phone numbers, emails, and addresses in a snap with dynamic fields.

== Description ==

Dynamic Contact Info Blocks is a WordPress plugin that helps you effortlessly manage your business contact details including phone numbers, emails, addresses, and custom fields. It provides an easy way to update and display this information throughout your WordPress site using shortcode-like placeholders.

### Features

- Easy management of contact information through WordPress admin interface
- Support for basic contact fields (phone, email, address)
- Custom fields support for additional information
- Clipboard copy functionality for easy placeholder copying
- Responsive admin interface
- Clean and organized code structure following WordPress Plugin Boilerplate standards
- Elementor integration: Works seamlessly with Elementor headers, footers, and widgets
- Elementor Dynamic Tags: Select DCIB fields directly in Elementor controls (requires Elementor Pro)
- WPForms Smart Tags: Use DCIB fields in WPForms notifications and confirmations (requires WPForms)
- Dynamic preview: Changes are reflected in real-time in Elementor frontend editor
- Phone validation: Validates and formats phone numbers with country-specific rules (uses libphonenumber)
- Global country selector: Set default country for phone number formatting and validation
- Link placeholders: Auto-generated tel:, mailto:, and map link variants for every field
- Maps URL override: Custom Google Maps or Apple Maps URL replaces auto-generated address link
- Email anti-spam: mailto: links encoded to deter harvesters

### Elementor Integration

The plugin works seamlessly with Elementor page builder. Placeholders work in:

- Elementor text widgets
- Elementor headers and footers
- Any Elementor content area

#### Elementor Dynamic Tags

If you have Elementor Pro, you can use Dynamic Tags to select DCIB fields directly in Elementor controls:

1. In any Elementor control that supports dynamic tags (e.g., text fields)
2. Click the dynamic tag icon
3. Look for the "Dynamic Contact Info" group
4. Select "DCIB Contact Info" from the list
5. Choose the field you want to display (Phone, Email, Address, or custom fields)

Changes are reflected in real-time in the Elementor frontend editor.

### WPForms Smart Tags

If you have WPForms installed, you can use DCIB fields in form notifications and confirmations:

1. In WPForms form builder, go to Settings > Notifications
2. In the email message, click "Show Smart Tags"
3. Look for DCIB tags like {dcib_phone}, {dcib_email}, {dcib_address}
4. Custom fields will appear as {dcib_your_custom_key}

These tags will be replaced with the current DCIB field values when the email is sent.

### Usage

#### Basic Fields

Use these placeholders in your posts, pages, or widgets:

- `(PHONE)` - Displays your phone number
- `(PHONE_LINK)` - Outputs a tel: URI for click-to-call links
- `(EMAIL)` - Displays your email address
- `(EMAIL_LINK)` - Outputs a mailto: URI (auto-encoded against spam harvesters)
- `(ADDRESS)` - Displays your address
- `(ADDRESS_LINK)` - Outputs a Google Maps search URL (or custom URL if Maps URL field set)

#### Custom Fields

Create custom fields with unique identifiers in the format `(CUSTOMFIELD)`. The value will be replaced wherever the identifier is used.

Phone, email, and address custom fields automatically get link variants — (CUSTOMFIELD_LINK) for tel:, mailto:, or map URLs.

#### Shortcode

You can also output any field via the shortcode:

- `[dcib key="PHONE"]`
- `[dcib key="EMAIL"]`
- `[dcib key="ADDRESS"]`
- `[dcib key="YOUR_CUSTOM_KEY"]`

== Installation ==

1. Upload the `dynamic-contact-info-blocks` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your contact information under the 'Contact Info' menu in the WordPress admin panel

== Requirements ==

- WordPress 5.9.0 or higher
- PHP 8.1 or higher

== Changelog ==

= 1.0 =

* Initial release
* Implemented contact information management system
* Added support for basic fields (phone, email, address)
* Added custom fields functionality
* Responsive admin interface
* WordPress Plugin Boilerplate architecture

== License ==

This plugin is licensed under the GPL v2 or later.

== Credits ==

Developed by Currier Marketing
