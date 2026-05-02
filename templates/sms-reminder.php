<?php
/**
 * SMS Reminder template.
 * Available: $booking, $content (pre-interpolated from settings)
 */
defined( 'ABSPATH' ) || exit;

if ( ! empty( $content ) ) {
    echo $content;
    return;
}

$site_name = get_bloginfo( 'name' );
$date      = wp_date( 'D M j', caswell_local_ts($booking->start_datetime) );
$time      = wp_date( 'g:i A', caswell_local_ts($booking->start_datetime) );
$duration  = $booking->session_length;

echo "{$site_name}: Reminder — you have a {$duration}-min appointment on {$date} at {$time}. Reply STOP to unsubscribe.";
