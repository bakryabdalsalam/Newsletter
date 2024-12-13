<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Newsletter_Email {

    protected $queue_table;

    public function __construct() {
        global $wpdb;
        $this->queue_table = $wpdb->prefix . 'my_newsletter_email_queue';

        add_action( 'transition_post_status', array( $this, 'auto_send_on_publish' ), 10, 3 );

        // Schedule the cron job if not already scheduled.
        if ( ! wp_next_scheduled( 'my_newsletter_process_email_queue' ) ) {
            wp_schedule_event( time(), 'every_five_minutes', 'my_newsletter_process_email_queue' );
        }

        add_action( 'my_newsletter_process_email_queue', array( $this, 'process_email_queue' ) );
    }

    public function auto_send_on_publish( $new_status, $old_status, $post ) {
        if ( ! My_Newsletter_Settings::is_auto_send_enabled() ) {
            return;
        }

        if ( 'publish' !== $new_status || 'publish' === $old_status ) {
            return;
        }

        $allowed_post_types = array( 'post', 'events', 'projects', 'testimonials' );
        if ( ! in_array( $post->post_type, $allowed_post_types ) ) {
            return;
        }

        global $wpdb;
        $subscribers_table = $wpdb->prefix . 'my_newsletter_subscribers';

        // Only send to confirmed subscribers
        $subscribers = $wpdb->get_results("SELECT email, unsub_token FROM $subscribers_table WHERE confirmed=1");

        if ( empty($subscribers) ) return;

        $post_title   = get_the_title( $post->ID );
        $post_url     = get_permalink( $post->ID );
        $post_excerpt = wp_trim_words(strip_tags($post->post_content), 40, '...');
        $template     = My_Newsletter_Settings::get_email_template();
        $subject      = 'New '.ucfirst($post->post_type).' Published: '.$post_title;
        $headers      = array('Content-Type: text/html; charset=UTF-8');

        // Queue these emails
        foreach ( $subscribers as $sub ) {
            $unsubscribe_link = add_query_arg(array('unsubscribe'=>$sub->unsub_token), home_url('/'));
            $message = str_replace(
                array('{post_title}','{post_url}','{post_excerpt}','{unsubscribe_link}'),
                array(esc_html($post_title), esc_url($post_url), esc_html($post_excerpt), esc_url($unsubscribe_link)),
                $template
            );

            $this->queue_email($sub->email, $subject, $message, $headers);
        }
    }

    protected function queue_email($email, $subject, $message, $headers = array()) {
        global $wpdb;
        $wpdb->insert(
            $this->queue_table,
            array(
                'email'   => $email,
                'subject' => $subject,
                'message' => $message,
                'headers' => maybe_serialize($headers),
                'status'  => 'pending',
                'attempts'=> 0
            ),
            array('%s','%s','%s','%s','%s','%d')
        );
    }

    public function process_email_queue() {
        global $wpdb;

        $batch_size = 20;

        $emails = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $this->queue_table WHERE status=%s ORDER BY queued_at ASC LIMIT %d", 'pending', $batch_size)
        );

        if ( empty($emails) ) {
            return; 
        }

        foreach ( $emails as $email_data ) {
            $headers = maybe_unserialize($email_data->headers);
            if ( ! is_array($headers) ) {
                $headers = array('Content-Type: text/html; charset=UTF-8');
            }

            $sent = wp_mail($email_data->email, $email_data->subject, $email_data->message, $headers);

            if ( $sent ) {
                // Update status to 'sent'
                $wpdb->update(
                    $this->queue_table,
                    array('status'=>'sent'),
                    array('id'=>$email_data->id),
                    array('%s'),
                    array('%d')
                );
            } else {
                // Increment attempts
                $attempts = $email_data->attempts + 1;
                $status   = $attempts >= 3 ? 'failed' : 'pending';

                $wpdb->update(
                    $this->queue_table,
                    array('attempts'=>$attempts, 'status'=>$status),
                    array('id'=>$email_data->id),
                    array('%d','%s'),
                    array('%d')
                );
            }
        }
    }
}

// Add a custom cron schedule for every 5 minutes
add_filter( 'cron_schedules', function($schedules) {
    if(!isset($schedules['every_five_minutes'])){
        $schedules['every_five_minutes'] = array(
            'interval' => 300, // 5 minutes
            'display'  => __('Every Five Minutes')
        );
    }
    return $schedules;
});
