<?php
/**
 * Plugin Name: My Newsletter Plugin
 * Plugin URI:  http://example.com
 * Description: A custom newsletter plugin to collect emails, send notifications on new posts, and store contact form submissions.
 * Version:     1.2.0
 * Author:      Bakry Abdelsalam
 * Author URI:  https://bakry2.vercel.app/
 * License:     GPL2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $my_newsletter_db_version;
$my_newsletter_db_version = '1.3.0';

/**
 * Get subscribers table name
 */
function my_newsletter_get_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'my_newsletter_subscribers';
}

/**
 * Get contacts table name
 */
function my_newsletter_get_contacts_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'my_newsletter_contacts';
}

/**
 * On plugin activation, create the subscribers and contacts tables
 */
function my_newsletter_plugin_activate() {
    global $wpdb, $my_newsletter_db_version;

    $subscribers_table = my_newsletter_get_table_name();
    $contacts_table = my_newsletter_get_contacts_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    // Subscribers table
    $sql_subscribers = "CREATE TABLE $subscribers_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL UNIQUE,
        subscribed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // Contacts table
    $sql_contacts = "CREATE TABLE $contacts_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        message text NOT NULL,
        subscribed tinyint(1) NOT NULL DEFAULT 0,
        submitted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_subscribers );
    dbDelta( $sql_contacts );

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

    // Add a submenu for Contact Entries
    add_submenu_page(
        'newsletter_subscribers',
        'Contact Form Entries',
        'Contact Entries',
        'manage_options',
        'newsletter_contact_entries',
        'my_newsletter_contact_entries_page'
    );
}
add_action('admin_menu', 'my_newsletter_admin_menu');

/**
 * Admin Page for Subscribers
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
        <form id="my-newsletter-form" method="post">
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
 * Admin Page for Contact Entries
 */
