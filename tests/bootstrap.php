<?php
/**
 * Bootstrap for unit tests.
 *
 * Brain Monkey lets us call plugin code without booting WordPress —
 * WP functions like get_option(), wp_date(), etc. are stubbed in
 * each test class' setUp().
 */

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../vendor/autoload.php';
