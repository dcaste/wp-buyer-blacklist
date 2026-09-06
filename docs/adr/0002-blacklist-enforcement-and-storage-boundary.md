# Blacklist storage boundary

This record describes an implementation storage decision only. Product behaviour, including enforcement scope and wp-admin order handling, is defined by the [Product PRD](../prd/product.md) and is not overridden by this record.

Blacklist entries, Audit events, and Blocked attempts are stored in dedicated plugin tables. A Blocked attempt belongs to one matching Blacklist entry, so it can retain the checkout evidence required for that entry and be deleted with it during deletion or identity erasure.

## Considered Options

- WordPress options, post types, or metadata storage
- Dedicated tables for blacklist, Audit events, and Blocked attempts
