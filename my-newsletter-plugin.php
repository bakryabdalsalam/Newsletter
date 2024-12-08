<?php
/**
 * Plugin Name: My Newsletter Plugin
 * Plugin URI:  http://example.com
 * Description: A custom newsletter plugin to collect emails and send notifications on new posts.
 * Version:     1.0.0
 * Author:      Bakry Abdelsalam
 * Author URI:  https://bakry2.vercel.app/
 * License:     GPL2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $my_newsletter_db_version;
$my_newsletter_db_version = '1.0.0';

// Define the table name for subscribers
function my_newsletter_get_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'my_newsletter_subscribers';
}

/**
 * Create table on activation
 */
function my_newsletter_plugin_activate() {
    global $wpdb, $my_newsletter_db_version;

    $table_name = my_newsletter_get_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL UNIQUE,
        subscribed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option('my_newsletter_db_version', $my_newsletter_db_version);
}
register_activation_hook( __FILE__, 'my_newsletter_plugin_activate' );


/**
 * Shortcode to display subscription form
 */
function my_newsletter_subscribe_form() {
    // Check if user just submitted the form
    $message = '';
    if ( isset( $_POST['my_newsletter_email'] ) && isset( $_POST['my_newsletter_nonce'] ) ) {
        if ( wp_verify_nonce( $_POST['my_newsletter_nonce'], 'my_newsletter_subscribe' ) ) {
            $email = sanitize_email( $_POST['my_newsletter_email'] );
            if ( is_email( $email ) ) {
                $success = my_newsletter_add_subscriber($email);
                if ( $success ) {
                    $message = '<p style="color:green;">You have successfully subscribed!</p>';
                } else {
                    $message = '<p style="color:red;">This email is already subscribed or an error occurred.</p>';
                }
            } else {
                $message = '<p style="color:red;">Please enter a valid email address.</p>';
            }
        } else {
            $message = '<p style="color:red;">Security check failed. Please try again.</p>';
        }
    }

    ob_start(); ?>
    <form action="" method="post">
        <?php echo $message; ?>
        <label for="my_newsletter_email">Subscribe to our newsletter:</label><br>
        <input type="email" name="my_newsletter_email" id="my_newsletter_email" required>
        <?php wp_nonce_field('my_newsletter_subscribe', 'my_newsletter_nonce'); ?>
        <input type="submit" value="Subscribe">
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('my_newsletter_subscribe', 'my_newsletter_subscribe_form');


/**
 * Function to add subscriber to the database
 */
function my_newsletter_add_subscriber($email) {
    global $wpdb;
    $table_name = my_newsletter_get_table_name();

    // Check if already subscribed
    $existing = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table_name WHERE email = %s", $email) );
    if ( $existing ) {
        return false; // Already subscribed
    }

    $inserted = $wpdb->insert(
        $table_name,
        array(
            'email' => $email
        ),
        array(
            '%s'
        )
    );

    return $inserted !== false;
}


/**
 * Send notification emails to subscribers when a post is published
 */
function my_newsletter_send_notifications( $ID, $post ) {
    // Only send for standard posts and only when the post is published initially
    if ( $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
        return;
    }

    global $wpdb;
    $table_name = my_newsletter_get_table_name();

    // Get all subscribers
    $subscribers = $wpdb->get_col("SELECT email FROM $table_name");
    if ( empty($subscribers) ) {
        return; // No subscribers
    }

    $post_title = get_the_title($ID);
    $post_url   = get_permalink($ID);

    $subject = 'New Post: ' . $post_title;
    $message = "Hello,\n\nA new post has been published on our website:\n\n";
    $message .= $post_title . "\n";
    $message .= $post_url . "\n\n";
    $message .= "Thank you for subscribing to our newsletter!";

    $headers = array('Content-Type: text/plain; charset=UTF-8');

    // Send emails in batches if needed, but here we will just loop.
    foreach ( $subscribers as $subscriber ) {
        wp_mail( $subscriber, $subject, $message, $headers );
    }
}
add_action( 'publish_post', 'my_newsletter_send_notifications', 10, 2 );

