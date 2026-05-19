# Caswell Booking — Developer Documentation

## Overview

WordPress plugin for **Ryan Caswell LMT** (castherapylmt.com). Handles appointment booking, Google Calendar integration, Square/Venmo payments, SMS/email notifications, and client accounts.

**Version:** 1.3.0

---

## Architecture

```
caswell-booking/
  caswell-booking.php         Main plugin file, bootstrap, helpers, encryption
  page-caswell-home.php       Custom home page template

  includes/
    class-admin.php           Admin settings page, sanitization, validation
    class-auth.php            User registration, login, forgot/reset password, email verification
    class-booking-db.php      Database layer (bookings, series, blocks)
    class-booking-handler.php AJAX handlers for booking flow, cancellation, Square payments, refunds
    class-cron.php            WP-Cron reminder scheduling (per-booking + hourly sweep)
    class-google-calendar.php Google Calendar API (OAuth2, fetch events, create/delete events, availability)
    class-notifications.php   Email + SMS (Twilio) for confirmations, reminders, cancellations
    class-recurring.php       Recurring series generation and creation

  public/
    booking-shortcode.php     [caswell_booking] shortcode — multi-step booking form
    account-shortcode.php     [caswell_account] shortcode — login/register/manage bookings
    js/booking.js             Front-end JavaScript for booking widget + account widget
    css/booking.css           All public-facing styles

  admin/
    admin-page.php            Settings page template with tabs

  templates/
    email-confirmation.php    HTML email template for booking confirmation
    email-reminder.php        HTML email template for appointment reminder
    sms-confirmation.php      SMS confirmation template
    sms-reminder.php          SMS reminder template
```

---

## Database Tables

### `wp_caswell_bookings`
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| client_id | BIGINT | WordPress user ID (nullable for guest bookings) |
| name | VARCHAR(120) | Client name |
| email | VARCHAR(200) | Client email |
| phone | VARCHAR(30) | Client phone |
| session_length | SMALLINT | Duration in minutes — see `caswell_session_length_options()` for offered values |
| start_datetime | DATETIME | Appointment start |
| end_datetime | DATETIME | Appointment end |
| status | VARCHAR(30) | confirmed, pending, cancelled |
| payment_method | VARCHAR(20) | square, venmo |
| payment_status | VARCHAR(20) | paid, unpaid |
| square_payment_id | VARCHAR(100) | Square payment ID (for refunds) |
| recurring_series_id | BIGINT | FK to series table (nullable) |
| notes | TEXT | Client notes |
| email_consent | TINYINT(1) | 1 if client ticked the email consent box (v1.5.3+) |
| sms_consent | TINYINT(1) | 1 if client ticked the SMS consent box (v1.5.3+) |
| consent_timestamp | DATETIME (nullable) | When consent was captured (v1.5.3+) |
| created_at | DATETIME | Creation timestamp |

### `wp_caswell_recurring_series`
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| client_id | BIGINT | WordPress user ID |
| session_length | SMALLINT | Duration in minutes |
| frequency | VARCHAR(20) | weekly, biweekly, monthly |
| day_of_week | TINYINT | 1=Mon ... 7=Sun |
| preferred_time | VARCHAR(10) | HH:MM format |
| start_date | DATE | Series start date |
| end_date | DATE | Optional end date |
| occurrences | INT | Optional max count |
| status | VARCHAR(20) | active, cancelled |
| created_at | DATETIME | Creation timestamp |

### `wp_caswell_blocks`
| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| start_datetime | DATETIME | Block start |
| end_datetime | DATETIME | Block end |
| label | VARCHAR(200) | Description (e.g. "Vacation") |
| created_at | DATETIME | Creation timestamp |

---

## AJAX Endpoints

All endpoints use `wp_ajax_` / `wp_ajax_nopriv_` hooks and require the `caswell_booking_nonce` nonce (except admin endpoints which use `caswell_admin_nonce`).

### Public (no auth required)

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_get_slots` | `Caswell_Booking_Handler::ajax_get_slots` | Get available time slots for a date |
| `caswell_submit_booking` | `Caswell_Booking_Handler::ajax_submit_booking` | Create a new booking |
| `caswell_register` | `Caswell_Auth::ajax_register` | Create client account |
| `caswell_login` | `Caswell_Auth::ajax_login` | Log in |
| `caswell_forgot_password` | `Caswell_Auth::ajax_forgot_password` | Send password reset email |
| `caswell_reset_password` | `Caswell_Auth::ajax_reset_password` | Reset password with token |
| `caswell_verify_email` | `Caswell_Auth::ajax_verify_email` | Resend verification email |

### Authenticated

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_logout` | `Caswell_Auth::ajax_logout` | Log out |
| `caswell_cancel_booking` | `Caswell_Booking_Handler::ajax_cancel_booking` | Cancel a single booking |
| `caswell_cancel_series` | `Caswell_Booking_Handler::ajax_cancel_series` | Cancel all future bookings in a series |

