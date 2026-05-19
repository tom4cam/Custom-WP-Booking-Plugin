<?php
defined( 'ABSPATH' ) || exit;

function caswell_branding_logo_url() {
    $url = caswell_get_option( 'branding_logo_url', '' );
    if ( ! is_string( $url ) ) return '';
    return trim( $url );
}
