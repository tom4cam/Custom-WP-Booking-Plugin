# Google Calendar

The plugin integrates with Google Calendar to determine real-time appointment availability. This is handled by the `Caswell_Google_Calendar` class in `includes/class-google-calendar.php`.

## OAuth2 Authentication

The plugin uses OAuth2 with a refresh token to authenticate with the Google Calendar API. Tokens are obtained via the refresh token and cached as WordPress transients to minimize API calls.

Credentials (client ID, client secret, refresh token) are stored encrypted in the database. See [[Admin-Settings]] for encryption details.

## Availability Logic

Slot availability is calculated in four steps:

### 1. Fetch Available Windows

Days are **open by default** during working hours (currently hardcoded 07:00–22:00 in `class-google-calendar.php`). Two modifiers can change that:

- **Glow events on the shared calendar** — events whose title contains the configured keyword (e.g., "Glow") are merged with the default window. Use them to extend availability outside the default hours (e.g., a 06:00–07:00 Glow event opens early).
- **Weekly schedule (admin override)** — if a day is explicitly enabled in Settings → Availability → Weekly Schedule, that schedule **replaces** all other sources for that day. Use it to narrow a specific day's hours or to mark a day open only inside a tight window.

Personal calendar events, existing bookings, and admin time blocks always subtract from whatever window is active (see step 2).

### 2. Fetch Blocks

Gather all blocking events from three sources:

- **Personal calendar events** — fetched from Google Calendar
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
