<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Booking_Handler {

    public function __construct() {
        add_action( 'wp_ajax_caswell_get_slots',        [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_nopriv_caswell_get_slots', [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_caswell_submit_booking',        [ $this, 'ajax_submit_booking' ] );
        add_action( 'wp_ajax_nopriv_caswell_submit_booking', [ $this, 'ajax_submit_booking' ] );
        add_action( 'wp_ajax_caswell_cancel_booking',        [ $this, 'ajax_cancel_booking' ] );
        add_action( 'wp_ajax_nopriv_caswell_cancel_booking', [ $this, 'ajax_cancel_booking' ] );
        add_action( 'wp_ajax_caswell_cancel_series',         [ $this, 'ajax_cancel_series' ] );
        add_action( 'wp_ajax_nopriv_caswell_cancel_series',  [ $this, 'ajax_cancel_series' ] );
        add_action( 'wp_ajax_caswell_add_block',             [ $this, 'ajax_add_block' ] );
        add_action( 'wp_ajax_caswell_delete_block',          [ $this, 'ajax_delete_block' ] );
    }

    /* ── Rate limiting ─────────────────────────────────────────────────── */

    private function check_rate_limit() {
        // Per-IP rate limiting
        $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $key = 'caswell_rate_' . md5( $ip );
        $count = (int) get_transient( $key );
        if ( $count >= 5 ) return false;
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );

        // Per-user rate limiting for logged-in users
        if ( is_user_logged_in() ) {
            $user_key = 'caswell_rate_u_' . get_current_user_id();
            $user_count = (int) get_transient( $user_key );
            if ( $user_count >= 10 ) return false;
            set_transient( $user_key, $user_count + 1, HOUR_IN_SECONDS );
        }

        return true;
    }

    /* ── AJAX: Get available slots ─────────────────────────────────────── */

    public function ajax_get_slots() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        $date   = sanitize_text_field( $_POST['date'] ?? '' );
        $length = absint( $_POST['session_length'] ?? 60 );

        if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( 'Invalid date.' );
        }

        // Validate date is a real calendar date
        $parts = explode( '-', $date );
        if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
            wp_send_json_error( 'Invalid date.' );
        }

        $enabled = caswell_enabled_session_lengths();
        if ( ! in_array( $length, $enabled, true ) ) {
            wp_send_json_error( 'Invalid session length.' );
        }

        // Check date is not in past
        if ( $date < wp_date( 'Y-m-d' ) ) {
            wp_send_json_success( [] );
        }

        $gcal   = new Caswell_Google_Calendar();
        $buffer = (int) caswell_get_option( 'buffer_time', 15 );

        // Validate buffer vs session length
        if ( $buffer >= $length ) {
            caswell_log( 'booking', "Buffer time ({$buffer}min) >= session length ({$length}min) — no slots possible" );
        }

        $windows = $gcal->get_available_windows( $date );
        $slots   = $gcal->windows_to_slots( $windows, $length, $buffer );

        // Format for JS
        $tz       = wp_timezone_string();
        $formatted = [];
        foreach ( $slots as $slot ) {
            $formatted[] = [
                'start_ts'    => $slot['start'],
                'end_ts'      => $slot['end'],
                'start_label' => wp_date( 'g:i A', $slot['start'] ),
                'end_label'   => wp_date( 'g:i A', $slot['end'] ),
            ];
        }

        // Filter out slots within the minimum advance window
        $min_hours = (int) caswell_get_option( 'min_hours_advance', 24 );
        $cutoff    = time() + $min_hours * 3600;
        $formatted = array_values( array_filter( $formatted, function( $slot ) use ( $cutoff ) {
            return $slot['start_ts'] >= $cutoff;
        } ) );

        wp_send_json_success( $formatted );
    }

    /* ── AJAX: Submit booking ──────────────────────────────────────────── */

    public function ajax_submit_booking() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        if ( ! $this->check_rate_limit() ) {
            wp_send_json_error( 'Too many booking attempts. Please try again later.' );
        }

        // Sanitize inputs
        $name           = sanitize_text_field( $_POST['name'] ?? '' );
        $email          = sanitize_email( $_POST['email'] ?? '' );
        $phone          = sanitize_text_field( $_POST['phone'] ?? '' );
        $session_length = absint( $_POST['session_length'] ?? 60 );
        $start_ts       = absint( $_POST['start_ts'] ?? 0 );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? 'square' );
        $square_token   = sanitize_text_field( $_POST['square_token'] ?? '' );
        $notes          = sanitize_textarea_field( $_POST['notes'] ?? '' );

        // Recurring fields
        $is_recurring      = ! empty( $_POST['recurring'] );
        $rec_frequency     = sanitize_text_field( $_POST['rec_frequency'] ?? 'weekly' );
        $rec_end_date      = sanitize_text_field( $_POST['rec_end_date'] ?? '' );
        $rec_occurrences   = absint( $_POST['rec_occurrences'] ?? 0 );

        // Validate required
        if ( ! $name || ! $email || ! $start_ts ) {
            wp_send_json_error( 'Missing required fields.' );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( 'Invalid email address.' );
        }
        $enabled = caswell_enabled_session_lengths();
        if ( ! in_array( $session_length, $enabled, true ) ) {
            wp_send_json_error( 'Invalid session length.' );
        }

        // Validate recurring end date if provided
        if ( $is_recurring && $rec_end_date ) {
            $rec_parts = explode( '-', $rec_end_date );
            if ( count( $rec_parts ) !== 3 || ! checkdate( (int) $rec_parts[1], (int) $rec_parts[2], (int) $rec_parts[0] ) ) {
                wp_send_json_error( 'Invalid recurring end date.' );
            }
        }

        // Validate payment method
        if ( ! in_array( $payment_method, [ 'square', 'venmo' ], true ) ) {
            wp_send_json_error( 'Invalid payment method.' );
        }

        $tz    = new DateTimeZone( wp_timezone_string() );
        $start = new DateTime( '@' . $start_ts );
        $start->setTimezone( $tz );
        $end   = clone $start;
        $end->modify( "+{$session_length} minutes" );

        // Enforce minimum advance booking window
        $min_hours = (int) caswell_get_option( 'min_hours_advance', 24 );
        if ( $start_ts < time() + $min_hours * 3600 ) {
            wp_send_json_error( 'This appointment must be booked at least ' . $min_hours . ' hours in advance.' );
        }

        // Verify availability (with transient-based locking to reduce race conditions)
        $lock_key = 'caswell_slot_lock_' . $start_ts . '_' . $session_length;
        if ( get_transient( $lock_key ) ) {
            wp_send_json_error( 'That time slot is being booked by someone else. Please choose another.' );
        }
        // Set a short lock (30 seconds) to prevent concurrent bookings
        set_transient( $lock_key, 1, 30 );

        $gcal = new Caswell_Google_Calendar();
        if ( ! $gcal->is_slot_available( $start_ts, $session_length ) ) {
            delete_transient( $lock_key );
            wp_send_json_error( 'That time slot is no longer available. Please choose another.' );
        }

        // Process payment
        $payment_id     = '';
        $payment_status = 'unpaid';

        if ( 'square' === $payment_method ) {
            if ( ! $square_token ) {
                delete_transient( $lock_key );
                wp_send_json_error( 'Card token missing.' );
            }
            $charge = $this->charge_square( $square_token, $session_length, $name, $email );
            if ( is_wp_error( $charge ) ) {
                delete_transient( $lock_key );
                wp_send_json_error( $charge->get_error_message() );
            }
            $payment_id     = $charge;
            $payment_status = 'paid';
        }

        // Determine client_id
        $client_id = is_user_logged_in() ? get_current_user_id() : null;

        // Handle recurring series
        $series_id = null;
        if ( $is_recurring ) {
            $recurring = new Caswell_Recurring();
            $result    = $recurring->create_series( [
                'client_id'      => $client_id,
                'name'           => $name,
                'email'          => $email,
                'phone'          => $phone,
                'session_length' => $session_length,
                'frequency'      => $rec_frequency,
                'start_ts'       => $start_ts,
                'end_date'       => $rec_end_date,
                'occurrences'    => $rec_occurrences,
                'payment_method' => $payment_method,
                'payment_status' => $payment_status,
                'payment_id'     => $payment_id,
                'notes'          => $notes,
            ] );
            if ( is_wp_error( $result ) ) {
                delete_transient( $lock_key );
                wp_send_json_error( $result->get_error_message() );
            }
            $booking_id = $result['first_booking_id'];
            $series_id  = $result['series_id'];
        } else {
            // Single booking
            $booking_id = Caswell_Booking_DB::insert_booking( [
                'client_id'         => $client_id,
                'name'              => $name,
                'email'             => $email,
                'phone'             => $phone,
                'session_length'    => $session_length,
                'start_datetime'    => $start->format( 'Y-m-d H:i:s' ),
                'end_datetime'      => $end->format( 'Y-m-d H:i:s' ),
                'status'            => 'confirmed',
                'payment_method'    => $payment_method,
                'payment_status'    => $payment_status,
                'square_payment_id' => $payment_id,
                'notes'             => $notes,
            ] );
        }

        // Release lock
        delete_transient( $lock_key );

        if ( ! $booking_id ) {
            caswell_log( 'booking', 'Failed to insert booking', [
                'name'  => $name,
                'email' => $email,
                'start' => $start->format( 'Y-m-d H:i:s' ),
            ] );
            wp_send_json_error( 'Failed to save booking. Please contact us.' );
        }

        $booking = Caswell_Booking_DB::get_booking( $booking_id );

        // Post to both Google Calendars (non-fatal on failure)
        $desc = "{$session_length}-min massage — {$name}" . ( $notes ? "\n\n{$notes}" : '' );
        $event_id = $gcal->create_event( 'primary', "Ryan Massage — {$name}", $start, $end, $desc );
        if ( ! $event_id ) {
            caswell_log( 'booking', "Google Calendar event creation failed for booking #{$booking_id}" );
        }
        $shared_cal_id = caswell_get_option( 'shared_calendar_id' );
        if ( $shared_cal_id ) {
            $gcal->create_event( $shared_cal_id, "Ryan Massage — {$name}", $start, $end, $desc );
        }

        // Send notifications
        $notifier = new Caswell_Notifications();
        $notifier->send_confirmation( $booking );

        // Schedule reminder
        Caswell_Cron::schedule_reminder( $booking_id );

        // Save phone to user meta if logged in
        if ( $client_id && $phone ) {
            update_user_meta( $client_id, 'caswell_phone', $phone );
        }

        caswell_log( 'booking', "Booking #{$booking_id} created", [
            'name'    => $name,
            'start'   => $start->format( 'Y-m-d H:i:s' ),
            'payment' => $payment_method,
        ] );

        // Venmo info for response
        $venmo_user  = caswell_get_option( 'venmo_username' );
        $price_key   = "venmo_price_{$session_length}";
        $venmo_price = caswell_get_option( $price_key );

        wp_send_json_success( [
            'booking_id'    => $booking_id,
            'start_label'   => wp_date( 'l, F j, Y \a\t g:i A', $start_ts ),
            'venmo_user'    => $venmo_user,
            'venmo_price'   => $venmo_price,
            'venmo_link'    => $venmo_user
                ? "venmo://paycharge?txn=pay&recipients={$venmo_user}&amount={$venmo_price}"
                : '',
        ] );
    }

    /* ── AJAX: Cancel single booking ───────────────────────────────────── */

    public function ajax_cancel_booking() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        if ( ! $booking_id ) wp_send_json_error( 'Invalid booking.' );

        $booking = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking ) wp_send_json_error( 'Booking not found.' );

        // Ownership check
        if ( is_user_logged_in() ) {
            if ( (int) $booking->client_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
                wp_send_json_error( 'Not authorized.' );
            }
        } else {
            wp_send_json_error( 'You must be logged in to cancel bookings.' );
        }

        Caswell_Booking_DB::update_booking_status( $booking_id, 'cancelled' );

        // Send cancellation notifications
        $notifier = new Caswell_Notifications();
        $notifier->send_cancellation( $booking );

        caswell_log( 'booking', "Booking #{$booking_id} cancelled by user #" . get_current_user_id() );

        wp_send_json_success( 'Booking cancelled.' );
    }

    /* ── AJAX: Cancel recurring series ────────────────────────────────── */

    public function ajax_cancel_series() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) wp_send_json_error( 'Not authorized.' );

        $series_id = absint( $_POST['series_id'] ?? 0 );
        if ( ! $series_id ) wp_send_json_error( 'Invalid series.' );

        $series = Caswell_Booking_DB::get_series( $series_id );
        if ( ! $series ) wp_send_json_error( 'Series not found.' );
        if ( (int) $series->client_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Not authorized.' );
        }

        // Get future bookings before cancelling (for notifications)
        $future_bookings = Caswell_Booking_DB::get_future_series_bookings( $series_id );

        Caswell_Booking_DB::cancel_future_series_bookings( $series_id );
        Caswell_Booking_DB::update_series_status( $series_id, 'cancelled' );

        // Send cancellation notification for the first future booking as representative
        if ( ! empty( $future_bookings ) ) {
            $notifier = new Caswell_Notifications();
            $notifier->send_cancellation( $future_bookings[0] );
        }

        caswell_log( 'booking', "Series #{$series_id} cancelled by user #" . get_current_user_id() );

        wp_send_json_success( 'Recurring series cancelled.' );
    }

    /* ── AJAX: Admin time blocks ───────────────────────────────────────── */

    public function ajax_add_block() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Not authorized.' );
        }

        $label = sanitize_text_field( $_POST['label'] ?? '' );
        $start = sanitize_text_field( $_POST['start_datetime'] ?? '' );
        $end   = sanitize_text_field( $_POST['end_datetime'] ?? '' );

        if ( ! $start || ! $end ) {
            wp_send_json_error( 'Start and end are required.' );
        }

        // Validate end is after start
        $start_dt = strtotime( $start );
        $end_dt   = strtotime( $end );
        if ( ! $start_dt || ! $end_dt ) {
            wp_send_json_error( 'Invalid date/time format.' );
        }
        if ( $end_dt <= $start_dt ) {
            wp_send_json_error( 'End time must be after start time.' );
        }

        $id = Caswell_Booking_DB::insert_block( [
            'start_datetime' => $start,
            'end_datetime'   => $end,
            'label'          => $label,
        ] );

        if ( ! $id ) {
            wp_send_json_error( 'Failed to add block.' );
        }

        caswell_log( 'admin', "Time block #{$id} added: {$start} to {$end}" );

        wp_send_json_success( [ 'id' => $id ] );
    }

    public function ajax_delete_block() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Not authorized.' );
        }

        $id = absint( $_POST['block_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( 'Invalid block ID.' );
        }

        Caswell_Booking_DB::delete_block( $id );
        caswell_log( 'admin', "Time block #{$id} deleted" );
        wp_send_json_success();
    }

    /* ── Square payment ────────────────────────────────────────────────── */

    private function charge_square( $token, $session_length, $name, $email ) {
        $access_token = caswell_decrypt( caswell_get_option( 'square_access_token' ) );
        $location_id  = caswell_get_option( 'square_location_id' );
        $sandbox      = caswell_get_option( 'square_sandbox_mode' );

        if ( ! $access_token || ! $location_id ) {
            return new WP_Error( 'square_config', 'Payment system not configured.' );
        }

        $price_key   = "venmo_price_{$session_length}";
        $price_cents = (int) ( (float) caswell_get_option( $price_key, 0 ) * 100 );

        if ( $price_cents <= 0 ) {
            return new WP_Error( 'square_price', 'No price configured for this session length.' );
        }

        $base_url = $sandbox
            ? 'https://connect.squareupsandbox.com'
            : 'https://connect.squareup.com';

        $idempotency_key = wp_generate_uuid4();

        $body = [
            'idempotency_key' => $idempotency_key,
            'source_id'       => $token,
            'amount_money'    => [
                'amount'   => $price_cents,
                'currency' => 'USD',
            ],
            'location_id' => $location_id,
            'buyer_email_address' => $email,
            'note'        => "Massage session — {$session_length} min — {$name}",
        ];

        caswell_log( 'square', 'Initiating payment', [
            'amount_cents' => $price_cents,
            'name'         => $name,
            'sandbox'      => (bool) $sandbox,
        ] );

        $response = wp_remote_post( "{$base_url}/v2/payments", [
            'timeout' => 20,
            'headers' => [
                'Authorization' => "Bearer {$access_token}",
                'Content-Type'  => 'application/json',
                'Square-Version'=> '2024-01-17',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'square', 'Payment request failed: ' . $response->get_error_message() );
            return new WP_Error( 'square_request', 'Payment request failed: ' . $response->get_error_message() );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $data        = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['payment']['id'] ) && in_array( $data['payment']['status'], [ 'COMPLETED', 'APPROVED' ], true ) ) {
            caswell_log( 'square', "Payment successful: {$data['payment']['id']}", [
                'status' => $data['payment']['status'],
                'amount' => $price_cents,
            ] );
            return $data['payment']['id'];
        }

        $error_msg  = $data['errors'][0]['detail'] ?? 'Payment failed.';
        $error_code = $data['errors'][0]['code'] ?? 'UNKNOWN';

        caswell_log( 'square', "Payment failed: {$error_code} — {$error_msg}", [
            'http_status' => $status_code,
            'errors'      => $data['errors'] ?? [],
        ] );

        return new WP_Error( 'square_payment', $error_msg );
    }

    /**
     * Refund a Square payment.
     *
     * @param string $payment_id  Square payment ID
     * @param int    $amount_cents Amount to refund in cents (0 = full refund)
     * @return string|WP_Error    Refund ID on success
     */
    public function refund_square( $payment_id, $amount_cents = 0 ) {
        $access_token = caswell_decrypt( caswell_get_option( 'square_access_token' ) );
        $sandbox      = caswell_get_option( 'square_sandbox_mode' );

        if ( ! $access_token || ! $payment_id ) {
            return new WP_Error( 'refund_config', 'Cannot process refund — missing configuration.' );
        }

        $base_url = $sandbox
            ? 'https://connect.squareupsandbox.com'
            : 'https://connect.squareup.com';

        $body = [
            'idempotency_key' => wp_generate_uuid4(),
            'payment_id'      => $payment_id,
            'reason'          => 'Appointment cancelled',
        ];

        if ( $amount_cents > 0 ) {
            $body['amount_money'] = [
                'amount'   => $amount_cents,
                'currency' => 'USD',
            ];
        }

        $response = wp_remote_post( "{$base_url}/v2/refunds", [
            'timeout' => 20,
            'headers' => [
                'Authorization'  => "Bearer {$access_token}",
                'Content-Type'   => 'application/json',
                'Square-Version' => '2024-01-17',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'square', 'Refund request failed: ' . $response->get_error_message() );
            return new WP_Error( 'refund_request', 'Refund request failed.' );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['refund']['id'] ) ) {
            caswell_log( 'square', "Refund successful: {$data['refund']['id']} for payment {$payment_id}" );
            return $data['refund']['id'];
        }

        $error_msg = $data['errors'][0]['detail'] ?? 'Refund failed.';
        caswell_log( 'square', "Refund failed for {$payment_id}: {$error_msg}" );
        return new WP_Error( 'refund_failed', $error_msg );
    }
}
