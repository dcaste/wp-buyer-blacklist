# Product PRD

This directory contains the v1 product requirements for WP Buyer Blacklist. It defines the product behaviour and acceptance criteria; it does not prescribe architecture, storage, hooks, or implementation choices.

## Read order

1. [Domain glossary](../../CONTEXT.md)
2. [Product PRD](product.md)

## Requirement IDs and implementation slicing

Requirements use stable `REQ-<AREA>-<NN>` identifiers. Implementation tickets should cover one requirement or one cohesive group, quote the relevant requirement and acceptance criteria, and link to the corresponding anchor in `product.md`. They must not reopen settled product decisions.

## Done checklist

- [x] The product problem, users, and v1 boundary are explicit.
- [x] Enrollment, matching, enforcement, CSV import, settings, permissions, audit, export, and privacy have requirement IDs and testable acceptance criteria.
- [x] [CONTEXT.md](../../CONTEXT.md) uses the same domain language.
- [x] V1 non-goals are explicit.
- [x] Product questions blocking v1 are resolved.
- [x] An implementation effort can be sliced from the requirements and acceptance criteria.
