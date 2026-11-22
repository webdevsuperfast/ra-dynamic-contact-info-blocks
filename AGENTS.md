# AGENTS.md — Dynamic Contact Info Blocks

WordPress plugin (v1.4.0) for centrally managing business contact details and outputting them via placeholders. No JS build pipeline, no tests, no custom tables.

## Commands

| Action | Command |
|---|---|
| Install deps | `composer install` |
| PHPCS lint | `vendor/bin/phpcs` (config: `phpcs.xml`) |
| Auto-fix lint | `vendor/bin/phpcbf` (runs in CI, non-failing) |

Composer deps are **dev-only** (`phpcs`, `wpcs`). The plugin never loads `vendor/autoload.php`. CI runs `composer install --no-dev --optimize-autoloader` in the build job but that produces an empty production autoloader — no server-side libraries exist.

PHPCS applies `WordPress-Core`, `WordPress-Docs`, `WordPress-Extra` with `FileName` sniff disabled. No other lint, typecheck, or formatter exists.

## Architecture

- **Entrypoint:** `dynamic-contact-info-blocks.php` — defines `DCIB_VERSION`, `DCIB_PLUGIN_PATH`, `DCIB_PLUGIN_URL`, loads `includes/class-dcib.php`, calls `DCIB::get_instance()`
- **Singleton:** `DCIB` in `includes/class-dcib.php` wires admin + public hooks via `define_admin_hooks()` / `define_public_hooks()`
- **Storage:** 7 `get_option`/`update_option` calls (no custom tables): `dcib_phone_number`, `dcib_email_address`, `dcib_address`, `dcib_custom_fields`, `dcib_default_country`, `dcib_maps_url`, `dcib_version`
- **Uninstall:** `uninstall.php` removes those 7 options
- **Settings API:** 6 settings registered via `register_setting()` in `admin/class-dcib-admin.php::register_settings()`, form posts to `options.php`
- **Custom field sanitization:** `sanitize_custom_fields()` is NOT in `register_setting()` — it's hooked via `pre_update_option_dcib_custom_fields` filter (line 53). Import bypasses it via the `DCIB_Admin::$skip_custom_fields_sanitize` flag
- **Phone validation:** `includes/class-dcib-phone-utils.php` is self-contained — no libphonenumber server-side. `is_valid()` uses a regex, `format_e164()` prepends country codes from a hardcoded dialing-code table, `get_countries()` prefers WooCommerce (`WC()->countries`) if active. Client-side validation via a vendored `libphonenumber-js` v1.12.6 bundle (`admin/js/lib/libphonenumber-js.min.js`, served locally — no CDN) in admin JS
- **Import/Export:** `includes/class-dcib-import-export.php` — JSON export/import of all dcib_ options. Import requires nonce (`dcib_import_settings`), `.json` extension, and ≤ 2 MB file size. Since 1.4.0, imported values are coerced to strings and import no longer erases the stored phone number
- **Migration:** `migrate_to_130()` runs when stored version < 1.3.0; `normalize_phone_tel()` runs when < 1.3.3. Both run on `admin_init` each load. They convert legacy phone strings to serialized `{value, tel}` format.

## Placeholders & Shortcodes

- `(PHONE)`, `(EMAIL)`, `(ADDRESS)` and custom `(YOUR_KEY)` replaced in `the_content`, `widget_text`, `widget_text_content`, and Elementor hooks
- Link variants: `(PHONE_LINK)` → `tel:+...`, `(EMAIL_LINK)` → `mailto:...`, `(ADDRESS_LINK)` → Google Maps URL (or custom if `dcib_maps_url` set). Custom fields of type phone/email/address auto-generate `(KEY_LINK)`
- Shortcode: `[dcib key="PHONE"]` or `[dcib key="YOUR_KEY"]`. Debug: `[dcib_debug key="PHONE"]`
- Custom keys validated by regex `/^\([A-Z0-9_]+\)$/`
- After replacement, `href`/`src` attributes are normalized to fix malformed URLs from concatenation. `mailto:` links get anti-spam encoding (`@` → `&#64;`, `.` → `&#46;`)
- Placeholder map is cached in `DCIB_Public::$cached_map` and cleared on any `dcib_` option update

## Elementor Integration

- Hooks into `elementor/frontend/the_content`, `elementor/widget/render_content`, `elementor/theme/the_content` for placeholder replacement
- Dynamic Tags (requires Elementor Pro): group `dcib`, tag `dcib-contact-info`, selectable fields including link variants
- Live preview: `public/js/dcib-elementor-editor.js` reloads preview on `elementor:init` + `preview:loaded`
- Tag registration: `elementor/init` → `elementor/dynamic_tags/register`

## WPForms Integration

- Smart tags: `{dcib_phone}`, `{dcib_email}`, `{dcib_address}`, `{dcib_phone_link}`, `{dcib_email_link}`, `{dcib_address_link}`, `{dcib_your_custom_key}`, `{dcib_your_custom_key_link}`
- Registered via `wpforms_loaded` hook, with `plugins_loaded` fallback at priority 20
- Self-guards: only registers if WPForms is detected

## CI/CD (`.github/workflows/deploy.yml`)

- Triggers: push to `main`, pull request to `main`, or any tag
- Lint job: `composer install` → `vendor/bin/phpcbf || true` (auto-fix, non-failing) → `vendor/bin/phpcs` — runs on all triggers
- Build/release run **only on tags**; build first runs a **version consistency check**: the git tag (`v1.x.y`, `v` stripped) must equal the `DCIB_VERSION` constant in `dynamic-contact-info-blocks.php`, or the job fails
- Build: `composer install --no-dev --optimize-autoloader` → zip excluding `.git/`, `.github/`, config files, `*.md`, `*.json`, and `.agents/`
- Release: creates GitHub release with zip artifact via `softprops/action-gh-release@v1`

## Security CI (`.github/workflows/security.yml`)

Separate workflow (added 1.4.0) running dependency, SAST and secret scanning gates: `dependency-review-action` on PRs, `osv-scanner` against `composer.lock`, PHP SAST via `run:` scan uploading SARIF as artifact, and `trufflesecurity/trufflehog` secret scan.

## Notable

- **No tests** — no unit, integration, or E2E tests
- **Requires PHP 8.1+** (declared in plugin header)
- **Updater** (`includes/class-dcib-updater.php`) is an in-house GitHub release checker — no external updater library. Auth token comes from the `dcib_updater_token` filter or the `DCIB_GITHUB_TOKEN` constant (NOT hardcoded). Since 1.4.0 it validates the release payload before offering an update and scopes the GitHub auth header to the plugin's own repo URLs only
- **Translation:** 55 strings in `languages/dynamic-contact-info-blocks.pot`, text domain `dynamic-contact-info-blocks`
- **Admin JS i18n** passed via `wp_localize_script()` as `dcibAdmin` object (includes messages, `defaultCountry` for validation)
- **Admin dropdown UI:** Placeholders/Actions dropdowns with keyboard nav (Arrow Up/Down/Home/End/Enter/Escape)
- **Code style:** `.editorconfig` enforces tabs (size 4) for PHP, UTF-8, LF line endings
