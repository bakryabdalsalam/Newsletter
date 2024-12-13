<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Newsletter_Frontend {

    public function __construct() {
        add_shortcode( 'my_newsletter_form', array( $this, 'subscribe_form_shortcode' ) );
        add_shortcode( 'my_newsletter_unsubscribe_form', array( $this, 'unsubscribe_form_shortcode' ) );

        add_action( 'wp_footer', array( $this, 'maybe_show_popup' ) );
        add_action( 'init', array( $this, 'handle_subscription' ) );
        add_action( 'init', array( $this, 'handle_unsubscribe_link' ) );
        add_action( 'init', array( $this, 'handle_confirmation_link' ) );
    }

    public function subscribe_form_shortcode() {
        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('my_newsletter_subscribe','my_newsletter_nonce'); ?>
            <p><input type="text" name="my_newsletter_name" placeholder="Your Name" required></p>
            <p><input type="email" name="my_newsletter_email" placeholder="Your Email" required></p>
            <p><textarea name="my_newsletter_message" placeholder="Your Message"></textarea></p>
            <p><label><input type="checkbox" name="my_newsletter_subscribe" value="1"> Subscribe to Newsletter</label></p>
            <button type="submit">Send</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function unsubscribe_form_shortcode() {
        ob_start();
        ?>
        <form method="post">
            <?php wp_nonce_field('my_newsletter_unsubscribe','my_newsletter_unsubscribe_nonce'); ?>
            <p><input type="email" name="my_newsletter_unsubscribe_email" placeholder="Your Email" required></p>
            <button type="submit">Unsubscribe</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function maybe_show_popup() {
        if ( ! is_admin() && My_Newsletter_Settings::is_popup_enabled() ) {
            ?>
            <div id="newsletter-popup-overlay" style="display:none;">
                <div id="newsletter-popup-content">
                    <?php echo do_shortcode('[my_newsletter_form]'); ?>
                    <button type="button" id="newsletter-popup-close" style="margin-top:20px;">Close</button>
                </div>
            </div>
            <script>
            (function($){
                $(document).ready(function(){
                    $('#newsletter-popup-overlay').fadeIn();
                    $('#newsletter-popup-close').on('click', function(){
                        $('#newsletter-popup-overlay').fadeOut();
                    });
                });
            })(jQuery);
            </script>
            <?php
        }
    }

    public function handle_subscription() {
        if ( ! isset($_POST['my_newsletter_nonce']) || ! wp_verify_nonce($_POST['my_newsletter_nonce'],'my_newsletter_subscribe') ) {
            return;
        }

        global $wpdb;
        $email   = sanitize_email( $_POST['my_newsletter_email'] );
        $name    = sanitize_text_field( $_POST['my_newsletter_name'] );
        $message = sanitize_textarea_field( $_POST['my_newsletter_message'] );
        $subscribe = isset($_POST['my_newsletter_subscribe']) ? 1 : 0;

        $contacts_table = $wpdb->prefix . 'my_newsletter_contacts';
        $subscribers_table = $wpdb->prefix . 'my_newsletter_subscribers';

        if ( is_email($email) ) {
            $wpdb->insert($contacts_table, array(
                'name' => $name,
                'email'=> $email,
                'message' => $message,
                'subscribed' => $subscribe
            ), array('%s','%s','%s','%d'));

            if ( $subscribe ) {
                // Check if already subscriber
                $already_subscribed = $wpdb->get_var($wpdb->prepare("SELECT id FROM $subscribers_table WHERE email=%s",$email));
                if ( ! $already_subscribed ) {
                    // Generate unsubscribe token
                    $token = wp_generate_uuid4();
                    $wpdb->insert($subscribers_table,array(
                        'email' => $email,
                        'unsub_token' => $token,
                        'confirmed' => My_Newsletter_Settings::is_double_optin_enabled() ? 0 : 1
                    ), array('%s','%s','%d'));
                }

                // If double opt-in is enabled, send confirmation email
                if ( My_Newsletter_Settings::is_double_optin_enabled() ) {
                    $this->send_confirmation_email($email, $name);
                } else {
                    // Send welcome email if double opt-in not required
                    $this->send_welcome_email($email, $name);
                }
            }

            // Notify admin
            $admin_email = My_Newsletter_Settings::get_admin_email();
            $subject = 'New Form Submission';
            $body = '<p>Name: '.esc_html($name).'</p><p>Email: '.esc_html($email).'</p><p>Subscribed: '.($subscribe?'Yes':'No').'</p><p>Message: '.nl2br(esc_html($message)).'</p>';
            wp_mail($admin_email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));

            add_action('wp_footer', function(){
                echo '<script>alert("Your form has been submitted successfully!");</script>';
            });
        }
    }

    public function handle_unsubscribe_link() {
        if ( isset($_GET['unsubscribe']) && !empty($_GET['unsubscribe']) ) {
            global $wpdb;
            $subscribers_table = $wpdb->prefix . 'my_newsletter_subscribers';
            $token = sanitize_text_field($_GET['unsubscribe']);
            $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscribers_table WHERE unsub_token=%s",$token));
            if ( $subscriber ) {
                $wpdb->delete($subscribers_table, array('id'=>$subscriber->id), array('%d'));
                add_action('wp_footer', function(){
                    echo '<script>alert("You have been unsubscribed successfully.");</script>';
                });
            }
        }
    }

    public function handle_confirmation_link() {
        if ( isset($_GET['confirm']) && !empty($_GET['confirm']) ) {
            global $wpdb;
            $subscribers_table = $wpdb->prefix . 'my_newsletter_subscribers';
            $token = sanitize_text_field($_GET['confirm']);
            $subscriber = $wpdb->get_row($wpdb->prepare("SELECT * FROM $subscribers_table WHERE unsub_token=%s",$token));
            if ( $subscriber && $subscriber->confirmed==0 ) {
                $wpdb->update($subscribers_table, array('confirmed'=>1), array('id'=>$subscriber->id), array('%d'), array('%d'));
                // Send welcome email now that they’re confirmed
                $this->send_welcome_email($subscriber->email, '');
                add_action('wp_footer', function(){
                    echo '<script>alert("Your subscription has been confirmed!");</script>';
                });
            }
        }
    }

    private function send_confirmation_email($email, $name) {
        global $wpdb;
        $subscribers_table = $wpdb->prefix . 'my_newsletter_subscribers';
        $token = $wpdb->get_var($wpdb->prepare("SELECT unsub_token FROM $subscribers_table WHERE email=%s",$email));
        $confirm_url = add_query_arg(array('confirm'=>$token), home_url('/'));

        $subject = 'Please Confirm Your Subscription';
        $body = '<p>Hello '.esc_html($name).',</p>';
        $body .= '<p>Please confirm your subscription by clicking the link below:</p>';
        $body .= '<p><a href="'.esc_url($confirm_url).'">Confirm Subscription</a></p>';
        $body .= '<p>Thank you!</p>';
        wp_mail($email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));
    }

    private function send_welcome_email($email, $name) {
        $subject = 'Welcome to Our Newsletter';
        $body = '<p>Hello '.esc_html($name).',</p>';
        $body .= '<p>Thank you for subscribing to our newsletter! Stay tuned for updates.</p>';
        wp_mail($email, $subject, $body, array('Content-Type: text/html; charset=UTF-8'));
    }
}
