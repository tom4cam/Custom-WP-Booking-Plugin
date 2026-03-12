<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Notifications {

    /* ── Email ─────────────────────────────────────────────────────────── */

    public function send_confirmation( $booking ) {
        $this->send_email( $booking, 'confirmation' );
        $this->send_sms( $booking, 'confirmation' );
        $this->notify_owner( $booking );
    }

    public function send_reminder( $booking ) {
        if ( caswell_get_option( 'enable_email_reminder' ) ) {
            $this->send_email( $booking, 'reminder' );
        }
        if ( caswell_get_option( 'enable_sms_reminder' ) ) {
            $this->send_sms( $booking, 'reminder' );
        }
    }

    /**
     * Send a cancellation notification to the client and owner.
     */
    public function send_cancellation( $booking ) {
        $from_name  = caswell_get_option( 'email_from_name', get_bloginfo( 'name' ) );
        $from_email = caswell_get_option( 'email_from_address', get_bloginfo( 'admin_email' ) );

        $start_ts = strtotime( $booking->start_datetime );
        $subject  = 'Appointment Cancelled — ' . wp_date( 'M j, Y', $start_ts );
        $body     = sprintf(
            "Hi %s,\n\nYour %d-minute appointment on %s at %s has been cancelled.\n\nIf you'd like to rebook, please visit our booking page.\n\n%s",
            $booking->name,
            $booking->session_length,
            wp_date( 'l, F j, Y', $start_ts ),
            wp_date( 'g:i A', $start_ts ),
            get_bloginfo( 'name' )
        );

        $headers = [
            "From: {$from_name} <{$from_email}>",
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = wp_mail( $booking->email, $subject, $body, $headers );
        if ( ! $sent ) {
            caswell_log( 'email', 'Failed to send cancellation email', [
                'booking_id' => $booking->id,
                'email'      => $booking->email,
            ] );
        }

        // Notify admin
        $admin_email = get_bloginfo( 'admin_email' );
        wp_mail(
            $admin_email,
            "[Cancelled] {$booking->name} — " . wp_date( 'M j, Y g:i A', $start_ts ),
            $body,
            $headers
        );

        // SMS cancellation to client
        if ( ! empty( $booking->phone ) ) {
            $sms_msg = sprintf(
                'Caswell Therapy: Your %d-min appointment on %s at %s has been cancelled.',
                $booking->session_length,
                wp_date( 'M j', $start_ts ),
                wp_date( 'g:i A', $start_ts )
            );
            $this->send_raw_sms( $this->format_phone( $booking->phone ), $sms_msg );
        }

        // SMS cancellation to owner
        if ( caswell_get_option( 'owner_notify_sms' ) ) {
            $owner_phone = caswell_get_option( 'owner_notify_phone' );
            if ( $owner_phone ) {
                $owner_msg = sprintf(
                    'Cancelled: %s, %s min, %s at %s',
                    $booking->name,
                    $booking->session_length,
                    wp_date( 'M j', $start_ts ),
                    wp_date( 'g:i A', $start_ts )
                );
                $this->send_raw_sms( $this->format_phone( $owner_phone ), $owner_msg );
            }
        }
    }

    private function send_email( $booking, $type ) {
        $from_name  = caswell_get_option( 'email_from_name', get_bloginfo( 'name' ) );
        $from_email = caswell_get_option( 'email_from_address', get_bloginfo( 'admin_email' ) );

        $subject_key = "email_{$type}_subject";
        $body_key    = "email_{$type}_body";

        $subject = caswell_get_option( $subject_key, $this->default_subject( $type ) );
        $body    = caswell_get_option( $body_key,    $this->default_email_body( $type ) );

        $subject = $this->interpolate( $subject, $booking );
        $body    = $this->interpolate( $body, $booking );

        // Use template file
        ob_start();
        $content = $body;
        include CASWELL_PLUGIN_DIR . "templates/email-{$type}.php";
        $html = ob_get_clean();

        $headers = [
            "From: {$from_name} <{$from_email}>",
            'Content-Type: text/html; charset=UTF-8',
        ];

        $sent = wp_mail( $booking->email, $subject, $html, $headers );
        if ( ! $sent ) {
            caswell_log( 'email', "Failed to send {$type} email", [
                'booking_id' => $booking->id,
                'email'      => $booking->email,
            ] );
        }

        // Also notify admin
        $admin_email = get_bloginfo( 'admin_email' );
        if ( 'confirmation' === $type ) {
            wp_mail(
                $admin_email,
                "[New Booking] {$booking->name} — " . wp_date( 'M j, Y g:i A', strtotime( $booking->start_datetime ) ),
                $html,
                $headers
            );
        }
    }

    /* ── Owner SMS notification ────────────────────────────────────────── */

    private function notify_owner( $booking ) {
        if ( ! caswell_get_option( 'owner_notify_sms' ) ) return;

        $owner_phone = caswell_get_option( 'owner_notify_phone' );
        if ( ! $owner_phone ) return;

        $start_ts = strtotime( $booking->start_datetime );
        $message  = sprintf(
            'New booking: %s, %s min, %s at %s',
            $booking->name,
            $booking->session_length,
            wp_date( 'M j', $start_ts ),
            wp_date( 'g:i A', $start_ts )
        );

        $this->send_raw_sms( $this->format_phone( $owner_phone ), $message );
    }

    /* ── SMS via Twilio ────────────────────────────────────────────────── */

    private function send_sms( $booking, $type ) {
        if ( empty( $booking->phone ) ) return;

        $template_key = "sms_{$type}_template";
        $template     = caswell_get_option( $template_key, $this->default_sms_template( $type ) );
        $message      = $this->interpolate( $template, $booking );

        $this->send_raw_sms( $this->format_phone( $booking->phone ), $message );
    }

    /**
     * Low-level Twilio SMS send with error handling and logging.
     *
     * @param string $to      E.164 phone number
     * @param string $message SMS body
     * @return bool           True on success
     */
    private function send_raw_sms( $to, $message ) {
        $account_sid = caswell_get_option( 'twilio_account_sid' );
        $auth_token  = caswell_decrypt( caswell_get_option( 'twilio_auth_token' ) );
        $from_phone  = caswell_get_option( 'twilio_from_phone' );

        if ( ! $account_sid || ! $auth_token || ! $from_phone ) {
            return false;
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

        $response = wp_remote_post( $url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$account_sid}:{$auth_token}" ),
            ],
            'body' => [
                'From' => $from_phone,
                'To'   => $to,
                'Body' => $message,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'twilio', 'SMS request failed: ' . $response->get_error_message(), [
                'to' => $to,
            ] );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code < 200 || $status_code >= 300 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            caswell_log( 'twilio', "SMS failed with HTTP {$status_code}", [
                'to'    => $to,
                'error' => $body['message'] ?? 'Unknown error',
                'code'  => $body['code'] ?? '',
            ] );
            return false;
        }

        caswell_log( 'twilio', "SMS sent to {$to}" );
        return true;
    }

    /* ── Interpolation ─────────────────────────────────────────────────── */

    private function interpolate( $template, $booking ) {
        $tz        = wp_timezone_string();
        $start_ts  = strtotime( $booking->start_datetime );
        $end_ts    = strtotime( $booking->end_datetime );

        $placeholders = [
            '{name}'      => esc_html( $booking->name ),
            '{email}'     => esc_html( $booking->email ),
            '{phone}'     => esc_html( $booking->phone ),
            '{date}'      => wp_date( 'l, F j, Y', $start_ts ),
            '{time}'      => wp_date( 'g:i A', $start_ts ),
            '{end_time}'  => wp_date( 'g:i A', $end_ts ),
            '{duration}'  => $booking->session_length . ' minutes',
            '{timezone}'  => $tz,
            '{site_name}' => get_bloginfo( 'name' ),
            '{site_url}'  => get_bloginfo( 'url' ),
        ];

        return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template );
    }

    private function default_subject( $type ) {
        return 'confirmation' === $type
            ? 'Your appointment is confirmed — {date}'
            : 'Reminder: Your appointment is tomorrow — {date}';
    }

    private function default_email_body( $type ) {
        if ( 'confirmation' === $type ) {
            return "Hi {name},\n\nYour massage appointment has been confirmed!\n\nDate: {date}\nTime: {time} – {end_time} ({timezone})\nDuration: {duration}\n\nWe look forward to seeing you.\n\nCaswell Therapy";
        }
        return "Hi {name},\n\nThis is a reminder of your upcoming massage appointment.\n\nDate: {date}\nTime: {time} – {end_time} ({timezone})\nDuration: {duration}\n\nSee you soon!\n\nCaswell Therapy";
    }

    private function default_sms_template( $type ) {
        if ( 'confirmation' === $type ) {
            return 'Caswell Therapy: Your {duration} appointment on {date} at {time} is confirmed. Reply STOP to unsubscribe.';
        }
        return 'Caswell Therapy: Reminder — you have a {duration} appointment tomorrow, {date} at {time}. Reply STOP to unsubscribe.';
    }

    private function format_phone( $phone ) {
        // Strip non-digits
        $digits = preg_replace( '/\D/', '', $phone );
        // Add +1 for US if needed
        if ( strlen( $digits ) === 10 ) {
            return '+1' . $digits;
        }
        if ( strlen( $digits ) === 11 && $digits[0] === '1' ) {
            return '+' . $digits;
        }
        return '+' . $digits;
    }
}
