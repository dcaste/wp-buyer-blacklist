# Best-practices audit: WordPress, PHP and React

Audit for #120, run on 2026-09-25 against `dev` at `41d8a09`. Guidance came from the Context7 MCP server, not from memory. Each finding names the Context7 library and the page it quotes.

Context7 libraries used:

- `/kasparsd/wp-docs-md`: WordPress handbooks, including the REST API Handbook
- `/websites/developer_wordpress_reference_functions`: WordPress function reference
- `/wordpress/gutenberg`: Gutenberg repository docs, covering `@wordpress/*` packages and how-to guides
- `/websites/php_net_manual_en`: the PHP manual

## Summary

| Area | Finding | Action |
| --- | --- | --- |
| WordPress | REST routes declare no `args` schema | #141 |
| WordPress / React | Admin and Users-list JavaScript strings can't be translated | #142 |
| PHP | Classes load through a hand-kept `require_once` list | #143 |
| PHP | No `declare( strict_types=1 )` | No change, see below |
| React | TypeScript is compiled but never type-checked | Noted, out of scope (tooling) |
| React | `@wordpress/components` is not used | No change, see below |

Already in line with the docs:

- All 13 REST endpoints set a `permission_callback`.
- Every `admin-post.php` handler checks a nonce.
- The admin script uses the generated `build/index.asset.php` for its dependencies and version.
- The app mounts with `createRoot` from `@wordpress/element`, not the `render` that has been deprecated since WordPress 6.2.
- Requests go through `@wordpress/api-fetch`. Its core script handle adds the REST nonce middleware.

## WordPress

### REST routes declare no argument schema

The REST API Handbook's "Arguments" section (`/kasparsd/wp-docs-md`, `wp-rest-api-handbook.md`) says each endpoint defines its arguments in `args`, with `type`, `required`, `validate_callback` and `sanitize_callback`. That way "the main callback [can] act only to process the request", and the schema is published to clients.

None of the plugin's 13 endpoints in `EntriesController`, `ImportExportController` and `DossierController` declare `args`. Every callback reads `$request->get_param()` and checks types and required fields by hand. `update_email_entry()` is one example, with its reason, `is_active` and identity checks. The checks work and are tested, but they're spread across callbacks, clients can't discover them, and invalid input returns the plugin's own error codes instead of core's `rest_invalid_param`.

Follow-up: #141.

### JavaScript strings can't be translated

The Gutenberg internationalization guide (`/wordpress/gutenberg`, `docs/how-to-guides/internationalization.md`) wraps JavaScript strings in `__()` from `@wordpress/i18n`. It registers the script with a `wp-i18n` dependency and calls `wp_set_script_translations()` so WordPress loads the JSON translations before the script runs.

PHP strings use the `wp-buyer-blacklist` text domain throughout. But `assets/admin/index.tsx` and `assets/js/customer-dialogs.js` hardcode English: every label, button, status and message on the Buyer Blacklist page, and the Users-list dialogs. Nothing imports `@wordpress/i18n` or calls `wp_set_script_translations()`. On a non-English site, the admin app stays in English while the PHP-rendered parts around it are translated.

`load_plugin_textdomain()` is only needed for translations shipped inside the plugin. The function reference (`/websites/developer_wordpress_reference_functions`, `load_plugin_textdomain`) says plugins hosted on WordPress.org get just-in-time loading from `wp-content/languages`. The plugin has no `languages/` folder, so no change there unless it will ship its own translation files.

Follow-up: #142.

## PHP

### Classes load through a hand-kept `require_once` list

`composer.json` declares a PSR-4 map (`WP_Buyer_Blacklist\` → `src/`), and ADR 0003 names the folders by namespace. But the production bootstrap `wp-buyer-blacklist.php` doesn't use it. It has 37 `require_once` lines in dependency order. A new class has to be added there by hand, and forgetting one only fails on a real site, because the tests load classes through Composer's autoloader.

The PHP manual's autoloading pages (`/websites/php_net_manual_en`, `language.oop5.autoload.php` and `function.spl-autoload-register.php`) show `spl_autoload_register()` with a PSR-4 mapping that works without Composer. That fits a plugin that ships without `vendor/`.

Follow-up: #143.

### No `declare( strict_types=1 )`

The PHP manual (`/websites/php_net_manual_en`, `language.types.declarations.php`) describes strict mode as a per-file opt-in that turns scalar type coercion into a `TypeError`. It applies only to calls made from that file.

No file in `src/` declares it. I don't recommend adding it now. WordPress often passes hook and filter arguments as strings or mixed values, and option and meta reads return strings or `false`. The plugin already uses typed signatures, and WordPress's own coding standards don't ask for strict types. Turning it on file by file would move type errors from coercion to runtime `TypeError`s in hook callbacks without a clear gain. Revisit this if the code base moves toward a static analyser.

## React and `@wordpress/*`

### TypeScript is compiled but never type-checked

`assets/admin/index.tsx` is TypeScript, but the repository has no `tsconfig.json` and no type-check script. `@wordpress/scripts` compiles it with Babel, which strips types without checking them. Gutenberg works the same way: its `npm run build` "does NOT type check", and it runs `tsc` separately through `npm run typecheck` (`/wordpress/gutenberg`, `AGENTS.md` and `packages/README.md`).

Type errors in the admin app go unnoticed. Adding `tsc --noEmit` would be new tooling, which #120 puts out of scope. I'm recording it here for the maintainer to decide.

### `@wordpress/components` is not used

Gutenberg's docs build admin UIs from `@wordpress/components` (`Button`, `SelectControl`, `TextareaControl`, `Modal`, `Notice`), which come with their own accessibility work. The plugin builds its UI from plain elements with WordPress admin CSS classes and a native `<dialog>`, following `docs/design/buyer-blacklist-admin-ui.md`. That's a design choice, not a gap. The markup already uses labelled fields, `aria-describedby`, `role="switch"` and `role="status"`.

## Other observations

These have no Context7 source, but they came up during the audit:

- `assets/admin/index.tsx` puts the whole app in one component. Four JSX lines are over 1,000 characters each, and the entry dialog is one line. Splitting it into components would make changes easier to review.
- Deleting an entry uses `window.confirm()`. It works and is accessible, but it doesn't match the styled dialogs used everywhere else.
