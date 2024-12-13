<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists('WP_List_Table') ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class My_Newsletter_Contacts_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'contact',
            'plural'   => 'contacts',
            'ajax'     => false
        ));
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox"/>',
            'name' => 'Name',
            'email' => 'Email',
            'message' => 'Message',
            'subscribed' => 'Subscribed',
            'submitted_at' => 'Submitted At'
        );
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="contact[]" value="%d"/>', $item['id']);
    }

    protected function column_name($item) {
        return esc_html($item['name']);
    }

    protected function column_email($item) {
        return esc_html($item['email']);
    }

    protected function column_message($item) {
        return esc_html($item['message']);
    }

    protected function column_subscribed($item) {
        return $item['subscribed'] ? 'Yes' : 'No';
    }

    protected function column_submitted_at($item) {
        return esc_html($item['submitted_at']);
    }

    public function get_sortable_columns() {
        return array(
            'name' => array('name', false),
            'email' => array('email', false)
        );
    }

    public function get_bulk_actions() {
        return array(
            'delete' => 'Delete'
        );
    }

    public function process_bulk_action() {
        global $wpdb;
        $table = $wpdb->prefix . 'my_newsletter_contacts';

        if ( $this->current_action() === 'delete' && !empty($_POST['contact']) ) {
            $ids = array_map('intval', $_POST['contact']);
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
        $table = $wpdb->prefix . 'my_newsletter_contacts';
        $search = ( isset($_REQUEST['s']) ) ? wp_unslash(trim($_REQUEST['s'])) : '';

        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'submitted_at';
        $order   = (!empty($_REQUEST['order'])) ? sanitize_text_field($_REQUEST['order']) : 'DESC';

        $where = '';
        if ($search) {
            $where = $wpdb->prepare("WHERE name LIKE %s OR email LIKE %s OR message LIKE %s", '%'.$wpdb->esc_like($search).'%', '%'.$wpdb->esc_like($search).'%', '%'.$wpdb->esc_like($search).'%');
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1)*$per_page;

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
        $table = $wpdb->prefix.'my_newsletter_contacts';
        $rows = $wpdb->get_results("SELECT name,email,message,subscribed,submitted_at FROM $table", ARRAY_A);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="contacts.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Name','Email','Message','Subscribed','Submitted At'));
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}
