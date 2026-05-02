<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Recurring {

    /**
     * Hard cap on a single recurring series. The booking page enforces this
     * client-side as well, so a request that arrives with more is either
     * spoofed or stale; in either case we silently cap to 12 and surface
     * the cap to the client via the `capped` flag in create_series's return.
     */
    const MAX_OCCURRENCES = 12;

    /**
     * Generate all occurrence datetimes for a series starting at $start_ts.
     *
     * @param int    $start_ts  Unix timestamp of first occurrence
     * @param string $frequency weekly|biweekly|monthly
     * @param string $end_date  Y-m-d or empty
     * @param int    $occurrences  Max count (0 = use end_date)
     * @return DateTime[]
     */
    public function generate_occurrences( $start_ts, $frequency, $end_date = '', $occurrences = 0 ) {
        $tz    = new DateTimeZone( wp_timezone_string() );
        $dates = [];
        $limit = $occurrences > 0 ? min( $occurrences, self::MAX_OCCURRENCES ) : self::MAX_OCCURRENCES;

        $cursor = new DateTime( '@' . $start_ts );
        $cursor->setTimezone( $tz );

        $end_dt = null;
        if ( $end_date ) {
            $end_dt = new DateTime( $end_date . ' 23:59:59', $tz );
        }

        for ( $i = 0; $i < $limit; $i++ ) {
            if ( $end_dt && $cursor > $end_dt ) break;
            $dates[] = clone $cursor;

            // Advance cursor based on frequency
            if ( 'monthly' === $frequency ) {
                // Handle monthly carefully to avoid day-of-month overflow
                // (e.g., Jan 31 + 1 month should become Feb 28, not March 3)
                $target_day = (int) $dates[0]->format( 'j' ); // original day-of-month
                $cursor->modify( '+1 month' );
                $actual_day = (int) $cursor->format( 'j' );
                // If the month doesn't have that day, clamp to last day of month
                if ( $actual_day < $target_day && $actual_day < 4 ) {
                    $cursor->modify( 'last day of previous month' );
                    // Restore the time
                    $cursor->setTime(
                        (int) $dates[0]->format( 'H' ),
                        (int) $dates[0]->format( 'i' ),
                        0
                    );
                }
            } elseif ( 'biweekly' === $frequency ) {
                $cursor->modify( '+14 days' );
            } else {
                // weekly (default)
                $cursor->modify( '+7 days' );
            }
        }

        return $dates;
    }

    /**
     * Check all occurrences for availability and create series + bookings.
     *
     * @return array|WP_Error  [ 'series_id' => int, 'first_booking_id' => int, 'conflicts' => [], 'capped' => bool ]
     */
    public function create_series( array $args ) {
        $client_id      = $args['client_id'] ?? null;
        $name           = $args['name'];
        $email          = $args['email'];
        $phone          = $args['phone'] ?? '';
        $session_length = (int) $args['session_length'];
        $frequency      = $args['frequency'];
        $start_ts       = (int) $args['start_ts'];
        $end_date       = $args['end_date'] ?? '';
        $occ_count      = (int) ( $args['occurrences'] ?? 0 );
        $payment_method = $args['payment_method'] ?? 'square';
        $payment_status = $args['payment_status'] ?? 'unpaid';
        $payment_id     = $args['payment_id'] ?? '';
        $notes          = $args['notes'] ?? '';

        // Validate frequency
        if ( ! in_array( $frequency, [ 'weekly', 'biweekly', 'monthly' ], true ) ) {
            return new WP_Error( 'invalid_frequency', 'Invalid recurring frequency.' );
        }

        $occurrences = $this->generate_occurrences( $start_ts, $frequency, $end_date, $occ_count );

        if ( empty( $occurrences ) ) {
            return new WP_Error( 'no_occurrences', 'No occurrences could be generated for the given parameters.' );
        }

        // Check if request was capped
        $was_capped = ( $occ_count > self::MAX_OCCURRENCES );

        $gcal      = new Caswell_Google_Calendar();
        $conflicts = [];

        foreach ( $occurrences as $occ_dt ) {
            if ( ! $gcal->is_slot_available( $occ_dt->getTimestamp(), $session_length ) ) {
                $conflicts[] = $occ_dt->format( 'Y-m-d H:i' );
            }
        }

        if ( ! empty( $conflicts ) ) {
            return new WP_Error(
                'recurring_conflict',
                'The following dates have conflicts: ' . implode( ', ', $conflicts ),
                $conflicts
            );
        }

        // Create series record
        $first_dt    = $occurrences[0];
        $series_id   = Caswell_Booking_DB::insert_series( [
            'client_id'      => $client_id,
            'session_length' => $session_length,
            'frequency'      => $frequency,
            'day_of_week'    => (int) $first_dt->format( 'N' ),
            'preferred_time' => $first_dt->format( 'H:i' ),
            'start_date'     => $first_dt->format( 'Y-m-d' ),
            'end_date'       => $end_date ?: null,
            'occurrences'    => $occ_count ?: null,
            'status'         => 'active',
        ] );

        if ( ! $series_id ) {
            return new WP_Error( 'series_insert', 'Failed to create recurring series.' );
        }

        $first_booking_id = null;
        $failed_bookings  = [];

        foreach ( $occurrences as $occ_dt ) {
            $end_dt = clone $occ_dt;
            $end_dt->modify( "+{$session_length} minutes" );

            $booking_id = Caswell_Booking_DB::insert_booking( [
                'client_id'          => $client_id,
                'name'               => $name,
                'email'              => $email,
                'phone'              => $phone,
                'session_length'     => $session_length,
                'start_datetime'     => $occ_dt->format( 'Y-m-d H:i:s' ),
                'end_datetime'       => $end_dt->format( 'Y-m-d H:i:s' ),
                'status'             => 'confirmed',
                'payment_method'     => $payment_method,
                'payment_status'     => $payment_status,
                'square_payment_id'  => $payment_id,
                'recurring_series_id'=> $series_id,
                'notes'              => $notes,
            ] );

            if ( $booking_id ) {
                Caswell_Cron::schedule_reminder( $booking_id );
                if ( null === $first_booking_id ) {
                    $first_booking_id = $booking_id;
                }
            } else {
                $failed_bookings[] = $occ_dt->format( 'Y-m-d H:i' );
                caswell_log( 'recurring', 'Failed to insert booking in series', [
                    'series_id' => $series_id,
                    'date'      => $occ_dt->format( 'Y-m-d H:i:s' ),
                ] );
            }
        }

        if ( ! empty( $failed_bookings ) ) {
            caswell_log( 'recurring', "Series #{$series_id}: " . count( $failed_bookings ) . ' bookings failed to insert' );
        }

        caswell_log( 'recurring', "Series #{$series_id} created with " . count( $occurrences ) . ' occurrences' );

        return [
            'series_id'        => $series_id,
            'first_booking_id' => $first_booking_id,
            'conflicts'        => [],
            'capped'           => $was_capped,
        ];
    }
}
