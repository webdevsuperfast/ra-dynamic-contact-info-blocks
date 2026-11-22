# Repository Guidelines

## Project Overview

WordPress plugin for centrally managing business contact details and outputting them via placeholders, shortcodes, Elementor Dynamic Tags, and WPForms Smart Tags. No custom tables, no JS framework, no test suite.

## Architecture & Data Flow

Singleton `DCIB` (`includes/class-dcib.php`) wires everything: `load_dependencies()` requires admin/public/phone-utils/wpforms/import-export; Elementor tag file is lazy-required only inside `register_elementor_dynamic_tags()`.

- Admin: `DCIB_Admin` registers 6 settings via Settings API (`options.php`), sanitizes custom fields via `pre_update_option_dcib_custom_fields` filter (not `register_setting`), migrates versions on `admin_init`.
- Public: `DCIB_Public` builds a placeholder map, caches it in `DCIB_Public::$cached_map`, clears cache on any `dcib_*` `updated_option`. Filterable via `dcib_placeholder_map`.
- Storage: 7 `wp_options` keys, no tables: `dcib_phone_number` (serialized `{value,tel}`), `dcib_email_address`, `dcib_address`, `dcib_custom_fields` (`{(KEY) => {type,value[,tel]}}`), `dcib_default_country` (default `US`), `dcib_maps_url`, `dcib_version`.

Placeholder render path (`public/class-dcib-public.php`):

1. `get_placeholder_map()`: `get_option()` x6 + `maybe_unserialize` for phone/custom.
2. Build `$map`: `(PHONE)/(EMAIL)/(ADDRESS)` + `(PHONE_LINK)` → `tel:+…`, `(EMAIL_LINK)` → `mailto:…`, `(ADDRESS_LINK)` → `dcib_maps_url` or `https://www.google.com/maps/search/?api=1&query=…`; custom `(KEY)` plus auto `(KEY_LINK)` for `type=phone/email/address`.
3. `normalize_placeholder_map()` + `apply_filters('dcib_placeholder_map', $map)` → cache.
4. `replace_contact_info($content)`: `strtr($content, esc_html($map))` → `normalize_links_in_html()` (fixes `http://tel:` concat) → `encode_mailto_links()` (`@` → `&#64;`, `.` → `&#46;`).
5. Alternate entries: `[dcib key="PHONE"]` shortcode (uppercases to `(KEY)`), `[dcib_debug]`, `DCIB_Elementor_Dynamic_Tag::render()` (`get_map()[(FIELD)]`), `DCIB_WPForms::process_wpforms_smart_tag()` (`{dcib_phone}` → `(PHONE)`, custom `{dcib_*}`).

Hook surface: `the_content`, `widget_text`, `widget_text_content`, `elementor/frontend/the_content:10`, `elementor/widget/render_content:20`, `elementor/theme/the_content:10`; `elementor/init:10` → `elementor/dynamic_tags/register`; `wpforms_loaded` + `plugins_loaded:20` → `wpforms_smart_tags` / `wpforms_smart_tag_process:10,3` (self-guards if WPForms absent).

## Key Directories

- `includes/`: `class-dcib.php` (singleton), `class-dcib-phone-utils.php` (regex + E.164 dialing table, prefers `WC()->countries` if WooCommerce active), `class-dcib-elementor-dynamic-tag.php` (`dcib-contact-info` / group `dcib`, categories `text,url`), `class-dcib-wpforms.php`, `class-dcib-import-export.php` (JSON export/import, `.json` + ≤2 MB gate, coerces to string).
- `admin/`: `class-dcib-admin.php` (settings, enqueue, `dcibAdmin` localize, migrations), `partials/dcib-admin-display.php` (`#dcib-form`, `#custom-fields` repeater `dcib_custom_fields[key/type/value][]`), `js/dcib-admin.js` (vanilla IIFE), `js/libphonenumber-js.min.js` (vendored build output, do not hand-edit), `css/dcib-admin.css`.
- `public/`: `class-dcib-public.php` (map + replace + shortcodes), `js/dcib-elementor-editor.js` (jQuery preview reload on `elementor:init` / `preview:loaded`).
- `languages/`: `dynamic-contact-info-blocks.pot` only (stale vs. current code — regenerate before release).
- `.github/workflows/`: `deploy.yml` (lint + tag release), `security.yml` (deps/SAST/secrets).

## Development Commands

```bash
composer install            # PHP lint deps only (dev-only)
vendor/bin/phpcs            # lint, config phpcs.xml (failing gate in CI)
vendor/bin/phpcbf           # auto-fix (non-failing in CI: phpcbf || true)
npm install # or: npm ci    # JS build deps
npm run build   # node build.js — copies libphonenumber bundle
npm run watch   # node build.js --watch (esbuild context watch)
npm test                    # stub, always exits 1 — no tests exist
```

CI (`deploy.yml`, triggers: PR→`main`, push→`main`, any tag): `lint` always runs; `build`/`release` run only on tags. Tag `vX.Y.Z` (stripped `v`) must equal both `DCIB_VERSION` and the `Version:` header or the job fails. Zip excludes `.git/*,.github/*,vendor/*,build.js,*.md,*.json,composer.*,phpcs.xml,.editorconfig,.agents/*`.

## Code Conventions & Common Patterns

