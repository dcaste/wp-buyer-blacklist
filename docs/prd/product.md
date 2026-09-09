# WP Buyer Blacklist: v1 Product Requirements

## 1. Purpose

WP Buyer Blacklist helps a single-store WooCommerce merchant prevent repeat fraud, chargebacks, non-payment, compliance breaches, and operational abuse. The merchant maintains a deliberate list of Buyer identities and the plugin stops an active match at the defined purchase boundary.

This is a manual blacklist product, not a fraud-scoring service, a WordPress-user ban, or a shared fraud network.

## 2. Users

- **Administrator:** owns the store's sensitive blacklist operations and configuration.
- **Blacklist operator:** a user with the standard WooCommerce `manage_woocommerce` capability, normally an Administrator or Shop Manager, who performs day-to-day list work.
- **Buyer:** a person represented in checkout or order data. A Buyer can be a guest and need not have a WordPress user or WooCommerce Customer record.

## 3. Product boundary

V1 is for one store installation. It provides a plugin-admin list of **Blacklist entries**, not the WordPress Users list and not an analytics dashboard. Each entry represents one Buyer identity using one Match type. Default buyer-facing and admin copy is English; all strings are translatable.

The plugin blocks matching active entries at classic checkout, Checkout Block, Store API checkout, and Agentic Commerce checkout before payment or final-order creation. WooCommerce may create temporary checkout drafts or sessions before that boundary. Buyers may browse and use the cart. It does not enforce the blacklist when staff create, edit, or manage orders in wp-admin, on subscription renewals, through the authenticated WooCommerce REST API, or through other undefined order-creation paths.

The plugin does not implement its own checkout rate limiting or retain IP addresses, fingerprints, or request counters. Merchants may independently enable WooCommerce's Store API rate limiting.

V1 supports WordPress 6.9 or later, WooCommerce 10.8 or later, PHP 8.3 or later, and WooCommerce High-Performance Order Storage (HPOS). Agentic Commerce support covers the API available in those compatible WooCommerce versions; its experimental upstream status may require a compatibility update.

## 4. Functional requirements

### 4.1 Blacklist list and enrollment

#### REQ-ADM-01 — List and entry view

The plugin must provide an admin blacklist list and an entry view for Blacklist operators.

**Acceptance criteria**

- The list is distinct from the WordPress Users list and presents Blacklist entries.
- The list is server-paginated with 20 entries per page, ordered by most recently created first, and supports filters for status and Match type plus search by value or reason.
- An entry view shows its Match type and value, reason, active/inactive status, Audit events, and associated Blocked attempts.
- A Buyer identity may be associated with a Customer or Order but remains an entry even when it has no WordPress user or Customer.
- An entry created from a Customer or Order is an identity snapshot: its value and Canonical value do not change when the source data changes or is deleted. A source association is informational only.

#### REQ-ADM-02 — Enrollment from existing WooCommerce data

A Blacklist operator must be able to create entries by selecting existing Customers or Orders.

**Acceptance criteria**

- The operator can start enrollment from the Customers list (single or bulk selection), Customer detail, Orders list, and Order detail.
- The normal enrollment path selects an existing Customer or Order; it does not require the operator to type an identifier.
- The operator selects one available Match type; email is selected by default.
- A Match type with no usable value in the selected source is unavailable and explains why; the plugin does not create an incomplete entry.
- Manual enrollment requires a non-empty free-text reason before confirmation. Bulk enrollment applies that reason to every newly created entry.
- V1 has no preset reason codes.

#### REQ-ADM-03 — Manual enrollment of a known identity

A Blacklist operator must be able to add a known fraudulent identity when no suitable Customer or Order exists.

**Acceptance criteria**

- Manual entry supports email, billing address, and shipping address.
- Manual entry does not support WooCommerce customer ID; that identifier is selected from an existing Customer.
- The operator selects the Match type, supplies a value valid for that type, and supplies a non-empty free-text reason.
- Address entry uses structured physical-delivery fields, not one ambiguous free-text address.
- A country value is a two-letter ISO code recognized by WooCommerce. State and postal-code values are retained as supplied, subject to their Match type's canonicalization.

#### REQ-ADM-04 — Duplicate handling

The plugin must prevent duplicate entries for the same Match type and Canonical value.

**Acceptance criteria**

- A duplicate creation changes no existing entry and creates no new entry.
- In an individual flow, the operator is told that the entry exists and can open it.
- In a bulk flow, duplicates are skipped and reported; the entered bulk reason is not stored for skipped identities.
- A deactivated entry remains the existing entry and can be reactivated rather than duplicated.

#### REQ-ADM-05 — Entry lifecycle and editing