function my_newsletter_contact_entries_page() {
    global $wpdb;
    $contacts_table = my_newsletter_get_contacts_table_name();
    $entries = $wpdb->get_results("SELECT id, name, email, message, subscribed, submitted_at FROM $contacts_table ORDER BY submitted_at DESC");
    ?>
    <div class="wrap">
        <h1>Contact Form Entries</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Message</th>
                    <th>Subscribed</th>
                    <th>Submitted At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( !empty($entries) ): ?>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?php echo esc_html($entry->name); ?></td>
                            <td><?php echo esc_html($entry->email); ?></td>
                            <td><?php echo esc_html($entry->message); ?></td>
                            <td><?php echo $entry->subscribed ? 'Yes' : 'No'; ?></td>
                            <td><?php echo esc_html($entry->submitted_at); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No contact entries found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Shortcode to display subscription/contact form
 */
function my_newsletter_subscribe_form() {
    $message = '';

    // Process form submission
    if ( isset( $_POST['my_newsletter_email'] ) && wp_verify_nonce( $_POST['my_newsletter_nonce'], 'my_newsletter_subscribe' ) ) {
        global $wpdb;
        $email = sanitize_email( $_POST['my_newsletter_email'] );
        $name = sanitize_text_field( $_POST['my_newsletter_name'] );
        $contact_message = sanitize_textarea_field( $_POST['my_newsletter_message'] );
        $subscribe = isset($_POST['my_newsletter_subscribe']) ? 1 : 0;

        $contacts_table = my_newsletter_get_contacts_table_name();
        $subscribers_table = my_newsletter_get_table_name();

        if ( is_email($email) ) {
            // Insert into contacts table
            $result = $wpdb->insert(
                $contacts_table, 
                array(
                    'name' => $name,
                    'email' => $email,
                    'message' => $contact_message,
                    'subscribed' => $subscribe
                ),
                array('%s','%s','%s','%d')
            );

            if ( $result ) {
                // If user chose to subscribe, also insert into subscribers if not already there
                if ( $subscribe ) {
                    $already_subscribed = $wpdb->get_var($wpdb->prepare("SELECT id FROM $subscribers_table WHERE email = %s", $email));
                    if (!$already_subscribed) {
                        $wpdb->insert($subscribers_table, array('email' => $email), array('%s'));
                    }
                }

                // Send email to Contact@rehamaliart.com with the submitted data including the message
                $admin_email = 'bakryabdalsalam6@gmail.com';
                $subject     = 'New Form Submission';
                $body        = "A new user has submitted the form:\n\n";
                $body       .= "Name: {$name}\n";
                $body       .= "Email: {$email}\n";
                $body       .= "Subscribed: " . ( $subscribe ? 'Yes' : 'No' ) . "\n";
                $body       .= "Message:\n{$contact_message}\n";

                // Send the email
                wp_mail( $admin_email, $subject, $body );

                $message = '<p style="color:green; text-align:center;">Your message has been sent successfully!</p>';
            } else {
                $message = '<p style="color:red; text-align:center;">An error occurred. Please try again.</p>';
            }
        } else {
            $message = '<p style="color:red; text-align:center;">Please enter a valid email address.</p>';
        }
    }

    ob_start(); ?>
    <form action="" method="post" class="newsletter-form" style="max-width:400px;margin:0 auto;">
        <?php echo $message; ?>
        <label for="my_newsletter_name">Name</label><br>
        <input type="text" name="my_newsletter_name" id="my_newsletter_name" class="form-input" required style="width:100%;margin-bottom:10px;"><br>

        <label for="my_newsletter_email">Email</label><br>
        <input type="email" name="my_newsletter_email" id="my_newsletter_email" class="form-input" placeholder="Enter your email..." required style="width:100%;margin-bottom:10px;"><br>

        <label for="my_newsletter_message">Message</label><br>
        <textarea name="my_newsletter_message" id="my_newsletter_message" rows="5" style="width:100%;margin-bottom:10px;"></textarea><br>

        <label for="my_newsletter_subscribe" style="margin-bottom:10px;">
            <input type="checkbox" name="my_newsletter_subscribe" id="my_newsletter_subscribe" value="1"> Subscribe to Newsletter
        </label><br><br>

        <?php wp_nonce_field('my_newsletter_subscribe', 'my_newsletter_nonce'); ?>
        <button type="submit" class="form-button" style="width:100%;">Send</button>
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

/**
 * Enqueue the popup JavaScript and styles
 */
function my_newsletter_enqueue_scripts() {
    wp_enqueue_script('my-newsletter-popup', plugin_dir_url(__FILE__) . 'js/my-newsletter-popup.js', array('jquery'), '1.0', true);
    wp_enqueue_style('my-newsletter-style', plugin_dir_url(__FILE__) . 'css/my-newsletter-style.css', array(), '1.0');
}
add_action('wp_enqueue_scripts', 'my_newsletter_enqueue_scripts');

/**
 * Print popup HTML in footer
 */
function my_newsletter_popup_html() {
    if ( ! is_admin() && ! is_page( 'supscripe' ) ) {
        ?>
        <div id="newsletter-popup-overlay" style="display:none;">
            <div id="newsletter-popup-content">
                <?php echo do_shortcode( '[my_newsletter_new_form]' ); ?>
                <button type="button" id="newsletter-popup-close" style="margin-top:20px;">Close</button>
            </div>
        </div>
        <?php
    }
}
add_action('wp_footer', 'my_newsletter_popup_html');


// New form function without the message field
function my_newsletter_new_form() {
    $message = '';

    // Process form submission
    if ( isset( $_POST['my_newsletter_email'] ) && wp_verify_nonce( $_POST['my_newsletter_nonce'], 'my_newsletter_subscribe' ) ) {
        global $wpdb;
        $email     = sanitize_email( $_POST['my_newsletter_email'] );
        $name      = sanitize_text_field( $_POST['my_newsletter_name'] );
        $subscribe = isset( $_POST['my_newsletter_subscribe'] ) ? 1 : 0;

        $contacts_table    = my_newsletter_get_contacts_table_name();
        $subscribers_table = my_newsletter_get_table_name();

        if ( is_email( $email ) ) {
            // Insert into contacts table without the message field
            $result = $wpdb->insert(
                $contacts_table,
                array(
                    'name'       => $name,
                    'email'      => $email,
                    'subscribed' => $subscribe,
                ),
                array( '%s', '%s', '%d' )
            );

            if ( $result ) {
                // If user chose to subscribe, insert into subscribers table if not already there
                if ( $subscribe ) {
                    $already_subscribed = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $subscribers_table WHERE email = %s", $email ) );
                    if ( ! $already_subscribed ) {
                        $wpdb->insert( $subscribers_table, array( 'email' => $email ), array( '%s' ) );
                    }
                }

                $message = '<p style="color:green; text-align:center;">Thank you for subscribing!</p>';
            } else {
                $message = '<p style="color:red; text-align:center;">An error occurred. Please try again.</p>';
            }
        } else {
            $message = '<p style="color:red; text-align:center;">Please enter a valid email address.</p>';
        }
    }

    ob_start(); ?>
    <form action="" method="post" class="newsletter-form" style="max-width:400px;margin:0 auto;">
        <?php echo $message; ?>
        <label for="my_newsletter_name">Name</label><br>
        <input type="text" name="my_newsletter_name" id="my_newsletter_name" class="form-input" required style="width:100%;margin-bottom:10px;"><br>

        <label for="my_newsletter_email">Email</label><br>
        <input type="email" name="my_newsletter_email" id="my_newsletter_email" class="form-input" placeholder="Enter your email..." required style="width:100%;margin-bottom:10px;"><br>

        <label for="my_newsletter_subscribe" style="margin-bottom:10px;">
            <input type="checkbox" name="my_newsletter_subscribe" id="my_newsletter_subscribe" value="1"> Subscribe to Newsletter
        </label><br><br>

        <?php wp_nonce_field( 'my_newsletter_subscribe', 'my_newsletter_nonce' ); ?>

        <button type="submit" class="form-button" style="width:100%;">Subscribe</button>
    </form>
    <?php
    return ob_get_clean();
}
// Register the new shortcode
add_shortcode( 'my_newsletter_new_form', 'my_newsletter_new_form' );