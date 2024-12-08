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
  * Register the "newsletter_subscriber" custom post type
  */
 function my_newsletter_register_cpt() {
     $labels = array(
         'name'               => _x( 'Newsletter Subscribers', 'Post Type General Name', 'my-newsletter-plugin' ),
         'singular_name'      => _x( 'Newsletter Subscriber', 'Post Type Singular Name', 'my-newsletter-plugin' ),
         'menu_name'          => __( 'Newsletter Subscribers', 'my-newsletter-plugin' ),
         'name_admin_bar'     => __( 'Subscriber', 'my-newsletter-plugin' ),
         'add_new_item'       => __( 'Add New Subscriber', 'my-newsletter-plugin' ),
         'new_item'           => __( 'New Subscriber', 'my-newsletter-plugin' ),
         'edit_item'          => __( 'Edit Subscriber', 'my-newsletter-plugin' ),
         'view_item'          => __( 'View Subscriber', 'my-newsletter-plugin' ),
         'all_items'          => __( 'All Subscribers', 'my-newsletter-plugin' ),
         'search_items'       => __( 'Search Subscribers', 'my-newsletter-plugin' ),
         'parent_item_colon'  => __( 'Parent Subscribers:', 'my-newsletter-plugin' ),
         'not_found'          => __( 'No subscribers found.', 'my-newsletter-plugin' ),
         'not_found_in_trash' => __( 'No subscribers found in Trash.', 'my-newsletter-plugin' )
     );
 
     $args = array(
         'labels'             => $labels,
         'public'             => true,
         'show_ui'            => true,
         'capability_type'    => 'post',
         'supports'           => array( 'title' ), 
         'menu_icon'          => 'dashicons-email', 
         'show_in_menu'       => true
     );
 
     register_post_type( 'newsletter_subscriber', $args );
 }
 add_action( 'init', 'my_newsletter_register_cpt' );
 
 /**
  * Shortcode to display subscription form
  */
 function my_newsletter_subscribe_form() {
     $message = '';
 
     // Process form submission
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
  * Add a subscriber to the database and create a corresponding CPT entry
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
 
     if ( $inserted !== false ) {
         // Create a corresponding post in the newsletter_subscriber CPT
         $post_id = wp_insert_post( array(
             'post_title'  => $email,
             'post_type'   => 'newsletter_subscriber',
             'post_status' => 'publish'
         ) );
 
         if ( is_wp_error( $post_id ) ) {
             // If insertion of the CPT fails, you might want to handle it,
             // but for this example we will just ignore.
         }
 
         return true;
     }
 
     return false;
 }
 
 /**
  * Send notification emails to subscribers when a post is published
  */
 function my_newsletter_send_notifications( $ID, $post ) {
     // Only send for standard posts and only when the post is published
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
 
     foreach ( $subscribers as $subscriber ) {
         wp_mail( $subscriber, $subject, $message, $headers );
     }
 }
 add_action( 'publish_post', 'my_newsletter_send_notifications', 10, 2 );
 