<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Newsletter_Install {
    public static $db_version = '2.1.0';

    public static function plugin_activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $subscribers_table = $wpdb->prefix . 'my_newsletter_subscribers';
        $contacts_table    = $wpdb->prefix . 'my_newsletter_contacts';
        $queue_table       = $wpdb->prefix . 'my_newsletter_email_queue';

        $sql_subscribers = "CREATE TABLE $subscribers_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL UNIQUE,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            confirmed tinyint(1) NOT NULL DEFAULT 1,
            unsub_token varchar(64) DEFAULT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql_contacts = "CREATE TABLE $contacts_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            message text NOT NULL,
            subscribed tinyint(1) NOT NULL DEFAULT 0,
            submitted_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_queue = "CREATE TABLE $queue_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            headers text NOT NULL,
            attempts tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            queued_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_subscribers );
        dbDelta( $sql_contacts );
        dbDelta( $sql_queue );

        add_option( 'my_newsletter_db_version', self::$db_version );

        // Default settings
        add_option( 'my_newsletter_admin_email', get_option('admin_email') );
        add_option( 'my_newsletter_from_email', get_option('admin_email') );
        add_option( 'my_newsletter_from_name', get_bloginfo('name') );
        add_option( 'my_newsletter_enable_popup', 1 );
        add_option( 'my_newsletter_auto_send', 1 );
        add_option( 'my_newsletter_double_optin', 0 );

        // Default template
        $default_template = '<h1>{post_title}</h1><p>{post_excerpt}</p><p><a href="{post_url}">Read Now</a></p><p>Thank you for subscribing!</p><p><a href="{unsubscribe_link}">Unsubscribe</a></p>';
        add_option( 'my_newsletter_email_template', $default_template );
    }
}
