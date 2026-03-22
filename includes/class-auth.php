<?php
defined( 'ABSPATH' ) || exit;

class Caswell_Auth {

    const ROLE = 'caswell_client';

    public function __construct() {
        add_action( 'wp_ajax_nopriv_caswell_register',       [ $this, 'ajax_register' ] );
        add_action( 'wp_ajax_nopriv_caswell_login',          [ $this, 'ajax_login' ] );
        add_action( 'wp_ajax_caswell_logout',                [ $this, 'ajax_logout' ] );
        add_action( 'wp_ajax_nopriv_caswell_forgot_password',[ $this, 'ajax_forgot_password' ] );
        add_action( 'wp_ajax_nopriv_caswell_reset_password', [ $this, 'ajax_reset_password' ] );
        add_action( 'wp_ajax_nopriv_caswell_verify_email',   [ $this, 'ajax_verify_email' ] );
        add_action( 'init', [ $this, 'handle_email_verification_link' ] );
    }

    public static function register_role() {
        if ( get_role( self::ROLE ) ) return;
        add_role( self::ROLE, 'Caswell Client', [
            'read' => true,
        ] );
    }

    /* ── Rate limiting for auth endpoints ─────────────────────────────── */

    private function check_auth_rate_limit( $action = 'auth' ) {
        $ip  = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        $key = 'caswell_' . $action . '_' . md5( $ip );
        $count = (int) get_transient( $key );
        if ( $count >= 10 ) {
            return false;
        }
        set_transient( $key, $count + 1, 15 * MINUTE_IN_SECONDS );
        return true;
    }

    /* ── AJAX: Register ────────────────────────────────────────────────── */

    public function ajax_register() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        if ( ! $this->check_auth_rate_limit( 'register' ) ) {
            wp_send_json_error( 'Too many attempts. Please try again later.' );
        }

        $name     = sanitize_text_field( $_POST['name'] ?? '' );
        $email    = sanitize_email( $_POST['email'] ?? '' );
        $password = $_POST['password'] ?? '';  // Will be hashed by WP

