# Best-practices audit: WordPress, PHP and React

Audit for #120, run on 2026-09-25 against `dev` at `41d8a09`. Guidance came from the Context7 MCP server, not from memory. Each finding names the Context7 library and the page it quotes.

Every item in the issue's Scope section has a row below, with one of these outcomes:

- **Finding**: it differs from the docs and has a follow-up issue.
- **Checked**: I compared it with the docs and it's fine.
- **Noted**: it differs from the docs, but I'm not recommending a change. The reason is given.

Context7 libraries used:

- `/kasparsd/wp-docs-md`: WordPress handbooks, including the REST API Handbook
- `/websites/developer_wordpress_reference_functions`: WordPress function reference
- `/wordpress/gutenberg`: Gutenberg repository docs, covering `@wordpress/*` packages and how-to guides
- `/reactjs/react.dev`: the React docs
- `/websites/php_net_manual_en`: the PHP manual
- `/phpcompatibility/phpcompatibilitywp`: the PHPCompatibilityWP README

## Summary

| Area | Scope item | Outcome |
| --- | --- | --- |
| WordPress | Hooks and loading order | Checked |
| WordPress | Capability checks | Checked |
| WordPress | Nonces | Checked |
| WordPress | Sanitizing input, escaping output | Checked |
| WordPress | Settings / Options API | Checked |
| WordPress | REST route registration | Finding: #141 |
| WordPress | i18n | Finding: #142 |
| WordPress | Script enqueueing | Checked |
| PHP | Minimum version and language features | Finding: #147 |
| PHP | Strict types | Noted |
| PHP | Namespacing and autoloading | Finding: #143 |
| PHP | Error handling | Checked |
| PHP | PHPUnit test structure | Noted |
| React | `@wordpress/element`, `components`, `api-fetch` | Checked, `components` noted |
| React | Hooks and state handling | Finding: #145 |
| React | Accessibility | Finding: #146 |
| React | TypeScript types | Noted |
| React | `@wordpress/scripts` build and test config | Checked |

## WordPress

### Hooks and loading order: checked

The bootstrap declares HPOS compatibility on `before_woocommerce_init`. It starts the plugin on `plugins_loaded` only when WooCommerce is loaded, and otherwise shows an Administrator notice. Each module registers its own hooks in `register()`. Settings register on `admin_init`, which is where the `register_setting()` reference says to call it (`/websites/developer_wordpress_reference_functions`, `register_setting`). REST routes register on `rest_api_init`.

`Schema::install()` runs on every `plugins_loaded`, but it returns early when the stored database version matches, so a normal request costs one autoloaded option read.

The function reference for `_load_textdomain_just_in_time()` (`/websites/developer_wordpress_reference_functions`) says that since WordPress 6.7, translations used before `init` trigger a `_doing_it_wrong` notice. The integration suite treats unexpected `_doing_it_wrong` calls as failures, and it passes, so the plugin doesn't load translations too early.

### Capability checks: checked

Two classes make every access decision. `OperatorAccess` allows `manage_options`, or `manage_woocommerce` when the Administrator allows managers. `PrivacyDataAccess` allows `manage_options` only. Menus, REST `permission_callback`s, admin-post handlers, the Users-list column and the enrollment actions all go through them. PHPCS's `WordPress.WP.Capabilities` sniff is configured with the WooCommerce capability.

### Nonces: checked

Every `admin-post.php` handler (enrollment, CSV export, dossier export, settings reset) verifies a nonce. REST requests from the admin app go through `@wordpress/api-fetch`, and core's script handle for it adds the REST nonce middleware (`/wordpress/gutenberg`, `packages/api-fetch/README.md`, `createNonceMiddleware`).

### Sanitizing input and escaping output: checked

Every read from `$_GET` or `$_POST` in `src/` checks the type, calls `wp_unslash()`, and sanitizes with `absint()`, `sanitize_key()`, `sanitize_text_field()` or `sanitize_textarea_field()`. Output escaping is enforced by the `WordPress.Security.EscapeOutput` sniff in `composer lint`, which passes. The Buyer block message is sanitized with `sanitize_textarea_field()` and keeps the plain-text-only rule from the PRD.

### Settings / Options API: checked

All three options are registered with `register_setting()`, each with a `type`, a `sanitize_callback` and a `default`, as the reference lists. One small overlap: `get_option()` calls repeat the defaults because registration only happens on `admin_init`, and checkout and REST requests read the options without it. That's correct, and it isn't worth a change.

