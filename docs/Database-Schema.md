# Database Schema

The plugin creates three custom tables in the WordPress database. All table names use the standard `$wpdb->prefix` (shown here as `wp_`).

## `wp_caswell_bookings`

Stores every individual appointment, including one-off bookings and individual occurrences within a recurring series.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `client_id` | BIGINT | WordPress user ID (nullable for guest bookings) |
| `name` | VARCHAR(120) | Client name |
| `email` | VARCHAR(200) | Client email |
| `phone` | VARCHAR(30) | Client phone |
| `session_length` | SMALLINT | Duration in minutes (15-min increments, configurable per Settings → Sessions) |
| `start_datetime` | DATETIME | Appointment start |
| `end_datetime` | DATETIME | Appointment end |
| `status` | VARCHAR(30) | `confirmed`, `pending`, or `cancelled` |
| `payment_method` | VARCHAR(20) | `square` or `venmo` |
| `payment_status` | VARCHAR(20) | `paid` or `unpaid` |
| `square_payment_id` | VARCHAR(100) | Square payment ID (used for refunds) |
| `gcal_primary_event_id` | VARCHAR(255) | Google Calendar event ID on `primary` calendar (used to delete the event on cancel) |
| `gcal_shared_event_id` | VARCHAR(255) | Google Calendar event ID on the shared calendar (also polled by the sync cron to detect manual deletions) |
| `recurring_series_id` | BIGINT | FK to `wp_caswell_recurring_series` (nullable) |
| `notes` | TEXT | Client notes |
| `created_at` | DATETIME | Creation timestamp |

**Related pages:** [[Payments]] (Square payment ID and refunds), [[Scheduling]] (recurring series linkage)

## `wp_caswell_recurring_series`

Defines a recurring appointment series. Individual bookings reference this table via `recurring_series_id`.

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `client_id` | BIGINT | WordPress user ID |
| `session_length` | SMALLINT | Duration in minutes |
| `frequency` | VARCHAR(20) | `weekly`, `biweekly`, or `monthly` |
| `day_of_week` | TINYINT | 1=Mon, 2=Tue, ... 7=Sun |
| `preferred_time` | VARCHAR(10) | Time in HH:MM format |
| `start_date` | DATE | Series start date |
| `end_date` | DATE | Optional end date |
| `occurrences` | INT | Optional maximum occurrence count |
| `status` | VARCHAR(20) | `active` or `cancelled` |
| `created_at` | DATETIME | Creation timestamp |

**Related page:** [[Scheduling]] (recurring series logic)

## `wp_caswell_blocks`

Admin-created time blocks that prevent bookings during specific windows (e.g., vacations, personal time).

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `start_datetime` | DATETIME | Block start |
| `end_datetime` | DATETIME | Block end |
| `label` | VARCHAR(200) | Description (e.g., "Vacation") |
| `created_at` | DATETIME | Creation timestamp |

**Related pages:** [[Google-Calendar]] (blocks are subtracted from availability windows), [[AJAX-Endpoints]] (admin endpoints for adding/deleting blocks)
