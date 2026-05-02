<?php
/**
 * Plugin Name: Caswell Booking
 * Plugin URI:  https://github.com/tom4cam/Custom-WP-Booking-Plugin
 * Description: White-label appointment booking system — Google Calendar integration, Square/Venmo payments, SMS/email notifications, and client accounts.
 * Version:     1.4.6
 * Author:      Caswell Therapy
 * License:     GPL-2.0+
 * Text Domain: caswell-booking
 */

defined( 'ABSPATH' ) || exit;

define( 'CASWELL_VERSION',    '1.4.6' );
define( 'CASWELL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CASWELL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CASWELL_PLUGIN_FILE', __FILE__ );

/* ── Autoload includes ─────────────────────────────────────────────────── */
foreach ( [
    'class-booking-db',
    'class-google-calendar',
    'class-notifications',
    'class-booking-handler',
    'class-auth',
    'class-recurring',
    'class-cron',
    'class-admin',
] as $file ) {
    require_once CASWELL_PLUGIN_DIR . "includes/{$file}.php";
}

/* ── Activation / Deactivation ─────────────────────────────────────────── */
register_activation_hook( __FILE__, 'caswell_activate' );
register_deactivation_hook( __FILE__, 'caswell_deactivate' );

function caswell_activate() {
    Caswell_Booking_DB::create_tables();
    Caswell_Auth::register_role();
    Caswell_Cron::schedule();
    caswell_create_pages();
    flush_rewrite_rules();
}

function caswell_deactivate() {
    Caswell_Cron::unschedule();
    flush_rewrite_rules();
}

/* ── Create default pages on activation ───────────────────────────────── */
function caswell_create_pages() {
    // Home page with custom template
    if ( ! get_option( 'caswell_home_page_id' ) ) {
        $page_id = wp_insert_post( [
            'post_title'   => 'Home',
            'post_name'    => 'caswell-home',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            'meta_input'   => [ '_wp_page_template' => 'page-caswell-home.php' ],
        ] );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'caswell_home_page_id', $page_id );
        }
    }

    // Standalone booking page — the shareable scheduling link
    if ( ! get_option( 'caswell_booking_page_id' ) ) {
        $booking_title = caswell_get_option( 'booking_page_title', 'Book an Appointment' );
        $page_id = wp_insert_post( [
            'post_title'   => $booking_title,
            'post_name'    => 'book',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[caswell_booking]',
        ] );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'caswell_booking_page_id', $page_id );
        }
    }

    // Account page
    if ( ! get_option( 'caswell_account_page_id' ) ) {
        $page_id = wp_insert_post( [
            'post_title'   => 'My Account',
            'post_name'    => 'caswell-account',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[caswell_account]',
        ] );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'caswell_account_page_id', $page_id );
        }
    }
}

/* ── Bootstrap ─────────────────────────────────────────────────────────── */
add_action( 'plugins_loaded', 'caswell_init' );

function caswell_init() {
    // Run DB migrations on existing installs without needing re-activation
    if ( (int) get_option( 'caswell_db_version', 0 ) < Caswell_Booking_DB::BOOKINGS_VERSION ) {
        Caswell_Booking_DB::create_tables();
    }

    new Caswell_Admin();
    new Caswell_Booking_Handler();
    new Caswell_Auth();
    new Caswell_Cron();

    // Ensure cron jobs are scheduled (covers updates that don't trigger
    // the activation hook — e.g., uploading a new plugin zip).
    Caswell_Cron::schedule();

    // Register shortcodes
    add_shortcode( 'caswell_booking', 'caswell_booking_shortcode' );
    add_shortcode( 'caswell_account', 'caswell_account_shortcode' );

    // Enqueue front-end assets
    add_action( 'wp_enqueue_scripts', 'caswell_enqueue_assets' );

    // Register custom page template
    add_filter( 'theme_page_templates', 'caswell_register_page_template' );
    add_filter( 'template_include',     'caswell_load_page_template' );
}

