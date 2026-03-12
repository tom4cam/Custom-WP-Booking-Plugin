<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Cron {

    const HOOK_REMINDERS = 'caswell_send_reminders';
    const HOOK_REMINDER  = 'caswell_send_single_reminder';

    public function __construct() {
        add_action( self::HOOK_REMINDERS, [ $this, 'process_reminders' ] );
        add_action( self::HOOK_REMINDER,  [ $this, 'send_single_reminder' ] );
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK_REMINDERS ) ) {
            wp_schedule_event( time(), 'hourly', self::HOOK_REMINDERS );
        }
    }

    public static function unschedule() {
        $ts = wp_next_scheduled( self::HOOK_REMINDERS );
        if ( $ts ) wp_unschedule_event( $ts, self::HOOK_REMINDERS );

        wp_clear_scheduled_hook( self::HOOK_REMINDER );
    }

    /**
     * Schedule a per-booking reminder at the configured hours-before time.
     */
    public static function schedule_reminder( $booking_id ) {
        $booking       = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking ) return;

        $hours_before  = (int) caswell_get_option( 'reminder_hours_before', 24 );
        $start_ts      = strtotime( $booking->start_datetime );
        $reminder_ts   = $start_ts - ( $hours_before * HOUR_IN_SECONDS );

        if ( $reminder_ts <= time() ) return; // Already past

        wp_schedule_single_event( $reminder_ts, self::HOOK_REMINDER, [ $booking_id ] );
    }

    /**
     * Hourly sweep — catch any reminders that may have been missed.
     */
    public function process_reminders() {
        $hours_before = (int) caswell_get_option( 'reminder_hours_before', 24 );
        $bookings     = Caswell_Booking_DB::get_bookings_needing_reminder( $hours_before );

        $notifier = new Caswell_Notifications();
        foreach ( $bookings as $booking ) {
            // Only send if not already sent (use transient flag)
            $flag_key = 'caswell_reminder_sent_' . $booking->id;
            if ( get_transient( $flag_key ) ) continue;
            $notifier->send_reminder( $booking );
            // Flag for 2 hours so we don't double-send
            set_transient( $flag_key, 1, 2 * HOUR_IN_SECONDS );
        }
    }

    /**
     * Single-booking reminder event.
     */
    public function send_single_reminder( $booking_id ) {
        $booking = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking || 'confirmed' !== $booking->status ) return;

        $flag_key = 'caswell_reminder_sent_' . $booking_id;
        if ( get_transient( $flag_key ) ) return;

        $notifier = new Caswell_Notifications();
        $notifier->send_reminder( $booking );
        set_transient( $flag_key, 1, 2 * HOUR_IN_SECONDS );
    }
}
