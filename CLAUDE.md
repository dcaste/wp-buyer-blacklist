# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

@AGENTS.md

WooCommerce plugin that blocks listed Buyers at checkout. Requires PHP 8.3, WordPress 6.9+, WooCommerce 10.8+ with HPOS. Product language is defined in `CONTEXT.md` (Buyer, Blacklist entry, Match type, Canonical value, Blocked attempt, Audit event, Enforcement boundary, and so on). Use those terms in code, docs and issues. Product behaviour is in `docs/prd/`, architecture decisions in `docs/adr/`.

## Commands

```bash
composer lint                 # PHPCS (WPCS), must pass before commit
composer lint:fix             # phpcbf auto-fix
composer test                 # PHP unit tests (tests/unit, *Test.php)
composer test:integration     # WordPress + WooCommerce integration tests (tests/integration)
npm run build                 # build the admin app: assets/admin/index.tsx -> build/
npm test                      # Jest tests in tests/js via wp-scripts
```

Single test:

```bash
vendor/bin/phpunit -c phpunit.xml.dist --filter SomeTest
# bin/test-integration.sh ignores extra args, so call phpunit directly with the same env:
WP_TESTS_DIR=/tmp/wordpress-tests-lib WC_PLUGIN_DIR=../woocommerce \
  vendor/bin/phpunit -c phpunit.integration.xml.dist --filter CheckoutEnforcementIntegrationTest
npx wp-scripts test-unit-js tests/js/admin.test.tsx
```

Integration tests need the WP test library (`bin/install-wp-tests.sh`, sets `WP_TESTS_DIR`, default `/tmp/wordpress-tests-lib`) and a MySQL database. `WC_PLUGIN_DIR` defaults to the sibling `../woocommerce` directory; override it when running from a worktree. The PHP CLI runs with `output_buffering=0`, so tests that fire `admin_init` must unhook `wp_admin_headers`.

`.circleci/config.yml` is leftover scaffold (PHP 5.6, old PHPUnit) and does not reflect the real toolchain.

## Architecture

- **Bootstrap:** `wp-buyer-blacklist.php` registers its own PSR-4 autoloader for `WP_Buyer_Blacklist\` → `src/`, because the plugin ships without `vendor/`. A new class needs no registration, but its namespace and file path must match its class name.
- **Composition root:** `src/Plugin.php` is built as `new Plugin( $wpdb )`, constructs every module by hand and calls each module's `register()`. It holds no feature logic and there is no DI container. Wire new modules there.
- **Feature folders** under `src/` are PSR-4 subnamespaces of `WP_Buyer_Blacklist` (ADR 0003): `Entries`, `Enforcement`, `Enrollment`, `ImportExport`, `Privacy`, `Settings`, `Access`, `Admin`, `Storage`. New code goes in the folder that owns its workflow.
- **REST:** each feature with routes has its own controller; all share `Plugin::REST_NAMESPACE` (`wp-buyer-blacklist/v1`). Endpoints are private and capability-checked through `Access\OperatorAccess` (operators) or `Privacy\PrivacyDataAccess` (Administrator-only actions: settings, export, erase, delete).
- **Storage:** entries, Audit events and Blocked attempts live in dedicated tables (`Storage\Schema`, ADR 0002). A Blocked attempt belongs to one entry and is deleted with it.
- **Enforcement:** `Enforcement\CheckoutEnforcement` combines one blocker per Match type (email, customer ID, address). Each uses an `Active*EntryFinder` against Canonical values and records a Blocked attempt. It covers classic checkout and Store API / Checkout Block. wp-admin order editing and authenticated REST order creation are intentionally not blocked.
- **Views:** PHP-rendered markup lives in `templates/<feature>/`. Classes pass variables to a view and do not build HTML inline. Inline scripts go through `wp_add_inline_script`.
- **Admin UI:** React + TypeScript app at `assets/admin/` (SCSS under `assets/admin/styles/`), built by `@wordpress/scripts` into `build/`, using WordPress-provided React and `@wordpress/*` packages. The page has three tabs (Manage entries, Import & Export, Settings); new admin features go in the tab that owns their workflow. ADR 0001 says `src/admin`, but the code is in `assets/admin`.