function caswell_enqueue_assets() {
    wp_enqueue_style(
        'caswell-booking',
        CASWELL_PLUGIN_URL . 'public/css/booking.css',
        [],
        CASWELL_VERSION
    );

    $square_app_id = caswell_get_option( 'square_application_id' );
    $square_sandbox = caswell_get_option( 'square_sandbox_mode' );
    $square_sdk_url = $square_sandbox
        ? 'https://sandbox.web.squarecdn.com/v1/square.js'
        : 'https://web.squarecdn.com/v1/square.js';

    if ( $square_app_id ) {
        wp_enqueue_script( 'square-web-payments', $square_sdk_url, [], null, true );
    }

    wp_enqueue_script(
        'caswell-booking',
        CASWELL_PLUGIN_URL . 'public/js/booking.js',
        [ 'jquery' ],
        CASWELL_VERSION,
        true
    );

    wp_localize_script( 'caswell-booking', 'caswellData', [
        'ajax_url'        => admin_url( 'admin-ajax.php' ),
        'nonce'           => wp_create_nonce( 'caswell_booking_nonce' ),
        'square_app_id'   => $square_app_id ?: '',
        'square_sandbox'  => (bool) $square_sandbox,
        'session_lengths' => caswell_enabled_session_lengths(),
        'default_length'  => caswell_get_option( 'default_session_length', 60 ),
    ] );
}

/* ── Shortcode callbacks ───────────────────────────────────────────────── */
function caswell_booking_shortcode( $atts ) {
    ob_start();
    require CASWELL_PLUGIN_DIR . 'public/booking-shortcode.php';
    return ob_get_clean();
}

function caswell_account_shortcode( $atts ) {
    ob_start();
    require CASWELL_PLUGIN_DIR . 'public/account-shortcode.php';
    return ob_get_clean();
}

/* ── Page template ─────────────────────────────────────────────────────── */
function caswell_register_page_template( $templates ) {
    $templates['page-caswell-home.php'] = 'Caswell Home Page';
    return $templates;
}

