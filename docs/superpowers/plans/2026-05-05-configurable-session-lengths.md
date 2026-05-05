# Configurable Session Lengths Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded `[ 30, 60, 90 ]` session-length list with a single helper-backed set of 15-minute increments (15, 30, 45, 60, 75, 90, 105, 120). Pricing/Venmo/Branding rows render only for enabled lengths. No DB migration. Bootstrap a Composer + PHPUnit + Brain Monkey test framework along the way and use it to TDD the session-length helpers.

**Architecture:** Three pure helpers (`caswell_session_length_options`, `caswell_enabled_session_lengths`, `caswell_resolve_default_length`) move to a new `includes/session-lengths.php` so they can be unit-tested in isolation. The Sessions tab renders a checkbox per option; the default-length dropdown and the Pricing/Venmo rows render **only** the currently-enabled lengths. Sanitizers iterate the full options list so disabled-length data is preserved across saves.

**Tech Stack:** WordPress (PHP 7.4+), Composer, PHPUnit ^9.6, Brain Monkey ^2.6 (mocks WP functions for unit tests — no MySQL, no WP install). GitHub Actions runs PHPUnit on every push.

**Spec:** `docs/superpowers/specs/2026-05-05-configurable-session-lengths-design.md`

**Branch:** `main` (matches existing release pattern — direct commits to `main`).

---

## Convention notes

- **TDD where it pays.** Pure helpers get unit tests (Tasks 2). UI markup (`admin/admin-page.php`, `page-caswell-home.php`) and HTTP-touching code (`create_event`, `charge_square`) stay manually verified — Brain Monkey could mock them but the cost outweighs the value here.
- **Don't backfill tests for unrelated existing code.** Only new/changed code in this PR.
- **Each task ends with `php -l`** on touched files plus the relevant test command if applicable.
- **Don't bump `CASWELL_VERSION` here.** Version bump and release happen at the end of the calendar-color plan.

## File map

- Create: `composer.json`, `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/Unit/SmokeTest.php`, `tests/Unit/SessionLengthsTest.php`, `.github/workflows/tests.yml`, `includes/session-lengths.php`.
- Modify: `.gitignore` (vendor/, phpunit cache).
- Modify: `caswell-booking.php` (require new helpers file; remove old definition of `caswell_enabled_session_lengths()`).
- Modify: `includes/class-admin.php` (sanitize over the options list; default-length fallback via new helper).
- Modify: `admin/admin-page.php` (Sessions tab checkboxes/dropdown; Branding tab pricing rows; Payments tab Venmo rows).
- Modify: `page-caswell-home.php` (homepage Services & Pricing cards loop over enabled lengths).
- Modify: 7 docs that mention "30/60/90".

---

### Task 1: Bootstrap the test framework

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/bootstrap.php`
- Create: `tests/Unit/SmokeTest.php`
- Create: `.github/workflows/tests.yml`
- Modify: `.gitignore`

- [ ] **Step 1: Add `composer.json`**

```json
{
    "name": "caswell/booking",
    "description": "Custom WordPress booking plugin for Ryan Caswell LMT.",
    "type": "wordpress-plugin",
    "license": "proprietary",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "brain/monkey": "^2.6"
    },
    "autoload-dev": {
        "psr-4": {
            "Caswell\\Tests\\": "tests/"
        }
    },
    "config": {
        "allow-plugins": {
            "composer/package-versions-deprecated": true
        },
        "sort-packages": true
    },
    "scripts": {
        "test": "phpunit"
    }
}
```

- [ ] **Step 2: Add `phpunit.xml.dist`**

```xml
<?xml version="1.0"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.6/phpunit.xsd"
    bootstrap="tests/bootstrap.php"
    colors="true"
    cacheResultFile=".phpunit.result.cache"
    convertDeprecationsToExceptions="true"
    failOnWarning="true"
    failOnRisky="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Add `tests/bootstrap.php`**

```php
<?php
/**
 * Bootstrap for unit tests.
 *
 * Brain Monkey lets us call plugin code without booting WordPress —
 * WP functions like get_option(), wp_date(), etc. are stubbed in
 * each test class' setUp().
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../vendor/autoload.php';
```

- [ ] **Step 4: Add `tests/Unit/SmokeTest.php` (proves the test runner works)**

```php
<?php

namespace Caswell\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {
    public function test_phpunit_is_wired_up(): void {
        $this->assertTrue( true );
    }
}
```

