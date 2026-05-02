<?php
defined( 'ABSPATH' ) || exit;

/**
 * Google Calendar integration using OAuth2 credentials.
 * No external library — all requests via wp_remote_post / wp_remote_get.
 */
class Caswell_Google_Calendar {

    private $access_token = null;

    /* ── OAuth2 token via refresh token ────────────────────────────────── */

    private function get_access_token() {
        if ( $this->access_token ) return $this->access_token;

        $cached = get_transient( 'caswell_google_token' );
        if ( $cached ) {
            $this->access_token = $cached;
            return $cached;
        }

        $client_id     = caswell_get_option( 'google_client_id' );
        $client_secret = caswell_decrypt( caswell_get_option( 'google_client_secret' ) );
        $refresh_token = caswell_decrypt( caswell_get_option( 'google_refresh_token' ) );

        if ( ! $client_id || ! $client_secret || ! $refresh_token ) return false;

        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body'    => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'gcal', 'Token refresh failed: ' . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 || empty( $body['access_token'] ) ) {
            caswell_log( 'gcal', "Token refresh HTTP {$status}", [
                'error' => $body['error'] ?? '',
                'desc'  => $body['error_description'] ?? '',
            ] );
            return false;
        }

        $token   = $body['access_token'];
        $expires = (int) ( $body['expires_in'] ?? 3600 );
        set_transient( 'caswell_google_token', $token, $expires - 60 );
        $this->access_token = $token;
        return $token;
    }

    /* ── Fetch events from a calendar ─────────────────────────────────── */

    /**
     * @param string $calendar_id  Google Calendar ID
     * @param string $time_min     RFC3339 datetime
     * @param string $time_max     RFC3339 datetime
     * @return array               Array of event objects
     */
    private function fetch_events( $calendar_id, $time_min, $time_max ) {
        $cache_key = 'caswell_gcal_' . md5( $calendar_id . $time_min . $time_max );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) return $cached;

        $token = $this->get_access_token();
        if ( ! $token ) return [];

        $url = add_query_arg( [
            'timeMin'      => urlencode( $time_min ),
            'timeMax'      => urlencode( $time_max ),
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
            'maxResults'   => 250,
        ], "https://www.googleapis.com/calendar/v3/calendars/" . rawurlencode( $calendar_id ) . "/events" );

        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [ 'Authorization' => "Bearer {$token}" ],
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'gcal', 'Fetch events failed: ' . $response->get_error_message(), [
                'calendar' => $calendar_id,
            ] );
            return [];
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            caswell_log( 'gcal', "Fetch events HTTP {$status}", [
                'calendar' => $calendar_id,
                'error'    => $body['error']['message'] ?? 'Unknown',
            ] );
            return [];
        }

        $events = $body['items'] ?? [];

        set_transient( $cache_key, $events, 15 * MINUTE_IN_SECONDS );
        return $events;
    }

    /* ── Public API ────────────────────────────────────────────────────── */

    /**
     * Get available windows from shared calendar filtered by keyword.
     *
     * @param string $date  Y-m-d
     * @return array  [ ['start' => DateTime, 'end' => DateTime], ... ]
     */
    public function get_available_windows( $date ) {
        // Validate the date is real (not e.g. 2025-02-31)
        $parts = explode( '-', $date );
        if ( count( $parts ) !== 3 || ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
            return [];
        }

        $shared_cal_id    = caswell_get_option( 'shared_calendar_id' );
        $personal_cal_id  = caswell_get_option( 'personal_calendar_id' );
        $keyword          = strtolower( trim( caswell_get_option( 'calendar_keyword', 'glow' ) ) );
        $blocking_keyword = strtolower( trim( caswell_get_option( 'blocking_keyword', 'terry' ) ) );

        if ( ! $shared_cal_id ) return [];

        $tz       = new DateTimeZone( wp_timezone_string() );
        $day_start= new DateTime( "{$date} 00:00:00", $tz );
        $day_end  = new DateTime( "{$date} 23:59:59", $tz );
        $time_min = $day_start->format( DateTime::RFC3339 );
        $time_max = $day_end->format( DateTime::RFC3339 );

        // 1. Walk shared calendar events. Each event is either:
        //    - A blocking event (title/desc matches blocking_keyword) → busy block
        //      (e.g. "Terry" events represent another therapist using the room)
        //    - A Glow event (title/desc matches keyword) → adds an available window
        //    - Neither → ignored
        // Blocking takes precedence: if an event matches both keywords (unusual),
        // it is treated as a block.
        //
        // Blocking events are padded by `buffer_time` minutes on each side so
        // a new appointment cannot be scheduled right up against an event
        // belonging to another practitioner sharing the room. This mirrors
        // the buffer between Ryan's own consecutive bookings.
        $shared_events  = $this->fetch_events( $shared_cal_id, $time_min, $time_max );
        $buffer_minutes = (int) caswell_get_option( 'buffer_time', 15 );
        $glow_windows   = [];
        $shared_blocks  = [];
        foreach ( $shared_events as $event ) {
            $title = strtolower( $event['summary']     ?? '' );
            $desc  = strtolower( $event['description'] ?? '' );
            $start = $this->parse_event_datetime( $event, 'start', $tz );
            $end   = $this->parse_event_datetime( $event, 'end',   $tz );
            if ( ! $start || ! $end || $end <= $start ) {
                continue;
            }
            if ( $blocking_keyword !== ''
                 && ( strpos( $title, $blocking_keyword ) !== false
                      || strpos( $desc, $blocking_keyword ) !== false ) ) {
                $padded_start = clone $start;
                $padded_end   = clone $end;
                if ( $buffer_minutes > 0 ) {
                    $padded_start->modify( "-{$buffer_minutes} minutes" );
                    $padded_end->modify( "+{$buffer_minutes} minutes" );
                }
                $shared_blocks[] = [ 'start' => $padded_start, 'end' => $padded_end ];
                continue;
            }
            if ( strpos( $title, $keyword ) !== false || strpos( $desc, $keyword ) !== false ) {
                $glow_windows[] = [ 'start' => $start, 'end' => $end ];
            }
        }

        // 2. Weekly schedule (admin override). When a day is explicitly enabled in
        // Settings → Availability → Weekly Schedule, that schedule replaces all other
        // window sources for that day — useful for restricting a day's hours.
        $weekly   = caswell_get_option( 'weekly_availability', [] );
        $day_num  = (int) $day_start->format( 'N' ); // 1=Mon … 7=Sun
        $schedule = $weekly[ $day_num ] ?? [];

        $schedule_window = [];
        if ( ! empty( $schedule['enabled'] ) && ! empty( $schedule['start'] ) && ! empty( $schedule['end'] ) ) {
            $sched_start = new DateTime( "{$date} {$schedule['start']}:00", $tz );
            $sched_end   = new DateTime( "{$date} {$schedule['end']}:00",   $tz );
            if ( $sched_end > $sched_start ) {
                $schedule_window = [ [ 'start' => $sched_start, 'end' => $sched_end ] ];
            }
        }

        // 3. Default open window. When the "Open by default" setting is on
        // (Settings → Caswell Booking → Availability), days are open during the
        // configured working hours; Glow events extend availability outside
        // those hours. When off, only Glow events define when bookings are open.
        $default_open      = (int) caswell_get_option( 'default_avail_open', 1 );
        $default_open_start = (string) caswell_get_option( 'default_open_start', '07:00' );
        $default_open_end   = (string) caswell_get_option( 'default_open_end',   '22:00' );

        if ( ! empty( $schedule_window ) ) {
            $windows = $schedule_window;
        } elseif ( $default_open ) {
            $default_window = [
                'start' => new DateTime( "{$date} {$default_open_start}:00", $tz ),
                'end'   => new DateTime( "{$date} {$default_open_end}:00",   $tz ),
            ];
            $windows = $this->merge_windows( array_merge( [ $default_window ], $glow_windows ) );
        } else {
            $windows = $glow_windows;
        }
        if ( empty( $windows ) ) return [];

        // 3. Blocks: shared-calendar blocking events (matched above) + personal calendar events
        $blocks = $shared_blocks;
        if ( $personal_cal_id ) {
            $personal_events = $this->fetch_events( $personal_cal_id, $time_min, $time_max );
            foreach ( $personal_events as $event ) {
                // Respect Google Calendar's "Show me as" setting — skip events
                // marked Free (transparency=transparent). Default for events
                // without the field is "opaque" (busy), which still blocks.
                if ( ( $event['transparency'] ?? 'opaque' ) === 'transparent' ) {
                    continue;
                }
                $start = $this->parse_event_datetime( $event, 'start', $tz );
                $end   = $this->parse_event_datetime( $event, 'end',   $tz );
                if ( $start && $end ) {
                    $blocks[] = [ 'start' => $start, 'end' => $end ];
                }
            }
        }

        // 4. Blocks from existing DB bookings
        $db_bookings = Caswell_Booking_DB::get_bookings_for_range(
            $day_end->format( 'Y-m-d H:i:s' ),
            $day_start->format( 'Y-m-d H:i:s' )
        );
        foreach ( $db_bookings as $booking ) {
            $bs = new DateTime( $booking->start_datetime, $tz );
            $be = new DateTime( $booking->end_datetime,   $tz );
            $blocks[] = [ 'start' => $bs, 'end' => $be ];
        }

        // 5. Admin time blocks from DB
        $admin_blocks = Caswell_Booking_DB::get_blocks_for_range(
            $day_start->format( 'Y-m-d H:i:s' ),
            $day_end->format( 'Y-m-d H:i:s' )
        );
        foreach ( $admin_blocks as $block ) {
            $bs = new DateTime( $block->start_datetime, $tz );
            $be = new DateTime( $block->end_datetime,   $tz );
            $blocks[] = [ 'start' => $bs, 'end' => $be ];
        }

        // 6. Subtract blocks from windows
        return $this->subtract_blocks( $windows, $blocks );
    }

    /**
     * Merge overlapping or adjacent windows into a non-overlapping, sorted set.
     */
    private function merge_windows( $windows ) {
        if ( count( $windows ) < 2 ) {
            return $windows;
        }
        usort( $windows, function ( $a, $b ) {
            return $a['start'] <=> $b['start'];
        } );
        $merged  = [];
        $current = $windows[0];
        for ( $i = 1, $n = count( $windows ); $i < $n; $i++ ) {
            if ( $windows[ $i ]['start'] <= $current['end'] ) {
                if ( $windows[ $i ]['end'] > $current['end'] ) {
                    $current['end'] = $windows[ $i ]['end'];
                }
            } else {
                $merged[] = $current;
                $current  = $windows[ $i ];
            }
        }
        $merged[] = $current;
        return $merged;
    }

    /**
     * Subtract blocks from windows, return remaining free windows.
     */
    private function subtract_blocks( $windows, $blocks ) {
        foreach ( $blocks as $block ) {
            $new_windows = [];
            foreach ( $windows as $win ) {
                // No overlap
                if ( $block['end'] <= $win['start'] || $block['start'] >= $win['end'] ) {
                    $new_windows[] = $win;
                    continue;
                }
                // Block overlaps — split window
                if ( $block['start'] > $win['start'] ) {
                    $new_windows[] = [ 'start' => $win['start'], 'end' => $block['start'] ];
                }
                if ( $block['end'] < $win['end'] ) {
                    $new_windows[] = [ 'start' => $block['end'], 'end' => $win['end'] ];
                }
            }
            $windows = $new_windows;
        }
        return $windows;
    }

    /**
     * Break free windows into discrete appointment slots.
     *
     * @param array  $windows        Free windows [ ['start'=>DT, 'end'=>DT], ... ]
     * @param int    $session_length Minutes
     * @param int    $buffer         Buffer minutes after each slot
     * @return array  [ ['start' => timestamp, 'end' => timestamp], ... ]
     */
    public function windows_to_slots( $windows, $session_length, $buffer ) {
        $slots          = [];
        $session_secs   = $session_length * 60;
        $buffer_secs    = $buffer * 60;
        $total_needed   = $session_secs + $buffer_secs;

        foreach ( $windows as $win ) {
            $cursor = clone $win['start'];
            while ( true ) {
                $slot_end = (clone $cursor)->modify( "+{$session_length} minutes" );
                // Must fit session + buffer within window
                $with_buffer = (clone $cursor)->modify( "+{$session_length} minutes +{$buffer} minutes" );
                if ( $with_buffer > $win['end'] && $slot_end > $win['end'] ) break;
                if ( $slot_end > $win['end'] ) break;

                $slots[] = [
                    'start' => $cursor->getTimestamp(),
                    'end'   => $slot_end->getTimestamp(),
                ];

                // Advance by session + buffer
                $cursor->modify( "+{$session_length} minutes +{$buffer} minutes" );
            }
        }

        return $slots;
    }

    private function parse_event_datetime( $event, $type, $tz ) {
        $dt_data = $event[ $type ] ?? [];
        if ( isset( $dt_data['dateTime'] ) ) {
            return new DateTime( $dt_data['dateTime'], $tz );
        }
        if ( isset( $dt_data['date'] ) ) {
            // All-day event
            return new DateTime( $dt_data['date'] . ( $type === 'end' ? ' 23:59:59' : ' 00:00:00' ), $tz );
        }
        return null;
    }

    /**
     * Create a calendar event.
     *
     * @param string   $calendar_id  'primary' or a full calendar ID
     * @param string   $title
     * @param DateTime $start
     * @param DateTime $end
     * @param string   $description
     * @return string|false  Event ID on success, false on failure
     */
    public function create_event( $calendar_id, $title, DateTime $start, DateTime $end, $description = '' ) {
        $token = $this->get_access_token();
        if ( ! $token ) return false;

        $url  = 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode( $calendar_id ) . '/events';
        $tz_string = wp_timezone_string();
        $body = [
            'summary'     => $title,
            'description' => $description,
            'start'       => [ 'dateTime' => $start->format( DateTime::RFC3339 ), 'timeZone' => $tz_string ],
            'end'         => [ 'dateTime' => $end->format( DateTime::RFC3339 ),   'timeZone' => $tz_string ],
        ];

        $response = wp_remote_post( $url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'gcal', 'Create event failed: ' . $response->get_error_message(), [
                'calendar' => $calendar_id,
                'title'    => $title,
            ] );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status < 200 || $status >= 300 ) {
            caswell_log( 'gcal', "Create event HTTP {$status}", [
                'calendar' => $calendar_id,
                'title'    => $title,
                'error'    => $data['error']['message'] ?? 'Unknown',
            ] );
            return false;
        }

        $event_id = $data['id'] ?? false;
        if ( $event_id ) {
            caswell_log( 'gcal', "Event created: {$event_id} on {$calendar_id}" );
        }
        return $event_id;
    }

    /**
     * Update an existing event's start/end (and optionally summary/description).
     * Uses PATCH so unrelated event fields (attendees, reminders) are preserved.
     *
     * @param string   $calendar_id  Calendar containing the event
     * @param string   $event_id     Google event ID to patch
     * @param DateTime $start        New start
     * @param DateTime $end          New end
     * @param string   $title        Optional — passes through if non-null
     * @param string   $description  Optional — passes through if non-null
     * @return bool
     */
    public function update_event( $calendar_id, $event_id, DateTime $start, DateTime $end, $title = null, $description = null ) {
        $token = $this->get_access_token();
        if ( ! $token || ! $event_id ) return false;

        $tz_string = wp_timezone_string();
        $body = [
            'start' => [ 'dateTime' => $start->format( DateTime::RFC3339 ), 'timeZone' => $tz_string ],
            'end'   => [ 'dateTime' => $end->format( DateTime::RFC3339 ),   'timeZone' => $tz_string ],
        ];
        if ( null !== $title )       $body['summary']     = $title;
        if ( null !== $description ) $body['description'] = $description;

        $url = 'https://www.googleapis.com/calendar/v3/calendars/'
             . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id );

        $response = wp_remote_request( $url, [
            'method'  => 'PATCH',
            'timeout' => 15,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'gcal', 'Update event failed: ' . $response->get_error_message(), [
                'calendar' => $calendar_id,
                'event_id' => $event_id,
            ] );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status >= 200 && $status < 300 ) {
            caswell_log( 'gcal', "Event updated: {$event_id} on {$calendar_id}" );
            return true;
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        caswell_log( 'gcal', "Update event HTTP {$status}", [
            'event_id' => $event_id,
            'error'    => $data['error']['message'] ?? 'Unknown',
        ] );
        return false;
    }

    /**
     * Diagnostic — create then delete a test event on the configured shared
     * calendar. Returns a structured result the admin UI can display.
     *
     * @return array  [ 'ok' => bool, 'message' => string, 'event_id' => string|null ]
     */
    public function test_shared_calendar_write() {
        $shared_cal_id = caswell_get_option( 'shared_calendar_id' );
        if ( ! $shared_cal_id ) {
            return [ 'ok' => false, 'message' => 'No shared calendar ID configured.', 'event_id' => null ];
        }
        $token = $this->get_access_token();
        if ( ! $token ) {
            return [ 'ok' => false, 'message' => 'Could not obtain Google access token. Re-connect OAuth.', 'event_id' => null ];
        }

        $tz    = new DateTimeZone( wp_timezone_string() );
        $start = ( new DateTime( 'now', $tz ) )->modify( '+10 years' );
        $end   = ( clone $start )->modify( '+15 minutes' );

        $event_id = $this->create_event(
            $shared_cal_id,
            'Caswell Booking — calendar write test (delete me if you see this)',
            $start,
            $end,
            "This is a one-off diagnostic event created by the plugin to verify\nthat the OAuth user has 'Make changes to events' permission on this\nshared calendar. It will be deleted automatically a moment after creation."
        );

        if ( ! $event_id ) {
            return [
                'ok'       => false,
                'message'  => 'Could not write to the shared calendar. Most likely the OAuth user (the Google account you connected) does not have "Make changes to events" permission on this calendar. Open Google Calendar → calendar settings → "Share with specific people" and grant write access. Check the debug log for the exact API error.',
                'event_id' => null,
            ];
        }

        $deleted = $this->delete_event( $shared_cal_id, $event_id );
        return [
            'ok'       => true,
            'message'  => $deleted
                ? 'Shared calendar write succeeded — test event was created and removed.'
                : 'Write succeeded, but the test event could not be deleted. Find it on the shared calendar in 10 years and remove it manually.',
            'event_id' => $event_id,
        ];
    }

    /**
     * Delete a calendar event.
     *
     * @param string $calendar_id  Calendar ID
     * @param string $event_id     Google Calendar event ID
     * @return bool
     */
    public function delete_event( $calendar_id, $event_id ) {
        $token = $this->get_access_token();
        if ( ! $token ) return false;

        $url = 'https://www.googleapis.com/calendar/v3/calendars/'
             . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id );

        $response = wp_remote_request( $url, [
            'method'  => 'DELETE',
            'timeout' => 15,
            'headers' => [ 'Authorization' => "Bearer {$token}" ],
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'gcal', 'Delete event failed: ' . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );
        // 200/204 = deleted; 404/410 = already gone (treat as success)
        if ( ( $status >= 200 && $status < 300 ) || 404 === $status || 410 === $status ) {
            caswell_log( 'gcal', "Event deleted: {$event_id} from {$calendar_id}" );
            return true;
        }

        caswell_log( 'gcal', "Delete event HTTP {$status}", [ 'event_id' => $event_id ] );
        return false;
    }

    /**
     * Check whether an event still exists on a calendar.
     *
     * @return bool|null  true=exists, false=deleted/cancelled, null=API error or token issue
     */
    public function event_exists( $calendar_id, $event_id ) {
        $token = $this->get_access_token();
        if ( ! $token ) return null;

        $url = 'https://www.googleapis.com/calendar/v3/calendars/'
             . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id );

        $response = wp_remote_get( $url, [
            'timeout' => 15,
            'headers' => [ 'Authorization' => "Bearer {$token}" ],
        ] );

        if ( is_wp_error( $response ) ) return null;

        $code = wp_remote_retrieve_response_code( $response );
        if ( 404 === $code || 410 === $code ) return false;
        if ( $code >= 200 && $code < 300 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            return ( $body['status'] ?? '' ) !== 'cancelled';
        }
        return null;
    }

    /**
     * Check if a specific slot is still available.
     */
    public function is_slot_available( $start_ts, $session_length ) {
        $tz    = new DateTimeZone( wp_timezone_string() );
        $start = new DateTime( '@' . $start_ts );
        $start->setTimezone( $tz );
        $date  = $start->format( 'Y-m-d' );

        $windows = $this->get_available_windows( $date );
        $buffer  = (int) caswell_get_option( 'buffer_time', 15 );
        $slots   = $this->windows_to_slots( $windows, $session_length, $buffer );

        foreach ( $slots as $slot ) {
            if ( $slot['start'] === $start_ts ) return true;
        }
        return false;
    }
}
