<?php defined( 'ABSPATH' ) || exit; ?>

<div id="caswell-booking-wrap" class="caswell-booking-wrap">

    <?php
    $current_user    = wp_get_current_user();
    /**
     * Only prefill the booking form for actual client accounts — never for
     * site admins. An admin viewing the booking page (e.g. testing the
     * widget) was getting their own contact info dropped into the form,
     * which is confusing at best and wrong at worst.
     */
    $is_logged_in    = is_user_logged_in() && ! current_user_can( 'manage_options' );
    $session_lengths = caswell_enabled_session_lengths();
    $default_length  = (int) caswell_get_option( 'default_session_length', 60 );

    /**
     * Reschedule mode — set when the booking page is loaded from a signed
     * "Reschedule" link in a confirmation email. The page renders a banner
     * confirming which booking is being rescheduled, and the booking handler
     * uses the (validated) booking_id+token to cancel the original after
     * the new booking is created.
     */
    $reschedule_booking = null;
    $reschedule_token   = '';
    if ( isset( $_GET['caswell_action'], $_GET['b'], $_GET['t'] )
         && $_GET['caswell_action'] === 'reschedule' ) {
        $rb = Caswell_Booking_DB::get_booking( absint( $_GET['b'] ) );
        if ( $rb && caswell_verify_booking_manage_token( $rb, sanitize_text_field( $_GET['t'] ) )
             && $rb->status !== 'cancelled' ) {
            $reschedule_booking = $rb;
            $reschedule_token   = sanitize_text_field( $_GET['t'] );
        }
    }
    ?>

    <?php if ( $reschedule_booking ) : ?>
        <div class="caswell-reschedule-banner">
            <strong>Rescheduling your appointment.</strong><br>
            Your existing booking on
            <em><?php echo esc_html( wp_date( 'l, F j \a\t g:i A', caswell_local_ts($reschedule_booking->start_datetime) ) ); ?></em>
            will be cancelled when you confirm a new time below. No new payment is required —
            your existing payment carries over.
        </div>
        <input type="hidden" id="caswell-reschedule-for" value="<?php echo esc_attr( $reschedule_booking->id ); ?>">
        <input type="hidden" id="caswell-reschedule-token" value="<?php echo esc_attr( $reschedule_token ); ?>">
    <?php endif; ?>

    <!-- Step 1: Pick Date & Length -->
    <div id="caswell-step-1" class="caswell-step caswell-step-active">
        <h3 class="caswell-step-title">Choose a Date &amp; Session Length</h3>

        <div class="caswell-date-picker-wrap">
            <label for="caswell-date">Date</label>
            <input type="date" id="caswell-date" name="date"
                   min="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>"
                   max="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+90 days' ) ) ); ?>" />
        </div>

        <div class="caswell-length-wrap">
            <label>Session Length</label>
            <div class="caswell-length-options">
                <?php foreach ( $session_lengths as $len ) :
                    $price = caswell_get_option( "service_price_{$len}", '' );
                ?>
                <label class="caswell-length-btn <?php echo $len === $default_length ? 'selected' : ''; ?>">
                    <input type="radio" name="session_length" value="<?php echo esc_attr( $len ); ?>"
                           <?php checked( $len, $default_length ); ?> />
                    <?php echo esc_html( $len ); ?> min
                    <?php if ( $price ) : ?><span class="caswell-price">$<?php echo esc_html( $price ); ?></span><?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button id="caswell-find-slots" class="caswell-btn caswell-btn-primary" disabled>
            Find Available Times
        </button>
    </div>

    <!-- Step 2: Pick Time Slot -->
    <div id="caswell-step-2" class="caswell-step" style="display:none;">
        <h3 class="caswell-step-title">Choose a Time</h3>
        <p id="caswell-slot-date-label" class="caswell-slot-date-label"></p>
        <div id="caswell-slots-loading" class="caswell-loading" style="display:none;">Loading available times…</div>
        <div id="caswell-slots-grid" class="caswell-slots-grid"></div>
        <div id="caswell-no-slots" class="caswell-notice" style="display:none;">
            No available times found for this date. Please try another day.
        </div>
        <button class="caswell-btn caswell-btn-secondary" id="caswell-back-to-1">← Back</button>
    </div>

    <!-- Step 3: Your Info & Payment -->
    <div id="caswell-step-3" class="caswell-step" style="display:none;">
        <h3 class="caswell-step-title">Your Information</h3>

        <form id="caswell-booking-form" novalidate>
            <?php wp_nonce_field( 'caswell_booking_nonce', 'caswell_nonce_field' ); ?>

            <div class="caswell-field-row">
                <div class="caswell-field">
                    <label for="caswell-name">Full Name <span class="required">*</span></label>
                    <input type="text" id="caswell-name" name="name" required
                           value="<?php echo $is_logged_in ? esc_attr( $current_user->display_name ) : ''; ?>" />
                </div>
            </div>

            <div class="caswell-field-row caswell-field-row-2">
                <div class="caswell-field">
                    <label for="caswell-email">Email <span class="required">*</span></label>
                    <input type="email" id="caswell-email" name="email" required
                           value="<?php echo $is_logged_in ? esc_attr( $current_user->user_email ) : ''; ?>" />
                </div>
                <div class="caswell-field">
                    <label for="caswell-phone">Phone <span class="required">*</span></label>
                    <input type="tel" id="caswell-phone" name="phone" required
                           value="<?php echo $is_logged_in ? esc_attr( get_user_meta( $current_user->ID, 'caswell_phone', true ) ) : ''; ?>" />
                </div>
            </div>

            <div class="caswell-field">
                <label for="caswell-notes">Notes (optional)</label>
                <textarea id="caswell-notes" name="notes" rows="3" placeholder="Any special requests or areas to focus on…"></textarea>
            </div>

            <!-- Recurring options -->
            <div class="caswell-recurring-toggle">
                <label>
                    <input type="checkbox" id="caswell-recurring-check" name="recurring" />
                    Make this a recurring appointment
                </label>
            </div>

            <div id="caswell-recurring-options" style="display:none;" class="caswell-recurring-options">
                <div class="caswell-field-row caswell-field-row-2">
                    <div class="caswell-field">
                        <label for="caswell-rec-freq">Frequency</label>
                        <select id="caswell-rec-freq" name="rec_frequency">
                            <option value="weekly">Weekly</option>
                            <option value="biweekly">Bi-weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    <div class="caswell-field">
                        <label for="caswell-rec-end">End Date <small>(optional)</small></label>
                        <input type="date" id="caswell-rec-end" name="rec_end_date" />
                    </div>
                </div>
                <div class="caswell-field" style="max-width:200px;">
                    <label for="caswell-rec-occ"># of Appointments <small>(max 12)</small></label>
                    <input type="number" id="caswell-rec-occ" name="rec_occurrences" min="1" max="12" value="12" />
                </div>
                <p class="caswell-recurring-help" style="margin:6px 0 0;font-size:0.86rem;color:#666">
                    Recurring bookings are limited to 12 appointments at a time. To go further, book another series after the last one.
                </p>

                <!-- Live conflict check — populated by booking.js whenever
                     date / time / length / frequency / count / end-date change. -->
                <div id="caswell-rec-conflicts" class="caswell-rec-conflicts" hidden></div>
            </div>

            <!-- Payment method -->
            <div class="caswell-payment-section">
                <h4>Payment</h4>
                <div class="caswell-payment-methods">
                    <label class="caswell-payment-opt selected">
                        <input type="radio" name="payment_method" value="square" checked /> Pay by Card
                    </label>
                    <label class="caswell-payment-opt">
                        <input type="radio" name="payment_method" value="venmo" /> Pay via Venmo
                    </label>
                </div>

                <div id="caswell-square-form" class="caswell-square-form">
                    <div id="card-container"></div>
                    <div id="caswell-square-error" class="caswell-error" style="display:none;"></div>
                </div>

                <div id="caswell-venmo-info" class="caswell-venmo-info" style="display:none;">
                    <p>You'll be shown Venmo details after booking to complete payment.</p>
                </div>
            </div>

            <?php if ( ! $reschedule_booking ) : ?>
            <div class="caswell-consent-section">
                <label class="caswell-consent-row">
                    <input type="checkbox" name="email_consent" id="caswell-email-consent" required />
                    <span><?php echo esc_html( caswell_render_consent_text( 'email' ) ); ?></span>
                </label>
                <label class="caswell-consent-row">
                    <input type="checkbox" name="sms_consent" id="caswell-sms-consent" required />
                    <span><?php echo esc_html( caswell_render_consent_text( 'sms' ) ); ?></span>
                </label>
            </div>
            <?php endif; ?>

            <div id="caswell-form-error" class="caswell-error" style="display:none;"></div>

            <div class="caswell-form-actions">
                <button type="button" class="caswell-btn caswell-btn-secondary" id="caswell-back-to-2">← Back</button>
                <button type="submit" class="caswell-btn caswell-btn-primary" id="caswell-submit-btn">
                    Confirm Booking
                </button>
            </div>

            <p class="caswell-opt-in-notice">
                Cancellations made less than 24 hours before the appointment are non-refundable.
            </p>
        </form>
    </div>

    <!-- Step 4: Confirmation -->
    <div id="caswell-step-4" class="caswell-step caswell-confirmation" style="display:none;">
        <div class="caswell-confirmation-icon">✓</div>
        <h3>Booking Confirmed!</h3>
        <p id="caswell-confirm-details"></p>

        <div id="caswell-venmo-section" class="caswell-venmo-section" style="display:none;">
            <hr />
            <h4>Complete Your Payment</h4>
            <p id="caswell-venmo-message"></p>
            <a id="caswell-venmo-link" href="#" class="caswell-btn caswell-btn-venmo" target="_blank">Open Venmo</a>
        </div>

        <?php if ( ! $is_logged_in ) : ?>
        <div class="caswell-post-booking-account">
            <hr />
            <p>Create a free account to manage your appointments.</p>
            <button class="caswell-btn caswell-btn-secondary" id="caswell-show-register">Create Account</button>
            <div id="caswell-register-form-wrap" style="display:none;">
                <!-- Registration form injected via account shortcode JS logic -->
                <form id="caswell-quick-register">
                    <input type="email" name="email" id="caswell-reg-email" placeholder="Email" />
                    <input type="password" name="password" placeholder="Password (min 8 characters)" />
                    <button type="submit" class="caswell-btn caswell-btn-primary">Create Account</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <button class="caswell-btn caswell-btn-secondary" id="caswell-book-another" style="margin-top:20px;">Book Another Appointment</button>
    </div>

    <!-- Hidden state fields -->
    <input type="hidden" id="caswell-selected-start-ts" value="" />
    <input type="hidden" id="caswell-selected-end-ts" value="" />

</div>