A Blacklist operator must be able to manage an entry after it is created.

**Acceptance criteria**

- The operator can change an entry's reason and switch it between active and inactive.
- An inactive entry does not block a Buyer and retains its history; it can be reactivated.
- The Match type and value cannot be edited. Changing either requires removal of the old entry and creation of a new entry.
- Deletion requires confirmation, removes the entry and its associated Blocked attempts, and leaves only the minimal non-personal deletion Audit event.

### 4.2 Matching

#### REQ-MATCH-01 — One Match type per entry

Each Blacklist entry must use exactly one of these Match types: email, WooCommerce customer ID, billing address, or shipping address. Email is the default Match type.

**Acceptance criteria**

- The product does not combine identifiers in one entry or evaluation.
- Email comparison ignores case and surrounding whitespace.
- A customer ID is a positive integer.
- An address comparison uses the normalized exact physical-delivery fields: street lines, city, state, postal code, and country.
- Address canonicalization trims field values, collapses internal whitespace, compares values without case distinction, and uppercases the country. It does not infer equivalent abbreviations, accents, or postal-code formats.
- Duplicate detection uses Match type and Canonical value.
- Checkout enforcement evaluates every available identity value in the current checkout: email, authenticated WooCommerce customer ID, billing address, and shipping address.
- An address is evaluated only when all of its required fields are available; incomplete addresses do not produce a partial address match.

### 4.3 Checkout enforcement and buyer message

#### REQ-ENF-01 — Enforce active matches at the purchase boundary

The plugin must stop a matching active Buyer at storefront checkout before payment or order creation.

**Acceptance criteria**

- A matching active entry prevents classic checkout, Checkout Block, Store API checkout, and Agentic Commerce checkout from completing payment or creating a final order.
- The plugin permits temporary checkout drafts or sessions created by WooCommerce before that boundary.
- Browsing and cart use remain available.
- An inactive entry does not block.
- wp-admin order creation, editing, and management remain available without an `ignore blacklist` control.

#### REQ-ENF-02 — Buyer message

The plugin must show one global configurable buyer-facing message when it blocks checkout.

**Acceptance criteria**

- The same configured message is used for every block.
- The message does not reveal the matched value, Match type, reason, or any other evidence that would disclose why the Buyer was blocked.
- The default message is `We’re unable to process your order. Please contact us for assistance.` and the setting accepts plain text only.
- Store API and Agentic Commerce responses use that same message with a generic checkout error and disclose no entry data.
- The message and admin UI strings are translatable.
- When WooCommerce is unavailable, the plugin shows an Administrator-only notice and performs no blacklist operations until WooCommerce is available.

### 4.4 CSV import

#### REQ-CSV-01 — Manual CSV import

A Blacklist operator must be able to run a repeatable, manually initiated CSV import. It is neither scheduled nor synchronized with an external list.

**Acceptance criteria**

- The importer accepts a UTF-8, comma-separated file with a header row.
- UTF-8 files with or without a byte-order mark and with LF or CRLF line endings are accepted. Completely empty rows are ignored.
- It supports `match_type`, `email`, `customer_id`, `address_line_1`, `address_line_2`, `city`, `state`, `postcode`, `country`, `reason`, and `status`; column order is arbitrary and unknown columns are ignored.
- `match_type` is required and is `email`, `customer_id`, `billing_address`, or `shipping_address`.
- Email rows require `email`; customer-ID rows require `customer_id`; address rows require `address_line_1`, `city`, `state`, `postcode`, and `country`, with `address_line_2` optional.
- A missing or blank `reason` becomes `Imported blacklist entry`.
- `status` is optional. Its accepted values are `active` and `inactive`; a missing or blank value defaults to `active`.
- A valid row creates an entry with its stated Match type, status, and type-specific canonicalization.

#### REQ-CSV-02 — Import validation and result

The import flow must validate before confirmation and report a partial result.

**Acceptance criteria**

- The operator selects a file, reviews counts and validation errors, then explicitly confirms the import.
- The importer completes validation before confirmation. After confirmation it creates entries in batches; an unexpected technical failure stops the import and is reported as a failure, not as a successful partial result.
- A valid non-duplicate row is imported; an invalid row creates no entry and reports its row number and reason.
- An existing Match type plus Canonical value is skipped without modification and reported as a duplicate.
- Within one file, the first valid occurrence is accepted and later equivalent occurrences are duplicates.
- The admin UI reports counts for created, duplicate, and rejected rows, with a per-row reason for rows not imported.
- Duplicate creations and validation failures appear in the immediate operation result but do not create Audit events.
- There is no product row limit; imports process in batches while retaining the defined partial-result behaviour.
- V1 has no import undo or result-file download.

