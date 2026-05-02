# CLAUDE.md

Instructions for Claude Code when working in this repository.

## What this is

WordPress plugin for Ryan Caswell LMT (castherapylmt.com). Appointment booking
system — Google Calendar integration, Square/Venmo payments, SMS/email
notifications, client accounts. Version 1.3.0. White-label capable.

## Authoritative docs

Before doing non-trivial work, read the relevant doc instead of inferring from
code:

- `DEVELOPER.md` — top-level architecture, DB schema, AJAX endpoints, auth
  model, encryption, cron, recurring series
- `docs/Architecture.md` — deeper structural overview
- `docs/Database-Schema.md` — full column lists
- `docs/AJAX-Endpoints.md` — request/response shapes and nonce names
- `docs/Google-Calendar.md`, `docs/Google-Calendar-Setup.md` — OAuth2 flow,
  availability computation
- `docs/Payments.md`, `docs/Payment-Configuration.md` — Square + Venmo
- `docs/Notifications.md` — email/SMS templates, placeholders
- `docs/Scheduling.md` — slot generation, buffer time, reminders
- `docs/Security.md` — encryption, nonces, sanitization patterns
- `docs/White-Label-Configuration.md` — branding options
- `docs/Admin-Settings.md` — settings reference

If you add behavior that contradicts a doc, update the doc in the same change.

## Code layout

```
caswell-booking.php        Bootstrap: constants, activation, pages, shortcodes, enqueue, helpers, logging
page-caswell-home.php      Custom home page template
includes/class-*.php       One class per concern (admin, auth, db, handler, cron, gcal, notifications, recurring)
public/booking-shortcode.php, account-shortcode.php
public/js/booking.js       Single front-end bundle for both widgets
public/css/booking.css
admin/admin-page.php, admin/admin.css
templates/email-*.php, sms-*.php
```

New front-end code goes in the existing `booking.js` / `booking.css` — do not
add new asset files unless there's a real reason.

## Environment — important

This directory is a **Dropbox-synced working copy**. There is no local
WordPress or test environment here; the plugin runs on the production site.

Consequences:
- You cannot run the plugin locally. No `wp-cli`, no PHPUnit suite, no dev
  server. Don't invent commands that assume one.
- There are no build/lint/test scripts. Don't add them without asking.
- File writes here sync to Dropbox — they are not deployed to the site by
  saving. Treat edits as staged changes. The user handles deployment.
- Because edits are effectively remote-bound, be conservative: prefer smaller,
  reviewable diffs over sweeping refactors.

## Conventions to follow

- **WordPress style**: `snake_case` functions, `Class_With_Underscores`,
  `CASWELL_` prefix on constants, `caswell_` prefix on global functions and
  option keys, `_caswell_` prefix on user meta.
- **Settings access**: always `caswell_get_option( $key, $default )`. Don't
  call `get_option('caswell_settings')` directly.
- **Sanitization/escaping**: sanitize on input (`sanitize_text_field`,
  `sanitize_email`, `absint`, etc.), escape on output (`esc_html`, `esc_attr`,
  `esc_url`, `wp_kses_post`). No raw `$_POST`/`$_GET` reaches output.
- **Nonces**: public booking AJAX uses `caswell_booking_nonce`; admin uses
  `caswell_admin_nonce`. Check with `check_ajax_referer`.
- **DB access**: go through `Caswell_Booking_DB` — don't write ad-hoc `$wpdb`
  queries in handlers.
- **Logging**: `caswell_log( $category, $message, $context )`. Categories in
  use: `booking`, `square`, `twilio`, `gcal`, `auth`, `admin`, `recurring`.
- **Timezones**: use `wp_date()` and WordPress's configured timezone.
  Calendar/booking datetimes are stored in site-local time.

## Landmines

- **Encryption key**: Square/Twilio/Google secrets are AES-256-CBC encrypted
  using a key derived from `AUTH_KEY`. Rotating `AUTH_KEY` in `wp-config.php`
  makes all stored secrets unrecoverable. Don't suggest rotating it as a fix.
- **Slot race condition**: booking submission uses a 30-second transient lock
  (`caswell_slot_lock_{ts}_{length}`). Preserve this when touching
  `ajax_submit_booking`.
- **Reminder double-send guard**: `caswell_reminder_sent_{id}` transients
  prevent duplicates across the per-booking event + hourly sweep. Don't remove.
- **DB migrations**: `Caswell_Booking_DB::create_tables()` runs on
  `plugins_loaded` when `caswell_db_version < BOOKINGS_VERSION`. Bump the
  version constant when changing schema.
- **Personal calendar is opt-in for writes.** When a booking confirms, an
  event is always written to the shared calendar (if configured), but the
  practitioner's primary calendar only gets a copy when
  `enable_primary_calendar_event` is on. Default off — Settings → Google
  Calendar → "Also create event on personal calendar".
- **Shared vs. personal event titles** are independently configurable.
  `gcal_shared_event_title` defaults to
  `{practitioner}: {client_short} ({duration} min)`
  (e.g. "Ryan: Jane S. (60 min)"); `gcal_event_title` (personal) defaults
  to `{practitioner} Appointment — {client}`. Both support `{practitioner}`,
  `{client}`, `{client_first}`, `{client_short}` (first name + last initial),
  `{duration}`, `{service}`.
- **Shared-calendar event semantics**: events whose title or description
  contains the Glow keyword (default "Glow", configurable in Settings →
  Availability) define available windows for Ryan. **Every other event on
  the shared calendar blocks availability** (padded by `buffer_time` on
  each side) — that includes "Terry", "Christy 90 Brandon", and any other
  practitioner's booking. Events marked "Show me as: Free" in Google
  Calendar are skipped. The legacy `blocking_keyword` setting still exists
  in settings but no longer drives behavior — it was made redundant by the
  "non-Glow always blocks" rule.
- **Page creation on activate**: `caswell_create_pages()` only creates pages
  if the `caswell_*_page_id` option is unset. If a user deletes a page, they
  must also clear the option to get it recreated.

## Task style here

- Keep diffs minimal and reversible. This plugin is in production.
- Don't add features, options, or abstractions beyond what was asked.
- Don't add new top-level docs — extend the existing `docs/` or `DEVELOPER.md`.
- When changing user-visible behavior, confirm the scope before editing.
