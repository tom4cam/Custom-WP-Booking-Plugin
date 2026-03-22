# Scheduling

This page covers three related systems: WP-Cron reminders, race condition protection, and recurring series.

## WP-Cron Reminders

The plugin uses two mechanisms to ensure appointment reminders are reliably sent. Both are managed by the `Caswell_Cron` class in `includes/class-cron.php`.

### Per-Booking Scheduled Events

When a booking is created, `wp_schedule_single_event()` schedules a one-time cron event to fire at the exact reminder time (e.g., 24 hours before the appointment).

### Hourly Sweep

A recurring hourly cron job scans for upcoming appointments and catches any reminders that were missed — for example, if WordPress cron was delayed or the site was temporarily down.

### Double-Send Prevention

Both mechanisms check for a transient flag before sending:

```
caswell_reminder_sent_{booking_id}
```

If this transient exists, the reminder is skipped. The flag is set immediately after a successful send. This ensures each client receives exactly one reminder per booking.

**Related page:** [[Notifications]] (email and SMS templates used by reminders)

## Race Condition Protection

When a booking is submitted, a transient-based lock is set for the requested slot:

```
caswell_slot_lock_{timestamp}_{session_length}
```

This 30-second lock prevents two clients from simultaneously booking the same time slot. The lock is released after the booking completes or fails.

**Related page:** [[AJAX-Endpoints]] (`caswell_submit_booking` action)

## Recurring Series

Recurring series are managed by the `Caswell_Recurring` class in `includes/class-recurring.php`, with data stored in the `wp_caswell_recurring_series` table (see [[Database-Schema]]).

### Supported Frequencies

- **Weekly** — same day and time every week
- **Biweekly** — every two weeks
- **Monthly** — same day of month (with overflow handling)

### Rules and Limits

- **Maximum 52 occurrences** per series (safety cap)
- **Monthly overflow:** When the target day doesn't exist in a month (e.g., January 31 → February), the plugin falls back to the last day of the month (February 28/29)
- **Availability check:** All occurrences are validated for availability before the series is created
- **Independent cancellation:** Individual bookings within a series can be cancelled without affecting other bookings in the series

### Cancellation

- **Single booking:** Use `caswell_cancel_booking` to cancel one occurrence
- **Entire series:** Use `caswell_cancel_series` to cancel all future bookings in the series

See [[AJAX-Endpoints]] for both cancellation actions.
