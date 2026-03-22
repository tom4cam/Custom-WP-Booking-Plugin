<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Admin {

    public function __construct() {
        add_action( 'admin_menu',    [ $this, 'add_menu' ] );
        add_action( 'admin_init',    [ $this, 'register_settings' ] );
        add_action( 'admin_notices', [ $this, 'security_notices' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_caswell_toggle_logging', [ $this, 'ajax_toggle_logging' ] );
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

        // Session lengths
        foreach ( [ 30, 60, 90 ] as $len ) {
            $clean[ "enable_{$len}min" ] = ! empty( $input[ "enable_{$len}min" ] ) ? 1 : 0;
        }
        $clean['default_session_length'] = absint( $input['default_session_length'] ?? 60 );

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
        foreach ( [ 30, 60, 90 ] as $len ) {
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

        // Owner SMS notifications
        $clean['owner_notify_sms']   = ! empty( $input['owner_notify_sms'] ) ? 1 : 0;
        $clean['owner_notify_phone'] = sanitize_text_field( $input['owner_notify_phone'] ?? '' );

        // Scheduling
        $clean['buffer_time'] = absint( $input['buffer_time'] ?? 15 );
        // Warn if buffer >= smallest enabled session length
        $smallest_enabled = PHP_INT_MAX;
        foreach ( [ 30, 60, 90 ] as $len ) {
            if ( ! empty( $clean[ "enable_{$len}min" ] ) ) {
                $smallest_enabled = min( $smallest_enabled, $len );
            }
        }
        if ( $smallest_enabled !== PHP_INT_MAX && $clean['buffer_time'] >= $smallest_enabled ) {
            add_settings_error( 'caswell_settings', 'buffer_too_large',
                "Buffer time ({$clean['buffer_time']} min) is >= your shortest session ({$smallest_enabled} min). No slots will be bookable for that length.",
                'error'
            );
        }
        $clean['reminder_hours_before'] = absint( $input['reminder_hours_before'] ?? 24 );
        $clean['enable_email_reminder'] = ! empty( $input['enable_email_reminder'] ) ? 1 : 0;
        $clean['enable_sms_reminder']   = ! empty( $input['enable_sms_reminder'] ) ? 1 : 0;

        // Services / Pricing (for homepage)
        foreach ( [ 30, 60, 90 ] as $len ) {
            $clean[ "service_price_{$len}" ]       = sanitize_text_field( $input[ "service_price_{$len}" ] ?? '' );
            $clean[ "service_description_{$len}" ] = sanitize_textarea_field( $input[ "service_description_{$len}" ] ?? '' );
        }

        // White-label / Branding
        $clean['business_name']      = sanitize_text_field( $input['business_name'] ?? '' );
        $clean['practitioner_name']  = sanitize_text_field( $input['practitioner_name'] ?? '' );
        $clean['service_type']       = sanitize_text_field( $input['service_type'] ?? '' );
        $clean['hero_subtitle']      = sanitize_text_field( $input['hero_subtitle'] ?? '' );
        $clean['gcal_event_title']   = sanitize_text_field( $input['gcal_event_title'] ?? '' );
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
}
