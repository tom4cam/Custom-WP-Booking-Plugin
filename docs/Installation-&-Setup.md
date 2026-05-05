# Installation & Setup

## Requirements

- WordPress 5.8+
- PHP 7.4+ (8.0+ recommended)
- SSL certificate (required for Square payments)
- Google Cloud account (for Calendar integration)
- Twilio account (optional, for SMS notifications)
- Square account (optional, for card payments)

## Installation

### Via Upload
1. Download the latest release from the [Releases page](https://github.com/tom4cam/Custom-WP-Booking-Plugin/releases)
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Click **Activate**

### Via FTP
1. Extract the plugin folder
2. Upload `caswell-booking/` to `wp-content/plugins/`
3. Activate in **Plugins** admin page

### Via Git (Development)
```bash
cd wp-content/plugins/
git clone https://github.com/tom4cam/Custom-WP-Booking-Plugin.git caswell-booking
```

## What Happens on Activation

The plugin automatically:
1. Creates database tables (`wp_caswell_bookings`, `wp_caswell_recurring_series`, `wp_caswell_blocks`)
2. Registers the `caswell_client` user role (read-only)
3. Schedules WP-Cron for appointment reminders
4. Creates three pages:
   - **Home** — Custom homepage template (`/caswell-home`)
   - **Book an Appointment** — Booking widget (`/book`)
   - **My Account** — Login/register/manage bookings (`/caswell-account`)

## Post-Installation Checklist

1. **Settings → Caswell Booking** — Configure all tabs
2. **Google Calendar tab** — Set up OAuth2 (see [Google Calendar Setup](Google-Calendar-Setup))
3. **Sessions tab** — Enable the session lengths you want to offer (15-min increments from 15 to 120) and pick a default
4. **Square tab** — Add payment credentials (see [Payment Configuration](Payment-Configuration))
5. **Business Info tab** — Set your branding (see [White-Label Configuration](White-Label-Configuration))
6. **SMS/Twilio tab** — Configure if using SMS notifications
7. **Scheduling tab** — Set buffer time, reminder timing

## WP-Cron Setup

For reliable reminders on low-traffic sites, add a server-side cron job:

```
*/15 * * * * curl -s https://yourdomain.com/wp-cron.php?doing_wp_cron
```

On Bluehost: cPanel → Cron Jobs → add the command above with a 15-minute interval.

## Updating

1. Download the new release
2. Deactivate the plugin
3. Replace the plugin folder
4. Re-activate — database migrations run automatically on `plugins_loaded`
