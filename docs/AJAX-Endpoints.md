# AJAX Endpoints

All endpoints use WordPress's `wp_ajax_` and `wp_ajax_nopriv_` hooks. Public and authenticated endpoints require the `caswell_booking_nonce` nonce. Admin endpoints use `caswell_admin_nonce`.

## Public (No Auth Required)

These actions are available to both logged-in and anonymous users.

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_get_slots` | `Caswell_Booking_Handler::ajax_get_slots` | Get available time slots for a date |
| `caswell_submit_booking` | `Caswell_Booking_Handler::ajax_submit_booking` | Create a new booking |
| `caswell_register` | `Caswell_Auth::ajax_register` | Create a client account |
| `caswell_login` | `Caswell_Auth::ajax_login` | Log in |
| `caswell_forgot_password` | `Caswell_Auth::ajax_forgot_password` | Send password reset email |
| `caswell_reset_password` | `Caswell_Auth::ajax_reset_password` | Reset password with token |
| `caswell_verify_email` | `Caswell_Auth::ajax_verify_email` | Resend verification email |

**Related pages:** [[Google-Calendar]] (slot availability), [[Payments]] (booking submission triggers payment), [[Authentication]] (register/login/reset flows)

## Authenticated

These actions require a logged-in user.

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_logout` | `Caswell_Auth::ajax_logout` | Log out |
| `caswell_cancel_booking` | `Caswell_Booking_Handler::ajax_cancel_booking` | Cancel a single booking |
| `caswell_cancel_series` | `Caswell_Booking_Handler::ajax_cancel_series` | Cancel all future bookings in a series |

**Related pages:** [[Scheduling]] (series cancellation), [[Payments]] (refunds on cancellation)

## Admin Only

These actions require the `manage_options` capability.

| Action | Handler | Description |
|--------|---------|-------------|
| `caswell_add_block` | `Caswell_Booking_Handler::ajax_add_block` | Add a time block |
| `caswell_delete_block` | `Caswell_Booking_Handler::ajax_delete_block` | Delete a time block |
| `caswell_toggle_logging` | `Caswell_Admin::ajax_toggle_logging` | Enable/disable debug logging |

**Related pages:** [[Database-Schema]] (`wp_caswell_blocks` table), [[Admin-Settings]] (logging toggle)
