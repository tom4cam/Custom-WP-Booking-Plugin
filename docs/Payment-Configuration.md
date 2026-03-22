# Payment Configuration

The plugin supports two payment methods: **Square** (card payments) and **Venmo** (manual transfer).

## Square Setup

### Prerequisites
- A [Square Developer](https://developer.squareup.com/) account
- SSL certificate on your WordPress site (required for PCI compliance)

### Configuration

1. Create an application in the [Square Developer Dashboard](https://developer.squareup.com/apps)
2. Copy your **Application ID** and **Access Token**
3. Find your **Location ID** in the Square Dashboard under Locations
4. Go to **Settings → Caswell Booking → Square** and enter:

| Field | Description |
|-------|-------------|
| Application ID | Square app ID (starts with `sandbox-sq0...` or `sq0...`) |
| Location ID | Your Square location ID |
| Access Token | Encrypted on save |
| Sandbox Mode | Enable for testing (uses Square sandbox environment) |

### Sandbox Testing

With sandbox mode enabled:
- Uses `sandbox.web.squarecdn.com` SDK
- Payments go to `connect.squareupsandbox.com`
- Use Square's [test card numbers](https://developer.squareup.com/docs/testing/test-values)

### Pricing

Session prices are configured in **Settings → Venmo** tab (shared pricing for both methods) and **Settings → Business Info → Service Pricing** (for homepage display).

## Venmo Setup

Venmo is a "pay later" option — the booking is created with `payment_status = 'unpaid'`, and the client is shown Venmo details after confirmation.

### Configuration

Go to **Settings → Caswell Booking → Venmo**:

| Field | Description |
|-------|-------------|
| Venmo Username | Your @username (shown to clients) |
| Price — 30/60/90 min | Amount shown in the Venmo deep link |

### Client Flow

1. Client selects "Pay via Venmo" during booking
2. Booking is created as unpaid
3. Confirmation screen shows the Venmo username and a deep link to the Venmo app
4. Client sends payment manually

## Refunds

Square payments can be refunded programmatically via `Caswell_Booking_Handler::refund_square()`. The payment ID is stored with each booking for this purpose.
