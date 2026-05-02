<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Booking_DB {

    const BOOKINGS_VERSION = 4;

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $bookings = "CREATE TABLE {$wpdb->prefix}caswell_bookings (
            id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id          BIGINT UNSIGNED DEFAULT NULL,
            name               VARCHAR(120)    NOT NULL DEFAULT '',
            email              VARCHAR(200)    NOT NULL DEFAULT '',
            phone              VARCHAR(30)     NOT NULL DEFAULT '',
            session_length     SMALLINT        NOT NULL DEFAULT 60,
            start_datetime     DATETIME        NOT NULL,
            end_datetime       DATETIME        NOT NULL,
            status             VARCHAR(30)     NOT NULL DEFAULT 'pending',
            payment_method     VARCHAR(20)     NOT NULL DEFAULT 'square',
            payment_status     VARCHAR(20)     NOT NULL DEFAULT 'unpaid',
            square_payment_id  VARCHAR(100)    NOT NULL DEFAULT '',
            gcal_primary_event_id VARCHAR(255) NOT NULL DEFAULT '',
            gcal_shared_event_id  VARCHAR(255) NOT NULL DEFAULT '',
            recurring_series_id BIGINT UNSIGNED DEFAULT NULL,
            notes              TEXT,
            created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY start_datetime (start_datetime),
            KEY status (status),
            KEY recurring_series_id (recurring_series_id),
            KEY gcal_shared_event_id (gcal_shared_event_id)
        ) $charset;";

        $series = "CREATE TABLE {$wpdb->prefix}caswell_recurring_series (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            client_id      BIGINT UNSIGNED DEFAULT NULL,
            session_length SMALLINT        NOT NULL DEFAULT 60,
            frequency      VARCHAR(20)     NOT NULL DEFAULT 'weekly',
            day_of_week    TINYINT         NOT NULL DEFAULT 1,
            preferred_time VARCHAR(10)     NOT NULL DEFAULT '09:00',
            start_date     DATE            NOT NULL,
            end_date       DATE            DEFAULT NULL,
            occurrences    INT             DEFAULT NULL,
            status         VARCHAR(20)     NOT NULL DEFAULT 'active',
            created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY status (status)
        ) $charset;";

        $blocks = "CREATE TABLE {$wpdb->prefix}caswell_blocks (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            start_datetime DATETIME        NOT NULL,
            end_datetime   DATETIME        NOT NULL,
            label          VARCHAR(200)    NOT NULL DEFAULT '',
            created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY start_datetime (start_datetime)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $bookings );
        dbDelta( $series );
        dbDelta( $blocks );

        update_option( 'caswell_db_version', self::BOOKINGS_VERSION );
    }

    /* ── Booking CRUD ──────────────────────────────────────────────────── */

    public static function insert_booking( array $data ) {
        global $wpdb;
        $defaults = [
            'client_id'          => null,
            'name'               => '',
            'email'              => '',
            'phone'              => '',
            'session_length'     => 60,
            'start_datetime'     => '',
            'end_datetime'       => '',
            'status'             => 'confirmed',
            'payment_method'     => 'square',
            'payment_status'     => 'paid',
            'square_payment_id'  => '',
            'recurring_series_id'=> null,
            'notes'              => '',
            'created_at'         => current_time( 'mysql' ),
        ];
        $row = array_merge( $defaults, $data );
        $result = $wpdb->insert( "{$wpdb->prefix}caswell_bookings", $row );
        return $result ? $wpdb->insert_id : false;
    }

    public static function get_booking( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caswell_bookings WHERE id = %d",
                $id
            )
        );
    }

    public static function get_bookings_for_range( $start, $end, $statuses = [ 'confirmed', 'pending' ] ) {
        global $wpdb;
        $placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $args = array_merge( [ $start, $end ], $statuses );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caswell_bookings
                 WHERE start_datetime < %s
                   AND end_datetime   > %s
                   AND status IN ($placeholders)
                 ORDER BY start_datetime ASC",
                ...$args
            )
        );
    }

    public static function get_client_bookings( $client_id, $upcoming_only = false ) {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}caswell_bookings WHERE client_id = %d";
        if ( $upcoming_only ) {
            $sql .= " AND start_datetime >= NOW() AND status != 'cancelled'";
        }
        $sql .= ' ORDER BY start_datetime ASC';
        return $wpdb->get_results( $wpdb->prepare( $sql, $client_id ) );
    }

    /**
     * Update start/end/length of a booking. Used by the admin reschedule UI.
     */
    public static function update_booking_times( $id, $start_datetime, $end_datetime, $session_length = null ) {
        global $wpdb;
        $data = [
            'start_datetime' => $start_datetime,
            'end_datetime'   => $end_datetime,
        ];
        $fmt  = [ '%s', '%s' ];
        if ( null !== $session_length ) {
            $data['session_length'] = (int) $session_length;
            $fmt[] = '%d';
        }
        return $wpdb->update(
            "{$wpdb->prefix}caswell_bookings",
            $data,
            [ 'id' => absint( $id ) ],
            $fmt,
            [ '%d' ]
        );
    }

    public static function update_booking_notes( $id, $notes ) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}caswell_bookings",
            [ 'notes' => (string) $notes ],
            [ 'id' => absint( $id ) ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Permanently delete a booking row. The cron sweep and other lookups
     * pretend it never existed. Used by the admin "Delete" action when a
     * booking is no longer worth keeping (test entries, very old, etc.).
     */
    public static function delete_booking( $id ) {
        global $wpdb;
        return $wpdb->delete(
            "{$wpdb->prefix}caswell_bookings",
            [ 'id' => absint( $id ) ],
            [ '%d' ]
        );
    }

    public static function update_booking_status( $id, $status ) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}caswell_bookings",
            [ 'status' => sanitize_text_field( $status ) ],
            [ 'id' => absint( $id ) ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Persist Google Calendar event IDs after a booking is created.
     */
    public static function update_booking_event_ids( $id, $primary_event_id, $shared_event_id ) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}caswell_bookings",
            [
                'gcal_primary_event_id' => (string) $primary_event_id,
                'gcal_shared_event_id'  => (string) $shared_event_id,
            ],
            [ 'id' => absint( $id ) ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Upcoming, still-confirmed bookings that have a shared-calendar event ID.
     * Used by the cron sweep that detects manual deletions from the shared calendar.
     */
    public static function get_upcoming_bookings_with_shared_event() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}caswell_bookings
              WHERE status = 'confirmed'
                AND gcal_shared_event_id <> ''
                AND start_datetime >= NOW()
              ORDER BY start_datetime ASC"
        );
    }

    public static function cancel_future_series_bookings( $series_id ) {
        global $wpdb;
        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}caswell_bookings
                    SET status = 'cancelled'
                  WHERE recurring_series_id = %d
                    AND start_datetime > NOW()
                    AND status IN ('confirmed','pending')",
                $series_id
            )
        );
    }

    public static function get_future_series_bookings( $series_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caswell_bookings
                  WHERE recurring_series_id = %d
                    AND start_datetime > NOW()
                    AND status IN ('confirmed','pending')
                  ORDER BY start_datetime ASC",
                $series_id
            )
        );
    }

    public static function get_bookings_needing_reminder( $hours_before ) {
        global $wpdb;
        $from = date( 'Y-m-d H:i:s', strtotime( "+{$hours_before} hours" ) );
        $to   = date( 'Y-m-d H:i:s', strtotime( "+{$hours_before} hours +15 minutes" ) );
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caswell_bookings
                  WHERE start_datetime BETWEEN %s AND %s
                    AND status = 'confirmed'",
                $from,
                $to
            )
        );
    }

    /* ── Block CRUD ────────────────────────────────────────────────────── */

    public static function insert_block( array $data ) {
        global $wpdb;
        $defaults = [
            'start_datetime' => '',
            'end_datetime'   => '',
            'label'          => '',
            'created_at'     => current_time( 'mysql' ),
        ];
        $row    = array_merge( $defaults, $data );
        $result = $wpdb->insert( "{$wpdb->prefix}caswell_blocks", $row );
        return $result ? $wpdb->insert_id : false;
    }

    public static function get_blocks_for_range( $range_start, $range_end ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caswell_blocks
                  WHERE start_datetime < %s
                    AND end_datetime   > %s
                  ORDER BY start_datetime ASC",
                $range_end,
                $range_start
            )
        );
    }

    public static function delete_block( $id ) {
        global $wpdb;
        return $wpdb->delete(
            "{$wpdb->prefix}caswell_blocks",
            [ 'id' => absint( $id ) ],
            [ '%d' ]
        );
    }

    public static function get_all_blocks() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}caswell_blocks
              WHERE end_datetime >= NOW()
              ORDER BY start_datetime ASC"
        );
    }

    /* ── Series CRUD ───────────────────────────────────────────────────── */

    public static function insert_series( array $data ) {
        global $wpdb;
        $defaults = [
            'client_id'      => null,
            'session_length' => 60,
            'frequency'      => 'weekly',
            'day_of_week'    => 1,
            'preferred_time' => '09:00',
            'start_date'     => '',
            'end_date'       => null,
            'occurrences'    => null,
            'status'         => 'active',
            'created_at'     => current_time( 'mysql' ),
        ];
        $row = array_merge( $defaults, $data );
        $result = $wpdb->insert( "{$wpdb->prefix}caswell_recurring_series", $row );
        return $result ? $wpdb->insert_id : false;
    }

    public static function get_series( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caswell_recurring_series WHERE id = %d",
                $id
            )
        );
    }

    public static function get_client_series( $client_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}caswell_recurring_series WHERE client_id = %d ORDER BY created_at DESC",
                $client_id
            )
        );
    }

    public static function update_series_status( $id, $status ) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}caswell_recurring_series",
            [ 'status' => sanitize_text_field( $status ) ],
            [ 'id' => absint( $id ) ],
            [ '%s' ],
            [ '%d' ]
        );
    }
}
