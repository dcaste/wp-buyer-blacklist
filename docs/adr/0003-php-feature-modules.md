# PHP feature modules

This record describes how the PHP code is organised. Product behaviour is defined by the [Product PRD](../prd/product.md) and this record does not change it.

PHP code lives in feature folders under `src/`. Each folder is a PSR-4 subnamespace of `WP_Buyer_Blacklist`, so `WP_Buyer_Blacklist\Entries\EntriesController` is in `src/Entries/EntriesController.php`. Folder names follow the language in `CONTEXT.md`.

| Folder         | Owns                                                                                  |
| -------------- | ------------------------------------------------------------------------------------- |
| `Admin/`       | The Buyer Blacklist admin page shell and its script and style enqueueing.             |
| `Settings/`    | Registering settings, the Buyer block message, and resetting settings to defaults.    |
| `Enforcement/` | Classic and Store API checkout validation, entry matching, and Blocked attempts.      |
| `Entries/`     | Blacklist entry values, their repository, Audit events, and the entry REST routes.    |
| `ImportExport/`| CSV import, CSV export, and import history.                                           |
| `Privacy/`     | Identity dossiers and the WordPress personal data exporter and eraser.                |
| `Access/`      | Deciding which Blacklist operators may manage entries.                                |
| `Enrollment/`  | Adding entries from the Users and Orders screens and the standalone enrollment page.  |
| `Storage/`     | Table names, install and uninstall.                                                   |

New code goes in the folder that owns its workflow. If no folder owns it, that is a reason to add one here, not to put it in `Plugin`.

`Plugin` is the composition root. It is built as `new Plugin( $wpdb )`, constructs every module by hand and calls each module's `register()`. It holds no feature logic. There is no DI container, because the object graph is small enough to read in one method.

Each feature that exposes REST routes has its own controller. All controllers share one namespace constant, `wp-buyer-blacklist/v1`.

PHP-rendered markup lives in view files under `templates/`, grouped by feature (for example `templates/settings/page.php`). Class methods pass a view the variables it needs and do not build HTML inline. Inline scripts go through `wp_add_inline_script`.

The React admin app currently lives in `src/admin/`. `src/Admin/` would differ from it only by case, and the two folders collide on macOS and Windows filesystems. The React source moves out of the PSR-4 root to `assets/admin/` before `src/Admin/` is created. This updates the path given in ADR-0001; the rest of that record stands.

## Considered Options

- Grouping by technical layer (`Admin/`, `Rest/`, `Checkout/`, `Storage/`). A change to one workflow would touch several folders, and the folders would not match the terms in `CONTEXT.md`.
- A DI container. It would hide the object graph behind configuration for a plugin with about a dozen services.
- Feature folders with a hand-written composition root.
