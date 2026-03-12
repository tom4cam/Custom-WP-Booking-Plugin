/* global jQuery, caswellData, Square */
(function ($) {
    'use strict';

    /* ── State ───────────────────────────────────────────────────────── */
    let squareCard = null;
    let squarePayments = null;
    let selectedStartTs = null;
    let selectedEndTs = null;
    let selectedDate = '';
    let selectedLength = 60;

    /* ── Init ────────────────────────────────────────────────────────── */
    $(function () {
        if ($('#caswell-booking-wrap').length) {
            initBookingWidget();
        }
        if ($('#caswell-account-wrap').length) {
            initAccountWidget();
        }
    });

    /* ═══════════════════════════════════════════════════════════════════
       BOOKING WIDGET
       ═══════════════════════════════════════════════════════════════════ */

    function initBookingWidget() {
        // Session length buttons
        $('.caswell-length-btn').on('click', function () {
            $('.caswell-length-btn').removeClass('selected');
            $(this).addClass('selected');
            selectedLength = parseInt($(this).find('input').val(), 10);
            checkStep1Ready();
        });

        // Date change
        $('#caswell-date').on('change', function () {
            selectedDate = $(this).val();
            checkStep1Ready();
        });

        // Find slots
        $('#caswell-find-slots').on('click', fetchSlots);

        // Back buttons
        $('#caswell-back-to-1').on('click', function () {
            showStep(1);
        });
        $('#caswell-back-to-2').on('click', function () {
            showStep(2);
        });

        // Payment method toggle
        $(document).on('change', 'input[name="payment_method"]', function () {
            $('.caswell-payment-opt').removeClass('selected');
            $(this).closest('.caswell-payment-opt').addClass('selected');
            const method = $(this).val();
            if (method === 'square') {
                $('#caswell-square-form').show();
                $('#caswell-venmo-info').hide();
            } else {
                $('#caswell-square-form').hide();
                $('#caswell-venmo-info').show();
            }
        });

        // Recurring toggle
        $('#caswell-recurring-check').on('change', function () {
            if ($(this).is(':checked')) {
                $('#caswell-recurring-options').slideDown(200);
            } else {
                $('#caswell-recurring-options').slideUp(200);
            }
        });

        // Form submit
        $('#caswell-booking-form').on('submit', function (e) {
            e.preventDefault();
            submitBooking();
        });

        // Book another
        $('#caswell-book-another').on('click', function () {
            resetWidget();
        });

        // Post-booking quick register
        $('#caswell-show-register').on('click', function () {
            $('#caswell-register-form-wrap').slideToggle(200);
        });
        $(document).on('submit', '#caswell-quick-register', function (e) {
            e.preventDefault();
            const $form = $(this);
            ajaxRequest('caswell_register', {
                name: $('#caswell-name').val(),
                email: $form.find('[name="email"]').val(),
                password: $form.find('[name="password"]').val(),
            }, function () {
                $('#caswell-register-form-wrap').html('<p style="color:green;font-weight:600;">Account created!</p>');
            }, function (msg) {
                $form.prepend('<p class="caswell-error">' + escHtml(msg) + '</p>');
            });
        });

        // Init Square if configured
        if (caswellData.square_app_id && typeof Square !== 'undefined') {
            initSquare();
        }
    }

    function checkStep1Ready() {
        const ready = selectedDate && selectedLength;
        $('#caswell-find-slots').prop('disabled', !ready);
    }

    function showStep(n) {
        $('.caswell-step').hide();
        $('#caswell-step-' + n).show();
        // Scroll to top of widget
        const $wrap = $('#caswell-booking-wrap');
        if ($wrap.length) {
            $('html,body').animate({ scrollTop: $wrap.offset().top - 80 }, 300);
        }
    }

    /* ── Fetch available slots ───────────────────────────────────────── */
    function fetchSlots() {
        showStep(2);
        $('#caswell-no-slots').hide();
        $('#caswell-slots-grid').empty();
        $('#caswell-slots-loading').show();
        $('#caswell-slot-date-label').text(formatDisplayDate(selectedDate));

        ajaxRequest('caswell_get_slots', {
            date: selectedDate,
            session_length: selectedLength,
        }, function (slots) {
            $('#caswell-slots-loading').hide();
            if (!slots || slots.length === 0) {
                $('#caswell-no-slots').show();
                return;
            }
            renderSlots(slots);
        }, function (msg) {
            $('#caswell-slots-loading').hide();
            $('#caswell-no-slots').text(msg).show();
        });
    }

    function renderSlots(slots) {
        const $grid = $('#caswell-slots-grid').empty();

        // Show timezone indicator
        var tzName = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        if (tzName) {
            var $tz = $('<div>', {
                class: 'caswell-tz-label',
                text: 'Times shown in ' + tzName,
            });
            $grid.before($tz);
        }

        slots.forEach(function (slot) {
            const $btn = $('<button>', {
                type: 'button',
                class: 'caswell-slot-btn',
                text: slot.start_label,
            });
            $btn.data('slot', slot);
            $btn.on('click', function () {
                $('.caswell-slot-btn').removeClass('selected');
                $(this).addClass('selected');
                selectedStartTs = slot.start_ts;
                selectedEndTs   = slot.end_ts;
                $('#caswell-selected-start-ts').val(slot.start_ts);
                $('#caswell-selected-end-ts').val(slot.end_ts);
                setTimeout(function () { showStep(3); }, 150);
            });
            $grid.append($btn);
        });
    }

    /* ── Square card form ────────────────────────────────────────────── */
    async function initSquare() {
        try {
            squarePayments = Square.payments(
                caswellData.square_app_id,
                caswellData.square_sandbox ? 'sandbox' : undefined
            );
            squareCard = await squarePayments.card();
            await squareCard.attach('#card-container');
        } catch (e) {
            console.error('Square init error:', e);
            $('#caswell-square-error').text('Card form unavailable. Please refresh or use Venmo.').show();
        }
    }

    /* ── Submit booking ──────────────────────────────────────────────── */
    async function submitBooking() {
        const $btn = $('#caswell-submit-btn');
        const $err = $('#caswell-form-error');
        $err.hide();
        $btn.prop('disabled', true).text('Processing\u2026');

        // Validate
        const name  = $.trim($('#caswell-name').val());
        const email = $.trim($('#caswell-email').val());
        if (!name || !email) {
            showFormError('Please fill in your name and email.');
            $btn.prop('disabled', false).text('Confirm Booking');
            return;
        }
        if (!selectedStartTs) {
            showFormError('Please select a time slot.');
            $btn.prop('disabled', false).text('Confirm Booking');
            return;
        }

        const paymentMethod = $('input[name="payment_method"]:checked').val();
        let squareToken = '';

        if (paymentMethod === 'square') {
            if (!squareCard) {
                showFormError('Card form not loaded. Please refresh the page or switch to Venmo.');
                $btn.prop('disabled', false).text('Confirm Booking');
                return;
            }
            try {
                const result = await squareCard.tokenize();
                if (result.status === 'OK') {
                    squareToken = result.token;
                } else {
                    const errs = (result.errors || []).map(function(e) { return e.message; }).join(' ');
                    showFormError(errs || 'Card tokenization failed.');
                    $btn.prop('disabled', false).text('Confirm Booking');
                    return;
                }
            } catch (e) {
                showFormError('Card processing error. Please try again.');
                $btn.prop('disabled', false).text('Confirm Booking');
                return;
            }
        }

        const data = {
            name:            name,
            email:           email,
            phone:           $('#caswell-phone').val(),
            session_length:  selectedLength,
            start_ts:        selectedStartTs,
            payment_method:  paymentMethod,
            square_token:    squareToken,
            notes:           $('#caswell-notes').val(),
            recurring:       $('#caswell-recurring-check').is(':checked') ? 1 : 0,
            rec_frequency:   $('#caswell-rec-freq').val(),
            rec_end_date:    $('#caswell-rec-end').val(),
            rec_occurrences: $('#caswell-rec-occ').val(),
        };

        ajaxRequest('caswell_submit_booking', data, function (result) {
            showConfirmation(result);
            $btn.prop('disabled', false).text('Confirm Booking');
        }, function (msg) {
            showFormError(msg);
            $btn.prop('disabled', false).text('Confirm Booking');
        });
    }

    function showFormError(msg) {
        $('#caswell-form-error').text(msg).show();
        $('html,body').animate({ scrollTop: $('#caswell-form-error').offset().top - 100 }, 200);
    }

    /* ── Confirmation ────────────────────────────────────────────────── */
    function showConfirmation(result) {
        $('#caswell-confirm-details').text('Your appointment on ' + result.start_label + ' is confirmed. A confirmation email and SMS have been sent.');

        if (result.venmo_user && $('input[name="payment_method"]:checked').val() === 'venmo') {
            const price = result.venmo_price ? '$' + result.venmo_price : '';
            $('#caswell-venmo-message').text(
                'Please send ' + price + ' to @' + result.venmo_user + ' on Venmo to complete your payment.'
            );
            if (result.venmo_link) {
                $('#caswell-venmo-link').attr('href', result.venmo_link);
            }
            $('#caswell-venmo-section').show();
        }

        // Pre-fill quick register email
        $('#caswell-reg-email').val($('#caswell-email').val());

        showStep(4);
    }

    function resetWidget() {
        selectedStartTs = null;
        selectedEndTs   = null;
        selectedDate    = '';
        $('#caswell-date').val('');
        $('#caswell-slots-grid').empty();
        $('#caswell-no-slots').hide();
        $('#caswell-venmo-section').hide();
        $('#caswell-form-error').hide();
        // Remove timezone label if present
        $('.caswell-tz-label').remove();
        showStep(1);
    }

    /* ═══════════════════════════════════════════════════════════════════
       ACCOUNT WIDGET
       ═══════════════════════════════════════════════════════════════════ */

    function initAccountWidget() {
        // Auth tabs
        $(document).on('click', '.caswell-auth-tab', function () {
            const tab = $(this).data('tab');
            $('.caswell-auth-tab').removeClass('active');
            $(this).addClass('active');
            $('.caswell-auth-panel').hide().removeClass('active');
            $('#caswell-auth-' + tab).show().addClass('active');
        });

        // Login form
        $(document).on('submit', '#caswell-login-form', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $err  = $('#caswell-login-error');
            $err.hide();
            ajaxRequest('caswell_login', {
                email:    $form.find('[name="email"]').val(),
                password: $form.find('[name="password"]').val(),
            }, function () {
                window.location.reload();
            }, function (msg) {
                $err.text(msg).show();
            });
        });

        // Register form
        $(document).on('submit', '#caswell-register-form-main', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $err  = $('#caswell-reg-error');
            $err.hide();
            ajaxRequest('caswell_register', {
                name:     $form.find('[name="name"]').val(),
                email:    $form.find('[name="email"]').val(),
                password: $form.find('[name="password"]').val(),
            }, function () {
                window.location.reload();
            }, function (msg) {
                $err.text(msg).show();
            });
        });

        // Logout
        $(document).on('click', '#caswell-logout-btn', function () {
            ajaxRequest('caswell_logout', {}, function () {
                window.location.reload();
            });
        });

        // Forgot password toggle
        $(document).on('click', '#caswell-forgot-link', function (e) {
            e.preventDefault();
            $('#caswell-forgot-form-wrap').slideToggle(200);
        });

        // Forgot password form
        $(document).on('submit', '#caswell-forgot-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $err  = $('#caswell-forgot-error');
            var $succ = $('#caswell-forgot-success');
            $err.hide();
            $succ.hide();

            ajaxRequest('caswell_forgot_password', {
                email: $form.find('[name="email"]').val(),
            }, function (result) {
                $succ.text(result.message || 'Check your email for a reset link.').show();
                $form.find('button').prop('disabled', true);
            }, function (msg) {
                $err.text(msg).show();
            });
        });

        // Reset password form
        $(document).on('submit', '#caswell-reset-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $err  = $('#caswell-reset-error');
            var $succ = $('#caswell-reset-success');
            $err.hide();
            $succ.hide();

            var newPass    = $form.find('[name="new_password"]').val();
            var confirmPass = $('#reset-password-confirm').val();

            if (newPass !== confirmPass) {
                $err.text('Passwords do not match.').show();
                return;
            }
            if (newPass.length < 8) {
                $err.text('Password must be at least 8 characters.').show();
                return;
            }

            ajaxRequest('caswell_reset_password', {
                token:        $form.find('[name="token"]').val(),
                uid:          $form.find('[name="uid"]').val(),
                new_password: newPass,
            }, function (result) {
                $succ.text(result.message || 'Password reset! You can now log in.').show();
                $form.find('button').prop('disabled', true);
            }, function (msg) {
                $err.text(msg).show();
            });
        });

        // Cancel single booking
        $(document).on('click', '.caswell-cancel-booking', function () {
            const bookingId = $(this).data('booking-id');
            if (!confirm('Cancel this appointment?')) return;
            const $card = $(this).closest('.caswell-booking-card');
            ajaxRequest('caswell_cancel_booking', { booking_id: bookingId }, function () {
                $card.find('.caswell-status').text('Cancelled').removeClass().addClass('caswell-status caswell-status-cancelled');
                $card.find('.caswell-cancel-booking').remove();
            }, function (msg) {
                alert('Error: ' + msg);
            });
        });

        // Cancel series
        $(document).on('click', '.caswell-cancel-series', function () {
            const seriesId = $(this).data('series-id');
            if (!confirm('Cancel all future appointments in this series?')) return;
            const $card = $(this).closest('.caswell-booking-card');
            ajaxRequest('caswell_cancel_series', { series_id: seriesId }, function () {
                $card.find('.caswell-status').text('Cancelled').removeClass().addClass('caswell-status caswell-status-cancelled');
                $card.find('.caswell-cancel-series').remove();
            }, function (msg) {
                alert('Error: ' + msg);
            });
        });

        // Auto-show reset form if present
        if ($('#caswell-auth-reset').length) {
            $('.caswell-auth-panel').hide();
            $('#caswell-auth-reset').show();
            $('.caswell-auth-tabs').hide();
        }
    }

    /* ── Helpers ─────────────────────────────────────────────────────── */

    function ajaxRequest(action, data, onSuccess, onError) {
        $.post(caswellData.ajax_url, Object.assign({
            action: action,
            nonce:  caswellData.nonce,
        }, data), function (response) {
            if (response.success) {
                if (typeof onSuccess === 'function') onSuccess(response.data);
            } else {
                const msg = (response.data && typeof response.data === 'string')
                    ? response.data : 'An error occurred.';
                if (typeof onError === 'function') onError(msg);
            }
        }).fail(function () {
            if (typeof onError === 'function') onError('Network error. Please try again.');
        });
    }

    function formatDisplayDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

}(jQuery));