- [ ] **Step 5: Update `.gitignore`**

Add these lines at the end of `.gitignore`:

```
# Composer / PHPUnit
/vendor/
.phpunit.result.cache
```

- [ ] **Step 6: Add `.github/workflows/tests.yml`**

```yaml
name: tests

on:
  push:
    branches: [main]
  pull_request:

jobs:
  phpunit:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['7.4', '8.1', '8.2']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist --no-progress
      - name: Run PHPUnit
        run: vendor/bin/phpunit --colors=always
```

- [ ] **Step 7: Install dependencies, mark vendor/ as Dropbox-ignored, run the smoke test**

```bash
composer install --no-interaction
xattr -w com.dropbox.ignored 1 vendor
vendor/bin/phpunit --testsuite Unit
```

The `xattr` line is a macOS-specific Dropbox feature that prevents the
50–100 MB of Composer dev dependencies from syncing to Dropbox. It's a
local file attribute — it doesn't affect git, doesn't get committed, and
doesn't appear anywhere in the repo. Without it, `vendor/` would sync to
Dropbox even though git ignores it. Reference: <https://help.dropbox.com/sync/ignored-files>.

Expected last line of the PHPUnit output: `OK (1 test, 1 assertion)`.

If `composer` isn't installed, fail the task and ask Tom to install it (`brew install composer` on macOS).

- [ ] **Step 8: Commit**

```bash
git add composer.json phpunit.xml.dist tests/bootstrap.php tests/Unit/SmokeTest.php .github/workflows/tests.yml .gitignore
git commit -m "$(cat <<'EOF'
chore(tests): bootstrap PHPUnit + Brain Monkey test framework

- composer.json with phpunit/phpunit ^9.6 and brain/monkey ^2.6 as dev deps
- phpunit.xml.dist auto-discovers tests/Unit
- tests/bootstrap.php registers ABSPATH and the Composer autoloader so
  Brain Monkey can stub WP functions per-test without a WP install
- tests/Unit/SmokeTest.php proves the runner works
- .github/workflows/tests.yml runs PHPUnit on PHP 7.4 / 8.1 / 8.2 on
  every push to main and on PRs
- vendor/ and .phpunit.result.cache excluded from git so Composer's
  vendor dir doesn't sync to Dropbox or land in commits

Tests are scoped to new code in this PR — no backfill of existing
untested code.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Extract session-length helpers, TDD `caswell_session_length_options()` and the resolver

**Files:**
- Create: `includes/session-lengths.php`
- Create: `tests/Unit/SessionLengthsTest.php`
- Modify: `caswell-booking.php` (require the new file; remove the existing `caswell_enabled_session_lengths()` definition)

- [ ] **Step 1: Read context**

Read `caswell-booking.php:215-235` to confirm the existing
`caswell_enabled_session_lengths()` body and surrounding helpers.

- [ ] **Step 2: Write the failing tests**

Create `tests/Unit/SessionLengthsTest.php`:

```php
<?php

namespace Caswell\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class SessionLengthsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/includes/session-lengths.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_options_returns_15min_increments_15_to_120(): void {
        $this->assertSame(
            [ 15, 30, 45, 60, 75, 90, 105, 120 ],
            caswell_session_length_options()
        );
    }

    public function test_enabled_returns_only_lengths_with_truthy_flag(): void {
        Functions\when( 'get_option' )->justReturn( [
            'enable_30min'  => 1,
            'enable_60min'  => 1,
            'enable_90min'  => 0,
            'enable_120min' => 1,
        ] );

        $this->assertSame(
            [ 30, 60, 120 ],
            caswell_enabled_session_lengths()
        );
    }

    public function test_enabled_falls_back_to_60_when_nothing_enabled(): void {
        Functions\when( 'get_option' )->justReturn( [] );

        $this->assertSame(
            [ 60 ],
            caswell_enabled_session_lengths()
        );
    }

    public function test_resolve_default_keeps_requested_when_enabled(): void {
        $this->assertSame(
            60,
            caswell_resolve_default_length( 60, [ 30, 60, 90 ] )
        );
    }

    public function test_resolve_default_falls_back_to_lowest_when_requested_disabled(): void {
        // Ryan disables 60 (his default). Lowest enabled (30) becomes the new default.
        $this->assertSame(
            30,
            caswell_resolve_default_length( 60, [ 30, 90 ] )
        );
    }

    public function test_resolve_default_returns_60_when_nothing_enabled(): void {
        $this->assertSame(
            60,
            caswell_resolve_default_length( 60, [] )
        );
    }

    public function test_resolve_default_coerces_zero_or_garbage_to_lowest_enabled(): void {
        $this->assertSame(
            15,
            caswell_resolve_default_length( 0, [ 15, 30, 60 ] )
        );
    }
}
```

- [ ] **Step 3: Run the tests, expect failure**

Run:

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: failure on `SessionLengthsTest` with "include … includes/session-lengths.php: Failed to open stream" (file doesn't exist yet).

- [ ] **Step 4: Create `includes/session-lengths.php`**

```php
<?php
/**
 * Session-length helpers.
 *
 * The single source of truth for what session lengths the plugin offers,
 * which are currently enabled by the admin, and how to pick a sensible
 * default when the admin's saved default no longer matches the enabled
 * set.
 *
 * Pure functions only — no side effects, no WP-bootstrap-time work.
 * Safe to require from caswell-booking.php at load time.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The canonical list of session-length options Ryan can offer.
 *
 * 15-minute increments from 15 to 120. Adding a value here makes it
 * appear as a checkbox in Settings → Sessions; once enabled, it flows
 * through pricing, Venmo, the booking widget, the homepage pricing
 * cards, and the calendar with no further code change.
 *
 * @return int[]
 */