### Admin Only (`manage_options`)

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_add_block` | `Caswell_Booking_Handler::ajax_add_block` | Add a time block |
| `caswell_delete_block` | `Caswell_Booking_Handler::ajax_delete_block` | Delete a time block |
| `caswell_toggle_logging` | `Caswell_Admin::ajax_toggle_logging` | Enable/disable debug logging |

---

## Google Calendar Flow

1. **Available windows**: Fetch events from shared calendar matching keyword ("Glow") OR use weekly schedule
2. **Blocks**: Fetch personal calendar events, DB bookings, admin time blocks
3. **Subtract**: Remove blocks from windows to get free windows
4. **Slice**: Break free windows into discrete slots based on session length + buffer time

OAuth2 tokens are obtained via refresh token and cached as WordPress transients.

---

## Payment Flow

### Square
1. Client-side: Square Web Payments SDK tokenizes card info
2. Server-side: Token sent to `v2/payments` endpoint
3. Payment ID stored with booking for potential refunds
4. Refund method available via `Caswell_Booking_Handler::refund_square()`

### Venmo
1. Client selects Venmo as payment method
2. Booking created with `payment_status = 'unpaid'`
3. After confirmation, client shown Venmo username and deep link

---

## Notification Templates

### Placeholders (usable in email/SMS templates):
- `{name}` — Client name
- `{email}` — Client email
- `{phone}` — Client phone
- `{date}` — Full date (e.g. "Monday, March 15, 2026")
- `{time}` — Start time (e.g. "2:00 PM")
- `{end_time}` — End time (e.g. "3:00 PM")
- `{duration}` — Session length (e.g. "60 minutes")
- `{timezone}` — WordPress timezone string
- `{site_name}` — Site name
- `{site_url}` — Site URL

---

## Authentication

- Custom WordPress role: `caswell_client` (read-only)
- Password reset uses time-limited SHA-256 hashed tokens stored in user meta
- Email verification uses similar token-based flow
- Tokens stored in: `_caswell_reset_token`, `_caswell_reset_expiry`, `_caswell_verify_token`, `_caswell_verify_expiry`

---

## Logging

When enabled (via admin Tools tab or `WP_DEBUG`), logs are written to `wp-content/caswell-booking.log`.

Use `caswell_log( $category, $message, $context = [] )` to write log entries.

Categories: `booking`, `square`, `twilio`, `gcal`, `auth`, `admin`, `recurring`

Log is auto-truncated to ~500 lines when it exceeds 1MB.

---

## Race Condition Protection

When a booking is submitted, a transient-based lock is set for the slot (`caswell_slot_lock_{ts}_{length}`). This 30-second lock prevents concurrent bookings for the same slot. The lock is released after the booking completes or fails.

---

## Encryption

Sensitive values (Square access token, Twilio auth token, Google client secret, Google refresh token) are encrypted with AES-256-CBC using a key derived from WordPress's `AUTH_KEY` constant. Each value gets a random IV.

**Warning:** If `AUTH_KEY` in `wp-config.php` is changed, all encrypted values become unrecoverable and must be re-entered.

---

## Recurring Series

- Supports: weekly, biweekly, monthly frequencies
- Maximum 52 occurrences per series (safety cap)
- Monthly recurrence handles day-of-month overflow (e.g., Jan 31 -> Feb 28)
- All occurrences are checked for availability before series creation
- Individual bookings in a series can be cancelled independently

---

## WP-Cron Reminders

Two mechanisms to ensure reminders are sent:

1. **Per-booking scheduled events**: `wp_schedule_single_event()` fires at the exact reminder time
2. **Hourly sweep**: Catches any missed reminders (e.g., if cron was delayed)

Both use transient flags (`caswell_reminder_sent_{id}`) to prevent double-sends.

---

## Admin Settings

Settings stored in `caswell_settings` option (serialized array). Key groups:

- **Google Calendar**: OAuth2 credentials, calendar IDs, keyword
- **Sessions**: Enabled lengths (15-min increments from 15 to 120 — see `caswell_session_length_options()`), default length
- **Availability**: Weekly schedule, minimum advance hours
- **Square**: App ID, location ID, access token, sandbox mode
- **Venmo**: Username, per-length pricing
- **Email**: From name/address, confirmation/reminder templates
- **SMS/Twilio**: Account SID, auth token, phone, templates, owner notifications
- **Scheduling**: Buffer time, reminder timing, reminder types
- **Business**: Phone, email, address, hours, bio, credentials, tagline, service pricing
