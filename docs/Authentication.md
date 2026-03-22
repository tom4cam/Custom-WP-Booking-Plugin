# Authentication

Client authentication is handled by the `Caswell_Auth` class in `includes/class-auth.php`. The plugin implements its own registration, login, password reset, and email verification flows via [[AJAX-Endpoints]].

## Custom WordPress Role

The plugin registers a custom WordPress role: **`caswell_client`**. This role has read-only capabilities, giving clients just enough access to manage their own bookings through the `[caswell_account]` shortcode without access to the WordPress admin dashboard.

## Password Reset

1. Client requests a reset via the `caswell_forgot_password` AJAX action.
2. A time-limited token is generated, hashed with SHA-256, and stored in user meta.
3. An email containing a reset link is sent to the client.
4. The client submits their new password with the token via the `caswell_reset_password` action.
5. The token and expiry are validated before the password is updated.

### Token Storage

| Meta Key | Purpose |
|----------|---------|
| `_caswell_reset_token` | SHA-256 hash of the reset token |
| `_caswell_reset_expiry` | Expiration timestamp for the reset token |

## Email Verification

Email verification follows a similar token-based flow:

1. A verification token is generated and stored in user meta.
2. A verification email is sent to the client.
3. The client clicks the link (or requests a resend via `caswell_verify_email`).
4. The token is validated and the email is marked as verified.

### Token Storage

| Meta Key | Purpose |
|----------|---------|
| `_caswell_verify_token` | SHA-256 hash of the verification token |
| `_caswell_verify_expiry` | Expiration timestamp for the verification token |

## Related Pages

- [[AJAX-Endpoints]] — All auth-related AJAX actions
- [[Admin-Settings]] — Email configuration for reset and verification messages