function caswell_session_length_options() {
    return [ 15, 30, 45, 60, 75, 90, 105, 120 ];
}

/**
 * The lengths the admin has enabled in Settings → Sessions.
 *
 * Order matches caswell_session_length_options(). Falls back to [ 60 ]
 * if nothing is enabled, so call sites never see an empty list.
 *
 * @return int[]
 */
function caswell_enabled_session_lengths() {
    $lengths = [];
    $options = get_option( 'caswell_settings', [] );
    foreach ( caswell_session_length_options() as $len ) {
        if ( ! empty( $options[ "enable_{$len}min" ] ) ) {
            $lengths[] = $len;
        }
    }
    return $lengths ?: [ 60 ];
}

/**
 * Resolve a desired default-length against the currently-enabled set.
 *
 * Returns $requested if it's enabled; otherwise the lowest enabled
 * length, or 60 if nothing is enabled. Used by the settings sanitizer
 * so that disabling the current default doesn't leave an invalid value
 * stored.
 *
 * @param mixed $requested  Requested default length (any scalar — coerced to int).
 * @param int[] $enabled    Currently-enabled lengths (already filtered).
 * @return int
 */
function caswell_resolve_default_length( $requested, array $enabled ) {
    $requested = (int) $requested;
    if ( $requested && in_array( $requested, $enabled, true ) ) {
        return $requested;
    }
    if ( $enabled ) {
        return (int) $enabled[0];
    }
    return 60;
}
```

- [ ] **Step 5: Update `caswell-booking.php` to require the new file and drop the duplicate definition**

In `caswell-booking.php`, find the existing `caswell_enabled_session_lengths()` function around line 223:

```php
function caswell_enabled_session_lengths() {
    $lengths  = [];
    $options  = get_option( 'caswell_settings', [] );
    foreach ( [ 30, 60, 90 ] as $len ) {
        if ( ! empty( $options[ "enable_{$len}min" ] ) ) {
            $lengths[] = $len;
        }
    }
    return $lengths ?: [ 60 ];
}
```

**Delete** that whole block. Then in `caswell-booking.php`, immediately after the existing `foreach ( [ 'class-booking-db', ..., 'class-admin' ] as $file ) { ... }` autoload block (which ends around line 31), add:

```php
require_once CASWELL_PLUGIN_DIR . 'includes/session-lengths.php';
```

(The existing autoload loop only handles `class-*.php` files; the new helpers file uses the bare `session-lengths.php` name, so it gets its own require line right after the loop.)

- [ ] **Step 6: Run the tests, expect pass**

Run:

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: `OK (8 tests, 8 assertions)` (1 smoke + 7 session-length tests).

- [ ] **Step 7: Syntax check**

```bash
php -l caswell-booking.php
php -l includes/session-lengths.php
```

Both should report `No syntax errors detected`.

- [ ] **Step 8: Commit**

```bash
git add includes/session-lengths.php tests/Unit/SessionLengthsTest.php caswell-booking.php
git commit -m "$(cat <<'EOF'
feat(sessions): extract session-length helpers; add options + resolver

