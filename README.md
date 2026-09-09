# WP Buyer Blacklist

WP Buyer Blacklist is a WooCommerce plugin that stops a Buyer from completing checkout when an active Blacklist entry matches one of the Buyer's identities. It is a manual list for one store. It is not a fraud-scoring service, a WordPress-user ban, or a shared fraud network.

## Requirements

- WordPress 6.9 or later
- WooCommerce 10.8 or later
- PHP 8.3 or later
- WooCommerce High-Performance Order Storage (HPOS)

The plugin supports classic checkout, Checkout Block, Store API checkout, and the Agentic Commerce checkout API when the installed WooCommerce version provides it.

In WordPress, go to **Plugins > Add New > Upload Plugin**, choose the plugin ZIP, select **Install Now**, then **Activate Plugin**. Activate WooCommerce first. Open **WooCommerce > Buyer Blacklist** to start. If WooCommerce is inactive, Administrators see a dependency notice and the plugin does not run Blacklist operations.

## Add and manage Blacklist entries

A **Blacklist entry** represents one Buyer identity. Each entry has one **Match type**, a reason, and an active or inactive status.

Use **WooCommerce > Buyer Blacklist** to add an entry manually. Manual entry supports email, billing address, and shipping address.

Use a WooCommerce Customer or Order enrollment flow to create an entry from existing WooCommerce data. Those flows can use email, WooCommerce customer ID, billing address, or shipping address. You can start enrollment from WooCommerce Customer and Order lists, their detail screens, and, for eligible WooCommerce Customers, the WordPress Users list. The Users-list action opens a dialog and shows the current Buyer data before creation.

The plugin prevents duplicate entries with the same Match type and Canonical value. If an entry already exists, reactivate or manage it instead of creating another one. Match type and value stay fixed after creation. To replace an identity, deactivate the old entry and create a new one.

An active entry blocks a matching Buyer. An inactive entry retains its reason, Audit events, and Blocked attempts but does not block checkout. A Blacklist operator can change a reason and activate or deactivate an entry. Deleting an entry requires confirmation and removes its associated Blocked attempts.

## Import entries from CSV

Open **Buyer Blacklist > Import and export** and select a UTF-8, comma-separated CSV file with a header row. The importer validates the file before it creates anything, then shows valid, duplicate, and rejected rows for review.

Supported columns are:

```text
match_type,email,customer_id,address_line_1,address_line_2,city,state,postcode,country,reason,status
```

`match_type` is required and must be `email`, `customer_id`, `billing_address`, or `shipping_address`. Email rows need `email`; Customer ID rows need `customer_id`; address rows need a complete structured address. `reason` defaults to `Imported blacklist entry`, and `status` defaults to `active`. See the downloadable example CSV in the Import and export screen.

## Checkout behaviour

At the enforcement boundary, the plugin checks every available identity for the Buyer: email, authenticated WooCommerce Customer ID, billing address, and shipping address. Address matching needs every required address field. A matching active entry stops checkout before payment or final-order creation.

The Buyer sees one configurable generic message. Administrators can change it in **Buyer Blacklist > Settings > Buyer-facing block message**. It accepts plain text only and never identifies the matching value, Match type, or reason. The default is:

```text
We’re unable to process your order. Please contact us for assistance.
```

Buyers can still browse and use the cart. The plugin does not block staff creating or editing orders in wp-admin, subscription renewals, authenticated WooCommerce REST API order creation, or other order-creation paths outside the supported checkout boundaries.

## Permissions and records

A **Blacklist operator** is an Administrator or, when the Administrator enables **Allow WooCommerce managers to manage Blacklist entries**, a user with WooCommerce management permission. Operators can create and manage entries, import CSV, see Blocked attempts, and enroll Buyers from the Users list.

Only an Administrator can configure settings, export Blacklist data, erase data, or delete entries. The full export is a UTF-8 CSV file. An identity dossier is a JSON file with its current entry, Audit events, and Blocked attempts.

Every entry creation, import, reason change, activation, deactivation, and deletion creates an **Audit event**. A **Blocked attempt** records each checkout stopped by an entry. The plugin does not record cart contents, payment data, or IP addresses.

Deleting a WooCommerce Customer does not remove a matching Blacklist entry. Administrators can erase plugin-held data through the plugin and WordPress personal-data tools. Deactivation keeps the data. Uninstall removes all plugin data only when an Administrator enabled the uninstall-cleanup setting first.

## Limits

Entries are created only by a Blacklist operator through manual enrollment or a confirmed CSV import. The plugin has no automatic enrollment rules, fraud scoring, automatic expiry, or checkout bypass.

It blocks supported storefront checkout paths only. It does not block staff creating or editing orders in wp-admin, subscription renewals, authenticated WooCommerce REST API order creation, or other unsupported order-creation paths.

## More detail

The [Product PRD](docs/prd/product.md) is the source of truth for matching rules, CSV validation, checkout behaviour, permissions, retention, and v1 limits. The [domain glossary](CONTEXT.md) defines the terms used by the plugin.

## Development checks

```sh
composer install
npm install
composer lint
composer test
npm test
```

PHP code follows [WordPress-Extra](https://github.com/WordPress/WordPress-Coding-Standards) via PHP_CodeSniffer. Run `composer lint:fix` to apply its safe formatting fixes.
