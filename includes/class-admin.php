<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class My_Newsletter_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
    }

    public function admin_menu() {
        $capability = 'manage_options';
        add_menu_page(
            'Newsletter',
            'Newsletter',
            $capability,
            'newsletter_subscribers',
            array( $this, 'render_subscribers_page' ),
            'dashicons-email-alt'
        );

        add_submenu_page(
            'newsletter_subscribers',
            'Contact Form Entries',
            'Contact Entries',
            $capability,
            'newsletter_contact_entries',
            array( $this, 'render_contacts_page' )
        );

        add_submenu_page(
            'newsletter_subscribers',
            'Settings',
            'Settings',
            $capability,
            'newsletter_settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_subscribers_page() {
        $list_table = new My_Newsletter_Subscribers_List_Table();
        $list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1>Subscribers</h1>
            <form method="get">
                <input type="hidden" name="page" value="newsletter_subscribers" />
                <?php $list_table->search_box('Search Subscribers', 'subscriber_search'); ?>
            </form>
            <form method="post">
                <?php
                $list_table->display();
                ?>
                <p>
                    <input type="submit" name="export_csv" class="button button-secondary" value="Export CSV">
                </p>
            </form>
        </div>
        <?php
    }

    public function render_contacts_page() {
        $list_table = new My_Newsletter_Contacts_List_Table();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1>Contact Entries</h1>
            <form method="get">
                <input type="hidden" name="page" value="newsletter_contact_entries" />
                <?php $list_table->search_box('Search Contacts', 'contact_search'); ?>
            </form>
            <form method="post">
                <?php
                $list_table->display();
                ?>
                <p>
                    <input type="submit" name="export_csv" class="button button-secondary" value="Export CSV">
                </p>
            </form>
        </div>
        <?php
    }

    public function render_settings_page() {
        if ( isset($_POST['my_newsletter_settings_nonce']) && wp_verify_nonce($_POST['my_newsletter_settings_nonce'],'my_newsletter_settings') ) {
            update_option( 'my_newsletter_admin_email', sanitize_email( $_POST['admin_email'] ) );
            update_option( 'my_newsletter_from_email', sanitize_email( $_POST['from_email'] ) );
            update_option( 'my_newsletter_from_name', sanitize_text_field( $_POST['from_name'] ) );
            update_option( 'my_newsletter_enable_popup', isset($_POST['enable_popup']) ? 1 : 0 );
            update_option( 'my_newsletter_auto_send', isset($_POST['auto_send']) ? 1 : 0 );
            update_option( 'my_newsletter_double_optin', isset($_POST['double_optin']) ? 1 : 0 );

            if ( isset($_POST['email_template']) ) {
                My_Newsletter_Settings::update_email_template( $_POST['email_template'] );
            }

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $admin_email   = get_option( 'my_newsletter_admin_email', get_option('admin_email') );
        $from_email    = get_option( 'my_newsletter_from_email', get_option('admin_email') );
        $from_name     = get_option( 'my_newsletter_from_name', get_bloginfo('name') );
        $enable_popup  = get_option( 'my_newsletter_enable_popup', 1 );
        $auto_send     = get_option( 'my_newsletter_auto_send', 1 );
        $double_optin  = get_option( 'my_newsletter_double_optin', 0 );
        $email_template = My_Newsletter_Settings::get_email_template();
        ?>

        <div class="wrap">
            <h1>Newsletter Settings</h1>
            <form method="post">
                <?php wp_nonce_field('my_newsletter_settings','my_newsletter_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="admin_email">Admin Notification Email</label></th>
                        <td><input type="email" name="admin_email" id="admin_email" value="<?php echo esc_attr( $admin_email ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="from_name">From Name</label></th>
                        <td><input type="text" name="from_name" id="from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th><label for="from_email">From Email</label></th>
                        <td><input type="email" name="from_email" id="from_email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Enable Popup</th>
                        <td>
                            <label><input type="checkbox" name="enable_popup" <?php checked($enable_popup,1);?>> Show popup form</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Auto Send on Publish</th>
                        <td>
                            <label><input type="checkbox" name="auto_send" <?php checked($auto_send,1);?>> Automatically send newsletter on new publish</label>
                        </td>
                    </tr>
                    <tr>
                        <th>Double Opt-In</th>
                        <td>
                            <label><input type="checkbox" name="double_optin" <?php checked($double_optin,1);?>> Require email confirmation before subscribing</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="email_template">Email Template</label></th>
                        <td>
                            <textarea name="email_template" id="email_template" rows="10" class="large-text"><?php echo esc_textarea($email_template); ?></textarea>
                            <p>Use placeholders: {post_title}, {post_url}, {post_excerpt}, {unsubscribe_link}</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