function caswell_load_page_template( $template ) {
    if ( is_page() ) {
        $meta = get_post_meta( get_the_ID(), '_wp_page_template', true );
        if ( 'page-caswell-home.php' === $meta ) {
            $plugin_template = CASWELL_PLUGIN_DIR . 'page-caswell-home.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
    }
    return $template;
}

/* ── Helpers ───────────────────────────────────────────────────────────── */
function caswell_get_option( $key, $default = '' ) {
    $options = get_option( 'caswell_settings', [] );
    return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

function caswell_business_name() {
    return caswell_get_option( 'business_name', get_bloginfo( 'name' ) );
}

function caswell_enabled_session_lengths() {
    $lengths  = [];
    $options  = get_option( 'caswell_settings', [] );
    foreach ( [ 30, 60, 90 ] as $len ) {
        if ( ! empty( $options[ "enable_{$len}min" ] ) ) {
            $lengths[] = $len;
        }
    }
    return $lengths ?: [ 60 ];
}

/**
 * Log a message to the caswell debug log.
 * Logs are stored in wp-content/caswell-booking.log when WP_DEBUG is enabled,
 * or when the caswell_enable_logging option is set.
 *
 * @param string $category  e.g. 'twilio', 'square', 'gcal', 'auth', 'booking'
 * @param string $message   Human-readable message
 * @param array  $context   Optional extra data
 */
function caswell_log( $category, $message, $context = [] ) {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
        if ( ! get_option( 'caswell_enable_logging' ) ) {
            return;
        }
    }

    $log_file = WP_CONTENT_DIR . '/caswell-booking.log';
    $timestamp = wp_date( 'Y-m-d H:i:s' );
    $entry = "[{$timestamp}] [{$category}] {$message}";
    if ( ! empty( $context ) ) {
        $entry .= ' | ' . wp_json_encode( $context );
    }
    $entry .= PHP_EOL;

    // Keep log file under 1MB — truncate if too large
    if ( file_exists( $log_file ) && filesize( $log_file ) > 1048576 ) {
        $lines = file( $log_file );
        $lines = array_slice( $lines, -500 ); // keep last 500 lines
        file_put_contents( $log_file, implode( '', $lines ) );
    }

    error_log( $entry, 3, $log_file );
}

/**
 * Encrypt a value for storage (Twilio/Square secrets).
 */
function caswell_encrypt( $value ) {
    if ( empty( $value ) ) return '';
    $key    = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
    $iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
    $iv     = openssl_random_pseudo_bytes( $iv_len );
    $enc    = openssl_encrypt( $value, 'aes-256-cbc', $key, 0, $iv );
    return base64_encode( $iv . $enc );
}

function caswell_decrypt( $value ) {
    if ( empty( $value ) ) return '';
    $key    = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
    $iv_len = openssl_cipher_iv_length( 'aes-256-cbc' );
    $raw    = base64_decode( $value );
    $iv     = substr( $raw, 0, $iv_len );
    $enc    = substr( $raw, $iv_len );
    return openssl_decrypt( $enc, 'aes-256-cbc', $key, 0, $iv );
}

/**
 * HMAC-signed self-service token for a booking. Allows clients to access
 * a "manage your booking" link from confirmation emails without logging in.
 *
 * The token is bound to the booking's id + email — both immutable for the
 * life of the row. So the link stays valid even after the booking is
 * rescheduled (start_datetime changes).
 */
function caswell_booking_manage_token( $booking ) {
    if ( ! is_object( $booking ) || empty( $booking->id ) ) return '';
    $payload = $booking->id . '|' . strtolower( (string) $booking->email );
    return substr( hash_hmac( 'sha256', $payload, AUTH_KEY ), 0, 32 );
}

function caswell_verify_booking_manage_token( $booking, $token ) {
    if ( ! is_object( $booking ) || empty( $token ) ) return false;
    return hash_equals( caswell_booking_manage_token( $booking ), (string) $token );
}

function caswell_booking_action_url( $booking, $action ) {
    if ( ! is_object( $booking ) ) return '';
    $base = get_option( 'caswell_booking_page_id' )
        ? get_permalink( (int) get_option( 'caswell_booking_page_id' ) )
        : home_url( '/book/' );
    return add_query_arg( [
        'caswell_action' => $action,
        'b' => $booking->id,
        't' => caswell_booking_manage_token( $booking ),
    ], $base );
}

function caswell_booking_reschedule_url( $booking ) {
    return caswell_booking_action_url( $booking, 'reschedule' );
}

function caswell_booking_cancel_url( $booking ) {
    return caswell_booking_action_url( $booking, 'cancel' );
}

/**
 * Render a Google Calendar event title from a template. Supports placeholders:
 *   {practitioner}   — the practitioner_name setting
 *   {client}         — full client name as entered on the form
 *   {client_first}   — first whitespace-delimited token of the client name
 *   {client_short}   — first name + last initial (e.g. "Jane S.").
 *                      Falls back to first name only when the client gave a
 *                      single-word name.
 *   {duration}       — session length in minutes
 *   {service}        — service_type setting
 */
function caswell_render_event_title( $template, $client_name, $session_length, $service = '' ) {
    $practitioner = caswell_get_option( 'practitioner_name', 'Appointment' );
    $service      = $service ?: caswell_get_option( 'service_type', 'appointment' );
    $client_name  = (string) $client_name;

    $parts        = preg_split( '/\s+/', trim( $client_name ), -1, PREG_SPLIT_NO_EMPTY );
    $client_first = $parts ? $parts[0] : $client_name;
    $client_short = $client_first;
    if ( count( $parts ) > 1 ) {
        $last_initial = mb_substr( end( $parts ), 0, 1 );
        if ( $last_initial !== '' ) {
            $client_short = $client_first . ' ' . mb_strtoupper( $last_initial ) . '.';
        }
    }

    return str_replace(
        [ '{practitioner}', '{client_short}', '{client_first}', '{client}', '{duration}', '{service}' ],
        [ $practitioner,    $client_short,    $client_first,    $client_name, (int) $session_length, $service ],
        (string) $template
    );
}
