<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Notifications {

    /* ── Header builder (consistent across every wp_mail call) ─────────── */

    /**
     * Build the standard set of headers we attach to every outbound message.
     * Includes deliverability-friendly basics that wp_mail won't add for you:
     *   - From / Reply-To pair (matched, on the same domain ideally)
     *   - Message-ID with a host that matches the From domain
     *   - Date (some hosts strip / mis-format wp_mail's auto-Date)
     *   - List-Unsubscribe (RFC 2369) — Gmail and Outlook give better
     *     placement to messages that expose a clean unsubscribe path
     *
     * @param string $content_type 'text/html' or 'text/plain'
     */
    private function build_headers( $content_type = 'text/html' ) {
        $from_name  = caswell_get_option( 'email_from_name', get_bloginfo( 'name' ) );
        $from_email = caswell_get_option( 'email_from_address', get_bloginfo( 'admin_email' ) );

        $domain = '';
        if ( $from_email && strpos( $from_email, '@' ) !== false ) {
            $domain = strtolower( substr( $from_email, strpos( $from_email, '@' ) + 1 ) );
        }
        if ( ! $domain ) {
            $host = wp_parse_url( site_url(), PHP_URL_HOST );
            $domain = $host ? preg_replace( '/^www\./', '', $host ) : 'localhost';
        }

        // Stable, unique Message-ID. Random component bound to wp_generate_uuid4.
        $msg_id = '<' . wp_generate_uuid4() . '@' . $domain . '>';

        $headers = [
            "From: {$from_name} <{$from_email}>",
            "Reply-To: {$from_name} <{$from_email}>",
            "Content-Type: {$content_type}; charset=UTF-8",
            "Message-ID: {$msg_id}",
            'Date: ' . gmdate( 'r' ),
            // Mailto-only List-Unsubscribe — Gmail/Outlook will surface a
            // one-click "Unsubscribe" button that reaches the practitioner.
            "List-Unsubscribe: <mailto:{$from_email}?subject=Unsubscribe>",
        ];

        return $headers;
    }

    /* ── Email ─────────────────────────────────────────────────────────── */

    /**
     * Send the booking-confirmed email + SMS + owner notification.
     * Returns whether the client confirmation email was successfully handed
     * off to wp_mail (a true return doesn't guarantee delivery, but a false
     * return guarantees it did NOT send and something is wrong).
     */
    public function send_confirmation( $booking ) {
        $email_ok = $this->send_email( $booking, 'confirmation' );
        $this->send_sms( $booking, 'confirmation' );
        $this->notify_owner( $booking );
        return (bool) $email_ok;
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
     * Send a reschedule notification to the client (and admin).
     *
     * @param object $booking         Updated booking row (with new times)
     * @param string $previous_start  MySQL datetime string for the old start
     * @param string $admin_message   Optional personal note from the practitioner
     */
    public function send_reschedule( $booking, $previous_start, $admin_message = '' ) {
        $old_ts   = caswell_local_ts( $previous_start );
        $new_ts   = caswell_local_ts($booking->start_datetime);
        $end_ts   = caswell_local_ts($booking->end_datetime);
        $subject  = 'Appointment Rescheduled — ' . wp_date( 'M j, Y', $new_ts );

        ob_start();
        $vars = [
            'booking'        => $booking,
            'old_ts'         => $old_ts,
            'new_ts'         => $new_ts,
            'end_ts'         => $end_ts,
            'admin_message'  => $admin_message,
            'site_name'      => get_bloginfo( 'name' ),
        ];
        extract( $vars, EXTR_SKIP );
        include CASWELL_PLUGIN_DIR . 'templates/email-rescheduled.php';
        $html = ob_get_clean();

        $headers = $this->build_headers( 'text/html' );

        $sent = wp_mail( $booking->email, $subject, $html, $headers );
        if ( ! $sent ) {
            caswell_log( 'email', 'Failed to send reschedule email', [
                'booking_id' => $booking->id,
                'email'      => $booking->email,
            ] );
        }

        // Notify admin
        $admin_email = get_bloginfo( 'admin_email' );
        wp_mail(
            $admin_email,
            "[Rescheduled] {$booking->name} — " . wp_date( 'M j, Y g:i A', $new_ts ),
            $html,
            $headers
        );

        // SMS to client
        if ( ! empty( $booking->phone ) ) {
            $biz_name = caswell_get_option( 'business_name', get_bloginfo( 'name' ) );
            $sms_msg  = sprintf(
                '%s: Your %d-min appointment has been moved from %s %s to %s %s.',
                $biz_name,
                $booking->session_length,
                wp_date( 'M j', $old_ts ),
                wp_date( 'g:i A', $old_ts ),
                wp_date( 'M j', $new_ts ),
                wp_date( 'g:i A', $new_ts )
            );
            $this->send_raw_sms( $this->format_phone( $booking->phone ), $sms_msg );
        }
    }

    /**
     * Send a cancellation notification to the client and owner.
     */
    public function send_cancellation( $booking ) {
        $start_ts = caswell_local_ts($booking->start_datetime);
        $subject  = 'Appointment Cancelled — ' . wp_date( 'M j, Y', $start_ts );
        $body     = sprintf(
            "Hi %s,\n\nYour %d-minute appointment on %s at %s has been cancelled.\n\nIf you'd like to rebook, please visit our booking page.\n\n%s",
            $booking->name,
            $booking->session_length,
            wp_date( 'l, F j, Y', $start_ts ),
            wp_date( 'g:i A', $start_ts ),
            get_bloginfo( 'name' )
        );

        $headers = $this->build_headers( 'text/plain' );

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
            $biz_name = caswell_get_option( 'business_name', get_bloginfo( 'name' ) );
            $sms_msg = sprintf(
                '%s: Your %d-min appointment on %s at %s has been cancelled.',
                $biz_name,
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

    /**
     * Send a templated email. Returns the boolean wp_mail returned —
     * true = handed off to mailer, false = wp_mail rejected it.
     */
    private function send_email( $booking, $type ) {
        $subject_key = "email_{$type}_subject";
        $body_key    = "email_{$type}_body";

        $subject = caswell_get_option( $subject_key, $this->default_subject( $type ) );
        $body    = caswell_get_option( $body_key,    $this->default_email_body( $type ) );

        $subject = $this->interpolate( $subject, $booking );
        $body    = $this->interpolate( $body, $booking );

        ob_start();
        $content = $body;
        include CASWELL_PLUGIN_DIR . "templates/email-{$type}.php";
        $html = ob_get_clean();

        $headers = $this->build_headers( 'text/html' );

        // For deliverability bookkeeping — caswell_log records what we
        // sent with which From, so misconfigured From-addresses surface in
        // the debug log and the Email Delivery Test result.
        $from_email_for_log = caswell_get_option( 'email_from_address', get_bloginfo( 'admin_email' ) );

        $sent = wp_mail( $booking->email, $subject, $html, $headers );
        caswell_log( 'email', $sent ? "Sent {$type} email" : "FAILED to send {$type} email", [
            'booking_id' => $booking->id,
            'email'      => $booking->email,
            'from'       => $from_email_for_log,
        ] );

        // Also notify admin — but skip if the admin email is the same address
        // as the client's, otherwise testing as Ryan-as-client lands two
        // confirmations in the same inbox.
        $admin_email = get_bloginfo( 'admin_email' );
        $same_inbox  = $admin_email && strcasecmp( trim( $admin_email ), trim( $booking->email ) ) === 0;
        if ( 'confirmation' === $type && ! $same_inbox ) {
            $admin_sent = wp_mail(
                $admin_email,
                "[New Booking] {$booking->name} — " . wp_date( 'M j, Y g:i A', caswell_local_ts( $booking->start_datetime ) ),
                $html,
                $headers
            );
            caswell_log( 'email', $admin_sent ? 'Sent admin alert email' : 'FAILED to send admin alert email', [
                'admin_email' => $admin_email,
            ] );
        }

        return (bool) $sent;
    }

    /* ── Owner SMS notification ────────────────────────────────────────── */

    private function notify_owner( $booking ) {
        if ( ! caswell_get_option( 'owner_notify_sms' ) ) return;

        $owner_phone = caswell_get_option( 'owner_notify_phone' );
        if ( ! $owner_phone ) return;

        $start_ts = caswell_local_ts($booking->start_datetime);
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
     * Low-level Twilio send. Routes through the configured channel:
     *   - 'sms'      → standard SMS via the From phone number
     *   - 'whatsapp' → WhatsApp via the WhatsApp sender (requires approved
     *                  Twilio WhatsApp sender or sandbox opt-in)
     *   - 'off'      → no-op (returns false; caller handles email-only fallback)
     *
     * Twilio's API endpoint is the same for both channels — the difference
     * is the `whatsapp:` prefix on From and To.
     *
     * @param string $to      E.164 phone number
     * @param string $message Message body
     * @return bool           True on success
     */
    private function send_raw_sms( $to, $message ) {
        $channel = caswell_get_option( 'notification_channel', 'sms' );
        if ( $channel === 'off' ) {
            return false;
        }

        $account_sid = caswell_get_option( 'twilio_account_sid' );
        $auth_token  = caswell_decrypt( caswell_get_option( 'twilio_auth_token' ) );

        if ( ! $account_sid || ! $auth_token ) {
            return false;
        }

        if ( $channel === 'whatsapp' ) {
            $from_raw = caswell_get_option( 'twilio_whatsapp_from' );
            if ( ! $from_raw ) {
                caswell_log( 'twilio', 'WhatsApp channel selected but twilio_whatsapp_from is empty' );
                return false;
            }
            // Strip any whatsapp: prefix the admin may have entered, then re-add.
            $from = 'whatsapp:' . preg_replace( '/^whatsapp:/i', '', trim( $from_raw ) );
            $to_  = 'whatsapp:' . preg_replace( '/^whatsapp:/i', '', trim( $to ) );
            $kind = 'whatsapp';
        } else {
            $from_phone = caswell_get_option( 'twilio_from_phone' );
            if ( ! $from_phone ) return false;
            $from = $from_phone;
            $to_  = $to;
            $kind = 'sms';
        }

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

        $response = wp_remote_post( $url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( "{$account_sid}:{$auth_token}" ),
            ],
            'body' => [
                'From' => $from,
                'To'   => $to_,
                'Body' => $message,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            caswell_log( 'twilio', "{$kind} request failed: " . $response->get_error_message(), [
                'to' => $to_,
            ] );
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code < 200 || $status_code >= 300 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            caswell_log( 'twilio', "{$kind} failed with HTTP {$status_code}", [
                'to'    => $to_,
                'error' => $body['message'] ?? 'Unknown error',
                'code'  => $body['code'] ?? '',
            ] );
            return false;
        }

        caswell_log( 'twilio', "{$kind} sent to {$to_}" );
        return true;
    }

    /* ── Interpolation ─────────────────────────────────────────────────── */

    private function interpolate( $template, $booking ) {
        $tz        = wp_timezone_string();
        $start_ts  = caswell_local_ts($booking->start_datetime);
        $end_ts    = caswell_local_ts($booking->end_datetime);

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
            return "Hi {name},\n\nYour appointment has been confirmed!\n\nDate: {date}\nTime: {time} – {end_time} ({timezone})\nDuration: {duration}\n\nWe look forward to seeing you.\n\n{site_name}";
        }
        return "Hi {name},\n\nThis is a reminder of your upcoming appointment.\n\nDate: {date}\nTime: {time} – {end_time} ({timezone})\nDuration: {duration}\n\nSee you soon!\n\n{site_name}";
    }

    private function default_sms_template( $type ) {
        if ( 'confirmation' === $type ) {
            return '{site_name}: Your {duration} appointment on {date} at {time} is confirmed. Reply STOP to unsubscribe.';
        }
        return '{site_name}: Reminder — you have a {duration} appointment tomorrow, {date} at {time}. Reply STOP to unsubscribe.';
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
