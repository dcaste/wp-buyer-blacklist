# Changelog

## 1.0.0

First release.

### Added

- Checkout enforcement for the classic checkout and the Checkout Block. An active entry that matches the Buyer's email, Customer ID, billing address or shipping address refuses the order before payment.
- A Buyer Blacklist page under WooCommerce with three tabs:
  - **Manage entries**: add email and address entries, search and filter the list, and open an entry to edit its reason, activate or deactivate it, and see its Audit history, Blocked attempts and current Buyer.
  - **Import & Export**: validate and import entries from a CSV file, download an example CSV, review recent imports, and export all entries.
  - **Settings**: the message a blocked Buyer sees, operator permissions, and whether to delete all data on uninstall.
- Enrollment from existing WooCommerce data: the Users screen (column, dialogs and bulk action) and the Orders list and order screen.
- Operator access for users who can manage WooCommerce, which an Administrator can turn off.
- An identity dossier download for an entry, and Administrator-only deletion of entries.
- Support for the WordPress personal-data export and erase tools.
- Translatable PHP and JavaScript strings under the `wp-buyer-blacklist` text domain.

### Requirements

- WordPress 6.9 or later
- WooCommerce 10.8 or later with High-Performance Order Storage
- PHP 8.3 or later
