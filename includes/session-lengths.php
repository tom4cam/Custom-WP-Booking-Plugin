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