### REST route registration: finding (#141)

The REST API Handbook's "Arguments" section (`/kasparsd/wp-docs-md`, `wp-rest-api-handbook.md`) says each endpoint defines its arguments in `args`, with `type`, `required`, `validate_callback` and `sanitize_callback`. That way "the main callback [can] act only to process the request", and the schema is published to clients.

None of the plugin's 13 endpoints declare `args`. Every callback reads `$request->get_param()` and checks types and required fields by hand. All 13 do set a `permission_callback`, and all share one namespace constant.

### i18n: finding (#142)

The Gutenberg internationalization guide (`/wordpress/gutenberg`, `docs/how-to-guides/internationalization.md`) wraps JavaScript strings in `__()` from `@wordpress/i18n` and calls `wp_set_script_translations()`.

PHP strings use the `wp-buyer-blacklist` text domain, and the PHPCS `WordPress.WP.I18n` sniff checks them. But `assets/admin/index.tsx` and `assets/js/customer-dialogs.js` hardcode English, and nothing calls `wp_set_script_translations()`. `load_plugin_textdomain()` is only needed for translations shipped inside the plugin (`/websites/developer_wordpress_reference_functions`, `load_plugin_textdomain`, "Modern Translation Loading").

### Script enqueueing: checked

The admin script and stylesheet load only on the Buyer Blacklist page and the Users screen. Dependencies and version come from the generated `build/index.asset.php`. The Users-list dialog script lists `wp-api-fetch` and is versioned by `filemtime()`. Inline scripts go through `wp_add_inline_script()`, as ADR 0003 requires.

## PHP

### Minimum version and language features: finding (#147)

The plugin header and `composer.json` require PHP 8.3. The code uses typed properties, union return types (`array|WP_Error`, `\WP_User|false`), `mixed`, `match` and `str_starts_with()`. It doesn't use constructor promotion or `readonly` (`/websites/php_net_manual_en`, `language.oop5.decon.php` and `language.oop5.properties.php`). Those would shorten the constructors, but the current style is consistent, so I'm not recommending a change.

The gap is in checking. `phpcompatibility/phpcompatibility-wp` is installed, but `phpcs.xml.dist` never enables it. The PHPCompatibilityWP README (`/phpcompatibility/phpcompatibilitywp`) shows `<config name="testVersion" .../>` plus `<rule ref="PHPCompatibilityWP"/>`. The ruleset's `minimum_supported_wp_version` is `6.0`, not the required `6.9`. A probe run with `testVersion` `8.3-` and with `6.9` found nothing, so this is configuration only.

### Strict types: noted

The PHP manual (`/websites/php_net_manual_en`, `language.types.declarations.php`) describes strict mode as a per-file opt-in that turns scalar type coercion into a `TypeError`. It applies only to calls made from that file.

No file declares it. I don't recommend adding it now. WordPress often passes hook and filter arguments as strings or mixed values, and option and meta reads return strings or `false`. The plugin already uses typed signatures, and WordPress's own coding standards don't ask for strict types. Turning it on would move type errors from coercion to runtime `TypeError`s in hook callbacks, for little gain.

### Namespacing and autoloading: finding (#143)

Namespaces follow ADR 0003: `WP_Buyer_Blacklist\<Feature>`, one class per file. `composer.json` declares the PSR-4 map, but the production bootstrap loads classes through 37 hand-ordered `require_once` lines. The PHP manual's autoloading pages (`/websites/php_net_manual_en`, `language.oop5.autoload.php` and `function.spl-autoload-register.php`) show `spl_autoload_register()` with a PSR-4 mapping that works without Composer. That fits a plugin that ships without `vendor/`.

### Error handling: checked

Value objects (`EmailEntry`, `AddressEntry`, `CustomerIdEntry`) and `CsvImport` throw `InvalidArgumentException` for invalid input. REST controllers catch it and return a `WP_Error` with a 400 status and a translated message. `EntryRepository` checks `$wpdb` results against `false`, and it wraps deletion in a transaction that rolls back on failure. Download handlers stop with `wp_die()` on a failed capability or nonce check.

### PHPUnit test structure: noted

