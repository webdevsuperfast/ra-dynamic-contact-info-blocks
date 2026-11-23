# Dynamic Contact Info Blocks

Dynamic Contact Info Blocks is a WordPress plugin that helps you effortlessly manage your business contact details including phone numbers, emails, addresses, and custom fields. It provides an easy way to update and display this information throughout your WordPress site using shortcode-like placeholders.

## Features

- Easy management of contact information through WordPress admin interface
- Support for basic contact fields (phone, email, address)
- Custom fields support for additional information
- Clipboard copy functionality for easy placeholder copying
- Responsive admin interface
- Clean and organized code structure following WordPress Plugin Boilerplate standards
- **Elementor integration**: Works seamlessly with Elementor headers, footers, and widgets
- **Elementor Dynamic Tags**: Select DCIB fields directly in Elementor controls (requires Elementor Pro)
- **WPForms Smart Tags**: Use DCIB fields in WPForms notifications and confirmations (requires WPForms)
- **Phone validation**: Validates and formats phone numbers with country-specific rules (uses libphonenumber)
- **Global country selector**: Set default country for phone number formatting and validation
- **Link placeholders**: Auto-generated `tel:`, `mailto:`, and map link variants for every field
- **Maps URL override**: Custom Google Maps or Apple Maps URL replaces auto-generated address link
- **Email anti-spam**: `mailto:` links encoded to deter harvesters
- **Dynamic preview**: Changes are reflected in real-time in Elementor frontend editor

## Usage

### Basic Fields

Use these placeholders in your posts, pages, or widgets:

- `(PHONE)` - Displays your phone number
- `(PHONE_LINK)` - Outputs a `tel:` URI for click-to-call links
- `(EMAIL)` - Displays your email address
- `(EMAIL_LINK)` - Outputs a `mailto:` URI (auto-encoded against spam harvesters)
- `(ADDRESS)` - Displays your address
- `(ADDRESS_LINK)` - Outputs a Google Maps search URL (or custom URL if Maps URL field set)

### Custom Fields

Create custom fields with unique identifiers in the format `(CUSTOMFIELD)`. The value will be replaced wherever the identifier is used.

Phone, email, and address custom fields automatically get link variants — `(CUSTOMFIELD_LINK)` for `tel:`, `mailto:`, or map URLs.

### Shortcode

You can also output any field via the shortcode:

- `[dcib key="PHONE"]`
- `[dcib key="EMAIL"]`
- `[dcib key="ADDRESS"]`
- `[dcib key="YOUR_CUSTOM_KEY"]`

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

#### WPForms Smart Tags

If you have WPForms installed, you can use DCIB fields in form notifications and confirmations:

1. In WPForms form builder, go to Settings > Notifications
2. In the email message, click "Show Smart Tags"
3. Look for DCIB tags like {dcib_phone}, {dcib_email}, {dcib_address}
4. Custom fields will appear as {dcib_your_custom_key}

These tags will be replaced with the current DCIB field values when the email is sent.

## Installation

1. Upload the `dynamic-contact-info-blocks` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your contact information under the 'Contact Info' menu in the WordPress admin panel

## Requirements

- WordPress 5.9.0 or higher
- PHP 8.1 or higher

## Changelog

### 1.0.2

- Fix false phone validation error on first save
- Revert back plugin URI to public GitHub repository

### 1.0.1

- Fixed "Requires at least" to use the major WordPress version and match the plugin header.
- Replaced the invalid Plugin URI with the author site.
- Registered a sanitize callback for the custom fields setting instead of a `pre_update_option` filter.
- Removed the recurring version migration that could overwrite saved phone numbers and custom fields.
- Custom field sanitizing now accepts the stored data format, so imports and programmatic updates no longer erase existing fields.
- Fixed the false "Phone number does not appear to be valid" error on the first save, where the not-yet-stored option was sanitized twice.

### 1.0

- Initial release

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Rotsen Mark Acob
