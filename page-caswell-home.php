<?php
/**
 * Template Name: Caswell Home Page
 * The single-page business website for Caswell Therapy LMT.
 */
defined( 'ABSPATH' ) || exit;

$o               = get_option( 'caswell_settings', [] );
$phone           = $o['business_phone']   ?? '';
$email_addr      = $o['business_email']   ?? '';
$address         = $o['business_address'] ?? '';
$hours           = $o['business_hours']   ?? '';
$practitioner    = $o['practitioner_name']        ?? '';
$practitioner_bio   = $o['practitioner_bio']      ?? $o['ryan_bio'] ?? '';
$practitioner_creds = $o['practitioner_credentials'] ?? $o['ryan_credentials'] ?? '';
$service_type    = $o['service_type']     ?? 'massage';
$hero_tag        = $o['hero_tagline']     ?? 'Therapeutic ' . ucfirst( $service_type ) . ' for Body &amp; Mind';
$hero_sub        = $o['hero_subtitle']    ?? 'Professional, therapeutic ' . $service_type . ' tailored to your needs — in a calm, welcoming space.';
$venmo_user      = $o['venmo_username']   ?? '';
$site_name       = $o['business_name']    ?? get_bloginfo( 'name' ) ?: 'My Business';
$booking_url     = get_permalink( get_option( 'caswell_booking_page_id' ) ) ?: '/book';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $site_name ); ?><?php echo $practitioner_creds ? ' — ' . esc_html( $practitioner_creds ) : ''; ?></title>
<?php wp_head(); ?>
<style>
*, *::before, *::after { box-sizing: border-box; }
body { margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: #333; background: #fff; }
#wpadminbar ~ #cw-nav { top: 32px; }
</style>
</head>
<body <?php body_class( 'caswell-page' ); ?>>
<?php wp_body_open(); ?>

<!-- ── Sticky Nav ──────────────────────────────────────────────────── -->
<nav id="cw-nav" style="
    position: sticky; top: 0; z-index: 200;
    background: #fff; height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 5%; box-shadow: 0 1px 8px rgba(0,0,0,0.08);
">
    <a href="#" style="display:flex;align-items:center;text-decoration:none;">
        <?php $caswell_nav_logo = caswell_branding_logo_url(); if ( $caswell_nav_logo ) : ?>
            <img src="<?php echo esc_url( $caswell_nav_logo ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="max-height:44px;width:auto;display:block;" />
        <?php else : ?>
            <span style="font-size:1.15rem;font-weight:700;color:#4a7c6f;"><?php echo esc_html( $site_name ); ?></span>
        <?php endif; ?>
    </a>
    <div style="display:flex;align-items:center;gap:24px;" class="cw-navlinks">
        <a href="#services" style="text-decoration:none;color:#555;font-weight:500;">Services</a>
        <a href="#about"    style="text-decoration:none;color:#555;font-weight:500;">About</a>
        <a href="#contact"  style="text-decoration:none;color:#555;font-weight:500;">Contact</a>
        <a href="<?php echo esc_url( $booking_url ); ?>" style="
            background:#4a7c6f;color:#fff;text-decoration:none;
            padding:10px 22px;border-radius:6px;font-weight:600;font-size:0.95rem;
            transition:background 0.2s;
        " onmouseover="this.style.background='#3a6259'" onmouseout="this.style.background='#4a7c6f'">Book Now</a>
    </div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────────────── -->
<section style="
    background: linear-gradient(150deg, #2d5248 0%, #4a7c6f 55%, #7ab89e 100%);
    color: #fff; text-align: center;
    padding: clamp(80px, 12vw, 140px) 20px;
">
    <p style="font-size:clamp(2rem,5vw,3.4rem);font-weight:700;margin:0 0 18px;line-height:1.2;text-shadow:0 2px 12px rgba(0,0,0,0.2);">
        <?php echo esc_html( $hero_tag ); ?>
    </p>
    <p style="font-size:1.15rem;opacity:0.88;margin:0 0 40px;max-width:520px;margin-left:auto;margin-right:auto;">
        <?php echo esc_html( $hero_sub ); ?>
    </p>
    <a href="<?php echo esc_url( $booking_url ); ?>" style="
        display:inline-block;background:#fff;color:#4a7c6f;
        font-size:1.15rem;font-weight:700;padding:16px 44px;
        border-radius:8px;text-decoration:none;
        box-shadow:0 4px 20px rgba(0,0,0,0.18);
        transition:transform 0.15s,box-shadow 0.15s;
    " onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(0,0,0,0.22)'"
       onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(0,0,0,0.18)'">
        Book an Appointment
    </a>
    <?php if ( $phone ) : ?>
    <p style="margin:24px 0 0;opacity:0.75;font-size:0.95rem;">
        Questions? Call <a href="tel:<?php echo esc_attr( preg_replace('/\D/','',$phone) ); ?>"
            style="color:#fff;font-weight:600;"><?php echo esc_html( $phone ); ?></a>
    </p>
    <?php endif; ?>
</section>

<!-- ── Services ───────────────────────────────────────────────────── -->
<section id="services" style="padding:72px 5%;max-width:1060px;margin:0 auto;">
    <h2 style="text-align:center;color:#4a7c6f;font-size:1.9rem;margin:0 0 48px;">Services &amp; Pricing</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:28px;">
        <?php
        foreach ( caswell_enabled_session_lengths() as $len ) :
            $price = $o["service_price_{$len}"] ?? '';
            $desc  = trim( (string) ( $o["service_description_{$len}"] ?? '' ) );
        ?>
        <div style="
            background:#fff;border:1px solid #e0e0da;border-radius:10px;
            padding:32px 24px;text-align:center;
            box-shadow:0 2px 14px rgba(0,0,0,0.07);
            transition:transform 0.2s;
        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform=''">
            <div style="font-size:2.2rem;font-weight:700;color:#4a7c6f;"><?php echo (int) $len; ?> <span style="font-size:1rem;font-weight:400;">min</span></div>
            <div style="font-size:1.5rem;font-weight:600;margin:10px 0 6px;"><?php echo $price ? '$' . esc_html($price) : 'Contact for pricing'; ?></div>
            <?php if ( $desc !== '' ) : ?>
            <p style="color:#666;font-size:0.9rem;margin:0 0 20px;"><?php echo esc_html( $desc ); ?></p>
            <?php endif; ?>
            <a href="<?php echo esc_url( $booking_url ); ?>" style="
                display:inline-block;background:#4a7c6f;color:#fff;
                text-decoration:none;padding:10px 24px;border-radius:6px;
                font-weight:600;font-size:0.9rem;
            ">Book This Session</a>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ── Booking CTA Banner ──────────────────────────────────────────── -->
<div style="background:#f0f7f5;padding:56px 5%;text-align:center;">
    <h2 style="color:#4a7c6f;font-size:1.7rem;margin:0 0 14px;">Ready to feel better?</h2>
    <p style="color:#555;font-size:1.05rem;max-width:480px;margin:0 auto 28px;">
        Scheduling is quick and easy — pick a date and time that works for you.
    </p>
    <a href="<?php echo esc_url( $booking_url ); ?>" style="
        display:inline-block;background:#4a7c6f;color:#fff;
        font-size:1.1rem;font-weight:700;padding:14px 40px;
        border-radius:8px;text-decoration:none;
        box-shadow:0 4px 16px rgba(74,124,111,0.35);
        transition:background 0.2s;
    " onmouseover="this.style.background='#3a6259'" onmouseout="this.style.background='#4a7c6f'">
        Schedule Online →
    </a>
</div>

<!-- ── About ──────────────────────────────────────────────────────── -->
<section id="about" style="padding:72px 5%;max-width:900px;margin:0 auto;">
    <h2 style="text-align:center;color:#4a7c6f;font-size:1.9rem;margin:0 0 48px;">About <?php echo $practitioner ? esc_html( $practitioner ) : 'Us'; ?></h2>
    <div style="display:flex;gap:48px;align-items:center;flex-wrap:wrap;">
        <div style="flex-shrink:0;">
            <?php
            $headshot_id = get_option( 'caswell_headshot_id' );
            if ( $headshot_id ) {
                echo wp_get_attachment_image( $headshot_id, 'medium', false, [
                    'style' => 'width:200px;height:200px;border-radius:50%;object-fit:cover;border:4px solid #8fbc8f;display:block;',
                ] );
            } else {
            ?>
            <div style="
                width:200px;height:200px;border-radius:50%;
                background:linear-gradient(135deg,#c8e6d4,#a8d5bc);
                display:flex;align-items:center;justify-content:center;
                border:4px solid #8fbc8f;font-size:3.5rem;
            ">&#x1F9D8;</div>
            <?php } ?>
        </div>
        <div style="flex:1;min-width:240px;">
            <?php if ( $practitioner ) : ?>
            <h3 style="color:#4a7c6f;margin:0 0 6px;font-size:1.5rem;"><?php echo esc_html( $practitioner ); ?></h3>
            <?php endif; ?>
            <?php if ( $practitioner_creds ) : ?>
            <span style="
                background:#8fbc8f;color:#fff;padding:4px 14px;
                border-radius:20px;font-size:0.82rem;font-weight:600;
                display:inline-block;margin-bottom:16px;
            "><?php echo esc_html( $practitioner_creds ); ?></span>
            <?php endif; ?>
            <?php if ( $practitioner_bio ) : ?>
            <p style="color:#444;line-height:1.75;margin:0;">
                <?php echo nl2br( esc_html( $practitioner_bio ) ); ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── Contact ─────────────────────────────────────────────────────── -->
<section id="contact" style="background:#f5f9f7;padding:72px 5%;">
<div style="max-width:800px;margin:0 auto;">
    <h2 style="text-align:center;color:#4a7c6f;font-size:1.9rem;margin:0 0 48px;">Contact &amp; Hours</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:32px;">
        <?php if ( $address ) : ?>
        <div>
            <div style="font-weight:700;color:#4a7c6f;margin-bottom:6px;font-size:0.85rem;text-transform:uppercase;letter-spacing:.06em;">Location</div>
            <div style="color:#444;"><?php echo nl2br( esc_html( $address ) ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( $hours ) : ?>
        <div>
            <div style="font-weight:700;color:#4a7c6f;margin-bottom:6px;font-size:0.85rem;text-transform:uppercase;letter-spacing:.06em;">Hours</div>
            <div style="color:#444;"><?php echo nl2br( esc_html( $hours ) ); ?></div>
        </div>
        <?php endif; ?>

        <div>
            <?php if ( $phone ) : ?>
            <div style="margin-bottom:12px;">
                <div style="font-weight:700;color:#4a7c6f;margin-bottom:4px;font-size:0.85rem;text-transform:uppercase;letter-spacing:.06em;">Phone</div>
                <a href="tel:<?php echo esc_attr( preg_replace('/\D/','',$phone) ); ?>"
                   style="color:#333;text-decoration:none;font-size:1.05rem;"><?php echo esc_html( $phone ); ?></a>
            </div>
            <?php endif; ?>
            <?php if ( $email_addr ) : ?>
            <div style="margin-bottom:12px;">
                <div style="font-weight:700;color:#4a7c6f;margin-bottom:4px;font-size:0.85rem;text-transform:uppercase;letter-spacing:.06em;">Email</div>
                <a href="mailto:<?php echo esc_attr( $email_addr ); ?>"
                   style="color:#333;text-decoration:none;"><?php echo esc_html( $email_addr ); ?></a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align:center;margin-top:48px;">
        <a href="<?php echo esc_url( $booking_url ); ?>" style="
            display:inline-block;background:#4a7c6f;color:#fff;
            font-size:1.05rem;font-weight:700;padding:13px 36px;
            border-radius:8px;text-decoration:none;
        ">Book Your Appointment</a>
    </div>
</div>
</section>

<!-- ── Footer ─────────────────────────────────────────────────────── -->
<footer style="background:#1e1e1e;color:#aaa;text-align:center;padding:28px 20px;font-size:0.88rem;">
    <p style="margin:0 0 6px;">&copy; <?php echo date('Y'); ?> <?php echo esc_html( $site_name ); ?>. All rights reserved.</p>
    <?php if ( $venmo_user ) : ?>
    <p style="margin:0;">
        Venmo: <a href="https://venmo.com/<?php echo esc_attr( ltrim($venmo_user,'@') ); ?>"
                  target="_blank" rel="noopener" style="color:#8fbc8f;text-decoration:none;">
            @<?php echo esc_html( ltrim($venmo_user,'@') ); ?></a>
    </p>
    <?php endif; ?>
</footer>

<!-- ── Floating Book Now (mobile) ──────────────────────────────────── -->
<a href="<?php echo esc_url( $booking_url ); ?>" id="cw-float-btn" style="
    position:fixed;bottom:24px;right:24px;z-index:300;
    background:#4a7c6f;color:#fff;text-decoration:none;
    padding:14px 24px;border-radius:50px;font-weight:700;font-size:0.95rem;
    box-shadow:0 4px 20px rgba(74,124,111,0.5);
    display:none;
    transition:transform 0.2s;
" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform=''">
    Book Now
</a>

<?php wp_footer(); ?>

<script>
// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
        var id = this.getAttribute('href').replace('#','');
        var el = document.getElementById(id);
        if (el) {
            e.preventDefault();
            var top = el.getBoundingClientRect().top + window.pageYOffset - 72;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    });
});

// Show floating button after scrolling past hero
var floatBtn = document.getElementById('cw-float-btn');
window.addEventListener('scroll', function() {
    floatBtn.style.display = window.scrollY > 400 ? 'block' : 'none';
}, { passive: true });

// Hide nav links on small screens
(function() {
    var links = document.querySelector('.cw-navlinks');
    if (!links) return;
    function check() { links.style.display = window.innerWidth < 600 ? 'none' : 'flex'; }
    check();
    window.addEventListener('resize', check);
})();
</script>
</body>
</html>
