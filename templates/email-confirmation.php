<?php defined( 'ABSPATH' ) || exit;
// Variables available: $content (pre-interpolated body text), $booking
$site_name = get_bloginfo( 'name' );
$site_url  = get_bloginfo( 'url' );
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
  .cta-btn { display:block; width:fit-content; margin:24px auto; background:#4a7c6f; color:#fff; text-decoration:none; padding:14px 32px; border-radius:8px; font-weight:600; text-align:center; }
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
    <p>Your massage therapy appointment has been confirmed. We look forward to seeing you!</p>

    <div class="detail-box">
      <table>
        <tr><td>Date:</td><td><?php echo esc_html( wp_date( 'l, F j, Y', strtotime( $booking->start_datetime ) ) ); ?></td></tr>
        <tr><td>Time:</td><td><?php echo esc_html( wp_date( 'g:i A', strtotime( $booking->start_datetime ) ) ); ?> – <?php echo esc_html( wp_date( 'g:i A', strtotime( $booking->end_datetime ) ) ); ?></td></tr>
        <tr><td>Duration:</td><td><?php echo esc_html( $booking->session_length ); ?> minutes</td></tr>
        <tr><td>Payment:</td><td><?php echo esc_html( ucfirst( $booking->payment_method ) ); ?> — <?php echo esc_html( ucfirst( $booking->payment_status ) ); ?></td></tr>
      </table>
    </div>

    <?php if ( $content ) : ?>
    <p><?php echo nl2br( esc_html( $content ) ); ?></p>
    <?php endif; ?>

    <p>If you need to reschedule or cancel, please contact us as soon as possible.</p>

    <a href="<?php echo esc_url( $site_url ); ?>" class="cta-btn">Visit Our Website</a>
  </div>
  <div class="footer">
    <p>&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved.</p>
    <p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_url ); ?></a></p>
  </div>
</div>
</body>
</html>
