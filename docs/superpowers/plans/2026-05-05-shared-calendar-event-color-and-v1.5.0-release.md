# Shared-Calendar Event Color + v1.5.0 Release Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Color every event written to the shared Google Calendar "Peacock" (`colorId` 7) so Ryan's bookings stand out on the Glow team calendar. Personal-calendar events are untouched. Then bump to v1.5.0 and ship a public GitHub release covering this plus the configurable-session-lengths work from the prior plan.

**Architecture:** A new `CASWELL_SHARED_EVENT_COLOR_ID` constant in `caswell-booking.php` holds the color id. `Caswell_Google_Calendar::create_event()` gains an optional `$color_id` parameter — backward-compatible default `''`. The three shared-calendar `create_event` callers (booking handler, admin manual booking, the test-write button) pass the constant; the two personal-calendar callers don't change.

**Tech Stack:** WordPress (PHP 7.4+). No new test coverage in this plan — `create_event` is HTTP-bound and the cost of mocking outweighs the value. Manual verification on the live site, plus the GitHub Actions PHPUnit job from the prior plan still runs.

**Spec:** `docs/superpowers/specs/2026-05-05-shared-calendar-event-color-design.md`

**Branch:** `main`.

**Prerequisite:** The configurable-session-lengths plan (`docs/superpowers/plans/2026-05-05-configurable-session-lengths.md`) must be fully merged and verified on the live site before starting this plan. The v1.5.0 release at the end ships both features together.

---

## Convention notes

- **Commits go straight to `main`.** Match the existing release pattern.
- **Each PHP edit ends with `php -l`.**
- **Risky steps require explicit Tom confirmation:**
  - Pushing the `v1.5.0` tag (Step 8.3)
  - Creating the GitHub release (Step 9.3)
  These are gated even though Tom approved the release in principle, because they happen days after the original approval and a pre-release smoke test could turn up issues.

## File map

- Modify: `caswell-booking.php` — add `CASWELL_SHARED_EVENT_COLOR_ID`; bump version (Task 6).
- Modify: `includes/class-google-calendar.php` — extend `create_event()` signature; pass color from `test_shared_calendar_write()`.
- Modify: `includes/class-booking-handler.php` — pass color on the shared-calendar call.
- Modify: `includes/class-admin.php` — pass color on the admin manual-booking shared-calendar call.
- Modify: `docs/Google-Calendar.md` — note that bookings appear Peacock on the shared calendar.

---

### Task 1: Add the `CASWELL_SHARED_EVENT_COLOR_ID` constant

**Files:**
- Modify: `caswell-booking.php` (next to existing `CASWELL_VERSION` etc., around line 14).

- [ ] **Step 1: Read context**

Read `caswell-booking.php:1-30` to see the existing `define()` block.

- [ ] **Step 2: Add the constant**

Immediately after the existing `define( 'CASWELL_VERSION', '1.4.10' );` line (or whatever the current version is), insert:

```php
define( 'CASWELL_SHARED_EVENT_COLOR_ID', '7' ); // Peacock — Google Calendar event color
```

Use `define()` (not `const`) to match the existing constant style in this file.

- [ ] **Step 3: Syntax check**

```bash
php -l caswell-booking.php
```

Expected: `No syntax errors detected in caswell-booking.php`.

- [ ] **Step 4: Commit**

