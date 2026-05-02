<?php
/**
 * Reschedule notification email template.
 *
 * @var object $booking
 * @var int    $old_ts
 * @var int    $new_ts
 * @var int    $end_ts
 * @var string $admin_message
 * @var string $site_name
 */
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html><body style="font-family:Helvetica,Arial,sans-serif;color:#1a1a1a;max-width:600px;margin:0 auto;padding:24px;line-height:1.55">
  <h2 style="margin:0 0 14px;font-weight:500">Hi <?php echo esc_html( $booking->name ); ?>,</h2>
  <p>Your appointment has been <strong>rescheduled</strong>.</p>

  <table style="width:100%;border-collapse:collapse;margin:18px 0;font-size:14px">
    <tr>
      <td style="padding:10px 12px;background:#fbeaea;color:#842029;width:140px;border-radius:6px 0 0 6px"><strong>Was</strong></td>
      <td style="padding:10px 12px;background:#fbeaea;color:#842029;border-radius:0 6px 6px 0">
        <?php echo esc_html( wp_date( 'l, F j, Y', $old_ts ) ); ?> at <?php echo esc_html( wp_date( 'g:i A', $old_ts ) ); ?>
      </td>
    </tr>
    <tr><td colspan="2" style="height:6px"></td></tr>
    <tr>
      <td style="padding:10px 12px;background:#e6f3ea;color:#1d6c34;border-radius:6px 0 0 6px"><strong>Now</strong></td>
      <td style="padding:10px 12px;background:#e6f3ea;color:#1d6c34;border-radius:0 6px 6px 0">
        <?php echo esc_html( wp_date( 'l, F j, Y', $new_ts ) ); ?> at <?php echo esc_html( wp_date( 'g:i A', $new_ts ) ); ?> – <?php echo esc_html( wp_date( 'g:i A', $end_ts ) ); ?>
      </td>
    </tr>
  </table>

  <?php if ( $admin_message ): ?>
    <div style="white-space:pre-wrap;margin:18px 0;padding:14px 16px;background:#f5f5f5;border-radius:8px;font-size:14px"><?php echo esc_html( $admin_message ); ?></div>
  <?php endif; ?>

  <p>If this new time doesn't work, please reply to this email and we'll find another option.</p>

  <p style="color:#666;font-size:12px;margin-top:24px">— <?php echo esc_html( $site_name ); ?></p>
</body></html>
