# Google Calendar

The plugin integrates with Google Calendar to determine real-time appointment availability. This is handled by the `Caswell_Google_Calendar` class in `includes/class-google-calendar.php`.

## OAuth2 Authentication

The plugin uses OAuth2 with a refresh token to authenticate with the Google Calendar API. Tokens are obtained via the refresh token and cached as WordPress transients to minimize API calls.

Credentials (client ID, client secret, refresh token) are stored encrypted in the database. See [[Admin-Settings]] for encryption details.

## Availability Logic

Slot availability is calculated in four steps:

### 1. Fetch Available Windows

Fetch events from the shared calendar that match a configured keyword (e.g., "Glow"), **or** fall back to the weekly schedule defined in [[Admin-Settings]].

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