Tests follow the WordPress plugin scaffold: `bin/install-wp-tests.sh`, `wp-phpunit` and `yoast/phpunit-polyfills` on PHPUnit 9.6. `phpunit.xml.dist` holds the unit suite and `phpunit.integration.xml.dist` the integration suite. Integration tests extend `WP_UnitTestCase` and use HTML snapshots for PHP-rendered screens.

One quirk: the unit suite's bootstrap also loads the WordPress test library, so unit tests need a database even though they extend PHPUnit's plain `TestCase`. They need WordPress functions such as `__()` and `wp_json_encode()`. Removing that dependency would mean a mocking library, which is new tooling and out of scope here.

## React and `@wordpress/*`

### `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`: checked, `components` noted

The app mounts with `createRoot` from `@wordpress/element`, not `render`, which has been deprecated since WordPress 6.2 (`/wordpress/gutenberg`, `packages/element/README.md`). All requests use `apiFetch` with REST paths.

It doesn't use `@wordpress/components`. Gutenberg's docs build admin UIs from those components, which come with their own accessibility work. The plugin instead follows `docs/design/buyer-blacklist-admin-ui.md` with plain elements, WordPress admin CSS classes and a native `<dialog>`. That's a design choice, not a gap. See the accessibility finding below for what it costs.

### Hooks and state handling: finding (#145)

The React docs (`/reactjs/react.dev`, `reference/react/useEffect.md` "Fetching data with Effects" and `learn/synchronizing-with-effects.md`) warn that responses may arrive out of order. They fix this with an `ignore` flag in the effect's cleanup, and they reset shown data when the selection changes.

`inspect( entry )` in `index.tsx` fetches an entry's Audit events, Blocked attempts and Buyer context outside any effect. It doesn't clear the previous entry's data, ignore stale responses, or catch errors. Opening two entries quickly can show one entry's Blocked attempts or Buyer under the other. The initial `load()` and the import-history effect don't handle errors either.

"Choosing the State Structure" (`/reactjs/react.dev`, `learn/choosing-the-state-structure.md`) recommends storing the selected id and deriving the item. `selectedEntry` holds a copy of the entry instead, which `update()` has to refresh by hand. That fits into the same fix.

`useEffect` dependency lists are otherwise correct, and the filtered and paged entry lists are computed during render, not stored in state, as "You Might Not Need an Effect" recommends.

### Accessibility: finding (#146)

The Gutenberg `@wordpress/a11y` README (`/wordpress/gutenberg`, `packages/a11y/README.md`) recommends `speak()` for announcing dynamic updates through a shared `role="status"` region that core keeps on the page.

The app renders `{ message && <p role="status">{ message }</p> }`, so the live region appears together with its first message, which screen readers often don't announce. Messages from the Manage Blacklist entry dialog render behind the modal, where they are hidden and inert while it's open.

Otherwise the markup follows the design spec's accessibility rules: form fields have labels and `aria-describedby`, the status switch uses `role="switch"` with `aria-checked`, status never relies on color alone, and the dialog close button has an `aria-label`.

### TypeScript types: noted

`index.tsx` declares types for its REST data (`Entry`, `Address`, `AuditEvent`, `BlockedAttempt`, `BuyerContext`) and types its state. A few `catch ( error: any )` clauses remain.

The repository has no `tsconfig.json` and no type-check script. `@wordpress/scripts` compiles with Babel, which strips types without checking them. Gutenberg itself runs `tsc` separately (`/wordpress/gutenberg`, `AGENTS.md` and `packages/README.md`: `npm run build` "does NOT type check"). Adding a `tsc --noEmit` step would be new tooling, which #120 puts out of scope. I'm recording it here for the maintainer to decide.

### `@wordpress/scripts` build and test config: checked

`npm run build` calls `wp-scripts build` with the one entry point, and the generated asset file is committed and enqueued. `jest.config.js` extends `@wordpress/scripts/config/jest-unit.config.js` and `@wordpress/jest-preset-default`. It only adds `jest-dom` and a transform for `@wordpress` ES modules. `npm test` calls `wp-scripts test-unit-js`.

## Outside the audit

These two points have no Context7 source. They came up while reading the code, and I'm not filing issues for them:

- `assets/admin/index.tsx` puts the whole app in one component. Four JSX lines are over 1,000 characters each, and the entry dialog is one line. Several of this session's PRs edit the same lines and will conflict with each other.
- Deleting an entry asks for confirmation with `window.confirm()`. It works and is accessible, but it doesn't match the styled dialogs used everywhere else.
