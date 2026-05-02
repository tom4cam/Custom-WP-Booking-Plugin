<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Cron {

    const HOOK_REMINDERS = 'caswell_send_reminders';
    const HOOK_REMINDER  = 'caswell_send_single_reminder';
    const HOOK_GCAL_SYNC = 'caswell_sync_shared_calendar';

    public function __construct() {
        add_action( self::HOOK_REMINDERS, [ $this, 'process_reminders' ] );
        add_action( self::HOOK_REMINDER,  [ $this, 'send_single_reminder' ] );
        add_action( self::HOOK_GCAL_SYNC, [ $this, 'sync_shared_calendar' ] );
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK_REMINDERS ) ) {
            wp_schedule_event( time(), 'hourly', self::HOOK_REMINDERS );
        }
        if ( ! wp_next_scheduled( self::HOOK_GCAL_SYNC ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::HOOK_GCAL_SYNC );
        }
    }

    public static function unschedule() {
        foreach ( [ self::HOOK_REMINDERS, self::HOOK_GCAL_SYNC ] as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) wp_unschedule_event( $ts, $hook );
        }
        wp_clear_scheduled_hook( self::HOOK_REMINDER );
    }

    /**
     * Schedule a per-booking reminder at the configured hours-before time.
     */
    public static function schedule_reminder( $booking_id ) {
        $booking       = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking ) return;

        $hours_before  = (int) caswell_get_option( 'reminder_hours_before', 24 );
        $start_ts      = caswell_local_ts($booking->start_datetime);
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

    /**
     * Hourly sync sweep — detect shared-calendar events that have been
     * deleted manually (e.g., from Ryan's Google Calendar UI). For each
     * missing event, mark the booking cancelled, clean up the primary-
     * calendar copy, and notify owner + client.
     */
    public function sync_shared_calendar() {
        $shared_cal_id = caswell_get_option( 'shared_calendar_id' );
        if ( ! $shared_cal_id ) return;

        $bookings = Caswell_Booking_DB::get_upcoming_bookings_with_shared_event();
        if ( empty( $bookings ) ) return;

        $gcal     = new Caswell_Google_Calendar();
        $notifier = new Caswell_Notifications();

        foreach ( $bookings as $booking ) {
            $exists = $gcal->event_exists( $shared_cal_id, $booking->gcal_shared_event_id );
            // Skip on null (API/auth error) so a transient hiccup doesn't mass-cancel.
            if ( null === $exists || true === $exists ) continue;

            // Manual deletion detected — run the full cancellation flow.
            Caswell_Booking_DB::update_booking_status( $booking->id, 'cancelled' );

            if ( ! empty( $booking->gcal_primary_event_id ) ) {
                $gcal->delete_event( 'primary', $booking->gcal_primary_event_id );
            }

            $notifier->send_cancellation( $booking );

            caswell_log( 'gcal', "Detected manual deletion from shared calendar; cancelled booking #{$booking->id}", [
                'booking_id'      => $booking->id,
                'client'          => $booking->name,
                'shared_event_id' => $booking->gcal_shared_event_id,
            ] );
        }
    }
}