        if ( ! $name || ! $email || ! $password ) {
            wp_send_json_error( 'All fields are required.' );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( 'Invalid email address.' );
        }
        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( 'Password must be at least 8 characters.' );
        }
        if ( email_exists( $email ) ) {
            wp_send_json_error( 'An account with this email already exists.' );
        }

        $username = sanitize_user( strtolower( str_replace( ' ', '.', $name ) ) . '.' . wp_generate_password( 4, false ) );
        $user_id  = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( $user_id->get_error_message() );
        }

        $user = new WP_User( $user_id );
        $user->set_role( self::ROLE );
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );

        // Send verification email
        $this->send_verification_email( $user_id, $email, $name );

        wp_set_auth_cookie( $user_id, true );
        wp_send_json_success( [ 'message' => 'Account created. You are now logged in. Please check your email to verify your address.' ] );
    }

    /* ── AJAX: Login ───────────────────────────────────────────────────── */

    public function ajax_login() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        if ( ! $this->check_auth_rate_limit( 'login' ) ) {
            wp_send_json_error( 'Too many login attempts. Please try again in 15 minutes.' );
        }

        $email    = sanitize_email( $_POST['email'] ?? '' );
        $password = $_POST['password'] ?? '';

        $user = get_user_by( 'email', $email );
        if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            wp_send_json_error( 'Invalid email or password.' );
        }

        wp_set_auth_cookie( $user->ID, true );
        wp_send_json_success( [ 'message' => 'Logged in successfully.' ] );
    }

    /* ── AJAX: Logout ──────────────────────────────────────────────────── */

    public function ajax_logout() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );
        wp_logout();
        wp_send_json_success();
    }

    /* ── AJAX: Forgot Password ─────────────────────────────────────────── */

    public function ajax_forgot_password() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        if ( ! $this->check_auth_rate_limit( 'forgot' ) ) {
            wp_send_json_error( 'Too many attempts. Please try again later.' );
        }

        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! $email || ! is_email( $email ) ) {
            wp_send_json_error( 'Please enter a valid email address.' );
        }

        $user = get_user_by( 'email', $email );

        // Always return success to prevent email enumeration
        if ( ! $user ) {
            wp_send_json_success( [ 'message' => 'If an account exists with that email, a password reset link has been sent.' ] );
        }

        // Generate a secure reset token
        $token     = wp_generate_password( 32, false );
        $token_hash = hash( 'sha256', $token );
        $expiry     = time() + HOUR_IN_SECONDS; // 1 hour expiry

        update_user_meta( $user->ID, '_caswell_reset_token', $token_hash );
        update_user_meta( $user->ID, '_caswell_reset_expiry', $expiry );

        // Build reset URL
        $account_page_id = get_option( 'caswell_account_page_id' );
        $base_url        = $account_page_id ? get_permalink( $account_page_id ) : home_url( '/caswell-account/' );
        $reset_url       = add_query_arg( [
            'caswell_action' => 'reset_password',
            'token'          => $token,
            'uid'            => $user->ID,
        ], $base_url );

        // Send email
        $site_name = get_bloginfo( 'name' );
        $subject   = "{$site_name} — Password Reset Request";
        $message   = sprintf(
            "Hi %s,\n\nWe received a request to reset your password. Click the link below to set a new password:\n\n%s\n\nThis link expires in 1 hour.\n\nIf you didn't request this, you can safely ignore this email.\n\n%s",
            $user->display_name,
            $reset_url,
            $site_name
        );
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        wp_mail( $email, $subject, $message, $headers );

        caswell_log( 'auth', "Password reset requested for user #{$user->ID} ({$email})" );

        wp_send_json_success( [ 'message' => 'If an account exists with that email, a password reset link has been sent.' ] );
    }

    /* ── AJAX: Reset Password ──────────────────────────────────────────── */

    public function ajax_reset_password() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        $token        = sanitize_text_field( $_POST['token'] ?? '' );
        $uid          = absint( $_POST['uid'] ?? 0 );
        $new_password = $_POST['new_password'] ?? '';

        if ( ! $token || ! $uid || ! $new_password ) {
            wp_send_json_error( 'Missing required fields.' );
        }
        if ( strlen( $new_password ) < 8 ) {
            wp_send_json_error( 'Password must be at least 8 characters.' );
        }

        $stored_hash = get_user_meta( $uid, '_caswell_reset_token', true );
        $expiry      = (int) get_user_meta( $uid, '_caswell_reset_expiry', true );

        if ( ! $stored_hash || ! $expiry ) {
            wp_send_json_error( 'Invalid or expired reset link. Please request a new one.' );
        }
        if ( time() > $expiry ) {
            delete_user_meta( $uid, '_caswell_reset_token' );
            delete_user_meta( $uid, '_caswell_reset_expiry' );
            wp_send_json_error( 'This reset link has expired. Please request a new one.' );
        }
        if ( ! hash_equals( $stored_hash, hash( 'sha256', $token ) ) ) {
            wp_send_json_error( 'Invalid or expired reset link. Please request a new one.' );
        }

        // Token is valid — reset password
        wp_set_password( $new_password, $uid );

        // Clean up token
        delete_user_meta( $uid, '_caswell_reset_token' );
        delete_user_meta( $uid, '_caswell_reset_expiry' );

        caswell_log( 'auth', "Password reset completed for user #{$uid}" );

        wp_send_json_success( [ 'message' => 'Password has been reset. You can now log in with your new password.' ] );
    }

    /* ── AJAX: Verify Email (resend) ───────────────────────────────────── */

    public function ajax_verify_email() {
        check_ajax_referer( 'caswell_booking_nonce', 'nonce' );

        $email = sanitize_email( $_POST['email'] ?? '' );
        if ( ! $email ) {
            wp_send_json_error( 'Email is required.' );
        }

        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            wp_send_json_success( [ 'message' => 'If that email is registered, a verification link has been sent.' ] );
        }

        // Check if already verified
        if ( get_user_meta( $user->ID, '_caswell_email_verified', true ) ) {
            wp_send_json_success( [ 'message' => 'Email is already verified.' ] );
        }

        $this->send_verification_email( $user->ID, $email, $user->display_name );

        wp_send_json_success( [ 'message' => 'Verification email sent. Please check your inbox.' ] );
    }

    /* ── Handle Email Verification Link (front-end GET request) ────────── */

    public function handle_email_verification_link() {
        if ( ! isset( $_GET['caswell_action'] ) || 'verify_email' !== $_GET['caswell_action'] ) {
            return;
        }

        $token = sanitize_text_field( $_GET['token'] ?? '' );
        $uid   = absint( $_GET['uid'] ?? 0 );

        if ( ! $token || ! $uid ) return;

        $stored_hash = get_user_meta( $uid, '_caswell_verify_token', true );
        $expiry      = (int) get_user_meta( $uid, '_caswell_verify_expiry', true );

        if ( ! $stored_hash || ! $expiry || time() > $expiry ) {
            // Expired or invalid — silently redirect
            wp_safe_redirect( home_url( '/caswell-account/?verification=expired' ) );
            exit;
        }

        if ( ! hash_equals( $stored_hash, hash( 'sha256', $token ) ) ) {
            wp_safe_redirect( home_url( '/caswell-account/?verification=invalid' ) );
            exit;
        }

        // Mark email as verified
        update_user_meta( $uid, '_caswell_email_verified', 1 );
        delete_user_meta( $uid, '_caswell_verify_token' );
        delete_user_meta( $uid, '_caswell_verify_expiry' );

        caswell_log( 'auth', "Email verified for user #{$uid}" );

        wp_safe_redirect( home_url( '/caswell-account/?verification=success' ) );
        exit;
    }

    /* ── Helper: Send verification email ───────────────────────────────── */

    private function send_verification_email( $user_id, $email, $name ) {
        $token      = wp_generate_password( 32, false );
        $token_hash = hash( 'sha256', $token );
        $expiry     = time() + DAY_IN_SECONDS; // 24 hour expiry

        update_user_meta( $user_id, '_caswell_verify_token', $token_hash );
        update_user_meta( $user_id, '_caswell_verify_expiry', $expiry );

        $verify_url = add_query_arg( [
            'caswell_action' => 'verify_email',
            'token'          => $token,
            'uid'            => $user_id,
        ], home_url( '/' ) );

        $site_name = get_bloginfo( 'name' );
        $subject   = "{$site_name} — Please Verify Your Email";
        $message   = sprintf(
            "Hi %s,\n\nThank you for creating an account. Please verify your email address by clicking the link below:\n\n%s\n\nThis link expires in 24 hours.\n\n%s",
            $name,
            $verify_url,
            $site_name
        );
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        wp_mail( $email, $subject, $message, $headers );
    }
}