Move caswell_enabled_session_lengths() out of caswell-booking.php into
a new includes/session-lengths.php that holds three pure helpers:

- caswell_session_length_options()  — canonical list of offered
  session lengths, 15-min increments from 15 to 120 minutes
- caswell_enabled_session_lengths() — what the admin currently has
  checked in Settings → Sessions (now reads the canonical list)
- caswell_resolve_default_length()  — picks a valid default-length
  when the saved default isn't in the enabled set

All three are unit-tested via Brain Monkey (no WP install required).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Update sanitizers in `class-admin.php`

**Files:**
- Modify: `includes/class-admin.php` — four sanitizer loops (lines 67, 70, 86, 176) plus default-length resolution.

- [ ] **Step 1: Read context**

Read `includes/class-admin.php:60-95` and `:170-185` to confirm exact loop bodies.

- [ ] **Step 2: Replace the `enable_{len}min` sanitizer + default-length lines**

Old (around line 67):

```php
        // Session lengths
        foreach ( [ 30, 60, 90 ] as $len ) {
            $clean[ "enable_{$len}min" ] = ! empty( $input[ "enable_{$len}min" ] ) ? 1 : 0;
        }
        $clean['default_session_length'] = absint( $input['default_session_length'] ?? 60 );
```

New:

```php
        // Session lengths
        foreach ( caswell_session_length_options() as $len ) {
            $clean[ "enable_{$len}min" ] = ! empty( $input[ "enable_{$len}min" ] ) ? 1 : 0;
        }

        // Default length: if the admin picked a default that's not in the
        // enabled set after this save (e.g. unchecked their current default),
        // silently fall back to the lowest enabled length. caswell_resolve_default_length()
        // is unit-tested.
        $enabled_now = [];
        foreach ( caswell_session_length_options() as $len ) {
            if ( ! empty( $clean[ "enable_{$len}min" ] ) ) {
                $enabled_now[] = $len;
            }
        }
        $clean['default_session_length'] = caswell_resolve_default_length(
            $input['default_session_length'] ?? 60,
            $enabled_now
        );
```

- [ ] **Step 3: Replace the `venmo_price_{len}` sanitizer loop**

Around line 86, replace:

```php
        foreach ( [ 30, 60, 90 ] as $len ) {
            $clean[ "venmo_price_{$len}" ] = sanitize_text_field( $input[ "venmo_price_{$len}" ] ?? '' );
        }
```

with:

```php
        foreach ( caswell_session_length_options() as $len ) {
            $clean[ "venmo_price_{$len}" ] = sanitize_text_field( $input[ "venmo_price_{$len}" ] ?? '' );
        }
```

- [ ] **Step 4: Replace the `service_price_{len}` / `service_description_{len}` sanitizer loop**

Around line 176, replace:

```php
        // Services / Pricing (for homepage)
        foreach ( [ 30, 60, 90 ] as $len ) {
            $clean[ "service_price_{$len}" ]       = sanitize_text_field( $input[ "service_price_{$len}" ] ?? '' );
            $clean[ "service_description_{$len}" ] = sanitize_textarea_field( $input[ "service_description_{$len}" ] ?? '' );
        }
```

with:

```php
        // Services / Pricing (for homepage)
        foreach ( caswell_session_length_options() as $len ) {
            $clean[ "service_price_{$len}" ]       = sanitize_text_field( $input[ "service_price_{$len}" ] ?? '' );
            $clean[ "service_description_{$len}" ] = sanitize_textarea_field( $input[ "service_description_{$len}" ] ?? '' );
        }
```

- [ ] **Step 5: Syntax check**

```bash
php -l includes/class-admin.php
```

Expected: `No syntax errors detected in includes/class-admin.php`.

- [ ] **Step 6: Run the test suite to make sure helpers still pass**

```bash
vendor/bin/phpunit --testsuite Unit
```

Expected: still `OK (8 tests, 8 assertions)`.

- [ ] **Step 7: Commit**

