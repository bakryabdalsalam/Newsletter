<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists('WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class My_Newsletter_Subscribers_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'subscriber',
            'plural'   => 'subscribers',
            'ajax'     => false
        ));
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox"/>',
            'email' => 'Email',
            'subscribed_at' => 'Subscribed At',
            'confirmed' => 'Confirmed'
        );
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="subscriber[]" value="%d"/>', $item['id']);
    }

    protected function column_email($item) {
        return esc_html($item['email']);
    }

    protected function column_subscribed_at($item) {
        return esc_html($item['subscribed_at']);
    }

    protected function column_confirmed($item) {
        return $item['confirmed'] ? 'Yes' : 'No';
    }

    public function get_sortable_columns() {
        return array(
            'email' => array('email', false),
            'subscribed_at' => array('subscribed_at', false)
        );
    }

    public function get_bulk_actions() {
        return array(
            'delete' => 'Delete'
        );
    }

    public function process_bulk_action() {
        global $wpdb;
        $table = $wpdb->prefix . 'my_newsletter_subscribers';

        if ( $this->current_action() === 'delete' && !empty($_POST['subscriber']) ) {
            $ids = array_map('intval', $_POST['subscriber']);
            foreach ($ids as $id) {
                $wpdb->delete($table, array('id'=>$id), array('%d'));
            }
        }

        if ( isset($_POST['export_csv']) ) {
            $this->export_csv();
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'my_newsletter_subscribers';
        $search = ( isset($_REQUEST['s']) ) ? wp_unslash(trim($_REQUEST['s'])) : '';

        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'subscribed_at';
        $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';

        $where = '';
        if ($search) {
            $where = $wpdb->prepare("WHERE email LIKE %s", '%'.$wpdb->esc_like($search).'%');
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ));

        $this->process_bulk_action();

        $items = $wpdb->get_results("SELECT * FROM $table $where ORDER BY $orderby $order LIMIT $offset,$per_page", ARRAY_A);

        $this->items = $items;
    }

    private function export_csv() {
        global $wpdb;
        $table = $wpdb->prefix.'my_newsletter_subscribers';
        $rows = $wpdb->get_results("SELECT email, subscribed_at, confirmed FROM $table", ARRAY_A);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="subscribers.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Email','Subscribed At','Confirmed'));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}
