# Shared-Calendar Event Color — Design

**Date:** 2026-05-05
**Status:** Approved (pending plan)
**Scope:** Google Calendar event creation for the shared calendar only

## Goal

Color every event the plugin writes to the **shared** Google Calendar
"Peacock" (Google Calendar `colorId` 7) so Ryan's bookings are
visually distinct from other practitioners' on the Glow shared
calendar. Personal-calendar events stay uncolored (default Google
event color).

## Non-goals

- Configurable color (this is hardcoded for now; trivial to promote to
  a setting later if anyone asks).
- Color changes on existing events. Only events created after the
  change ship are colored. Old events keep whatever color they have
  (none, in practice). PATCH-based reschedules don't change the
  color — Google Calendar preserves the original `colorId` on update.
- Coloring the personal-calendar event (`enable_primary_calendar_event`
  path). Out of scope by user request — Ryan's personal calendar
  stays uncolored.

## Approach

Hardcoded constant + optional parameter on `create_event()`. The
booking handler and admin "new booking" handler pass the constant
when writing to the shared calendar. Personal-calendar `create_event`
calls don't pass it.

### Constant

Add to `caswell-booking.php` near the other constants:

```php
const CASWELL_SHARED_EVENT_COLOR_ID = '7'; // Peacock — Google Calendar
```

String, because Google Calendar's API takes `colorId` as a string.

### Helper signature change

In `includes/class-google-calendar.php:380`, extend
`create_event()` with an optional `$color_id` parameter:

```php
public function create_event(
    $calendar_id,
    $title,
    DateTime $start,
    DateTime $end,
    $description = '',
    $color_id = ''
) {
    // ...
    $body = [ /* existing fields */ ];
    if ( '' !== $color_id ) {
        $body['colorId'] = (string) $color_id;
    }
    // ...
}
```

Default `''` (not `null`) so the existing call shape is
backward-compatible — every current caller stays valid without edits.

### Call-site updates

Three sites write to the shared calendar today. All three pass
`CASWELL_SHARED_EVENT_COLOR_ID`:

| File | Line | Path |
| --- | --- | --- |
| `includes/class-booking-handler.php` | 310 | Self-service booking — shared event |
| `includes/class-admin.php` | 642 | Admin manual "new booking" — shared event |
| `includes/class-google-calendar.php` | 507 | `test_shared_calendar_write()` — settings-page test write |

Two sites write to the **personal** calendar today. They stay
unchanged:

| File | Line | Path |
| --- | --- | --- |
| `includes/class-booking-handler.php` | 300 | Self-service booking — primary event |
| `includes/class-admin.php` | 634 | Admin manual "new booking" — primary event |

### Reschedule path

`update_event()` (`class-google-calendar.php:441`) is unchanged.
Google Calendar's PATCH preserves any field not present in the
request body, so the original `colorId` stays put across reschedules.

## Migration / compatibility

None. New events get the color; old events stay as they are.
No setting, no schema change, no admin UI.

## Risks

- **Wrong shade.** If "Peacock" turns out to be the wrong blue in
  practice (sometimes Google Calendar's colors look different on
  different clients), swapping to Blueberry (id 9) is a one-character
  change to the constant.
- **Color invisible if shared calendar uses calendar-level color
  override.** Some Google Calendar clients display every event in a
  calendar's color regardless of `colorId`. This is a per-viewer
  setting on Google's side; nothing we can do about it from this
  plugin. Acceptable — Ryan and the Glow team are the relevant
  viewers and they'll see the per-event color.
- **Existing uncolored events stay uncolored.** Acceptable per
  non-goals.

## Out of scope (could be follow-up specs)

- Backfill: a one-shot button "color all my future shared-calendar
  events Peacock" that PATCHes existing events with the colorId.
- Configurable color (admin setting in Settings → Google Calendar).
- Different colors per practitioner in a multi-practitioner install.

## Docs to update in the same change

- `docs/Google-Calendar.md` — add a one-line note that bookings appear
  in Peacock on the shared calendar.

## Open questions

None.
