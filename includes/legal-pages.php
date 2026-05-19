<?php
defined( 'ABSPATH' ) || exit;

function caswell_legal_default_content( $kind ) {
    $valid = [ 'privacy', 'terms' ];
    if ( ! in_array( $kind, $valid, true ) ) {
        return '';
    }
    $file = $kind === 'privacy'
        ? 'legal-privacy-policy.html'
        : 'legal-terms-of-use.html';
    $path = dirname( __DIR__ ) . '/templates/' . $file;
    if ( ! is_readable( $path ) ) {
        return '';
    }
    $html = file_get_contents( $path );
    if ( false === $html ) {
        return '';
    }

    $business = caswell_get_option( 'business_name', '' );
    if ( '' === $business ) {
        $business = get_bloginfo( 'name' );
    }
    $contact = caswell_get_option( 'business_email', '' );
    if ( '' === $contact ) {
        $contact = get_bloginfo( 'admin_email' );
    }

    $tokens = [
        '{business_name}'      => $business,
        '{practitioner_name}'  => caswell_get_option( 'practitioner_name', '' ),
        '{service_type}'       => caswell_get_option( 'service_type', 'massage therapy' ),
        '{contact_email}'      => $contact,
        '{site_url}'           => home_url(),
        '{state}'              => 'Utah',
        '{last_updated_date}'  => date( 'F j, Y' ),
    ];

    return strtr( $html, $tokens );
}

function caswell_legal_page_url( $kind ) {
    $option  = $kind === 'terms' ? 'caswell_terms_page_id' : 'caswell_privacy_page_id';
    $page_id = (int) get_option( $option, 0 );
    if ( $page_id ) {
        $url = get_permalink( $page_id );
        if ( $url ) return $url;
    }
    $slug = $kind === 'terms' ? 'terms-of-use' : 'privacy-policy';
    return home_url( '/' . $slug );
}
