# Google Calendar Setup

The plugin reads Google Calendar to determine available appointment slots.

## How It Works

1. **Available windows**: Days are open by default during configurable working hours (07:00–22:00). Events on the shared calendar matching a keyword (default: "Glow") **extend** availability outside those hours.
2. **Blocks**: Events on your personal calendar, existing bookings, and admin time blocks are subtracted from the open windows.
3. **Slots**: Free windows are sliced into bookable time slots based on session length + buffer time.

Default working hours and the open-by-default toggle live in **Settings → Caswell Booking → Availability → Default Availability**. To enforce tighter hours for a specific day of the week, enable that day in **Weekly Schedule** — an enabled weekly-schedule day **replaces** the default and any Glow windows for that day.

## Cancelling Bookings From Google Calendar

If you delete a booking event from the **shared** calendar (e.g., directly in Google Calendar), the hourly sync cron will detect the deletion within the next hour, mark the booking cancelled, remove the matching event from your primary calendar, and send cancellation notifications to both you and the client. The cancel link in the confirmation email and the WP admin Bookings page do the same thing immediately.

## OAuth2 Setup (One-Time)

### Step 1: Create OAuth2 Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project (or select existing)
3. Enable the **Google Calendar API**
4. Go to **APIs & Services → Credentials**
5. Click **Create Credentials → OAuth 2.0 Client ID**
6. Application type: **Web application**
7. Copy the **Client ID** and **Client Secret**

### Step 2: Get a Refresh Token

1. Go to [OAuth 2.0 Playground](https://developers.google.com/oauthplayground)
2. Click the gear icon → check **Use your own OAuth credentials**
3. Enter your Client ID and Client Secret
4. In Step 1, enter scope: `https://www.googleapis.com/auth/calendar`
5. Click **Authorize APIs** and sign in with the Google account that owns your calendars
6. In Step 2, click **Exchange authorization code for tokens**
7. Copy the **Refresh token**

### Step 3: Configure in WordPress

Go to **Settings → Caswell Booking → Google Calendar** and enter:

| Field | Value |
|-------|-------|
| OAuth2 Client ID | From Step 1 |
| OAuth2 Client Secret | From Step 1 (encrypted on save) |
| OAuth2 Refresh Token | From Step 2 (encrypted on save) |
| Shared Calendar ID | The calendar containing availability events |
| Personal Calendar ID | `primary` (or a specific calendar ID) |
| Availability Keyword | Default: `Glow` (case-insensitive) |

## Calendar IDs

- **Shared Calendar ID**: Found in Google Calendar → hover over the calendar → three-dot menu → Settings → Calendar ID
- **Personal Calendar ID**: Use `primary` for your main calendar, or copy the specific Calendar ID

## Weekly Schedule (Alternative)

Instead of (or in addition to) calendar keyword events, you can define regular working hours in **Settings → Availability → Weekly Schedule**. When a day has a schedule enabled, it overrides keyword-based availability for that day.

## Troubleshooting

- **No slots showing**: Check that the shared calendar has events with the keyword in the title for the selected date
- **Token errors**: Re-generate the refresh token via OAuth Playground
- **Check logs**: Enable debug logging in Settings → Tools tab