```bash
git add includes/class-admin.php
git commit -m "$(cat <<'EOF'
feat(sessions): sanitize all configurable lengths; default-length fallback

Settings sanitizer iterates caswell_session_length_options() for the
enable_*min flag, venmo_price_*, service_price_*, and
service_description_* keys. Disabled-length data is preserved across
saves (existing behavior).

The default-length value now flows through caswell_resolve_default_length(),
which silently falls back to the lowest enabled length when the saved
default isn't in the enabled set (e.g. Ryan unchecked his current default).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Sessions tab UI — checkboxes + default dropdown

**Files:**
- Modify: `admin/admin-page.php` lines 96–122.

- [ ] **Step 1: Read context**

Read `admin/admin-page.php:96-125` to confirm exact markup.

- [ ] **Step 2: Replace the Sessions tab block**

Old:

```php
        <!-- ── Session Lengths ────────────────────────────────────────── -->
        <div id="tab-sessions" class="caswell-tab-content">
            <h2>Session Lengths</h2>
            <table class="form-table">
                <tr>
                    <th>Enabled Lengths</th>
                    <td>
                        <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                        <label style="margin-right:20px;">
                            <input type="checkbox" name="caswell_settings[enable_<?php echo $len; ?>min]" value="1" <?php checked( ! empty( $o[ "enable_{$len}min" ] ) ); ?> />
                            <?php echo $len; ?> minutes
                        </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_length">Default Session Length</label></th>
                    <td>
                        <select id="default_length" name="caswell_settings[default_session_length]">
                            <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                            <option value="<?php echo $len; ?>" <?php selected( (int) ( $o['default_session_length'] ?? 60 ), $len ); ?>><?php echo $len; ?> min</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
```

New:

```php
        <!-- ── Session Lengths ────────────────────────────────────────── -->
        <div id="tab-sessions" class="caswell-tab-content">
            <h2>Session Lengths</h2>
            <p class="description">Check each session length you want to offer. Enabling a length here makes its Pricing and Venmo/Square rows appear in the Branding and Payments tabs.</p>
            <table class="form-table">
                <tr>
                    <th>Enabled Lengths</th>
                    <td>
                        <div style="display:grid;grid-template-columns:repeat(4,max-content);column-gap:24px;row-gap:6px;">
                            <?php foreach ( caswell_session_length_options() as $len ) : ?>
                            <label>
                                <input type="checkbox" name="caswell_settings[enable_<?php echo (int) $len; ?>min]" value="1" <?php checked( ! empty( $o[ "enable_{$len}min" ] ) ); ?> />
                                <?php echo (int) $len; ?> minutes
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="default_length">Default Session Length</label></th>
                    <td>
                        <select id="default_length" name="caswell_settings[default_session_length]">
                            <?php
                            $current_default = (int) ( $o['default_session_length'] ?? 60 );
                            foreach ( caswell_enabled_session_lengths() as $len ) :
                            ?>
                            <option value="<?php echo (int) $len; ?>" <?php selected( $current_default, $len ); ?>><?php echo (int) $len; ?> min</option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Only currently-enabled lengths appear here. If you change the enabled set, save the page and the dropdown will update.</p>
                    </td>
                </tr>
            </table>
        </div>
```

- [ ] **Step 3: Syntax check**

```bash
php -l admin/admin-page.php
```

Expected: `No syntax errors detected in admin/admin-page.php`.

- [ ] **Step 4: Commit**

```bash
git add admin/admin-page.php
git commit -m "$(cat <<'EOF'
feat(sessions): Sessions tab renders all configurable lengths

The "Enabled Lengths" checkboxes now come from
caswell_session_length_options() (15/30/45/60/75/90/105/120),
laid out in a 4-column grid. The Default Session Length dropdown
only lists currently-enabled lengths so Ryan can't pick a default
he just disabled.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Pricing rows in Branding tab + Venmo rows in Payments tab

**Files:**
- Modify: `admin/admin-page.php` line 278 (Payments → Venmo prices) and line 509 (Branding → Service Pricing).

- [ ] **Step 1: Read context**

Read `admin/admin-page.php:270-290` and `:505-520` to confirm exact markup.

- [ ] **Step 2: Replace the Venmo prices loop**

Old (around line 278):

```php
                <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                <tr>
                    <th><label>Price — <?php echo $len; ?> min</label></th>
                    <td>
                        <span>$</span>
                        <input type="text" name="caswell_settings[venmo_price_<?php echo $len; ?>]" value="<?php echo esc_attr( $o[ "venmo_price_{$len}" ] ?? '' ); ?>" style="width:80px;" placeholder="0.00" />
                    </td>
                </tr>
                <?php endforeach; ?>
```

New:

