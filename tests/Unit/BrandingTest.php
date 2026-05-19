<?php
namespace Caswell\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

final class BrandingTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        if ( ! function_exists( 'caswell_get_option' ) ) {
            eval( '
                function caswell_get_option( $key, $default = "" ) {
                    $opts = $GLOBALS["__caswell_opts"] ?? [];
                    return $opts[ $key ] ?? $default;
                }
            ' );
        }
        require_once dirname( __DIR__, 2 ) . '/includes/branding.php';
    }

    protected function tearDown(): void {
        unset( $GLOBALS['__caswell_opts'] );
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_logo_url_returns_empty_when_unset(): void {
        $GLOBALS['__caswell_opts'] = [];
        $this->assertSame( '', caswell_branding_logo_url() );
    }

    public function test_logo_url_returns_trimmed_url_when_set(): void {
        $GLOBALS['__caswell_opts'] = [ 'branding_logo_url' => '  https://example.com/logo.png  ' ];
        $this->assertSame( 'https://example.com/logo.png', caswell_branding_logo_url() );
    }

    public function test_logo_url_returns_empty_for_non_string(): void {
        $GLOBALS['__caswell_opts'] = [ 'branding_logo_url' => 12345 ];
        $this->assertSame( '', caswell_branding_logo_url() );
    }
}
