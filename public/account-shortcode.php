<?php defined( 'ABSPATH' ) || exit; ?>

<div id="caswell-account-wrap" class="caswell-account-wrap">

<?php
// Email verification status messages
$verification = sanitize_text_field( $_GET['verification'] ?? '' );
if ( 'success' === $verification ) :
?>
    <div class="caswell-notice" style="background:#d4edda;border-color:#c3e6cb;color:#155724;margin-bottom:16px;">Your email has been verified successfully.</div>
<?php elseif ( 'expired' === $verification ) : ?>
    <div class="caswell-error" style="margin-bottom:16px;">Verification link has expired. Please request a new one from your account page.</div>
<?php elseif ( 'invalid' === $verification ) : ?>
    <div class="caswell-error" style="margin-bottom:16px;">Invalid verification link.</div>
<?php endif; ?>

<?php if ( is_user_logged_in() ) :
    $user     = wp_get_current_user();
    $user_id  = $user->ID;

    $upcoming = Caswell_Booking_DB::get_client_bookings( $user_id, true );
    $past     = array_filter(
        Caswell_Booking_DB::get_client_bookings( $user_id, false ),
        fn($b) => caswell_local_ts($b->start_datetime) < time() || $b->status === 'cancelled'
    );
    $series   = Caswell_Booking_DB::get_client_series( $user_id );
?>

    <div class="caswell-account-header">
        <h2>My Account</h2>
        <p>Welcome back, <strong><?php echo esc_html( $user->display_name ); ?></strong></p>
        <button id="caswell-logout-btn" class="caswell-btn caswell-btn-secondary caswell-btn-sm">Log Out</button>
    </div>

    <!-- Upcoming Appointments -->
    <section class="caswell-account-section">
        <h3>Upcoming Appointments</h3>
        <?php if ( empty( $upcoming ) ) : ?>
            <p class="caswell-empty">No upcoming appointments. <a href="<?php echo esc_url( home_url( '/#booking' ) ); ?>">Book one now →</a></p>
        <?php else : ?>
        <div class="caswell-bookings-list">
            <?php foreach ( $upcoming as $booking ) : ?>
            <div class="caswell-booking-card" data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
                <div class="caswell-booking-date">
                    <?php echo esc_html( wp_date( 'l, F j, Y', caswell_local_ts($booking->start_datetime) ) ); ?>
                </div>
                <div class="caswell-booking-time">
                    <?php echo esc_html( wp_date( 'g:i A', caswell_local_ts($booking->start_datetime) ) ); ?>
                    – <?php echo esc_html( wp_date( 'g:i A', caswell_local_ts($booking->end_datetime) ) ); ?>
                    (<?php echo esc_html( $booking->session_length ); ?> min)
                </div>
                <div class="caswell-booking-meta">
                    Status: <span class="caswell-status caswell-status-<?php echo esc_attr( $booking->status ); ?>"><?php echo esc_html( ucfirst( $booking->status ) ); ?></span>
                    &nbsp;|&nbsp; Payment: <?php echo esc_html( ucfirst( $booking->payment_status ) ); ?>
                    <?php if ( $booking->recurring_series_id ) : ?>
                    &nbsp;|&nbsp; <span class="caswell-recurring-badge">Recurring</span>
                    <?php endif; ?>
                </div>
                <?php if ( caswell_local_ts($booking->start_datetime) > time() + 3600 ) : // Can cancel if >1hr away ?>
                <button class="caswell-btn caswell-btn-danger caswell-btn-sm caswell-cancel-booking"
                        data-booking-id="<?php echo esc_attr( $booking->id ); ?>">
                    Cancel
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- Recurring Series -->
    <?php if ( ! empty( $series ) ) : ?>
    <section class="caswell-account-section">
        <h3>Recurring Series</h3>
        <div class="caswell-bookings-list">
            <?php foreach ( $series as $s ) : ?>
            <div class="caswell-booking-card">
                <div class="caswell-booking-date">
                    <?php echo esc_html( ucfirst( $s->frequency ) ); ?> — <?php echo esc_html( $s->session_length ); ?> min sessions
                </div>
                <div class="caswell-booking-meta">
                    Started: <?php echo esc_html( $s->start_date ); ?>
                    &nbsp;|&nbsp; Status: <span class="caswell-status caswell-status-<?php echo esc_attr( $s->status ); ?>"><?php echo esc_html( ucfirst( $s->status ) ); ?></span>
                </div>
                <?php if ( 'active' === $s->status ) : ?>
                <button class="caswell-btn caswell-btn-danger caswell-btn-sm caswell-cancel-series"
                        data-series-id="<?php echo esc_attr( $s->id ); ?>">
                    Cancel All Future
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Past Appointments -->
    <?php if ( ! empty( $past ) ) : ?>
    <section class="caswell-account-section">
        <h3>Past Appointments</h3>
        <div class="caswell-bookings-list caswell-bookings-past">
            <?php foreach ( array_slice( (array) $past, 0, 10 ) as $booking ) : ?>
            <div class="caswell-booking-card caswell-booking-past">
                <div class="caswell-booking-date">
                    <?php echo esc_html( wp_date( 'l, F j, Y', caswell_local_ts($booking->start_datetime) ) ); ?>
                </div>
                <div class="caswell-booking-time">
                    <?php echo esc_html( wp_date( 'g:i A', caswell_local_ts($booking->start_datetime) ) ); ?>
                    (<?php echo esc_html( $booking->session_length ); ?> min) —
                    <span class="caswell-status caswell-status-<?php echo esc_attr( $booking->status ); ?>"><?php echo esc_html( ucfirst( $booking->status ) ); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

