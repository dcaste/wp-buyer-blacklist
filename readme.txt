=== WP Buyer Blacklist ===
Tags: woocommerce, checkout, blacklist, fraud, orders
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Stops listed Buyers from completing checkout in a WooCommerce store.

== Description ==

You keep a list of Blacklist entries. Each entry names one Buyer identity: an email address, a WooCommerce Customer ID, a billing address or a shipping address. When an active entry matches the Buyer at checkout, the order is refused before payment. This works in the classic checkout and in the Checkout Block.

The list belongs to one store and you fill it by hand. The plugin does not score orders for fraud, share a list with other stores, or ban WordPress user accounts.

Find it under **WooCommerce > Buyer Blacklist**. It has three tabs: Manage entries, Import and export, and Settings. You can also enroll a Customer from the Users screen, or an order's Buyer from the Orders screens.

The full guide, screenshots and source code are on GitHub: https://github.com/dcaste/wp-buyer-blacklist

== Installation ==

1. Download `wp-buyer-blacklist-1.0.0.zip` from the latest release on GitHub. Do not use the "Source code" download.
2. In WordPress, go to **Plugins > Add New > Upload Plugin**, choose the ZIP and select **Install Now**.
3. Select **Activate Plugin**.

WooCommerce must be active, with High-Performance Order Storage turned on. WordPress will not activate the plugin without WooCommerce.

To update, install the newer ZIP the same way. WordPress asks whether to replace the current version. Your entries stay in the database.

== Changelog ==

= 1.0.0 =
* First release.
* Checkout enforcement for email, Customer ID, billing address and shipping address entries.
* Manage entries, Import and export, and Settings tabs.
* Enrollment from the Users screen and the Orders screens.
* CSV import with validation, CSV export and an example file.
* Audit history and Blocked attempts for each entry.
* Support for the WordPress personal-data export and erase tools.

The same history is in CHANGELOG.md in the repository.
