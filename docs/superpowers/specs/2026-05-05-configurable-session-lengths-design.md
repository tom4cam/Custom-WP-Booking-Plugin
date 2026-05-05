# Configurable Session Lengths — Design

**Date:** 2026-05-05
**Status:** Approved (pending plan)
**Scope:** Admin Sessions tab, related pricing UIs, homepage template

## Goal

Let Ryan offer session lengths beyond the hardcoded 30 / 60 / 90 (e.g. 15, 45,
75, 120 min) by checking boxes in the existing Sessions tab. New lengths flow
automatically into the booking widget, admin pricing, Venmo/Square charging,
notifications, calendar events, and the homepage Services & Pricing cards.

## Non-goals

- Arbitrary user-typed durations (e.g. 23 min). Only standard 15-min
  increments are supported.
- A repeater UI for adding/removing rows.
- Per-recurring-schedule pricing.
- A filter hook for white-label overrides (can be added later if needed).
- The shared-calendar event color change (separate spec).

## Approach

Single source of truth helper + render-only-enabled rows. Smallest possible
diff that keeps existing settings working unchanged.

### Source of truth

Add to `caswell-booking.php` near `caswell_enabled_session_lengths()`:

```php
function caswell_session_length_options() {
    return [ 15, 30, 45, 60, 75, 90, 105, 120 ];
}
```

Plain function (not a constant) for future filterability without committing
to it now.

### Sites that change

Replace the literal `[ 30, 60, 90 ]` everywhere it appears:

| File | Line | Use |
| --- | --- | --- |
| `caswell-booking.php` | 226 | `caswell_enabled_session_lengths()` — loop over options |
| `admin/admin-page.php` | 103 | Sessions tab "Enabled Lengths" checkboxes |
| `admin/admin-page.php` | 115 | Sessions tab "Default Session Length" dropdown |
| `admin/admin-page.php` | 278 | Payments tab Venmo/Square charge prices |
| `admin/admin-page.php` | 509 | Branding tab Service Price + Description |
| `includes/class-admin.php` | 67 | Sanitizer for `enable_{len}min` |
| `includes/class-admin.php` | 86 | Sanitizer for `venmo_price_{len}` |
| `includes/class-admin.php` | 176 | Sanitizers for `service_price_{len}` and `service_description_{len}` |
| `page-caswell-home.php` | 96 | Homepage Services & Pricing cards |

### Admin UI behavior

**Sessions tab:**

- "Enabled Lengths" — checkbox per option from
  `caswell_session_length_options()`. Layout in two visual rows of four to
  keep it compact.
- "Default Session Length" — dropdown lists only currently-enabled lengths
  (i.e. uses `caswell_enabled_session_lengths()`), so Ryan can't pick a
  default he just disabled.

**Payments tab → Venmo prices:**

- Loop over `caswell_enabled_session_lengths()` instead of the hardcoded
  list. A row appears/disappears for each enabled length after a Save.
- Add a small `<p class="description">` above the rows: *"Add or remove
  session lengths in the Sessions tab."*

**Branding tab → Service Pricing:**

- Same: loop over `caswell_enabled_session_lengths()`. Same hint text above
  the rows.

### Sanitizer behavior

All four sanitizer loops in `class-admin.php` iterate
`caswell_session_length_options()`. They unconditionally read every key
from `$input`, so disabled lengths still get their pricing data preserved
(hidden but not lost) — matches current behavior, where toggling
`enable_60min` off doesn't wipe `service_price_60`.

**Default-length sanitizer:** if `default_session_length` isn't in the
post-save enabled set, fall back to the lowest enabled length (numeric
ascending — first element of `caswell_enabled_session_lengths()`), or
60 if nothing is enabled (matches the existing `[ 60 ]` fallback in that
helper). Silent — no settings error.

### Homepage template

Replace the hardcoded `$sessions = [ 30 => …, 60 => …, 90 => … ]` array
with a loop over `caswell_enabled_session_lengths()`. For each enabled
length:

- Card heading: `"{N} min"` (matches existing display style on line 112).
- Description `<p>`: render only if `service_description_{len}` is set.
  Drop the hardcoded English fallbacks ("Focused treatment targeting…").
  Production already has user-set descriptions for 30/60/90, so no
  visible regression.
- Price line and "Book This Session" button: unchanged.

### Migration / compatibility

None needed. Existing settings keys (`enable_30min`, `service_price_60`,
`venmo_price_90`, etc.) keep working — the sanitizer just iterates more
keys now. New keys are absent in `wp_options` until Ryan checks the
corresponding box and saves.

`caswell_db_version` stays put. No DB schema change.

## Verified safe

The following downstream code already accepts any whole-minute length and
needs no change:

- `wp_caswell_bookings.session_length` — `SMALLINT`
- `Caswell_Google_Calendar::windows_to_slots()`,
  `Caswell_Google_Calendar::is_slot_available()`
- `Caswell_Booking_Handler::charge_square()` — reads
  `venmo_price_{$session_length}` dynamically
- `Caswell_Recurring` — passes `session_length` straight through
- `Caswell_Notifications` — reads length from the booking row
- `caswell_render_event_title()` — substitutes `{duration}` from the
  booking row
- Admin manual booking + reschedule (`class-admin.php:301`) already uses
  `max( 15, absint( … ) )`

## Open questions

None.

## Risks

- **Homepage description fallback removal.** If a future install enables a
  length without setting a description, the card has no descriptive text.
  Mitigation: production has descriptions set; mention in
  `docs/Admin-Settings.md` that description is recommended.
- **Default-length silent fallback.** If Ryan disables 60 (current
  default), the dropdown will show whatever the first remaining enabled
  length is. Acceptable — disabling the default is an unusual action and
  the alternative (a settings error blocking save) is more annoying.

## Docs to update in the same change

- `docs/Admin-Settings.md` — Sessions tab section (note the wider list of
  options and that pricing rows follow enabled lengths).
- `DEVELOPER.md` — if it documents the literal `[30, 60, 90]` list, update
  it to point at `caswell_session_length_options()`.
