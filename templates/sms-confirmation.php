<?php
/**
 * SMS Confirmation template.
 * Return a plain-text string — no HTML.
 * Available: $booking, $content (pre-interpolated from settings)
 */
defined( 'ABSPATH' ) || exit;

if ( ! empty( $content ) ) {
    echo $content;
    return;
}

$site_name      = get_bloginfo( 'name' );
$date           = wp_date( 'D M j', strtotime( $booking->start_datetime ) );
$time           = wp_date( 'g:i A', strtotime( $booking->start_datetime ) );
$duration       = $booking->session_length;
$reschedule_url = caswell_booking_reschedule_url( $booking );

echo "{$site_name}: Your {$duration}-min appointment on {$date} at {$time} is confirmed. Reschedule/cancel: {$reschedule_url} (cancellations <24h are non-refundable). Reply STOP to unsubscribe.";
