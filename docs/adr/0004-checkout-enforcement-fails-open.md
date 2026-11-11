# Checkout enforcement fails open

This record describes what checkout enforcement does when it cannot read Blacklist entries. The rest of enforcement behaviour is defined by the [Product PRD](../prd/product.md) and this record does not change it.

At the enforcement boundary, each Match type looks up active Blacklist entries with the Buyer's Canonical value. If that lookup fails, for example because the database returns an error, the Match type counts as not matched. Checkout continues as if no entry matched, no Blocked attempt is recorded, and the Buyer sees no block message.

Every failed lookup is written to the WooCommerce log at the `error` level with the source `wp-buyer-blacklist`, so an operator can find it under **WooCommerce > Status > Logs**. The log line names the Match type and the database error. It never includes the Buyer's email, Customer ID or address. A database error can quote the SQL, which holds only the SHA-256 hash of the Canonical value.

The other Match types are still checked. A failure in one lookup doesn't stop another Match type from blocking the Buyer.

## Considered Options

- **Fail closed.** Refuse the order whenever a lookup fails. No listed Buyer gets through during an outage, but any database problem stops every checkout in the store, including all Buyers who aren't listed. The plugin would turn a storage fault into a full sales outage.
- **Fail open.** Let the order proceed and log the failure. A listed Buyer could complete checkout while the lookup is failing. The store keeps selling to everyone else.

The maintainer chose fail open. A blacklist is a fraud and abuse control for a small number of Buyers, and a missed block can be handled after the fact (cancel or refund the order). A checkout outage for every Buyer can't.

## Consequences

- `EntryRepository` treats a lookup that sets `$wpdb->last_error` as returning no entries, and logs it.
- `EnforcementFailureIntegrationTest` breaks the lookup query and checks that classic checkout and the Store API both proceed.
- An operator who needs to know about missed blocks has to watch the WooCommerce log. The plugin sends no other alert.
