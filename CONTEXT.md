# Buyer Blacklist

This context defines the product language for preventing specified Buyers from placing WooCommerce orders.

## Language

**Buyer**:
A person represented in WooCommerce checkout or order data who may be subject to blacklisting. A Buyer can be a guest and need not have a WordPress user or WooCommerce Customer record.
_Avoid_: Customer, client, account, user

**Blacklist entry**:
A record for one Buyer identity that can block purchase when active. It has exactly one Match type, a reason, and an active or inactive status; it has no expiry.
_Avoid_: Blacklist record, ban

**Blacklist operator**:
An Administrator or a user with WooCommerce management permission whom an Administrator allows to manage Blacklist entries. An operator cannot delete, erase, or export Blacklist data.
_Avoid_: Store manager, moderator

**Match type**:
The one identifier category used by a Blacklist entry: email, WooCommerce customer ID, billing address, or shipping address.
_Avoid_: Identifier combination, matching rule, signal set

**Canonical value**:
The normalized value of a Blacklist entry's Match type used for comparison and duplicate detection.
_Avoid_: Raw value, normalized identifier

**Address**:
The structured physical-delivery fields used by a billing-address or shipping-address Match type: street lines, city, state, postal code, and country.
_Avoid_: Location, free-text address

**Blocked attempt**:
A record that a matching active Blacklist entry stopped a Buyer at the enforcement boundary.
_Avoid_: Customer notification, failed order

**Audit event**:
A record of an administrative action affecting blacklist data or settings. It is distinct from a Blocked attempt.
_Avoid_: Log message, buyer notification

**Enforcement boundary**:
The purchase point where an active matching entry is evaluated: the classic checkout, Checkout Block, Store API, or Agentic Commerce checkout. It prevents payment and final-order creation, while allowing any temporary checkout state required by WooCommerce.
_Avoid_: Checkout type, request source, order-creation hook