```php
                <?php $venmo_lens = caswell_enabled_session_lengths(); ?>
                <?php if ( $venmo_lens ) : ?>
                <tr><td colspan="2"><p class="description">One row per session length you've enabled. Add or remove lengths in the <strong>Sessions</strong> tab.</p></td></tr>
                <?php foreach ( $venmo_lens as $len ) : ?>
                <tr>
                    <th><label>Price — <?php echo (int) $len; ?> min</label></th>
                    <td>
                        <span>$</span>
                        <input type="text" name="caswell_settings[venmo_price_<?php echo (int) $len; ?>]" value="<?php echo esc_attr( $o[ "venmo_price_{$len}" ] ?? '' ); ?>" style="width:80px;" placeholder="0.00" />
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
```

- [ ] **Step 3: Replace the Branding Service Pricing loop**

Old (around line 509):

```php
                <tr><th colspan="2"><h3>Service Pricing</h3></th></tr>
                <?php foreach ( [ 30, 60, 90 ] as $len ) : ?>
                <tr>
                    <th><?php echo $len; ?>-min Session</th>
                    <td>
                        Price: $<input type="text" name="caswell_settings[service_price_<?php echo $len; ?>]" value="<?php echo esc_attr( $o[ "service_price_{$len}" ] ?? '' ); ?>" style="width:80px;" placeholder="0" />
                        &nbsp; Description: <input type="text" name="caswell_settings[service_description_<?php echo $len; ?>]" value="<?php echo esc_attr( $o[ "service_description_{$len}" ] ?? '' ); ?>" class="regular-text" />
                    </td>
                </tr>
                <?php endforeach; ?>
```

New:

```php
                <tr><th colspan="2"><h3>Service Pricing</h3></th></tr>
                <?php $branding_lens = caswell_enabled_session_lengths(); ?>
                <?php if ( $branding_lens ) : ?>
                <tr><td colspan="2"><p class="description">One row per session length you've enabled. Add or remove lengths in the <strong>Sessions</strong> tab.</p></td></tr>
                <?php foreach ( $branding_lens as $len ) : ?>
                <tr>
                    <th><?php echo (int) $len; ?>-min Session</th>
                    <td>
                        Price: $<input type="text" name="caswell_settings[service_price_<?php echo (int) $len; ?>]" value="<?php echo esc_attr( $o[ "service_price_{$len}" ] ?? '' ); ?>" style="width:80px;" placeholder="0" />
                        &nbsp; Description: <input type="text" name="caswell_settings[service_description_<?php echo (int) $len; ?>]" value="<?php echo esc_attr( $o[ "service_description_{$len}" ] ?? '' ); ?>" class="regular-text" />
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
```

- [ ] **Step 4: Syntax check**

```bash
php -l admin/admin-page.php
```

Expected: `No syntax errors detected in admin/admin-page.php`.

- [ ] **Step 5: Commit**

```bash
git add admin/admin-page.php
git commit -m "$(cat <<'EOF'
feat(sessions): Pricing/Venmo rows track enabled session lengths

Both the Branding tab "Service Pricing" rows and the Payments tab
Venmo price rows now render only for currently-enabled session
lengths. A "Add or remove lengths in the Sessions tab" hint is
shown above each block. Disabled-length values are still kept in
the database (sanitizer preserves them).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Homepage Services & Pricing cards

**Files:**
- Modify: `page-caswell-home.php` lines 92–123.

- [ ] **Step 1: Read context**

Read `page-caswell-home.php:90-125` to confirm exact markup.

- [ ] **Step 2: Replace the `$sessions` loop**

Old (around line 95–121):

```php
        $sessions = [
            30 => [ 'label' => '30-Minute', 'desc' => 'Focused treatment targeting specific areas of tension.' ],
            60 => [ 'label' => '60-Minute', 'desc' => 'Full-body therapeutic massage for relaxation and relief.' ],
            90 => [ 'label' => '90-Minute', 'desc' => 'Extended deep work with extra attention to problem areas.' ],
        ];
        foreach ( $sessions as $len => $cfg ) :
            if ( empty( $o["enable_{$len}min"] ) ) continue;
            $price = $o["service_price_{$len}"] ?? '';
            $desc  = $o["service_description_{$len}"] ?? $cfg['desc'];
        ?>
        <div style="
            background:#fff;border:1px solid #e0e0da;border-radius:10px;
            padding:32px 24px;text-align:center;
            box-shadow:0 2px 14px rgba(0,0,0,0.07);
            transition:transform 0.2s;
        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
            <div style="font-size:2.2rem;font-weight:700;color:#4a7c6f;"><?php echo $len; ?> <span style="font-size:1rem;font-weight:400;">min</span></div>
            <div style="font-size:1.5rem;font-weight:600;margin:10px 0 6px;"><?php echo $price ? '$' . esc_html($price) : 'Contact for pricing'; ?></div>
            <p style="color:#666;font-size:0.9rem;margin:0 0 20px;"><?php echo esc_html( $desc ); ?></p>
            <a href="<?php echo esc_url( $booking_url ); ?>" style="
                display:inline-block;background:#4a7c6f;color:#fff;
                text-decoration:none;padding:10px 24px;border-radius:6px;
                font-weight:600;font-size:0.9rem;
            ">Book This Session</a>
        </div>
        <?php endforeach; ?>
