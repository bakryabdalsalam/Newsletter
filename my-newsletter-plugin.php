<?php
/**
 * Plugin Name: My Newsletter Plugin
 * Plugin URI:  http://example.com
 * Description: A custom newsletter plugin with a queue system for sending emails, settings, templates, and WP_List_Table integration.
 * Version:     2.1.0
 * Author:      Bakry Abdelsalam
 * Author URI:  https://bakry2.vercel.app/
 * License:     GPL2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MY_NEWSLETTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_NEWSLETTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once MY_NEWSLETTER_PLUGIN_DIR . 'includes/class-install.php';
require_once MY_NEWSLETTER_PLUGIN_DIR . 'includes/class-settings.php';
require_once MY_NEWSLETTER_PLUGIN_DIR . 'includes/class-admin.php';
require_once MY_NEWSLETTER_PLUGIN_DIR . 'includes/class-frontend.php';
require_once MY_NEWSLETTER_PLUGIN_DIR . 'includes/class-email.php';
require_once MY_NEWSLETTER_PLUGIN_DIR . 'includes/class-subscribers-list-table.php';
require_once MY_NEWSLETTER_PLUGIN_DIR . 'includes/class-contacts-list-table.php';

register_activation_hook( __FILE__, array( 'My_Newsletter_Install', 'plugin_activate' ) );

function my_newsletter_plugin_init() {
    new My_Newsletter_Admin();
    new My_Newsletter_Frontend();
    new My_Newsletter_Email();
}
add_action( 'plugins_loaded', 'my_newsletter_plugin_init' );

// Set the 'From' email address and name globally
add_filter( 'wp_mail_from', function( $original ) {
    $from_email = get_option( 'my_newsletter_from_email', get_option('admin_email') );
    return is_email( $from_email ) ? $from_email : $original;
});

add_filter( 'wp_mail_from_name', function( $original ) {
    $from_name = get_option( 'my_newsletter_from_name', get_bloginfo('name') );
    return ! empty( $from_name ) ? $from_name : $original;
});
