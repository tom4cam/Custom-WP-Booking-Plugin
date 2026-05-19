<?php
defined( 'ABSPATH' ) || exit;

function caswell_consent_text_defaults() {
    return [
        'email' => 'I agree to receive appointment confirmation and reminder emails from {business_name} at the email address provided.',
        'sms'   => 'I agree to receive appointment confirmation and reminder text messages from {business_name} at the phone number provided. Message frequency varies. Msg & data rates may apply. Reply STOP to opt out.',
    ];
}

function caswell_render_consent_text( $kind ) {
    $defaults = caswell_consent_text_defaults();
    if ( ! isset( $defaults[ $kind ] ) ) {
        return '';
    }
    $template = caswell_get_option( "{$kind}_consent_text", '' );
    if ( '' === $template ) {
        $template = $defaults[ $kind ];
    }
    $business = caswell_get_option( 'business_name', '' );
    if ( '' === $business ) {
        $business = get_bloginfo( 'name' );
    }
    return str_replace( '{business_name}', $business, $template );
}