```

New:

```php
        foreach ( caswell_enabled_session_lengths() as $len ) :
            $price = $o["service_price_{$len}"] ?? '';
            $desc  = trim( (string) ( $o["service_description_{$len}"] ?? '' ) );
        ?>
        <div style="
            background:#fff;border:1px solid #e0e0da;border-radius:10px;
            padding:32px 24px;text-align:center;
            box-shadow:0 2px 14px rgba(0,0,0,0.07);
            transition:transform 0.2s;
        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
            <div style="font-size:2.2rem;font-weight:700;color:#4a7c6f;"><?php echo (int) $len; ?> <span style="font-size:1rem;font-weight:400;">min</span></div>
            <div style="font-size:1.5rem;font-weight:600;margin:10px 0 6px;"><?php echo $price ? '$' . esc_html($price) : 'Contact for pricing'; ?></div>
            <?php if ( $desc !== '' ) : ?>
            <p style="color:#666;font-size:0.9rem;margin:0 0 20px;"><?php echo esc_html( $desc ); ?></p>
            <?php endif; ?>
            <a href="<?php echo esc_url( $booking_url ); ?>" style="
                display:inline-block;background:#4a7c6f;color:#fff;
                text-decoration:none;padding:10px 24px;border-radius:6px;
                font-weight:600;font-size:0.9rem;
            ">Book This Session</a>
        </div>
        <?php endforeach; ?>
```

- [ ] **Step 3: Syntax check**

```bash
php -l page-caswell-home.php
```

Expected: `No syntax errors detected in page-caswell-home.php`.

- [ ] **Step 4: Commit**

```bash
git add page-caswell-home.php
git commit -m "$(cat <<'EOF'
feat(sessions): homepage pricing cards loop over enabled lengths

Drop the hardcoded 30/60/90 array on the homepage Services & Pricing
section. Cards now render for every length Ryan has enabled. If a
length has no admin-set description, the description paragraph is
omitted (no fallback English copy).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Update doc references to "30/60/90"

**Files:**
- Modify: `DEVELOPER.md` (lines 56, 230)
- Modify: `docs/Database-Schema.md` (line 16)
- Modify: `docs/Developer-Reference.md` (line 47)
- Modify: `docs/Installation-&-Setup.md` (line 46)
- Modify: `docs/Admin-Settings.md` (line 13)
- Modify: `docs/Payment-Configuration.md` (line 47)
- Modify: `docs/Google-Calendar.md` (line 54)

- [ ] **Step 1: Update `DEVELOPER.md`**

Around line 56:

```
| session_length | SMALLINT | Duration in minutes (30/60/90) |
```

→

```
| session_length | SMALLINT | Duration in minutes — see `caswell_session_length_options()` for offered values |
```

Around line 230:

```
- **Sessions**: Enabled lengths (30/60/90), default length
```

→

```
- **Sessions**: Enabled lengths (15-min increments from 15 to 120 — see `caswell_session_length_options()`), default length
```

- [ ] **Step 2: Update `docs/Database-Schema.md`**

Line 16:

```
| `session_length` | SMALLINT | Duration in minutes (30/60/90) |
```

→

```
| `session_length` | SMALLINT | Duration in minutes (15-min increments, configurable per Settings → Sessions) |
```

- [ ] **Step 3: Update `docs/Developer-Reference.md`**

Line 47, same edit as Database-Schema.md.

- [ ] **Step 4: Update `docs/Installation-&-Setup.md`**

Line 46:

```
3. **Sessions tab** — Enable desired session lengths (30/60/90 min)
```

→

```
3. **Sessions tab** — Enable the session lengths you want to offer (15-min increments from 15 to 120) and pick a default
```

- [ ] **Step 5: Update `docs/Admin-Settings.md`**

Line 13:

