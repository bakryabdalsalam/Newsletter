<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Newsletter_Settings {

    public static function get_admin_email() {
        return get_option( 'my_newsletter_admin_email', get_option('admin_email') );
    }

    public static function get_from_name() {
        return get_option( 'my_newsletter_from_name', get_bloginfo('name') );
    }

    public static function get_from_email() {
        return get_option( 'my_newsletter_from_email', get_option('admin_email') );
    }

    public static function is_popup_enabled() {
        return (bool) get_option( 'my_newsletter_enable_popup', 1 );
    }

    public static function is_auto_send_enabled() {
        return (bool) get_option( 'my_newsletter_auto_send', 1 );
    }

    public static function is_double_optin_enabled() {
        return (bool) get_option( 'my_newsletter_double_optin', 0 );
    }

    public static function get_email_template() {
        return get_option( 'my_newsletter_email_template', '' );
    }

    public static function update_email_template( $template ) {
        update_option( 'my_newsletter_email_template', wp_kses_post( $template ) );
    }
}
