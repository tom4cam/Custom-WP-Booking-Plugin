# Security

## Overview

The plugin follows WordPress security best practices and addresses common OWASP vulnerabilities.

## CSRF Protection

All AJAX endpoints verify WordPress nonces:
- Public endpoints: `caswell_booking_nonce`
- Admin endpoints: `caswell_admin_nonce`

Every form includes `wp_nonce_field()` and every handler calls `check_ajax_referer()`.

## Input Validation & Sanitization

All user inputs are sanitized using WordPress functions:
- `sanitize_text_field()` for text inputs
- `sanitize_email()` for email addresses
- `sanitize_textarea_field()` for multi-line text
- `absint()` for integers
- `is_email()` for email validation
- Date validation via `checkdate()` and regex patterns
- Payment method and frequency validated against whitelists

## Output Escaping (XSS Prevention)

All output uses appropriate escaping:
- `esc_html()` for text content
- `esc_attr()` for HTML attributes
- `esc_url()` for URLs
- `esc_textarea()` for textarea content
- `wp_json_encode()` for JavaScript data

## Rate Limiting

| Endpoint | Limit | Window |
|----------|-------|--------|
| Booking submissions | 5 per IP | 1 hour |
| Booking submissions (logged-in) | 10 per user | 1 hour |
| Login | 10 per IP | 15 minutes |
| Registration | 10 per IP | 15 minutes |
| Forgot password | 10 per IP | 15 minutes |

Rate limits use WordPress transients for storage.

## Encryption

Sensitive credentials are encrypted at rest using AES-256-CBC:
- Square access token
- Twilio auth token
- Google client secret
- Google refresh token

Encryption key is derived from WordPress's `AUTH_KEY` constant via SHA-256.

**Warning:** Changing `AUTH_KEY` in `wp-config.php` makes all encrypted values unrecoverable. Re-enter them after any key change.

## Authentication Security

- Custom role `caswell_client` has minimal permissions (read-only)
- Password reset tokens: SHA-256 hashed, 1-hour expiry
- Email verification tokens: SHA-256 hashed, 24-hour expiry
- Token comparison uses `hash_equals()` (timing-safe)
- Forgot-password always returns success (prevents email enumeration)
- Minimum password length: 8 characters

## Race Condition Protection

Booking submissions use transient-based slot locking:
- Key: `caswell_slot_lock_{timestamp}_{length}`
- TTL: 30 seconds
- Prevents concurrent bookings for the same time slot

## Authorization

- Booking cancellation: ownership check (client_id must match current user)
- Series cancellation: ownership check
- Admin endpoints: `current_user_can('manage_options')` check
- Time block management: admin-only

## SQL Injection Prevention

All database queries use:
- WordPress `$wpdb->prepare()` for parameterized queries
- WordPress API functions (`wp_insert_post`, `update_user_meta`, etc.)

## PCI Compliance

- Card data never touches your server — Square Web Payments SDK tokenizes client-side
- Only the payment token is transmitted to your server
- SSL is required and checked (admin notice shown if missing)

## Logging

Debug logging (when enabled) writes to `wp-content/caswell-booking.log`:
- Auto-truncated to ~500 lines when exceeding 1MB
- Logs: API calls, authentication events, booking operations, payment transactions, errors
- Categories: `booking`, `square`, `twilio`, `gcal`, `auth`, `admin`, `recurring`

## Recommendations

1. **Enable SSL** — Required for Square payments and general security
2. **Set up server-side cron** — More reliable than WP-Cron for reminders
3. **Keep WordPress updated** — Plugin relies on WP core security functions
4. **Use strong AUTH_KEY** — Protects encrypted credentials
5. **Monitor logs** — Enable logging during initial setup, then disable in production
