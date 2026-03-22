# Custom WP Booking Plugin

A white-label WordPress appointment booking plugin with Google Calendar integration, Square/Venmo payments, Twilio SMS/email notifications, recurring appointments, and client accounts.

**Current Version:** 1.3.0

## Documentation

- **[Installation & Setup](Installation-%26-Setup.md)** — How to install, activate, and configure the plugin
- **[White-Label Configuration](White-Label-Configuration.md)** — Customize branding, business name, and all public-facing text
- **[Google Calendar Setup](Google-Calendar-Setup.md)** — OAuth2 credentials, calendar IDs, and availability keyword
- **[Payment Configuration](Payment-Configuration.md)** — Square and Venmo setup
- **[Notifications](Notifications.md)** — Email and SMS templates, Twilio setup, owner alerts
- **[Security](Security.md)** — Security features, compliance notes, and best practices
- **[Developer Reference](Developer-Reference.md)** — Architecture, database schema, AJAX endpoints, hooks

## Quick Start

1. Upload the `caswell-booking` folder to `wp-content/plugins/`
2. Activate in WordPress admin → Plugins
3. Go to Settings → Caswell Booking
4. Configure Google Calendar OAuth2 credentials
5. Set up payment (Square and/or Venmo)
6. Customize branding in the Business Info tab
7. The plugin auto-creates three pages: Home, Book, and My Account
