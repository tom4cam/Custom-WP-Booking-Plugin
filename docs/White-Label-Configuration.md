# White-Label Configuration

As of v1.3.0, the plugin is fully white-label. All business-specific text is configurable via **Settings → Caswell Booking → Business Info**.

## Branding Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Business Name** | Used in SMS, emails, cancellation notices, and homepage | WordPress site name |
| **Practitioner Name** | Displayed on homepage "About" section and calendar events | (none) |
| **Service Type** | Used in calendar descriptions, email templates, homepage | "massage" |
| **Calendar Event Title** | Google Calendar event title format | `{practitioner} Appointment — {client}` |
| **Booking Page Title** | Title of the standalone booking page | "Book an Appointment" |
| **Hero Tagline** | Main headline on the homepage | "Therapeutic [Service Type] for Body & Mind" |
| **Hero Subtitle** | Subtitle text below the hero headline | "Professional, therapeutic [service] tailored to your needs..." |

## Calendar Event Title Placeholders

The **Calendar Event Title** field supports these placeholders:

- `{practitioner}` — Practitioner Name setting
- `{client}` — Client's name from booking
- `{duration}` — Session length in minutes
- `{service}` — Service Type setting

**Example:** `{practitioner} {service} — {client}` → "Jane Smith massage — John Doe"

## Homepage Content

| Setting | Description |
|---------|-------------|
| **Practitioner Bio** | Text shown in the "About" section |
| **Credentials / License** | Badge shown below the practitioner name (e.g. "LMT") |
| **Service Pricing** | Per-length prices shown on service cards |
| **Service Descriptions** | Per-length description text on service cards |

## Email & SMS Templates

Default email and SMS templates use the `{site_name}` placeholder, which resolves to your Business Name (or WordPress site name as fallback).

### Available Placeholders
- `{name}` — Client name
- `{email}` — Client email
- `{phone}` — Client phone
- `{date}` — Full date (e.g. "Monday, March 15, 2026")
- `{time}` — Start time (e.g. "2:00 PM")
- `{end_time}` — End time
- `{duration}` — Session length (e.g. "60 minutes")
- `{timezone}` — WordPress timezone
- `{site_name}` — Site/business name
- `{site_url}` — Site URL

## Migration from v1.2.0

If upgrading from v1.2.0:
- `ryan_bio` is automatically migrated to `practitioner_bio`
- `ryan_credentials` is automatically migrated to `practitioner_credentials`
- All defaults remain the same — no visible changes until you customize