```
Enabled session lengths (30, 60, and/or 90 minutes) and the default length.
```

→

```
Enabled session lengths (any subset of 15, 30, 45, 60, 75, 90, 105, 120 minutes) and the default length. Pricing and Venmo rows in other tabs auto-follow the lengths enabled here.
```

- [ ] **Step 6: Update `docs/Payment-Configuration.md`**

Line 47:

```
| Price — 30/60/90 min | Amount shown in the Venmo deep link |
```

→

```
| Price — _N_ min | Amount shown in the Venmo deep link, one row per enabled session length |
```

- [ ] **Step 7: Update `docs/Google-Calendar.md`**

Line 54:

```
- The selected **session length** (30, 60, or 90 minutes)
```

→

```
- The selected **session length** (any length enabled in Settings → Sessions)
```

- [ ] **Step 8: Commit**

```bash
git add DEVELOPER.md docs/Database-Schema.md docs/Developer-Reference.md docs/Installation-&-Setup.md docs/Admin-Settings.md docs/Payment-Configuration.md docs/Google-Calendar.md
git commit -m "$(cat <<'EOF'
docs(sessions): drop hardcoded 30/60/90 references

Update DEVELOPER.md, docs/Database-Schema.md, docs/Developer-Reference.md,
docs/Installation-&-Setup.md, docs/Admin-Settings.md, docs/Payment-Configuration.md,
and docs/Google-Calendar.md to point at caswell_session_length_options()
(15-min increments from 15 to 120) instead of the old 30/60/90 list.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Manual verification on the live site (Tom)

**Files:** none — manual smoke test by Tom after Dropbox sync propagates the commits.

Run **all** of the following and report any unexpected behavior. Pause before moving to the calendar-color plan if anything fails.

- [ ] **PHPUnit passes locally.** Run `vendor/bin/phpunit --testsuite Unit`. Expect `OK (8 tests, 8 assertions)`.

- [ ] **Sessions tab — new options visible.** WP Admin → Caswell Booking → Sessions. Confirm the "Enabled Lengths" block now shows eight checkboxes: 15, 30, 45, 60, 75, 90, 105, 120 minutes. The 30/60/90 boxes you already had checked should still be checked.

- [ ] **Enable a new length and save.** Check 15 minutes. Save. Reload the Sessions tab. Confirm 15 is still checked.

- [ ] **Default-length dropdown updates.** With 15/30/60/90 enabled, the Default Session Length dropdown lists exactly those four. Pick 60 (assuming that was your default). Save. Confirm it stuck.

- [ ] **Pricing rows in Branding tab.** Branding tab → Service Pricing. Confirm there's a row per enabled length. Set the 15-min price to something obvious (e.g. `40`) and a description (e.g. `Quick chair-massage style session`). Save.

- [ ] **Venmo prices in Payments tab.** Payments tab → confirm same set of length rows. Set the 15-min Venmo price (e.g. `40`). Save.

- [ ] **Disable a length you don't use.** Sessions tab → uncheck 105 minutes. Save. Confirm the Pricing/Venmo tabs no longer show 105-min rows.

- [ ] **Default-length silent fallback.** Disable 60 (current default). Save. Confirm the page reloads without errors and the new default is the lowest enabled length. Re-enable 60 and set it back as the default. Save.

- [ ] **Booking widget — new length offered.** On the public booking page, confirm the new length(s) appear as session-length buttons with their prices.

- [ ] **End-to-end test booking.** Book a 15-min session via the public widget. Confirm: (a) the booking saves, (b) the calendar event is created, (c) the confirmation email arrives, (d) the booking appears in WP Admin → Bookings with `15 min`.

- [ ] **Homepage cards.** Visit the homepage. Confirm a card appears for each enabled length, with the right price, and only enabled lengths.

- [ ] **No regressions for 30/60/90.** Book a 60-min session as a regression check. Verify the existing flow still works end-to-end.

If everything passes, proceed to the calendar-color plan: `docs/superpowers/plans/2026-05-05-shared-calendar-event-color-and-v1.5.0-release.md`.

---

## Out of scope (deliberately not in this plan)

- Version bump and tag/release — handled in the calendar-color plan after both features ship.
- A filter hook for white-label installs to override `caswell_session_length_options()`.
- Rendering checkboxes for currently-disabled lengths in the Pricing/Venmo tabs (would require remembering "had data, now disabled" — not worth it).
- Backfilling tests for existing untested code outside this PR's scope.
