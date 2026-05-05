<?php

namespace Caswell\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class SessionLengthsTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once dirname( __DIR__, 2 ) . '/includes/session-lengths.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_options_returns_15min_increments_15_to_120(): void {
        $this->assertSame(
            [ 15, 30, 45, 60, 75, 90, 105, 120 ],
            caswell_session_length_options()
        );
    }

    public function test_enabled_returns_only_lengths_with_truthy_flag(): void {
        Functions\when( 'get_option' )->justReturn( [
            'enable_30min'  => 1,
            'enable_60min'  => 1,
            'enable_90min'  => 0,
            'enable_120min' => 1,
        ] );

        $this->assertSame(
            [ 30, 60, 120 ],
            caswell_enabled_session_lengths()
        );
    }

    public function test_enabled_falls_back_to_60_when_nothing_enabled(): void {
        Functions\when( 'get_option' )->justReturn( [] );

        $this->assertSame(
            [ 60 ],
            caswell_enabled_session_lengths()
        );
    }

    public function test_resolve_default_keeps_requested_when_enabled(): void {
        $this->assertSame(
            60,
            caswell_resolve_default_length( 60, [ 30, 60, 90 ] )
        );
    }

    public function test_resolve_default_falls_back_to_lowest_when_requested_disabled(): void {
        // Ryan disables 60 (his default). Lowest enabled (30) becomes the new default.
        $this->assertSame(
            30,
            caswell_resolve_default_length( 60, [ 30, 90 ] )
        );
    }

    public function test_resolve_default_returns_60_when_nothing_enabled(): void {
        $this->assertSame(
            60,
            caswell_resolve_default_length( 60, [] )
        );
    }

    public function test_resolve_default_coerces_zero_or_garbage_to_lowest_enabled(): void {
        $this->assertSame(
            15,
            caswell_resolve_default_length( 0, [ 15, 30, 60 ] )
        );
    }
}
