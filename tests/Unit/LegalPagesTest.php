<?php
namespace Caswell\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class LegalPagesTest extends TestCase {
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
        require_once dirname( __DIR__, 2 ) . '/includes/legal-pages.php';
    }

    protected function tearDown(): void {
        unset( $GLOBALS['__caswell_opts'] );
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_privacy_default_is_nonempty_html(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'Site Name' );
        Functions\when( 'home_url' )->justReturn( 'https://example.com' );
        $GLOBALS['__caswell_opts'] = [];
        $html = caswell_legal_default_content( 'privacy' );
        $this->assertNotEmpty( $html );
        $this->assertStringContainsString( '<h2>', $html );
    }

    public function test_terms_default_is_nonempty_html(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'Site Name' );
        Functions\when( 'home_url' )->justReturn( 'https://example.com' );
        $GLOBALS['__caswell_opts'] = [];
        $html = caswell_legal_default_content( 'terms' );
        $this->assertNotEmpty( $html );
        $this->assertStringContainsString( '<h2>', $html );
    }

    public function test_interpolates_business_name(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'Site Title' );
        Functions\when( 'home_url' )->justReturn( 'https://example.com' );
        $GLOBALS['__caswell_opts'] = [ 'business_name' => 'CAS Therapy' ];
        $html = caswell_legal_default_content( 'privacy' );
        $this->assertStringContainsString( 'CAS Therapy', $html );
        $this->assertStringNotContainsString( '{business_name}', $html );
    }

    public function test_privacy_and_terms_are_different(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'X' );
        Functions\when( 'home_url' )->justReturn( 'https://x.test' );
        $GLOBALS['__caswell_opts'] = [];
        $this->assertNotSame(
            caswell_legal_default_content( 'privacy' ),
            caswell_legal_default_content( 'terms' )
        );
    }

    public function test_unknown_kind_returns_empty(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'X' );
        Functions\when( 'home_url' )->justReturn( 'https://x.test' );
        $GLOBALS['__caswell_opts'] = [];
        $this->assertSame( '', caswell_legal_default_content( 'marketing' ) );
    }

    public function test_content_contains_no_block_markers(): void {
        Functions\when( 'get_bloginfo' )->justReturn( 'X' );
        Functions\when( 'home_url' )->justReturn( 'https://x.test' );
        $GLOBALS['__caswell_opts'] = [];
        $this->assertStringNotContainsString( '<!-- wp:', caswell_legal_default_content( 'privacy' ) );
        $this->assertStringNotContainsString( '<!-- wp:', caswell_legal_default_content( 'terms' ) );
    }

    public function test_legal_page_url_fallback_when_unset(): void {
        Functions\when( 'get_option' )->justReturn( 0 );
        Functions\when( 'home_url' )->alias( function ( $path = '' ) {
            return 'https://example.com' . $path;
        } );
        $this->assertSame( 'https://example.com/privacy-policy', caswell_legal_page_url( 'privacy' ) );
        $this->assertSame( 'https://example.com/terms-of-use',   caswell_legal_page_url( 'terms' ) );
    }
}