```bash
git add caswell-booking.php
git commit -m "$(cat <<'EOF'
feat(gcal): add CASWELL_SHARED_EVENT_COLOR_ID constant (Peacock = 7)

Hardcoded for now — Ryan's bookings on the shared calendar will
render in Peacock blue so they're visually distinct from other
practitioners' events. Trivial to promote to a setting later if a
white-label install needs a different color.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Extend `create_event()` with `$color_id` parameter

**Files:**
- Modify: `includes/class-google-calendar.php` lines 380–427 (`create_event()`) and lines 493–525 (`test_shared_calendar_write()`).

- [ ] **Step 1: Read context**

Read `includes/class-google-calendar.php:370-430` and `:490-525` to confirm exact signatures.

- [ ] **Step 2: Update `create_event()` signature and body**

Old signature (line 380):

```php
    public function create_event( $calendar_id, $title, DateTime $start, DateTime $end, $description = '' ) {
```

New:

```php
    public function create_event( $calendar_id, $title, DateTime $start, DateTime $end, $description = '', $color_id = '' ) {
```

Old request body (around line 386):

```php
        $body = [
            'summary'     => $title,
            'description' => $description,
            'start'       => [ 'dateTime' => $start->format( DateTime::RFC3339 ), 'timeZone' => $tz_string ],
            'end'         => [ 'dateTime' => $end->format( DateTime::RFC3339 ),   'timeZone' => $tz_string ],
        ];
```

New:

```php
        $body = [
            'summary'     => $title,
            'description' => $description,
            'start'       => [ 'dateTime' => $start->format( DateTime::RFC3339 ), 'timeZone' => $tz_string ],
            'end'         => [ 'dateTime' => $end->format( DateTime::RFC3339 ),   'timeZone' => $tz_string ],
        ];
        if ( '' !== (string) $color_id ) {
            $body['colorId'] = (string) $color_id;
        }
```

Also update the docblock for `create_event()` to mention the new param:

Old:

```php
     * @param string   $description
     * @return string|false  Event ID on success, false on failure
```

New:

```php
     * @param string   $description
     * @param string   $color_id     Optional Google Calendar colorId ('1'–'11'). Empty = no override.
     * @return string|false  Event ID on success, false on failure
```

- [ ] **Step 3: Pass the color from `test_shared_calendar_write()`**

In the same file, around line 507 in `test_shared_calendar_write()`, find:

```php
        $event_id = $this->create_event(
```

and update the call to pass `CASWELL_SHARED_EVENT_COLOR_ID` as the sixth argument. Read the existing call to know the exact arg list — it currently passes 5 args. Add the constant as arg 6:

Example (the exact `$start` / `$end` / `$title` etc. names should match what the existing code uses):

```php
        $event_id = $this->create_event(
            $shared_cal_id,
            $title,
            $start,
            $end,
            $description,
            CASWELL_SHARED_EVENT_COLOR_ID
        );
```

If the existing call passes args on a single line, expand to multi-line as shown above for readability.

- [ ] **Step 4: Syntax check**

```bash
php -l includes/class-google-calendar.php
```

Expected: `No syntax errors detected in includes/class-google-calendar.php`.

- [ ] **Step 5: Run unit tests (sanity, no new tests but make sure nothing broke)**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: still passes (the prior plan's tests).

- [ ] **Step 6: Commit**

```bash
git add includes/class-google-calendar.php
git commit -m "$(cat <<'EOF'
feat(gcal): create_event() accepts optional color_id

Adds an optional 6th parameter $color_id to Caswell_Google_Calendar::create_event().
When non-empty, the value is sent as the Google Calendar event's colorId.
Default '' preserves existing behavior — every current call site stays
valid without edits.

Caswell_Google_Calendar::test_shared_calendar_write() now passes
CASWELL_SHARED_EVENT_COLOR_ID so the Settings → Google Calendar test
event matches the color of real bookings.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Color the booking-handler shared-calendar event

**Files:**
- Modify: `includes/class-booking-handler.php` line 310.

- [ ] **Step 1: Read context**

Read `includes/class-booking-handler.php:295-315` to confirm the call shape.

- [ ] **Step 2: Update the shared-calendar `create_event` call**

Old (line 310):

```php
            $shared_event_id  = $gcal->create_event( $shared_cal_id, $shared_title, $start, $end, $desc );
```

New:

```php
            $shared_event_id  = $gcal->create_event( $shared_cal_id, $shared_title, $start, $end, $desc, CASWELL_SHARED_EVENT_COLOR_ID );
```

The personal-calendar `create_event` call on line 300 stays unchanged (no color override).

- [ ] **Step 3: Syntax check**

```bash
php -l includes/class-booking-handler.php
```

Expected: `No syntax errors detected in includes/class-booking-handler.php`.

- [ ] **Step 4: Commit**

```bash
git add includes/class-booking-handler.php
git commit -m "$(cat <<'EOF'
feat(gcal): color self-service-booking shared events Peacock

Self-service bookings now write the shared-calendar event with
colorId 7 (Peacock). Personal calendar events are unchanged.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Color the admin manual-booking shared-calendar event

**Files:**
- Modify: `includes/class-admin.php` line 642.

- [ ] **Step 1: Read context**

Read `includes/class-admin.php:625-650` to confirm the call shape.

- [ ] **Step 2: Update the shared-calendar `create_event` call**

Old (line 642):

```php
            $shared_event_id  = $gcal->create_event( $shared_cal_id, $shared_title, $start, $end, $desc );
```

New:

```php
            $shared_event_id  = $gcal->create_event( $shared_cal_id, $shared_title, $start, $end, $desc, CASWELL_SHARED_EVENT_COLOR_ID );
```

The personal-calendar `create_event` call on line 634 stays unchanged.

- [ ] **Step 3: Syntax check**

```bash
php -l includes/class-admin.php
```

Expected: `No syntax errors detected in includes/class-admin.php`.

- [ ] **Step 4: Commit**

```bash
git add includes/class-admin.php
git commit -m "$(cat <<'EOF'
feat(gcal): color admin-created shared events Peacock

Manually-created bookings (WP Admin → Bookings → + New booking) now
write the shared-calendar event with colorId 7 (Peacock), matching
self-service bookings.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Note the color in `docs/Google-Calendar.md`

**Files:**
- Modify: `docs/Google-Calendar.md`.

- [ ] **Step 1: Read context**

Read `docs/Google-Calendar.md` end-to-end to find a sensible spot. The "Shared-calendar blocking events" section around line 27 or the section that describes shared event creation is a good fit.

- [ ] **Step 2: Add a one-line note**

Add (or insert) the following sentence in the section that describes shared-calendar event creation:

> All events the plugin writes to the shared calendar use Google Calendar's **Peacock** color (event `colorId` 7) so Ryan's bookings are visually distinct from other practitioners' events. The color is set on creation; reschedules preserve it. To change the color, edit `CASWELL_SHARED_EVENT_COLOR_ID` in `caswell-booking.php` (Google Calendar's palette is `'1'`–`'11'`).

- [ ] **Step 3: Commit**

```bash
git add docs/Google-Calendar.md
git commit -m "$(cat <<'EOF'
docs(gcal): note Peacock color on shared-calendar events

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Bump to v1.5.0

**Files:**
- Modify: `caswell-booking.php` (plugin header `Version:` and `CASWELL_VERSION` constant).

- [ ] **Step 1: Read context**

Read `caswell-booking.php:1-30` and locate the `Version:` header line and `CASWELL_VERSION` define.

- [ ] **Step 2: Update both version strings**

Plugin header (line 6):

```
 * Version:     1.4.10
```

→

```
 * Version:     1.5.0
```

Constant (line 14):

```php
define( 'CASWELL_VERSION',    '1.4.10' );
```

→

```php
define( 'CASWELL_VERSION',    '1.5.0' );
```

(Preserve the existing column alignment / spacing.)

- [ ] **Step 3: Syntax check**

```bash
php -l caswell-booking.php
```

Expected: `No syntax errors detected in caswell-booking.php`.

- [ ] **Step 4: Commit**

```bash
git add caswell-booking.php
git commit -m "$(cat <<'EOF'
v1.5.0 — Configurable session lengths + Peacock shared-calendar events

Bumps both the plugin header Version field and the CASWELL_VERSION
constant. This is the release commit that the v1.5.0 tag will point
to in Task 8.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Manual pre-release verification on the live site (Tom)

**Files:** none — manual smoke test by Tom after Dropbox sync propagates the commits.

Run **all** of the following before tagging. Pause and fix if anything fails.

- [ ] **PHPUnit still green.** `vendor/bin/phpunit --testsuite Unit` reports `OK`.

- [ ] **Settings → Google Calendar → Test write.** Click "Test shared-calendar write". Confirm the test event appears on the shared calendar in **Peacock** color.

- [ ] **Self-service booking.** Book a 60-min session via the public widget. On the shared calendar, confirm the event is Peacock. On Ryan's personal calendar (if `enable_primary_calendar_event` is on), confirm the event uses the **default** Google Calendar color (no Peacock).

- [ ] **Admin "+ New booking".** WP Admin → Bookings → + New booking. Create a 30-min booking. Same checks: shared event is Peacock, personal event is uncolored.

- [ ] **Reschedule preserves color.** Reschedule one of the test bookings via the booking-management link. Confirm the rescheduled shared event is still Peacock.

- [ ] **Plugin header shows v1.5.0.** WP Admin → Plugins. Confirm the Caswell Booking row shows version 1.5.0.

- [ ] **Configurable session-lengths regression sweep.** Re-run the smoke checks from the prior plan's Task 8 — at minimum: a 15-min booking still works, a 60-min booking still works, the homepage cards still render, the Sessions tab still works.

If everything passes, **explicitly say "ready to tag v1.5.0"** to authorize Task 8.

---

### Task 8: Tag v1.5.0 and push the tag (gated)

**Prerequisite:** Tom has said "ready to tag v1.5.0" after Task 7.

- [ ] **Step 1: Verify working tree is clean and on `main`**

```bash
git status
git rev-parse --abbrev-ref HEAD
git log --oneline -5
```

Expected: working tree clean, branch `main`, latest commit is the v1.5.0 bump from Task 6.

- [ ] **Step 2: Create the annotated tag**

```bash
git tag -a v1.5.0 -m "$(cat <<'EOF'
v1.5.0

- Configurable session lengths (15-min increments from 15 to 120,
  configurable per Settings → Sessions). Pricing/Venmo rows
  auto-follow the enabled set. Default-length silently falls back
  if you disable the current default. No DB migration.
- Shared-calendar events now use Google Calendar's Peacock color
  (colorId 7) so Ryan's bookings are visually distinct from other
  practitioners' events. Personal-calendar events unchanged.
- Internal: PHPUnit + Brain Monkey test framework bootstrapped.
  Pure session-length helpers extracted to includes/session-lengths.php
  with unit tests. GitHub Actions runs PHPUnit on push.
EOF
)"
```

- [ ] **Step 3: Confirm push is wanted, then push**

**Stop and ask Tom:** "About to push tag v1.5.0 to origin. Confirm?"

After explicit confirmation:

```bash
git push origin main
git push origin v1.5.0
```

Expected: both pushes succeed. The CI workflow should kick off on `main`.

- [ ] **Step 4: Confirm CI passes**

Wait for the GitHub Actions `tests` workflow on the `main` push to finish. If it fails, **do not proceed to Task 9** — investigate and fix first.

```bash
gh run list --branch main --limit 3
```

Expected: latest run on `main` shows `completed success`.

---

### Task 9: Create the public GitHub release (gated)

**Prerequisite:** Tag pushed and CI green from Task 8.

- [ ] **Step 1: Confirm release is wanted**

**Stop and ask Tom:** "CI is green on v1.5.0. About to publish a public GitHub release. Confirm?"

- [ ] **Step 2: Create the release**

After explicit confirmation:

```bash
gh release create v1.5.0 \
  --repo tom4cam/Custom-WP-Booking-Plugin \
  --title "v1.5.0 — Configurable session lengths + Peacock shared-calendar events" \
  --notes "$(cat <<'EOF'
## What's new

### Configurable session lengths

Settings → Sessions now offers eight options in 15-minute increments:
**15, 30, 45, 60, 75, 90, 105, 120 minutes**. Check the ones you want
to offer. The Pricing and Venmo/Square rows in the Branding and
Payments tabs auto-follow your enabled set, so you only see fields for
lengths you actually use. Disabling your current default-length
silently falls back to the lowest enabled length. No database
migration; existing 30/60/90 setups keep working unchanged.

### Peacock shared-calendar events

Every appointment the plugin writes to the shared Google Calendar now
uses the **Peacock** event color (Google Calendar `colorId` 7) so the
practitioner's bookings stand out from other practitioners' events on
a shared studio calendar. Personal calendar events are unchanged.
Existing events keep whatever color they already have; only events
created from v1.5.0 onward are colored.

### Internal

- PHPUnit + Brain Monkey test framework bootstrapped. Pure
  session-length helpers live in `includes/session-lengths.php`
  with unit tests covering the canonical option list,
  enabled-list filtering, and default-length resolution.
- GitHub Actions runs the PHPUnit suite on every push to `main`
  and every PR (PHP 7.4 / 8.1 / 8.2).

## Upgrade notes

No action required. Settings, bookings, and calendar events are all
preserved.
EOF
)"
```

- [ ] **Step 3: Verify the release**

```bash
gh release view v1.5.0 --repo tom4cam/Custom-WP-Booking-Plugin
```

Expected: shows the v1.5.0 release with the rendered notes and the source-code archive attachments.

- [ ] **Step 4: Final confirmation to Tom**

Report the release URL back to Tom:

```bash
gh release view v1.5.0 --repo tom4cam/Custom-WP-Booking-Plugin --json url --jq .url
```

---

## Out of scope (deliberately not in this plan)

- Re-coloring existing past or future events that were created before v1.5.0.
- Making the color a user-configurable setting (would add a Settings → Google Calendar dropdown — easy follow-up if asked).
- Changes to `update_event()` — Google Calendar PATCH preserves the existing `colorId` so reschedules don't need any color handling.
- Any Square/Venmo charging changes for the new session lengths beyond the existing per-length `venmo_price_*` plumbing already covered in the prior plan.
