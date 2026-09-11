# Buyer Blacklist admin UI specification

## Purpose and source material

This document is the implementation contract for the Buyer Blacklist admin UI.
The visual references are:

- `docs/UI/manage-entries.png`: Manage entries.
- `docs/UI/import-export.png`: Import & Export.
- `docs/UI/settings.png`: Settings.
- `docs/UI/box.png`: entry management dialog.
- `docs/UI/users-list.png`: Buyer Blacklist column on the native WordPress Users list.

Use the references for visual hierarchy and component placement. Preserve WordPress
admin navigation, capability checks, nonces, notices, form semantics, and familiar
button/input behaviour. This is a WordPress admin screen, not a separate application shell.

The product vocabulary in `CONTEXT.md` is binding. In particular, write "Buyer",
"Blacklist entry", "Blacklist operator", "Match type", "Canonical value", "Blocked
attempt", and "Audit event". Do not substitute "customer", "record", "ban", or "log"
where those terms have a defined meaning.

## Visual tokens

Use these values consistently. They are the reviewed implementation target derived
from the supplied screens, not suggestions for a new theme.

| Token | Value | Use |
| --- | --- | --- |
| `--wpbb-page` | `#f7f9fc` | Admin-page background |
| `--wpbb-surface` | `#ffffff` | Cards, dialogs, inputs |
| `--wpbb-surface-subtle` | `#f8fafc` | Table headers, quiet panels |
| `--wpbb-border` | `#dce3ee` | Decorative card and table rules |
| `--wpbb-input-border` | `#64748b` | Input and select boundaries |
| `--wpbb-text` | `#0f172a` | Headings and primary text |
| `--wpbb-muted` | `#64748b` | Help text and secondary text |
| `--wpbb-primary` | `#1463f3` | Selected tab, primary action, links |
| `--wpbb-primary-hover` | `#0c52d4` | Hovered primary control |
| `--wpbb-focus` | `#1463f3` | 2px visible focus outline |
| `--wpbb-success` / `--wpbb-success-bg` | `#166534` / `#dcfce7` | Active and completed states |
| `--wpbb-danger` / `--wpbb-danger-bg` | `#b42318` / `#fee2e2` | Destructive, failed, and Blacklisted states |
| `--wpbb-warning` / `--wpbb-warning-bg` | `#92400e` / `#fef3c7` | Warning and irreversible cleanup states |
| `--wpbb-neutral` / `--wpbb-neutral-bg` | `#475569` / `#f1f5f9` | Inactive and Not listed states |

Use the system admin font stack. Type sizes are 28px/34px for the page title,
20px/28px for card titles, 16px/24px for section headings, 14px/20px for body
text and labels, and 12px/18px for helper text and status chips. Use weight 700
for titles, 600 for labels and actions, and 400 for supporting copy.

The spacing scale is 4, 8, 12, 16, 24, and 32px. Cards use 24px padding on wide
screens and 16px on narrow screens, an 8px radius, a 1px `--wpbb-border` border,
and `0 1px 2px rgb(15 23 42 / 8%)` shadow. Inputs and buttons use a 6px radius.
Status chips use a 9999px radius. Do not add gradients or decorative animation.

## Shared structure

Every Buyer Blacklist page starts with the WordPress page heading "Buyer Blacklist"
and the subtitle "Block problematic buyers from placing orders in your store." It
then shows the existing three-tab navigation:

- Manage entries
- Import & Export
- Settings, visible only to an Administrator with the existing privacy-data permission

The selected tab has primary-blue text and a 3px primary-blue bottom rule. The
unselected tabs retain a clear border and hover state. Keep WordPress Screen
Options and adjacent admin controls untouched.

Use a wide content layout. At 960px or below, two-column card layouts become one
column. Tables stay semantic tables; wrap their container horizontally when needed
instead of hiding data or replacing it with unlabeled cards.

## Icon inventory

Custom SVG files provide the large 24px icon inside a 56px pale background tile.
They are decorative and must have `aria-hidden="true"` because adjacent text names
the meaning.

| Asset | Location |
| --- | --- |
| `entries.svg` | Total entries summary card |
| `active.svg` | Active entries summary card |
| `inactive.svg` | Inactive entries summary card |
| `blocked-attempts.svg` | Blocked attempts summary card and dialog panel |
| `upload.svg` | Import & Export export/import cards and drop target |
| `message.svg` | Buyer-facing block message settings card |
| `permissions.svg` | Permissions settings card |
| `data-cleanup.svg` | Data & cleanup settings card |
| `notifications.svg` | Notifications card, only when notification support ships |
| `audit-history.svg` | Audit history dialog panel |

Use WordPress Dashicons for compact controls: `trash`, `email-alt`, `download`,
`cloud-upload` or `upload`, `search`, `no-alt`, `location-alt`,
`info-outline`, `warning`, `update`, `arrow-left-alt2`,
`arrow-right-alt2`, and `arrow-down-alt2`. The supplied notification SVG
includes animation markup. Render it without animation, including when
`prefers-reduced-motion` is enabled.

