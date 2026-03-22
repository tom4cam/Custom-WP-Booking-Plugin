# Architecture

The plugin follows a class-based structure with separate files for each major feature area.

## File & Folder Structure

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

## Key Classes

| Class | File | Responsibility |
|-------|------|----------------|
| `Caswell_Admin` | `includes/class-admin.php` | Admin settings UI, sanitization, validation |
| `Caswell_Auth` | `includes/class-auth.php` | [[Authentication]] — registration, login, password reset, email verification |
| `Caswell_Booking_DB` | `includes/class-booking-db.php` | [[Database-Schema]] — CRUD for bookings, series, and blocks |
| `Caswell_Booking_Handler` | `includes/class-booking-handler.php` | [[AJAX-Endpoints]] — booking flow, cancellation, [[Payments]] |
| `Caswell_Cron` | `includes/class-cron.php` | [[Scheduling]] — WP-Cron reminders |
| `Caswell_Google_Calendar` | `includes/class-google-calendar.php` | [[Google-Calendar]] — OAuth2, availability |
| `Caswell_Notifications` | `includes/class-notifications.php` | [[Notifications]] — email and SMS |
| `Caswell_Recurring` | `includes/class-recurring.php` | [[Scheduling]] — recurring series logic |

## Entry Point

`caswell-booking.php` is the main plugin file. It bootstraps the plugin by loading all class files, registering shortcodes, enqueuing scripts/styles, and providing global helper functions including the encryption utilities (see [[Admin-Settings]]).
