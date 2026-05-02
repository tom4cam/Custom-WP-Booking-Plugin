# Google Calendar

The plugin integrates with Google Calendar to determine real-time appointment availability. This is handled by the `Caswell_Google_Calendar` class in `includes/class-google-calendar.php`.

## OAuth2 Authentication

The plugin uses OAuth2 with a refresh token to authenticate with the Google Calendar API. Tokens are obtained via the refresh token and cached as WordPress transients to minimize API calls.

Credentials (client ID, client secret, refresh token) are stored encrypted in the database. See [[Admin-Settings]] for encryption details.

## Availability Logic

Slot availability is calculated in four steps:

### 1. Fetch Available Windows

A day's open windows are determined by, in order of precedence:

- **Weekly schedule (admin override)** — if a day is explicitly enabled in Settings → Availability → Weekly Schedule, that schedule **replaces** all other sources for that day.
- **Default availability + Glow events** — when "Open by default" is on (Settings → Availability → Default Availability; on by default), days are open during the configured working hours (default 07:00–22:00) and any "Glow" events on the shared calendar are merged in (so they can extend availability outside working hours).
- **Glow events only** — when "Open by default" is off, only events whose title contains the keyword (e.g., "Glow") on the shared calendar define open windows.

Personal calendar events, existing bookings, and admin time blocks always subtract from whatever window is active (see step 2).

**Personal calendar transparency:** events on the personal calendar marked **Free** (Google Calendar's "Show me as: Free", `transparency=transparent` in the API) are skipped — they do **not** block bookings. Use this to put informational items on your calendar (reminders, all-day flags, travel dates kept "Free") without taking yourself off the booking grid.

**Shared-calendar blocking events:** every event on the shared calendar that is *not* a Glow event is treated as a busy block. This includes "Terry", "Christy 90 Brandon", and any arbitrary studio events. Padded by `buffer_time` minutes on each side so new appointments can't be booked right up against another practitioner's event. Events marked "Show me as: Free" (transparency=transparent) are exempt — use that for informational items that shouldn't block. The legacy `blocking_keyword` setting still exists for visibility but no longer changes behavior.

## Booking → Calendar Sync

When a booking is confirmed, an event is created on **two** calendars: the OAuth user's `primary` calendar and the configured shared calendar. Both event IDs are stored in `wp_caswell_bookings` (`gcal_primary_event_id`, `gcal_shared_event_id`).

When a booking is cancelled (via the cancel link, the WP admin Bookings page, or the public account page), the plugin deletes the matching events from both calendars.

An hourly cron (`caswell_sync_shared_calendar`) detects shared-calendar events that were deleted manually outside the plugin (e.g., from Ryan's Google Calendar UI). For each missing event, the plugin marks the booking cancelled, deletes the primary-calendar copy, and triggers the standard cancellation notifications to the client and the owner.

### 2. Fetch Blocks

Gather all blocking events from four sources:

- **Personal calendar events** — fetched from Google Calendar
- **Shared-calendar blocking events** — events on the shared calendar whose title or description contains the blocking keyword (default `Terry`)
- **Existing bookings** — from the `wp_caswell_bookings` table (see [[Database-Schema]])
- **Admin time blocks** — from the `wp_caswell_blocks` table (see [[Database-Schema]])

### 3. Subtract Blocks from Windows

Remove all blocked time ranges from the available windows, producing a set of free windows.

### 4. Slice into Slots

Break the free windows into discrete bookable time slots based on:

- The selected **session length** (30, 60, or 90 minutes)
- The configured **buffer time** between appointments (see [[Admin-Settings]])

## Event Management

When a booking is confirmed, an event is created on the Google Calendar. When a booking is cancelled, the corresponding event is deleted. See [[AJAX-Endpoints]] for the relevant actions.
