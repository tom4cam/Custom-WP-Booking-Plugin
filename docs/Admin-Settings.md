# Admin Settings

All plugin settings are stored in a single WordPress option: `caswell_settings` (serialized array). The admin settings page is rendered by `admin/admin-page.php` with a tabbed interface, and managed by the `Caswell_Admin` class in `includes/class-admin.php`.

## Settings Groups

### Google Calendar

OAuth2 credentials, calendar IDs, and the availability keyword (e.g., "Glow"). See [[Google-Calendar]] for how these are used.

### Sessions

Enabled session lengths (30, 60, and/or 90 minutes) and the default length.

### Availability

Weekly schedule (available days/times) and minimum advance booking hours.

### Square

App ID, Location ID, Access Token, and Sandbox Mode toggle. See [[Payments]] for the Square payment flow.

### Venmo

Username and per-length pricing. See [[Payments]] for the Venmo payment flow.

### Email

"From" name and address, and confirmation/reminder templates. See [[Notifications]] for template details and placeholders.

### SMS / Twilio

Account SID, Auth Token, From Phone Number, SMS templates, and owner notification settings. See [[Notifications]] for details.

### Scheduling

Buffer time between appointments, reminder timing (e.g., 24 hours before), and reminder types (email, SMS, or both). See [[Scheduling]] for how reminders are delivered.

### Business

Phone, email, address, hours, bio, credentials, tagline, and service pricing. Used throughout the public-facing booking interface.

## Encryption

Sensitive values are encrypted at rest using **AES-256-CBC**. The following fields are encrypted:

- Square Access Token
- Twilio Auth Token
- Google Client Secret
- Google Refresh Token

### How It Works

- The encryption key is derived from WordPress's `AUTH_KEY` constant (defined in `wp-config.php`).
- Each encrypted value receives a random initialization vector (IV).
- Encryption and decryption are handled by helper functions in the main plugin file (`caswell-booking.php`). See [[Architecture]].

### Warning

If `AUTH_KEY` in `wp-config.php` is changed, **all encrypted values become unrecoverable** and must be re-entered in the admin settings.

## Debug Logging

Logging can be toggled via the admin Tools tab or is automatically enabled when `WP_DEBUG` is true. See the [[Home]] page for logging details, or [[AJAX-Endpoints]] for the `caswell_toggle_logging` admin action.
