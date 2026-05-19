<?php
namespace Caswell\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class ConsentTextTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        // caswell_get_option is defined in caswell-booking.php; redefine a
        // tiny stand-in so we don't have to load the whole plugin.
        if ( ! function_exists( 'caswell_get_option' ) ) {
            eval( '
                function caswell_get_option( $key, $default = "" ) {
                    $opts = $GLOBALS["__caswell_opts"] ?? [];
                    return $opts[ $key ] ?? $default;
                }
            ' );
        }

        require_once dirname( __DIR__, 2 ) . '/includes/consent.php';
    }

    protected function tearDown(): void {
        unset( $GLOBALS['__caswell_opts'] );
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_email_default_text_includes_emails_phrase(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
        $GLOBALS['__caswell_opts'] = [];
        $text = caswell_render_consent_text( 'email' );
        $this->assertStringContainsString( 'reminder emails', $text );
        $this->assertStringContainsString( 'My Site', $text );
        $this->assertStringNotContainsString( '{business_name}', $text );
    }

    public function test_sms_default_text_includes_stop_phrase(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'My Site' );
        $GLOBALS['__caswell_opts'] = [];
        $text = caswell_render_consent_text( 'sms' );
        $this->assertStringContainsString( 'Reply STOP to opt out', $text );
        $this->assertStringContainsString( 'Msg & data rates may apply', $text );
        $this->assertStringContainsString( 'My Site', $text );
    }

    public function test_interpolates_configured_business_name(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'fallback' );
        $GLOBALS['__caswell_opts'] = [ 'business_name' => 'CAS Therapy' ];
        $this->assertStringContainsString( 'CAS Therapy', caswell_render_consent_text( 'email' ) );
        $this->assertStringContainsString( 'CAS Therapy', caswell_render_consent_text( 'sms'   ) );
    }

    public function test_falls_back_to_site_name_when_business_name_empty(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'Site Title' );
        $GLOBALS['__caswell_opts'] = [ 'business_name' => '' ];
        $this->assertStringContainsString( 'Site Title', caswell_render_consent_text( 'email' ) );
    }

    public function test_uses_configured_template_when_setting_present(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'Site Title' );
        $GLOBALS['__caswell_opts'] = [
            'business_name'      => 'Acme Co',
            'email_consent_text' => 'Custom email line for {business_name}.',
        ];
        $this->assertSame(
            'Custom email line for Acme Co.',
            caswell_render_consent_text( 'email' )
        );
    }

    public function test_unknown_kind_returns_empty_string(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'X' );
        $GLOBALS['__caswell_opts'] = [];
        $this->assertSame( '', caswell_render_consent_text( 'marketing' ) );
    }
}
