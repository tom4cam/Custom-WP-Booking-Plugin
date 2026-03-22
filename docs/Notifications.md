# Notifications

The plugin sends email and SMS notifications for booking confirmations, reminders, and cancellations.

## Email Notifications

### Configuration (Settings → Email tab)

| Field | Description |
|-------|-------------|
| From Name | Sender name (defaults to site name) |
| From Email | Sender email (defaults to admin email) |
| Confirmation Subject | Subject line for booking confirmations |
| Confirmation Body | Body text (supports placeholders) |
| Reminder Subject | Subject line for reminders |
| Reminder Body | Body text (supports placeholders) |

### Email Templates

HTML email templates are in `templates/`:
- `email-confirmation.php` — Styled confirmation with appointment details
- `email-reminder.php` — Styled reminder with appointment details

Both templates receive the `$booking` object and `$content` (interpolated body text from settings).

### Admin Notifications

The admin (WordPress admin email) automatically receives:
- A copy of every booking confirmation email
- Cancellation notifications with client details

## SMS Notifications (Twilio)

### Configuration (Settings → SMS/Twilio tab)

| Field | Description |
|-------|-------------|
| Account SID | Twilio Account SID |
| Auth Token | Encrypted on save |
| From Phone Number | Twilio phone number (E.164 format, e.g. `+15005550006`) |
| Confirmation Template | SMS text for confirmations |
| Reminder Template | SMS text for reminders |

### Owner SMS Alerts

| Field | Description |
|-------|-------------|
| Notify Owner on New Booking | Toggle SMS alerts for new bookings |
| Owner Phone Number | Phone to receive alerts |

Owner receives SMS for:
- New bookings
- Cancellations

## Placeholders

Available in email and SMS templates:

| Placeholder | Value |
|-------------|-------|
| `{name}` | Client name |
| `{email}` | Client email |
| `{phone}` | Client phone |
| `{date}` | Full date (e.g. "Monday, March 15, 2026") |
| `{time}` | Start time (e.g. "2:00 PM") |
| `{end_time}` | End time |
| `{duration}` | Session length (e.g. "60 minutes") |
| `{timezone}` | WordPress timezone |
| `{site_name}` | Business name / site name |
| `{site_url}` | Site URL |

## Reminders

Reminders are sent via WP-Cron with two mechanisms:

1. **Per-booking events**: `wp_schedule_single_event()` fires at the exact reminder time
2. **Hourly sweep**: Catches any missed reminders

Both use transient flags (`caswell_reminder_sent_{id}`) to prevent double-sends.

Configure in **Settings → Scheduling**:
- **Reminder Timing**: Hours before appointment (default: 24)
- **Enable Email Reminders**: Toggle
- **Enable SMS Reminders**: Toggle
