# Payments

The plugin supports two payment methods: **Square** (credit card) and **Venmo**. Payment processing is handled in `Caswell_Booking_Handler` (see [[Architecture]]).

## Square

### Flow

1. **Client-side:** The Square Web Payments SDK tokenizes the client's card information in the browser. No raw card data reaches the server.
2. **Server-side:** The token is sent to the Square `v2/payments` endpoint to charge the card.
3. **Storage:** The Square payment ID is stored in the `square_payment_id` column of the booking record (see [[Database-Schema]]).
4. **Refunds:** Available via `Caswell_Booking_Handler::refund_square()`, using the stored payment ID.

### Configuration

Square credentials (App ID, Location ID, Access Token) and sandbox mode toggle are managed in [[Admin-Settings]].

## Venmo

### Flow

1. Client selects Venmo as their payment method during booking.
2. The booking is created with `payment_status = 'unpaid'`.
3. After confirmation, the client is shown the practitioner's Venmo username and a deep link to complete payment outside the plugin.

### Configuration

The Venmo username and per-length pricing are configured in [[Admin-Settings]].

## Booking Status

The `payment_status` field on each booking tracks whether payment has been received:

- `paid` — Payment completed (Square transactions)
- `unpaid` — Payment pending (Venmo bookings, or failed transactions)

See [[Database-Schema]] for the full bookings table structure and [[AJAX-Endpoints]] for the booking submission and cancellation actions.