<?php else : // Not logged in — show login/register tabs ?>

    <div class="caswell-auth-wrap">
        <div class="caswell-auth-tabs">
            <button class="caswell-auth-tab active" data-tab="login">Log In</button>
            <button class="caswell-auth-tab" data-tab="register">Create Account</button>
        </div>

        <!-- Login Form -->
        <div id="caswell-auth-login" class="caswell-auth-panel active">
            <form id="caswell-login-form">
                <div class="caswell-field">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" required />
                </div>
                <div class="caswell-field">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required />
                </div>
                <div class="caswell-error" id="caswell-login-error" style="display:none;"></div>
                <button type="submit" class="caswell-btn caswell-btn-primary">Log In</button>
                <p style="margin-top:12px;"><a href="#" id="caswell-forgot-link" style="font-size:0.9rem;">Forgot your password?</a></p>
            </form>

            <!-- Forgot Password (hidden by default) -->
            <div id="caswell-forgot-form-wrap" style="display:none; margin-top:16px;">
                <form id="caswell-forgot-form">
                    <p style="font-size:0.9rem;color:var(--cw-text-light);margin-bottom:12px;">Enter your email and we'll send you a link to reset your password.</p>
                    <div class="caswell-field">
                        <label for="forgot-email">Email</label>
                        <input type="email" id="forgot-email" name="email" required />
                    </div>
                    <div class="caswell-error" id="caswell-forgot-error" style="display:none;"></div>
                    <div class="caswell-notice" id="caswell-forgot-success" style="display:none;"></div>
                    <button type="submit" class="caswell-btn caswell-btn-primary">Send Reset Link</button>
                </form>
            </div>
        </div>

        <?php
        // Check if user arrived via a password reset link
        $reset_token = sanitize_text_field( $_GET['token'] ?? '' );
        $reset_uid   = absint( $_GET['uid'] ?? 0 );
        $is_reset    = ( isset( $_GET['caswell_action'] ) && 'reset_password' === $_GET['caswell_action'] && $reset_token && $reset_uid );
        ?>
        <?php if ( $is_reset ) : ?>
        <!-- Password Reset Form -->
        <div id="caswell-auth-reset" class="caswell-auth-panel" style="margin-top:20px;">
            <h3 style="color:var(--cw-primary);margin:0 0 16px;">Set New Password</h3>
            <form id="caswell-reset-form">
                <input type="hidden" name="token" value="<?php echo esc_attr( $reset_token ); ?>" />
                <input type="hidden" name="uid" value="<?php echo esc_attr( $reset_uid ); ?>" />
                <div class="caswell-field">
                    <label for="reset-password">New Password <small>(min 8 characters)</small></label>
                    <input type="password" id="reset-password" name="new_password" required minlength="8" />
                </div>
                <div class="caswell-field">
                    <label for="reset-password-confirm">Confirm Password</label>
                    <input type="password" id="reset-password-confirm" required minlength="8" />
                </div>
                <div class="caswell-error" id="caswell-reset-error" style="display:none;"></div>
                <div class="caswell-notice" id="caswell-reset-success" style="display:none;background:#d4edda;border-color:#c3e6cb;color:#155724;"></div>
                <button type="submit" class="caswell-btn caswell-btn-primary">Reset Password</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Register Form -->
        <div id="caswell-auth-register" class="caswell-auth-panel" style="display:none;">
            <form id="caswell-register-form-main">
                <div class="caswell-field">
                    <label for="reg-name">Full Name</label>
                    <input type="text" id="reg-name" name="name" required />
                </div>
                <div class="caswell-field">
                    <label for="reg-email">Email</label>
                    <input type="email" id="reg-email" name="email" required />
                </div>
                <div class="caswell-field">
                    <label for="reg-password">Password <small>(min 8 characters)</small></label>
                    <input type="password" id="reg-password" name="password" required minlength="8" />
                </div>
                <div class="caswell-error" id="caswell-reg-error" style="display:none;"></div>
                <button type="submit" class="caswell-btn caswell-btn-primary">Create Account</button>
            </form>
        </div>
    </div>

<?php endif; ?>

</div>