## Manage entries

The Manage entries screen has four equal summary cards in this order: Total
entries, Active entries, Inactive entries, and Blocked attempts. Values are real
aggregates, never hard-coded sample numbers. Each card includes a short
plain-language explanation.

Below the summary is an initially open "Add new Blacklist entry" card. It has a
keyboard-operable disclosure control with an accessible expanded state. The form
shows Match type and its relevant identity fields, a reason field marked optional,
a primary "Add entry" action, and a secondary "Import CSV" link to the Import &
Export tab. Match types must reflect supported manual creation endpoints. Do not
show WooCommerce customer ID as manually creatable until the backend supports it end to end.

The "Blacklist entries" card contains a search field, Match type and status filters,
a Reset action, sortable Updated column, pagination, and a semantic table. The
columns are selection, Match type, Canonical value, Reason, Status, Updated, and
Actions. Address Canonical values show their readable multi-line form with a
"View details" control. Status always includes words such as Active or Inactive,
not color alone. The Manage button opens the entry dialog.

## Entry management dialog

Use the native dialog element with a labelled heading, close button, backdrop, and
focus management. On open, focus the dialog heading or first actionable control.
Escape and the close control dismiss it without saving. Restore focus to the Manage
button that opened it.

At wide widths, show a two-column layout. The left column holds the entry identity
card, status, Buyer details when uniquely identified, editable reason with a
500-character counter, an accessible Active switch, and footer actions. The right
column holds Audit history and Blocked attempts. At 960px or below, show the same
sections in one column.

The entry identity card shows Match type, Canonical value, created time, entry
identifier, and an Active or Inactive text chip. Buyer details must clearly say
when there is no uniquely identified registered Buyer or an address is not provided.
Audit events form a chronological timeline. Blocked attempts have a count and an
explicit empty state. Deleting requires a clear confirmation and uses danger
styling. "Export dossier" is secondary. "Save changes" is the only primary footer action.

## Import & Export

The first row has an Export Blacklist entries card and an Import blacklist entries
card. Export downloads the complete Blacklist CSV. Import accepts a CSV through a
labelled Choose file control or drag and drop. State the accepted type and 5MB
maximum next to the target and enforce the size before validation.

The user sees the existing validation result before confirmation: valid rows,
duplicates, rejected rows, reasons, and a distinct Confirm import action. Clear
success, warning, failure, and empty states all include text as well as color. The
example CSV download appears in the import card.

The supplied Recent imports table is required only when durable import history is
available. It lists date and time, file name, entries found, imported, skipped,
status, and a View details action. Its sample filenames, counts, dates, and statuses
are illustrative. Do not fabricate history when no persisted history exists.

## Settings

The Buyer-facing block message card has a textarea and a live, non-editable preview
of the Buyer-facing checkout error. Preserve the configured message exactly in
the preview, with line breaks shown. The preview is not proof that a checkout
submission has been attempted.

The Permissions card controls whether users with WooCommerce management permission
can manage Blacklist entries. The Data & cleanup card controls deletion on plugin uninstall. Its warning
says that data deletion is permanent and suggests export first. Save changes is
primary. Reset to defaults is secondary and requires confirmation before replacing
current settings.

The Notifications card in the reference is future scope. It ships only with
blocked-attempt email notification behavior and a validated recipient setting. Do
not render a non-functional notification toggle in the core settings work.

## Users list

The Buyer Blacklist column in the native WordPress Users table uses compact chips
for Not listed, Inactive, and Blacklisted. Each chip names the state and is paired
with an Enroll or Manage text link when the current user can act. Blacklisted has
an accessible explanation that the Buyer cannot place new orders. It may be a
tooltip, but must also be available through keyboard focus and an accessible name
or description. Do not alter unrelated Users-list controls.

## Accessibility and state rules

- Every interactive element is reachable by keyboard and has a 2px focus ring with
  at least 2px offset.
- Labels remain visible for fields. Placeholder text does not replace a label.
- Server and client success, warning, and error notices use an appropriate live
  region and are not color-only.
- Toggle controls expose their checked state and associated setting name.
- Icons alone never carry action or status meaning. Icon-only buttons have an
  accessible name.
- Dialogs trap focus while open and prevent background interaction.
- Respect `prefers-reduced-motion`; no state depends on motion.
- Maintain at least 4.5:1 text contrast and 3:1 contrast for non-text UI
  boundaries and focus indicators.

## Scope boundaries

Existing behavior includes Blacklist entry creation and management, CSV validation
and confirmation, CSV and dossier export, Buyer context, Audit events, Blocked
attempts, operator permissions, and uninstall cleanup.

New backend work is required for summary aggregates, durable Recent imports, and
blocked-attempt email notifications. Do not represent any of these as already
implemented. The reference screens contain fictional names, dates, counts, reasons,
and email addresses. They exist to demonstrate presentation, not to define
production data.
