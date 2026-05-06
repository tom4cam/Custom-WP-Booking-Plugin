<?php defined( 'ABSPATH' ) || exit;
// Variables available: $content (pre-interpolated body text), $booking
$site_name      = get_bloginfo( 'name' );
$site_url       = get_bloginfo( 'url' );
$reschedule_url = caswell_booking_reschedule_url( $booking );
$cancel_url     = caswell_booking_cancel_url( $booking );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $site_name ); ?> — Appointment Confirmed</title>
<style>
  body { margin:0; padding:0; background:#f5f9f7; font-family:'Segoe UI',Arial,sans-serif; color:#333; }
  .wrapper { max-width:580px; margin:32px auto; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08); }
  .header { background:#4a7c6f; color:#fff; padding:28px 32px; text-align:center; }
  .header h1 { margin:0; font-size:1.4rem; }
  .header .checkmark { font-size:2.5rem; margin-bottom:8px; display:block; }
  .body { padding:32px; }
  .body p { line-height:1.7; margin:0 0 16px; }
  .detail-box { background:#f0f7f5; border-radius:8px; padding:20px 24px; margin:20px 0; border-left:4px solid #4a7c6f; }
  .detail-box table { width:100%; border-collapse:collapse; }
  .detail-box td { padding:6px 0; }
  .detail-box td:first-child { font-weight:600; color:#4a7c6f; width:40%; }
  .footer { background:#f0f0ee; padding:20px 32px; text-align:center; font-size:0.8rem; color:#888; }
  .footer a { color:#4a7c6f; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <span class="checkmark">✓</span>
    <h1>Appointment Confirmed!</h1>
  </div>
  <div class="body">
    <p>Hi <strong><?php echo esc_html( $booking->name ); ?></strong>,</p>
    <p>Your appointment has been confirmed. We look forward to seeing you!</p>

    <div class="detail-box">
      <table>
        <tr><td>Date:</td><td><?php echo esc_html( wp_date( 'l, F j, Y', caswell_local_ts($booking->start_datetime) ) ); ?></td></tr>
        <tr><td>Time:</td><td><?php echo esc_html( wp_date( 'g:i A', caswell_local_ts($booking->start_datetime) ) ); ?> – <?php echo esc_html( wp_date( 'g:i A', caswell_local_ts($booking->end_datetime) ) ); ?></td></tr>
        <tr><td>Duration:</td><td><?php echo esc_html( $booking->session_length ); ?> minutes</td></tr>
        <?php $caswell_addr_html = caswell_business_address_html(); if ( $caswell_addr_html ) : ?>
        <tr><td>Location:</td><td><?php echo $caswell_addr_html; ?></td></tr>
        <?php endif; ?>
        <tr><td>Payment:</td><td><?php echo esc_html( ucfirst( $booking->payment_method ) ); ?> — <?php echo esc_html( ucfirst( $booking->payment_status ) ); ?></td></tr>
      </table>
    </div>

    <?php if ( $content ) : ?>
    <p><?php echo nl2br( esc_html( $content ) ); ?></p>
    <?php endif; ?>

    <p style="margin-top:24px;font-weight:600">Need to make a change?</p>
    <?php $venmo_link = caswell_venmo_payment_link( $booking ); ?>
    <table role="presentation" style="margin:8px 0 16px;border-collapse:separate;border-spacing:8px 0">
      <tr>
        <?php if ( $venmo_link ) : $venmo_amount = caswell_get_option( "venmo_price_{$booking->session_length}", '' ); ?>
        <td><a href="<?php echo esc_url( $venmo_link ); ?>" style="display:inline-block;background:#3D95CE;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600;font-size:0.95rem">Pay $<?php echo esc_html( $venmo_amount ); ?> with Venmo</a></td>
        <?php endif; ?>
        <td><a href="<?php echo esc_url( $reschedule_url ); ?>" style="display:inline-block;background:#4a7c6f;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600;font-size:0.95rem">Reschedule</a></td>
        <td><a href="<?php echo esc_url( $cancel_url ); ?>" style="display:inline-block;background:#fff;color:#c0392b;text-decoration:none;padding:10px 18px;border-radius:6px;font-weight:600;font-size:0.95rem;border:1px solid #c0392b">Cancel</a></td>
      </tr>
    </table>
    <p style="font-size:0.85rem;color:#666;margin:8px 0 0">
      Cancellations made <strong>less than 24 hours</strong> before the appointment are non-refundable.
    </p>

    <a href="<?php echo esc_url( $site_url ); ?>" style="display:block;width:fit-content;margin:24px auto;background:#4a7c6f;color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;text-align:center;">Visit Our Website</a>
  </div>
  <div class="footer">
    <p>&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved.</p>
    <p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_url ); ?></a></p>
  </div>
</div>
</body>
</html>
