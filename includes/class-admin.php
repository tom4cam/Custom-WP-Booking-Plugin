<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Admin {

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_menu' ] );
        add_action( 'admin_init',    [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'security_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_caswell_toggle_logging',         [ $this, 'ajax_toggle_logging' ] );
        add_action( 'wp_ajax_caswell_admin_reschedule',       [ $this, 'ajax_admin_reschedule' ] );
        add_action( 'wp_ajax_caswell_admin_cancel',           [ $this, 'ajax_admin_cancel' ] );
        add_action( 'wp_ajax_caswell_admin_update_notes',     [ $this, 'ajax_admin_update_notes' ] );
        add_action( 'wp_ajax_caswell_admin_test_shared_cal',  [ $this, 'ajax_admin_test_shared_cal' ] );
        add_action( 'wp_ajax_caswell_admin_delete_booking',   [ $this, 'ajax_admin_delete_booking' ] );
        add_action( 'wp_ajax_caswell_admin_test_email',       [ $this, 'ajax_admin_test_email' ] );
        add_action( 'wp_ajax_caswell_admin_new_booking',      [ $this, 'ajax_admin_new_booking' ] );

        // Self-service cancel via token (no login required) — handled on `init`
        // before WordPress routes to the booking page so we can render our own
        // confirmation HTML for the cancel flow.
        add_action( 'init', [ $this, 'maybe_handle_self_serve_cancel' ] );
    }

    public function add_menu() {
        add_options_page(
            'Caswell Booking Settings',
            'Caswell Booking',
            'manage_options',
            'caswell-booking',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'caswell_settings_group', 'caswell_settings', [
            'sanitize_callback' => [ $this, 'sanitize_settings' ],
        ] );
    }

    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) return [];
        $clean    = [];
        $existing = get_option( 'caswell_settings', [] );

        // Google Calendar — OAuth2
        $clean['google_client_id']     = sanitize_text_field( $input['google_client_id'] ?? '' );
        $new_secret = sanitize_text_field( $input['google_client_secret'] ?? '' );
        if ( $new_secret ) {
            $clean['google_client_secret'] = caswell_encrypt( $new_secret );
        } else {
            $clean['google_client_secret'] = $existing['google_client_secret'] ?? '';
        }
        $new_rt = sanitize_text_field( $input['google_refresh_token'] ?? '' );
        if ( $new_rt ) {
            $clean['google_refresh_token'] = caswell_encrypt( $new_rt );
        } else {
            $clean['google_refresh_token'] = $existing['google_refresh_token'] ?? '';
        }
        $clean['shared_calendar_id']   = sanitize_text_field( $input['shared_calendar_id'] ?? '' );
        $clean['personal_calendar_id'] = sanitize_text_field( $input['personal_calendar_id'] ?? '' );
        $clean['calendar_keyword']     = sanitize_text_field( $input['calendar_keyword'] ?? 'Glow' );
        $clean['blocking_keyword']     = sanitize_text_field( $input['blocking_keyword'] ?? 'Terry' );

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

        // Square — encrypt secrets
        $clean['square_application_id'] = sanitize_text_field( $input['square_application_id'] ?? '' );
        $clean['square_location_id']    = sanitize_text_field( $input['square_location_id'] ?? '' );
        $clean['square_sandbox_mode']   = ! empty( $input['square_sandbox_mode'] ) ? 1 : 0;

        $new_at   = sanitize_text_field( $input['square_access_token'] ?? '' );
        if ( $new_at ) {
            $clean['square_access_token'] = caswell_encrypt( $new_at );
        } else {
            $clean['square_access_token'] = $existing['square_access_token'] ?? '';
        }

        // Venmo
        $clean['venmo_username']   = sanitize_text_field( $input['venmo_username'] ?? '' );
        foreach ( caswell_session_length_options() as $len ) {
            $clean[ "venmo_price_{$len}" ] = sanitize_text_field( $input[ "venmo_price_{$len}" ] ?? '' );
        }

        // Notifications – Email
        $clean['email_from_name']              = sanitize_text_field( $input['email_from_name'] ?? '' );
        $clean['email_from_address']           = sanitize_email( $input['email_from_address'] ?? '' );
        $clean['email_confirmation_subject']   = sanitize_text_field( $input['email_confirmation_subject'] ?? '' );
        $clean['email_confirmation_body']      = sanitize_textarea_field( $input['email_confirmation_body'] ?? '' );
        $clean['email_reminder_subject']       = sanitize_text_field( $input['email_reminder_subject'] ?? '' );
        $clean['email_reminder_body']          = sanitize_textarea_field( $input['email_reminder_body'] ?? '' );

        // Notifications – SMS / Twilio
        $clean['twilio_account_sid']          = sanitize_text_field( $input['twilio_account_sid'] ?? '' );
        $clean['twilio_from_phone']           = sanitize_text_field( $input['twilio_from_phone'] ?? '' );
        $clean['twilio_whatsapp_from']        = sanitize_text_field( $input['twilio_whatsapp_from'] ?? '' );
        $channel = $input['notification_channel'] ?? 'sms';
        $clean['notification_channel'] = in_array( $channel, [ 'sms', 'whatsapp', 'off' ], true ) ? $channel : 'sms';
        $clean['sms_confirmation_template']   = sanitize_textarea_field( $input['sms_confirmation_template'] ?? '' );
        $clean['sms_reminder_template']       = sanitize_textarea_field( $input['sms_reminder_template'] ?? '' );

        $new_token = sanitize_text_field( $input['twilio_auth_token'] ?? '' );
        if ( $new_token ) {
            $clean['twilio_auth_token'] = caswell_encrypt( $new_token );
        } else {
            $clean['twilio_auth_token'] = $existing['twilio_auth_token'] ?? '';
        }

        // Availability
        $clean['min_hours_advance'] = absint( $input['min_hours_advance'] ?? 24 );
        $weekly_raw = $input['weekly_availability'] ?? [];
        $weekly     = [];
        for ( $d = 1; $d <= 7; $d++ ) {
            $day_data  = $weekly_raw[ $d ] ?? [];
            $start_time = $this->sanitize_time( $day_data['start'] ?? '' );
            $end_time   = $this->sanitize_time( $day_data['end'] ?? '' );
            $enabled    = ! empty( $day_data['enabled'] ) ? 1 : 0;
            // Disable the day if end time is not after start time
            if ( $enabled && $start_time && $end_time && $end_time <= $start_time ) {
                $enabled = 0;
                add_settings_error( 'caswell_settings', 'bad_time_range',
                    "Weekly availability: end time must be after start time (day {$d}). Day has been disabled.",
                    'error'
                );
            }
            $weekly[ $d ] = [
                'enabled' => $enabled,
                'start'   => $start_time,
                'end'     => $end_time,
            ];
        }
        $clean['weekly_availability'] = $weekly;

        // Default availability (open by default)
        $clean['default_avail_open']  = ! empty( $input['default_avail_open'] ) ? 1 : 0;
        $clean['default_open_start']  = $this->sanitize_time( $input['default_open_start'] ?? '' ) ?: '07:00';
        $clean['default_open_end']    = $this->sanitize_time( $input['default_open_end'] ?? '' )   ?: '22:00';
        if ( strcmp( $clean['default_open_end'], $clean['default_open_start'] ) <= 0 ) {
            add_settings_error( 'caswell_settings', 'bad_default_hours',
                'Default open hours: end time must be after start time. Reverted to 07:00–22:00.',
                'error'
            );
            $clean['default_open_start'] = '07:00';
            $clean['default_open_end']   = '22:00';
        }

        // Owner notifications (SMS + email)
        $clean['owner_notify_sms']           = ! empty( $input['owner_notify_sms'] ) ? 1 : 0;
        $clean['owner_notify_phone']         = sanitize_text_field( $input['owner_notify_phone'] ?? '' );
        $clean['owner_notify_email']         = ! empty( $input['owner_notify_email'] ) ? 1 : 0;
        $clean['owner_notify_email_address'] = sanitize_email( $input['owner_notify_email_address'] ?? '' );

        // Scheduling
        $clean['buffer_time'] = absint( $input['buffer_time'] ?? 15 );
        $clean['reminder_hours_before'] = absint( $input['reminder_hours_before'] ?? 24 );
        $clean['enable_email_reminder'] = ! empty( $input['enable_email_reminder'] ) ? 1 : 0;
        $clean['enable_sms_reminder']   = ! empty( $input['enable_sms_reminder'] ) ? 1 : 0;

        // Services / Pricing (for homepage)
        foreach ( caswell_session_length_options() as $len ) {
            $clean[ "service_price_{$len}" ]       = sanitize_text_field( $input[ "service_price_{$len}" ] ?? '' );
            $clean[ "service_description_{$len}" ] = sanitize_textarea_field( $input[ "service_description_{$len}" ] ?? '' );
        }

        // White-label / Branding
        $clean['business_name']      = sanitize_text_field( $input['business_name'] ?? '' );
        $clean['practitioner_name']  = sanitize_text_field( $input['practitioner_name'] ?? '' );
        $clean['service_type']       = sanitize_text_field( $input['service_type'] ?? '' );
        $clean['hero_subtitle']      = sanitize_text_field( $input['hero_subtitle'] ?? '' );
        $clean['gcal_event_title']        = sanitize_text_field( $input['gcal_event_title'] ?? '' );
        $clean['gcal_shared_event_title'] = sanitize_text_field( $input['gcal_shared_event_title'] ?? '' );
        $clean['enable_primary_calendar_event'] = ! empty( $input['enable_primary_calendar_event'] ) ? 1 : 0;
        $clean['booking_page_title'] = sanitize_text_field( $input['booking_page_title'] ?? '' );

        // Business info
        $clean['business_phone']   = sanitize_text_field( $input['business_phone'] ?? '' );
        $clean['business_email']   = sanitize_email( $input['business_email'] ?? '' );
        $clean['business_address'] = sanitize_textarea_field( $input['business_address'] ?? '' );
        $clean['business_hours']   = sanitize_textarea_field( $input['business_hours'] ?? '' );
        $clean['practitioner_bio']         = sanitize_textarea_field( $input['practitioner_bio'] ?? '' );
        $clean['practitioner_credentials'] = sanitize_text_field( $input['practitioner_credentials'] ?? '' );
        $clean['hero_tagline']     = sanitize_text_field( $input['hero_tagline'] ?? '' );

        // Migrate legacy field names if present in existing settings
        if ( empty( $clean['practitioner_bio'] ) && ! empty( $existing['ryan_bio'] ) ) {
            $clean['practitioner_bio'] = $existing['ryan_bio'];
        }
        if ( empty( $clean['practitioner_credentials'] ) && ! empty( $existing['ryan_credentials'] ) ) {
            $clean['practitioner_credentials'] = $existing['ryan_credentials'];
        }

        // Clear Google calendar transients on save
        delete_transient( 'caswell_google_token' );

        return $clean;
    }

    private function sanitize_time( $t ) {
        return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t ) ? $t : '';
    }

    public function security_notices() {
        $screen = get_current_screen();
        if ( ! $screen || 'settings_page_caswell-booking' !== $screen->id ) return;

        if ( ! is_ssl() ) {
            echo '<div class="notice notice-error"><p><strong>Caswell Booking:</strong> Your site is not using HTTPS. SSL is required for PCI-compliant card processing via Square. Please enable SSL immediately.</p></div>';
        }

        if ( ! wp_next_scheduled( Caswell_Cron::HOOK_REMINDERS ) ) {
            echo '<div class="notice notice-warning"><p><strong>Caswell Booking:</strong> WP-Cron reminders are not scheduled. ';
            echo 'If your site has low traffic, add a server-side cron job: <code>*/15 * * * * curl -s ' . esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ) . '</code> via Bluehost cPanel &gt; Cron Jobs.</p></div>';
        }
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_caswell-booking' !== $hook ) return;
        wp_enqueue_style( 'caswell-admin', CASWELL_PLUGIN_URL . 'admin/admin.css', [], CASWELL_VERSION );
        wp_add_inline_script( 'jquery', 'var caswellAdminData = ' . wp_json_encode( [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'caswell_admin_nonce' ),
        ] ) . ';' );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        require CASWELL_PLUGIN_DIR . 'admin/admin-page.php';
    }

    public function ajax_toggle_logging() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Not authorized.' );
        }
        $enabled = ! empty( $_POST['enabled'] );
        update_option( 'caswell_enable_logging', $enabled ? 1 : 0 );
        wp_send_json_success();
    }

    /* ── Admin booking actions ─────────────────────────────────────────── */

    private function require_admin() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Not authorized.' ] );
        }
    }

    private function delete_booking_calendar_events( $booking ) {
        if ( ! $booking ) return;
        $gcal = new Caswell_Google_Calendar();
        if ( ! empty( $booking->gcal_primary_event_id ) ) {
            $gcal->delete_event( 'primary', $booking->gcal_primary_event_id );
        }
        $shared_cal_id = caswell_get_option( 'shared_calendar_id' );
        if ( $shared_cal_id && ! empty( $booking->gcal_shared_event_id ) ) {
            $gcal->delete_event( $shared_cal_id, $booking->gcal_shared_event_id );
        }
    }

    /**
     * Reschedule a booking. Accepts:
     *   - new_date  (Y-m-d)
     *   - new_time  (H:i)         OR a shift_hours integer (DST adjust)
     *   - length    (int minutes, optional)
     *   - notes     (string, optional)
     *   - message   (string, optional — included in client email)
     *   - send_email (1/0, default 1)
     *
     * Updates DB, patches both Google Calendar events (primary + shared) in
     * place via update_event(), and emails the client.
     */
    public function ajax_admin_reschedule() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        $this->require_admin();

        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        $booking    = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking ) wp_send_json_error( [ 'message' => 'Booking not found.' ] );
        if ( $booking->status === 'cancelled' ) {
            wp_send_json_error( [ 'message' => 'Booking is already cancelled — un-cancel before rescheduling.' ] );
        }

        $tz             = new DateTimeZone( wp_timezone_string() );
        $previous_start = $booking->start_datetime;
        $session_length = isset( $_POST['length'] ) ? max( 15, absint( $_POST['length'] ) ) : (int) $booking->session_length;

        $shift_hours = isset( $_POST['shift_hours'] ) ? (int) $_POST['shift_hours'] : 0;
        if ( $shift_hours !== 0 ) {
            $start = new DateTime( $booking->start_datetime, $tz );
            $sign  = $shift_hours >= 0 ? '+' : '-';
            $start->modify( $sign . abs( $shift_hours ) . ' hours' );
            $new_date = $start->format( 'Y-m-d' );
            $new_time = $start->format( 'H:i' );
        } else {
            $new_date = sanitize_text_field( $_POST['new_date'] ?? '' );
            $new_time = sanitize_text_field( $_POST['new_time'] ?? '' );
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_date ) ) {
                wp_send_json_error( [ 'message' => 'Invalid date.' ] );
            }
            if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $new_time ) ) {
                wp_send_json_error( [ 'message' => 'Invalid time.' ] );
            }
        }

        try {
            $start = new DateTime( "{$new_date} {$new_time}:00", $tz );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => 'Could not parse the new date/time.' ] );
        }
        $end = ( clone $start )->modify( "+{$session_length} minutes" );

        $start_str = $start->format( 'Y-m-d H:i:s' );
        $end_str   = $end->format( 'Y-m-d H:i:s' );

        Caswell_Booking_DB::update_booking_times( $booking_id, $start_str, $end_str, $session_length );

        if ( isset( $_POST['notes'] ) ) {
            Caswell_Booking_DB::update_booking_notes( $booking_id, sanitize_textarea_field( $_POST['notes'] ) );
        }

        // Update Google Calendar — patch in place when we have IDs, else recreate
        $gcal     = new Caswell_Google_Calendar();
        $shared   = caswell_get_option( 'shared_calendar_id' );
        $patched_primary = false;
        $patched_shared  = false;

        if ( ! empty( $booking->gcal_primary_event_id ) ) {
            $patched_primary = $gcal->update_event( 'primary', $booking->gcal_primary_event_id, $start, $end );
        }
        if ( $shared && ! empty( $booking->gcal_shared_event_id ) ) {
            $patched_shared = $gcal->update_event( $shared, $booking->gcal_shared_event_id, $start, $end );
        }

        $reloaded = Caswell_Booking_DB::get_booking( $booking_id );
        $send_email = ! isset( $_POST['send_email'] ) || ! empty( $_POST['send_email'] );
        if ( $send_email ) {
            $admin_message = sanitize_textarea_field( $_POST['message'] ?? '' );
            $notifier = new Caswell_Notifications();
            $notifier->send_reschedule( $reloaded, $previous_start, $admin_message );
        }

        caswell_log( 'booking', "Booking #{$booking_id} rescheduled by admin", [
            'from'            => $previous_start,
            'to'              => $start_str,
            'patched_primary' => $patched_primary,
            'patched_shared'  => $patched_shared,
        ] );

        wp_send_json_success( [
            'message'         => 'Booking rescheduled.',
            'patched_primary' => $patched_primary,
            'patched_shared'  => $patched_shared,
            'new_start'       => $start_str,
            'new_end'         => $end_str,
        ] );
    }

    public function ajax_admin_cancel() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        $this->require_admin();

        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        $booking    = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking ) wp_send_json_error( [ 'message' => 'Booking not found.' ] );
        if ( $booking->status === 'cancelled' ) {
            wp_send_json_success( [ 'message' => 'Already cancelled.' ] );
        }

        Caswell_Booking_DB::update_booking_status( $booking_id, 'cancelled' );
        $this->delete_booking_calendar_events( $booking );

        $send_email = ! isset( $_POST['send_email'] ) || ! empty( $_POST['send_email'] );
        if ( $send_email ) {
            $notifier = new Caswell_Notifications();
            $notifier->send_cancellation( $booking );
        }

        caswell_log( 'booking', "Booking #{$booking_id} cancelled by admin" );
        wp_send_json_success( [ 'message' => 'Booking cancelled.' ] );
    }

    public function ajax_admin_update_notes() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        $this->require_admin();

        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        $booking    = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking ) wp_send_json_error( [ 'message' => 'Booking not found.' ] );

        $notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
        Caswell_Booking_DB::update_booking_notes( $booking_id, $notes );

        wp_send_json_success( [ 'message' => 'Notes saved.' ] );
    }

    public function ajax_admin_test_shared_cal() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        $this->require_admin();
        $gcal   = new Caswell_Google_Calendar();
        $result = $gcal->test_shared_calendar_write();
        if ( $result['ok'] ) {
            wp_send_json_success( $result );
        }
        wp_send_json_error( $result );
    }

    /**
     * Permanently delete a booking. Also removes the matching Google
     * Calendar events on a best-effort basis. Distinct from the existing
     * Cancel action — Cancel keeps the row in place (as a "cancelled" row,
     * for record-keeping) and emails the client; Delete just removes it
     * with no email.
     */
    public function ajax_admin_delete_booking() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        $this->require_admin();

        $booking_id = absint( $_POST['booking_id'] ?? 0 );
        $booking    = Caswell_Booking_DB::get_booking( $booking_id );
        if ( ! $booking ) wp_send_json_error( [ 'message' => 'Booking not found.' ] );

        $this->delete_booking_calendar_events( $booking );
        Caswell_Booking_DB::delete_booking( $booking_id );

        caswell_log( 'booking', "Booking #{$booking_id} permanently deleted by admin", [
            'name'  => $booking->name,
            'email' => $booking->email,
        ] );
        wp_send_json_success( [ 'message' => 'Booking deleted.' ] );
    }

    /**
     * Self-service cancel link from confirmation emails/SMS.
     *
     *  /book/?caswell_action=cancel&b=ID&t=TOKEN
     *
     * GET shows a confirmation page with the 24h-no-refund policy and the
     * booking details. The user clicks "Confirm cancellation" which POSTs
     * to the same URL with `confirm=1` — that runs the cancellation,
     * deletes both Google Calendar events, and emails the client.
     *
     * Token is bound to booking id + email (HMAC of AUTH_KEY) so a leaked
     * link doesn't grant cancel rights to other bookings, but stays valid
     * even after the booking is rescheduled.
     */
    public function maybe_handle_self_serve_cancel() {
        if ( empty( $_GET['caswell_action'] ) || $_GET['caswell_action'] !== 'cancel' ) return;
        if ( empty( $_GET['b'] ) || empty( $_GET['t'] ) ) return;

        $booking = Caswell_Booking_DB::get_booking( absint( $_GET['b'] ) );
        $token   = sanitize_text_field( $_GET['t'] );
        if ( ! $booking || ! caswell_verify_booking_manage_token( $booking, $token ) ) {
            wp_die( 'This link is no longer valid.', 'Caswell Booking', [ 'response' => 410 ] );
        }

        if ( $booking->status === 'cancelled' ) {
            $this->render_cancel_page( $booking, 'already_cancelled' );
            exit;
        }

        $start_ts   = caswell_local_ts($booking->start_datetime);
        $hours_out  = ( $start_ts - time() ) / 3600;
        $within_24h = $hours_out >= 0 && $hours_out < 24;

        // POST means they clicked "Confirm cancellation"
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['confirm'] ) ) {
            // Re-check token from the form
            if ( ! caswell_verify_booking_manage_token( $booking, sanitize_text_field( $_POST['t'] ?? '' ) ) ) {
                wp_die( 'Invalid token.', 'Caswell Booking', [ 'response' => 403 ] );
            }
            Caswell_Booking_DB::update_booking_status( $booking->id, 'cancelled' );
            $this->delete_booking_calendar_events( $booking );
            $notifier = new Caswell_Notifications();
            $notifier->send_cancellation( $booking );
            caswell_log( 'booking', "Booking #{$booking->id} self-cancelled by client", [
                'within_24h' => $within_24h,
            ] );
            $this->render_cancel_page( $booking, 'cancelled' );
            exit;
        }

        // GET — show the confirmation page
        $this->render_cancel_page( $booking, $within_24h ? 'within_24h' : 'confirm' );
        exit;
    }

    private function render_cancel_page( $booking, $mode ) {
        $start_ts = caswell_local_ts($booking->start_datetime);
        $title    = caswell_business_name() . ' — ' . ( $mode === 'cancelled' ? 'Cancelled' : 'Cancel appointment' );
        $primary  = '#4a7c6f';
        ?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background: #f5f9f7; margin: 0; padding: 32px 16px; color: #333; line-height: 1.55; }
    .card { max-width: 540px; margin: 4vh auto; background: #fff; border-radius: 12px; padding: 32px 28px; box-shadow: 0 4px 18px rgba(0,0,0,.06); }
    h1 { margin: 0 0 12px; font-size: 1.6rem; color: <?php echo $primary; ?>; }
    .summary { background: #f0f7f5; border-left: 4px solid <?php echo $primary; ?>; border-radius: 6px; padding: 14px 16px; margin: 18px 0; font-size: 0.95rem; }
    .summary strong { color: <?php echo $primary; ?>; }
    .warn { background: #fff5d6; border-left: 4px solid #d99e00; color: #6a4400; padding: 14px 16px; border-radius: 6px; margin: 18px 0; font-size: 0.95rem; }
    .ok { background: #e6f4ec; border-left: 4px solid #2d8a4e; color: #1d6c34; padding: 14px 16px; border-radius: 6px; margin: 18px 0; }
    .btn { display: inline-block; padding: 12px 22px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.98rem; border: 0; cursor: pointer; font-family: inherit; }
    .btn-danger { background: #c0392b; color: #fff; }
    .btn-danger:hover { background: #a83323; }
    .btn-ghost { background: #fff; color: #555; border: 1px solid #ccc; margin-right: 8px; }
    .btn-primary { background: <?php echo $primary; ?>; color: #fff; }
    a { color: <?php echo $primary; ?>; }
</style>
</head><body>
<div class="card">
    <?php if ( $mode === 'cancelled' ) : ?>
        <h1>Appointment cancelled</h1>
        <p>Your appointment has been cancelled. A confirmation email is on its way.</p>
        <div class="summary">
            <div><strong>Was scheduled for:</strong> <?php echo esc_html( wp_date( 'l, F j, Y \a\t g:i A', $start_ts ) ); ?></div>
            <div><strong>Client:</strong> <?php echo esc_html( $booking->name ); ?></div>
        </div>
        <p>If you'd like to rebook for another time, <a href="<?php echo esc_url( get_permalink( (int) get_option( 'caswell_booking_page_id' ) ) ?: home_url( '/book/' ) ); ?>">visit the booking page</a>.</p>

    <?php elseif ( $mode === 'already_cancelled' ) : ?>
        <h1>Already cancelled</h1>
        <p>This appointment was already cancelled. No further action is needed.</p>

    <?php else : ?>
        <h1>Cancel this appointment?</h1>
        <p>You're about to cancel:</p>
        <div class="summary">
            <div><strong>When:</strong> <?php echo esc_html( wp_date( 'l, F j, Y \a\t g:i A', $start_ts ) ); ?></div>
            <div><strong>Length:</strong> <?php echo (int) $booking->session_length; ?> minutes</div>
            <div><strong>Client:</strong> <?php echo esc_html( $booking->name ); ?></div>
        </div>

        <?php if ( $mode === 'within_24h' ) : ?>
            <div class="warn">
                <strong>Heads up — your appointment is less than 24 hours away.</strong><br>
                Cancellations made less than 24 hours before the appointment are <strong>non-refundable</strong>.
                You can still cancel below if you can't make it; please reach out directly if you'd like to discuss.
            </div>
        <?php else : ?>
            <p style="font-size:0.9rem;color:#666">Cancellations made <strong>more than 24 hours</strong> in advance are eligible for a refund. Cancellations within 24 hours are non-refundable.</p>
        <?php endif; ?>

        <form method="post" style="margin-top:24px">
            <input type="hidden" name="confirm" value="1">
            <input type="hidden" name="t" value="<?php echo esc_attr( caswell_booking_manage_token( $booking ) ); ?>">
            <a class="btn btn-ghost" href="<?php echo esc_url( get_permalink( (int) get_option( 'caswell_booking_page_id' ) ) ?: home_url( '/' ) ); ?>">Keep appointment</a>
            <button type="submit" class="btn btn-danger">Confirm cancellation</button>
            <p style="margin-top:18px"><a href="<?php echo esc_url( caswell_booking_reschedule_url( $booking ) ); ?>">Or reschedule instead</a></p>
        </form>
    <?php endif; ?>
</div>
</body></html>
        <?php
    }

    /**
     * Create a booking on behalf of a client. Skips payment, creates GCal
     * events on both calendars, sends the same confirmation email + SMS the
     * regular booking flow uses.
     */
    public function ajax_admin_new_booking() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        $this->require_admin();

        $name     = sanitize_text_field( $_POST['name']  ?? '' );
        $email    = sanitize_email(      $_POST['email'] ?? '' );
        $phone    = sanitize_text_field( $_POST['phone'] ?? '' );
        $date     = sanitize_text_field( $_POST['date']  ?? '' );
        $time     = sanitize_text_field( $_POST['time']  ?? '' );
        $length   = max( 15, absint( $_POST['length'] ?? 60 ) );
        $notes    = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $send     = ! isset( $_POST['send_notifications'] ) || ! empty( $_POST['send_notifications'] );

        if ( ! $name )                       wp_send_json_error( [ 'message' => 'Name is required.' ] );
        if ( ! $email || ! is_email( $email ) ) wp_send_json_error( [ 'message' => 'Valid email is required.' ] );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) wp_send_json_error( [ 'message' => 'Invalid date.' ] );
        if ( ! preg_match( '/^\d{1,2}:\d{2}$/',     $time ) ) wp_send_json_error( [ 'message' => 'Invalid time.' ] );

        $tz       = new DateTimeZone( wp_timezone_string() );
        try {
            $start = new DateTime( "{$date} {$time}:00", $tz );
        } catch ( Exception $e ) {
            wp_send_json_error( [ 'message' => 'Could not parse the date/time.' ] );
        }
        $end = ( clone $start )->modify( "+{$length} minutes" );

        // Insert booking. Marked confirmed + unpaid — admin can mark paid later.
        $booking_id = Caswell_Booking_DB::insert_booking( [
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone,
            'session_length' => $length,
            'start_datetime' => $start->format( 'Y-m-d H:i:s' ),
            'end_datetime'   => $end->format( 'Y-m-d H:i:s' ),
            'status'         => 'confirmed',
            'payment_method' => 'venmo',  // a non-square placeholder; admin can edit
            'payment_status' => 'unpaid',
            'notes'          => $notes,
        ] );
        if ( ! $booking_id ) wp_send_json_error( [ 'message' => 'Could not save the booking.' ] );

        $booking = Caswell_Booking_DB::get_booking( $booking_id );

        // Google Calendar events (best effort). Same opt-in rule as the
        // public booking flow — primary calendar is off by default.
        $gcal          = new Caswell_Google_Calendar();
        $service       = caswell_get_option( 'service_type', 'appointment' );
        $desc          = "Manually scheduled by admin.\nClient: {$name}\nEmail: {$email}\nPhone: {$phone}";
        if ( $notes ) $desc .= "\n\nNotes: {$notes}";

        $primary_event_id = '';
        if ( caswell_get_option( 'enable_primary_calendar_event', 0 ) ) {
            $primary_title_tpl = caswell_get_option( 'gcal_event_title', '{practitioner} Appointment — {client}' );
            $primary_title     = caswell_render_event_title( $primary_title_tpl, $name, $length, $service );
            $primary_event_id  = $gcal->create_event( 'primary', $primary_title, $start, $end, $desc );
        }

        $shared_cal_id   = caswell_get_option( 'shared_calendar_id' );
        $shared_event_id = '';
        if ( $shared_cal_id ) {
            $shared_title_tpl = caswell_get_option( 'gcal_shared_event_title', '{practitioner}: {client_short} ({duration} min)' );
            $shared_title     = caswell_render_event_title( $shared_title_tpl, $name, $length, $service );
            $shared_event_id  = $gcal->create_event( $shared_cal_id, $shared_title, $start, $end, $desc, CASWELL_SHARED_EVENT_COLOR_ID );
        }
        Caswell_Booking_DB::update_booking_event_ids( $booking_id, (string) $primary_event_id, (string) $shared_event_id );

        // Notifications.
        $email_sent = false;
        if ( $send ) {
            $notifier   = new Caswell_Notifications();
            $email_sent = $notifier->send_confirmation( $booking );
            Caswell_Cron::schedule_reminder( $booking_id );
        }

        caswell_log( 'booking', "Booking #{$booking_id} created manually by admin", [
            'name'           => $name,
            'email'          => $email,
            'start'          => $start->format( 'Y-m-d H:i:s' ),
            'gcal_primary'   => (bool) $primary_event_id,
            'gcal_shared'    => (bool) $shared_event_id,
            'notifications'  => $send,
            'email_sent'     => $email_sent,
        ] );

        wp_send_json_success( [
            'message'       => $send
                ? ( $email_sent
                    ? 'Booking created. Confirmation email + SMS sent.'
                    : 'Booking created. Email could not be delivered — check Tools → Email Delivery Test.' )
                : 'Booking created. No notifications sent.',
            'booking_id'    => $booking_id,
            'gcal_primary'  => (bool) $primary_event_id,
            'gcal_shared'   => (bool) $shared_event_id,
            'email_sent'    => $email_sent,
        ] );
    }

    /**
     * Diagnostic — send a test email to a configurable address. Helps
     * verify wp_mail delivery without having to walk through a full
     * booking flow. Reports back whether wp_mail accepted the message.
     */
    public function ajax_admin_test_email() {
        check_ajax_referer( 'caswell_admin_nonce', 'nonce' );
        $this->require_admin();

        $to = sanitize_email( $_POST['to'] ?? '' );
        if ( ! $to || ! is_email( $to ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
        }

        $from_name  = caswell_get_option( 'email_from_name',    get_bloginfo( 'name' ) );
        $from_email = caswell_get_option( 'email_from_address', get_bloginfo( 'admin_email' ) );

        // Domain-mismatch warning. When the From-address domain differs from
        // the site domain (e.g. From: foo@gmail.com on castherapylmt.com),
        // SPF/DKIM alignment is impossible and Gmail/Outlook will spam-fold
        // or reject the message.
        $site_host    = wp_parse_url( site_url(), PHP_URL_HOST );
        $site_domain  = $site_host ? preg_replace( '/^www\./', '', strtolower( $site_host ) ) : '';
        $from_domain  = '';
        if ( $from_email && strpos( $from_email, '@' ) !== false ) {
            $from_domain = strtolower( substr( $from_email, strpos( $from_email, '@' ) + 1 ) );
        }
        $domain_mismatch = ( $site_domain && $from_domain && $site_domain !== $from_domain );
        $generic_from    = in_array( $from_domain, [ 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'aol.com' ], true );

        $subject = '[' . get_bloginfo( 'name' ) . '] Email delivery test';
        $body    = "<p>If you're seeing this, your site can send mail through wp_mail.</p>"
                 . "<p>From header used: <code>" . esc_html( $from_name . ' <' . $from_email . '>' ) . "</code></p>"
                 . "<p>Site: <code>" . esc_html( site_url() ) . "</code></p>"
                 . "<p>Sent at: " . esc_html( gmdate( 'r' ) ) . "</p>";

        // Reuse the same header set the real notifications use, so the test
        // exercises the production-equivalent path.
        $notifier = new Caswell_Notifications();
        $reflect  = new ReflectionMethod( $notifier, 'build_headers' );
        $reflect->setAccessible( true );
        $headers  = $reflect->invoke( $notifier, 'text/html' );

        $sent = wp_mail( $to, $subject, $body, $headers );
        caswell_log( 'email', $sent ? 'Test email sent OK' : 'Test email FAILED', [
            'to'              => $to,
            'from'            => "{$from_name} <{$from_email}>",
            'domain_mismatch' => $domain_mismatch,
        ] );

        $advisories = [];
        if ( $generic_from ) {
            $advisories[] = "Your <strong>From</strong> address is on a free provider (<code>{$from_domain}</code>). Gmail and Outlook strictly enforce DMARC for these domains — your messages will be rejected or spam-foldered. Use an address on your own domain (e.g. <code>bookings@{$site_domain}</code>).";
        } elseif ( $domain_mismatch ) {
            $advisories[] = "Your <strong>From</strong> address is on <code>{$from_domain}</code> but your site is <code>{$site_domain}</code>. SPF/DKIM alignment isn't possible across domains — Gmail/Outlook will treat these as spoofed. Either move the From-address to <code>{$site_domain}</code>, or send from a provider (SendGrid/Mailgun/Postmark) authorized for <code>{$from_domain}</code>.";
        }

        if ( $sent ) {
            $msg = "wp_mail accepted the message. Check {$to} (and the spam folder). If it doesn't arrive, your host is likely silently dropping outbound mail — install an SMTP plugin (e.g. WP Mail SMTP) and configure SendGrid / Mailgun / Gmail SMTP.";
            if ( $advisories ) $msg .= '<br><br><strong>Heads up:</strong> ' . implode( ' ', $advisories );
            wp_send_json_success( [
                'message'    => $msg,
                'advisories' => $advisories,
            ] );
        }
        $msg = "wp_mail returned false — the message was not handed off. Check the From address (<code>{$from_email}</code>) is on a domain you own and that your host allows outbound mail. The debug log has more detail.";
        if ( $advisories ) $msg .= '<br><br>' . implode( ' ', $advisories );
        wp_send_json_error( [
            'message'    => $msg,
            'advisories' => $advisories,
        ] );
    }
}
