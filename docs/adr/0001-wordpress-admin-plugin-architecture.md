# WordPress admin plugin architecture

This record describes an implementation architecture direction only. Product behaviour is defined by the [Product PRD](../prd/product.md) and is not overridden by this record.

The plugin uses a conventional PHP bootstrap and dedicated WooCommerce admin page, with the admin interface implemented in React and TypeScript under `src/admin` and built with `@wordpress/scripts`. The admin screen is organized into three tabs: **Manage entries** for manual entry creation and entry-management views, **Import & Export** for CSV enrollment and exports, and **Settings** for the global Buyer block message and related settings. Future administration features must be placed in the tab that owns their workflow rather than appended to an unstructured screen. This uses WordPress-provided React and stable public `@wordpress/*` packages, generated asset metadata, and private capability-protected REST endpoints; `@wordpress/create-block` is not used because this is not a block plugin.

Business logic implemented in PHP and React components must have unit tests. Integration tests complement those unit tests where WordPress or WooCommerce hooks, persistence, checkout routes, or HPOS behavior must be exercised.

## Considered Options

- A PHP-rendered administration interface
- A block-plugin scaffold through `@wordpress/create-block`
- A conventional PHP plugin with a React/TypeScript admin application
