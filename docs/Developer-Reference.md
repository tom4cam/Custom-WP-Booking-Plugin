# Developer Reference

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
    js/booking.js             Front-end JavaScript for booking + account widgets
    css/booking.css           All public-facing styles

  admin/
    admin-page.php            Settings page template with tabs

  templates/
    email-confirmation.php    HTML email for booking confirmation
    email-reminder.php        HTML email for appointment reminder
    sms-confirmation.php      SMS confirmation template
    sms-reminder.php          SMS reminder template
```

## Database Tables

### `wp_caswell_bookings`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| client_id | BIGINT | WordPress user ID (nullable) |
| name | VARCHAR(120) | Client name |
| email | VARCHAR(200) | Client email |
| phone | VARCHAR(30) | Client phone |
| session_length | SMALLINT | Duration in minutes (15-min increments, configurable per Settings → Sessions) |
| start_datetime | DATETIME | Appointment start |
| end_datetime | DATETIME | Appointment end |
| status | VARCHAR(30) | confirmed, pending, cancelled |
| payment_method | VARCHAR(20) | square, venmo |
| payment_status | VARCHAR(20) | paid, unpaid |
| square_payment_id | VARCHAR(100) | Square payment ID (for refunds) |
| recurring_series_id | BIGINT | FK to series table (nullable) |
| notes | TEXT | Client notes |
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
| label | VARCHAR(200) | Description |
| created_at | DATETIME | Creation timestamp |

## AJAX Endpoints

All use `wp_ajax_` / `wp_ajax_nopriv_` hooks with nonce verification.

### Public (no auth required)

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_get_slots` | `Caswell_Booking_Handler::ajax_get_slots` | Get available time slots |
| `caswell_submit_booking` | `Caswell_Booking_Handler::ajax_submit_booking` | Create a booking |
| `caswell_register` | `Caswell_Auth::ajax_register` | Create client account |
| `caswell_login` | `Caswell_Auth::ajax_login` | Log in |
| `caswell_forgot_password` | `Caswell_Auth::ajax_forgot_password` | Send reset email |
| `caswell_reset_password` | `Caswell_Auth::ajax_reset_password` | Reset password |
| `caswell_verify_email` | `Caswell_Auth::ajax_verify_email` | Resend verification |

### Authenticated

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_logout` | `Caswell_Auth::ajax_logout` | Log out |
| `caswell_cancel_booking` | `Caswell_Booking_Handler::ajax_cancel_booking` | Cancel a booking |
| `caswell_cancel_series` | `Caswell_Booking_Handler::ajax_cancel_series` | Cancel a series |

### Admin Only (`manage_options`)

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_add_block` | `Caswell_Booking_Handler::ajax_add_block` | Add time block |
| `caswell_delete_block` | `Caswell_Booking_Handler::ajax_delete_block` | Delete time block |
| `caswell_toggle_logging` | `Caswell_Admin::ajax_toggle_logging` | Toggle debug logging |

## Shortcodes

- `[caswell_booking]` — Multi-step booking form (date → time → info/payment → confirmation)
- `[caswell_account]` — Client account (login/register/manage bookings)

## Helper Functions

```php
// Get a plugin setting
caswell_get_option( $key, $default = '' )

// Get enabled session lengths (array of ints)
caswell_enabled_session_lengths()

// Get the configured business name
caswell_business_name()

// Log a debug message
caswell_log( $category, $message, $context = [] )

// Encrypt/decrypt sensitive values
caswell_encrypt( $value )
caswell_decrypt( $value )
```

## Google Calendar Flow

1. `get_available_windows($date)` — Fetch keyword-matched events OR weekly schedule
2. Subtract: personal calendar events, DB bookings, admin time blocks
3. `windows_to_slots($windows, $length, $buffer)` — Slice into discrete slots

## Recurring Series

- Supports: weekly, biweekly, monthly
- Maximum 52 occurrences (safety cap)
- Monthly handles day-of-month overflow (Jan 31 → Feb 28)
- All occurrences checked for availability before creation
- Individual bookings can be cancelled independently

## Settings Storage

All settings stored in `caswell_settings` option (serialized array). Key groups:
- Google Calendar, Sessions, Availability, Square, Venmo
- Email templates, SMS/Twilio, Scheduling
- Business info, Branding/white-label, Service pricing
