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
$my_newsletter_db_version = '1.2.0';

/**
 * Get subscribers table name
 */
function my_newsletter_get_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'my_newsletter_subscribers';
}

/**
 * On plugin activation, create the subscribers table
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
 * Add Admin Menu for Newsletter
 */
function my_newsletter_admin_menu() {
    add_menu_page(
        'Newsletter Subscribers', 
        'Newsletter', 
        'manage_options', 
        'newsletter_subscribers', 
        'my_newsletter_admin_page',
        'dashicons-email-alt'
    );
}
add_action('admin_menu', 'my_newsletter_admin_menu');

/**
 * Admin Page Content
 */
function my_newsletter_admin_page() {
    global $wpdb;
    $table_name = my_newsletter_get_table_name();

    // Handle email sending
    if ( isset($_POST['send_email']) ) {
        check_admin_referer('send_newsletter_email', 'my_newsletter_nonce');

        $emails = isset($_POST['selected_emails']) ? $_POST['selected_emails'] : array();
        $subject = sanitize_text_field($_POST['email_subject']);
        $message = wp_kses_post($_POST['email_message']);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        foreach ($emails as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
        echo '<div class="updated"><p>Email(s) sent successfully!</p></div>';
    }

    // Fetch subscribers
    $subscribers = $wpdb->get_results("SELECT id, email, subscribed_at FROM $table_name");

    ?>
    <div class="wrap">
        <h1>Newsletter Subscribers</h1>
        <form method="post">
            <?php wp_nonce_field('send_newsletter_email', 'my_newsletter_nonce'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 5%;"><input type="checkbox" id="select_all"></th>
                        <th>Email</th>
                        <th>Subscribed At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscribers as $subscriber): ?>
                        <tr>
                            <td><input type="checkbox" name="selected_emails[]" value="<?php echo esc_attr($subscriber->email); ?>"></td>
                            <td><?php echo esc_html($subscriber->email); ?></td>
                            <td><?php echo esc_html($subscriber->subscribed_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h2>Send Email</h2>
            <p>
                <label for="email_subject">Subject:</label><br>
                <input type="text" name="email_subject" id="email_subject" required class="regular-text">
            </p>
            <p>
                <label for="email_message">Message:</label><br>
                <textarea name="email_message" id="email_message" rows="5" class="large-text" required></textarea>
            </p>
            <p>
                <input type="submit" name="send_email" value="Send Email" class="button button-primary">
            </p>
        </form>
    </div>

    <script>
        document.getElementById('select_all').addEventListener('change', function() {
            let checkboxes = document.querySelectorAll('input[name="selected_emails[]"]');
            for (let box of checkboxes) {
                box.checked = this.checked;
            }
        });
    </script>
    <?php
}

/**
 * Shortcode to display subscription form
 */
function my_newsletter_subscribe_form() {
    $message = '';

    // Process form submission
    if ( isset( $_POST['my_newsletter_email'] ) && wp_verify_nonce( $_POST['my_newsletter_nonce'], 'my_newsletter_subscribe' ) ) {
        global $wpdb;
        $email = sanitize_email( $_POST['my_newsletter_email'] );
        $table_name = my_newsletter_get_table_name();

        if ( is_email($email) ) {
            $result = $wpdb->insert($table_name, array('email' => $email), array('%s'));
            if ( $result ) {
                $message = '<p style="color:green; text-align:center;">You have successfully subscribed!</p>';
            } else {
                $message = '<p style="color:red; text-align:center;">This email is already subscribed.</p>';
            }
        } else {
            $message = '<p style="color:red; text-align:center;">Please enter a valid email address.</p>';
        }
    }

    ob_start(); ?>
    <form action="" method="post" class="newsletter-form">
        <?php echo $message; ?>
        <label for="my_newsletter_email" class="form-label">Join Our Newsletter</label>
        <input type="email" name="my_newsletter_email" id="my_newsletter_email" class="form-input" placeholder="Enter your email..." required>
        <?php wp_nonce_field('my_newsletter_subscribe', 'my_newsletter_nonce'); ?>
        <button type="submit" class="form-button">Subscribe</button>
    </form>
    <?php return ob_get_clean();
}
add_shortcode('my_newsletter_form', 'my_newsletter_subscribe_form');



/**
 * Auto-send email to all subscribers when a new post is published
 */
function my_newsletter_auto_send_email($ID, $post) {
    // Only send for standard posts and ensure the post is published
    if ( $post->post_type !== 'post' || $post->post_status !== 'publish' ) {
        return;
    }

    global $wpdb;
    $table_name = my_newsletter_get_table_name();

    // Fetch all subscriber emails
    $subscribers = $wpdb->get_col("SELECT email FROM $table_name");

    if ( empty($subscribers) ) {
        return; // No subscribers to send to
    }

    // Post details
    $post_title = get_the_title($ID);
    $post_url   = get_permalink($ID);
    $subject    = 'New Post Published: ' . $post_title;

    $message    = '<h1>' . esc_html($post_title) . '</h1>';
    $message   .= '<p>A new post has been published on our website. You can read it here:</p>';
    $message   .= '<p><a href="' . esc_url($post_url) . '" target="_blank">Read Now</a></p>';
    $message   .= '<p>Thank you for subscribing to our newsletter!</p>';

    $headers = array('Content-Type: text/html; charset=UTF-8');

    // Send email to all subscribers
    foreach ( $subscribers as $email ) {
        wp_mail( $email, $subject, $message, $headers );
    }
}
add_action('publish_post', 'my_newsletter_auto_send_email', 10, 2);