### 4.5 Audit and blocked attempts

#### REQ-AUD-01 — Administrative audit trail

The plugin must retain an Audit event when an entry is created, imported, has its reason changed, is activated, deactivated, or deleted.

**Acceptance criteria**

- An Audit event identifies the date and time, acting administrator, and action.
- Audit event and Blocked attempt times are stored in UTC and displayed in the store's configured time zone.
- For ordinary administrative actions, the event stores the acting WordPress user ID and a snapshot of that user's display name, but not the user's email address.
- The reason is stored on the entry and need not be copied to every Audit event unless the reason changed.
- Audit data is immutable while it retains personal data.
- Entry deletion or a privacy erasure retains only the minimal non-personal indication that deletion or erasure occurred.
- That minimal indication contains only its date/time and action type; it retains no actor, Match type, or identity value.

#### REQ-AUD-02 — Blocked-attempt log

The plugin must retain a Blocked attempt for a blocked storefront checkout and make it visible to Blacklist operators.

**Acceptance criteria**

- A Blocked attempt contains date/time, the evaluated identity value, blocked outcome, and matching Blacklist entry.
- When multiple active entries match one checkout, the plugin retains one Blocked attempt for each matching entry.
- Each request that reaches the enforcement boundary creates a new Blocked attempt for each matching entry; the plugin does not deduplicate reattempts.
- It does not retain cart contents, payment data, or IP address.
- It remains available while its Blacklist entry exists, including while that entry is inactive.
- V1 has no automatic retention expiry and sends no email, order-note, or other administrator notification for a blocked attempt.

### 4.6 Export and privacy

#### REQ-PRIV-01 — Export

An Administrator must be able to export the full blacklist and the complete dossier for one identity.

**Acceptance criteria**

- A full-list export includes the current Blacklist entries, including active and inactive entries.
- An identity dossier includes its current entry, change history, and associated Blocked attempts.
- The full-list export is a UTF-8 CSV file with a header row. An identity dossier is a structured JSON file.
- Only an Administrator can initiate either export.

#### REQ-PRIV-02 — Customer deletion and identity erasure

Deleting a WooCommerce Customer must not automatically delete a matching Blacklist entry. An Administrator must be able to erase plugin-held data for one identity.

**Acceptance criteria**

- A matching entry remains after WooCommerce deletes the Customer account.
- Erasure removes the identity's entry and associated Blocked attempts.
- Erasure retains only a minimal non-personal Audit indication that it happened.
- The plugin also registers with WordPress personal-data export and erasure tools for identities found by email. The plugin's own interface remains available for Customer ID and address entries.
- Only an Administrator can perform erasure.
- Deactivation retains all plugin data. Uninstallation removes it only when an Administrator has explicitly enabled a setting to remove all plugin data on uninstall.

### 4.7 Permissions and settings

#### REQ-PERM-01 — Configurable Blacklist operator access

V1 must use standard WordPress and WooCommerce capabilities only; it creates no custom role or custom role-management UI.

**Acceptance criteria**

- The "Allow WooCommerce managers to manage Blacklist entries" setting is enabled by default. When it is enabled, a user with `manage_woocommerce` can view the list, create and manage entries, import CSV, view Blocked attempts, and enroll Buyers from the Users list.
- When the setting is disabled, those operations require `manage_options`.
- Users with `manage_options` can perform all day-to-day operations.
- Users with `manage_options` exclusively export, erase data, configure settings, and configure blacklist permissions.

#### REQ-PERM-02 — Delegable buyer-message editing

An Administrator must be able to optionally allow `manage_woocommerce` users to change the global buyer message.

**Acceptance criteria**

- The delegation is disabled by default.
- When disabled, only an Administrator can change the message.
- When enabled, `manage_woocommerce` users can change the message.
- Export and erasure remain Administrator-only regardless of this setting.

## 5. V1 non-goals

V1 does not include:

- administrator notifications, dashboards, charts, risk scoring, or automatic enrollment rules;
- a shared cross-store or multisite blacklist, cross-installation synchronization, or third-party fraud, payment, device-intelligence, or card-fingerprint services;
- phone, IP, combined identifiers, exemptions, bypass mechanisms, automatic expiry, or automatic identity merging;
- custom roles or custom role management;
- subscription-renewal enforcement, scheduled CSV imports, external list synchronization, CSV column mapping, import undo, or a result-file download; or
- silent, deceptive, or fake technical buyer-facing block errors.

## 6. Deferred questions

None block v1. Product positioning, screenshots, additional Match types, notifications, analytics, and automation may be considered in separate future work.