- PHP 8.1+, tabs width 4 (`[*.php]` in `.editorconfig`), UTF-8/LF. Lint: `WordPress-Core`, `WordPress-Docs`, `WordPress-Extra` with `WordPress.Files.FileName` disabled.
- Every PHP file: `if ( ! defined( 'ABSPATH' ) ) exit;`. `uninstall.php` additionally guards `WP_UNINSTALL_PLUGIN` then deletes the 7 `dcib_*` options.
- Escaping/i18n: `esc_html__/esc_html_e/esc_attr_e/esc_textarea`, domain `dynamic-contact-info-blocks` everywhere; zero hardcoded JS user strings — all via `wp_localize_script('…', 'dcibAdmin', …)` (15 keys: `copiedToClipboard,confirmRemove,copyKey,actions,remove,typePhone,typeEmail,typeAddress,typeCustom,fieldKeyLabel,fieldTypeLabel,fieldValueLabel,defaultCountry,invalidNumber,validNumber`).
- Custom keys: `/^\([A-Z0-9_]+\)$/`, enforced client-side by `enforceParentheses()` (strip `[^a-zA-Z0-9_]`, uppercase, wrap `(…)` preserving caret).
- Phone: server `DCIB_Phone_Utils::is_valid()` is regex-only; client uses vendored `libphonenumber.parsePhoneNumber(value, dcibAdmin.defaultCountry).isValid()` + `formatNational()` synced to hidden `.dcib-phone-tel` on submit.
- Shared admin dropdown contract (PHP partial + JS must match): `.dcib-dropdown/.dcib-dropdown-toggle/.dcib-dropdown-menu/.dcib-dropdown-item`, `data-action={add-field,copy-key,copy-link,remove}`, `data-copy`, `data-enforce-parens`, `data-type`; keyboard `ArrowDown/ArrowUp/Home/End/Enter/Space/Escape/Tab`; remove uses `confirm(dcibAdmin.confirmRemove)`.
- JS: vanilla IIFE with early-exit `getElementById("dcib-form")`, `formChanged` + `beforeunload` guard, event delegation on `document.click`. Only `dcib-elementor-editor.js` uses jQuery.

## Important Files

- `dynamic-contact-info-blocks.php:31` — `DCIB_VERSION='1.0'`, header (`Requires at least: 5.9.0`, `Requires PHP: 8.1`), loads `includes/class-dcib.php` → `DCIB::get_instance()`.
- `includes/class-dcib.php` — hook wiring, `register_elementor_dynamic_tags_init()`. Note: `enqueue_elementor_editor_scripts()` (lines ~143-151) is currently unhooked dead code.
- `admin/class-dcib-admin.php` — `register_settings()`, `sanitize_custom_fields()`, `check_version_and_migrate()/migrate_to_130()/normalize_phone_tel()`, `enqueue_scripts()` (gated to `toplevel_page_dcib-settings`, `contact-info_page_dcib-import-export`), `handle_export/handle_import()`.
- `public/class-dcib-public.php` — `get_placeholder_map()/get_map()/replace_contact_info()/shortcode()/debug_shortcode()/normalize_links_in_html()/encode_mailto_links()/clear_cache()`.
- `includes/class-dcib-import-export.php:115` — 2 MB import limit string (missing from POT).
- `build.js` — esbuild copy `node_modules/libphonenumber-js/bundle/libphonenumber-js.min.js` → `admin/js/libphonenumber-js.min.js` (`minify:false`).
- `composer.json`, `package.json`, `phpcs.xml`, `uninstall.php`, `readme.txt`, `README.md`.

## Runtime/Tooling Preferences

- Runtime: PHP `>=8.1`, WordPress `>=5.9.0` (tested to `7.1`). CI lints/builds on PHP `8.3`.
- Package managers: Composer (PHP, dev-only — plugin never loads `vendor/autoload.php`, production zip has no `vendor/`) + npm/Node (`package-lock.json`; no Bun/Yarn/pnpm, no `.nvmrc`/`engines` pin).
- No updater library in tree (no `includes/class-dcib-updater.php`); no JS build beyond esbuild copy.
- Dependabot: weekly `composer:/` + `github-actions:/`, cap 5, prefixes `chore:`/`ci:`.

## Testing & QA

No tests exist — no PHPUnit/Pest/Codeception, no `tests/`/`test/`/`__tests__/`/`e2e/`/`fixtures/`/`coverage/`, no `phpunit.xml`, no Jest/Vitest/Playwright/Cypress configs. `npm test` is an intentional stub (`exit 1`).

QA is lint + security scans only:

- `vendor/bin/phpcs` (WPCS ruleset) — the failing gate.
- `security.yml` (PR/push/tags + weekly Mon 05:00 UTC + dispatch): `dependency-review-action` (PR-only, fail on `high`), `osv-scanner` on `composer.lock`, Semgrep `p/php + p/security-audit` (excludes `admin/js/lib`, `vendor`; uploads `semgrep.sarif`), TruffleHog `--only-verified` full history.
- Manual verification for behavior changes: set values on the settings page, then check a page containing `(PHONE)/(EMAIL)/(ADDRESS)`, a `_LINK` variant, one custom `(KEY)`, and `[dcib key="…"]` — plus Elementor render and WPForms `{dcib_*}` tag if touched.

## CI

Install the local-ci skill (one-time setup):

```bash
npx skills add redwoodjs/local-ci --skill local-ci
```

Before completing any work, run the `local-ci` skill to validate
your changes locally. If it fails, fix the issue and re-run.
Do not report work as done until it passes.
