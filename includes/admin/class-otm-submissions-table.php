<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OTM_Submissions_Table extends WP_List_Table {

    private $status_filter = '';
    private $task_filter = 0;

    public function __construct() {
        parent::__construct([
            'singular' => 'otm_submission',
            'plural'   => 'otm_submissions',
            'ajax'     => false,
        ]);
        $this->status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $this->task_filter = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID','otm'),
            'task' => __('Task','otm'),
            'user' => __('User','otm'),
            'status' => __('Status','otm'),
            'points' => __('Points','otm'),
            'created' => __('Submitted','otm'),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'id' => ['id', false],
            'created' => ['created_at', false],
            'points' => ['awarded_points', false],
        ];
    }

    protected function column_cb( $item ) {
        return '<input type="checkbox" name="ids[]" value="'.intval($item->id).'" />';
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
                $actions = [
                    'reply' => '<a href="#" class="otm-reply-action" data-id="'.intval($item->id).'">'.__('Reply','otm').'</a>'
                ];
                return intval($item->id) . $this->row_actions( $actions );
            case 'task':
                $title = get_the_title($item->task_id);
                return $title ? '<a href="'.esc_url(get_edit_post_link($item->task_id)).'">'.esc_html($title).'</a>' : '<em>'.esc_html__('Task deleted','otm').'</em>';
            case 'user':
                $u = get_user_by('id', $item->user_id);
                return $u ? '<a href="'.esc_url(get_edit_user_link($u->ID)).'">'.esc_html($u->display_name).'</a><br><small>'.esc_html($u->user_email).'</small>' : '<em>'.esc_html__('User deleted','otm').'</em>';
            case 'status':
                return '<span class="otm-status-badge otm-status-'.esc_attr($item->status).'">'.esc_html(ucfirst($item->status)).'</span>';
            case 'points':
                return intval($item->awarded_points);
            case 'created':
                return '<time datetime="'.esc_attr($item->created_at).'">'.esc_html(human_time_diff(strtotime($item->created_at), current_time('timestamp'))).' '.esc_html__('ago','otm').'</time>';
            default:
                return '';
        }
    }

    public function get_bulk_actions() {
        return [
            'approve' => __('Approve','otm'),
            'reject' => __('Reject','otm'),
            'set_points' => __('Set Points…','otm'),
        ];
    }

    public function process_bulk_action() {
        if ( empty($_POST['ids']) || ! is_array($_POST['ids']) ) return;
        if ( ! current_user_can('otm_moderate_submissions') ) return;
        check_admin_referer('bulk-otm_submissions');
        $action = $this->current_action();
        if ( ! in_array($action, ['approve','reject','set_points'], true) ) return;
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $bulk_points = isset($_POST['bulk_points']) ? intval($_POST['bulk_points']) : null;
        foreach ( array_map('absint', $_POST['ids']) as $id ) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
            if ( ! $row ) continue;
            if ( $action === 'approve' ) {
                $wpdb->update($table, [ 'status' => 'approved', 'approved_at' => current_time('mysql',1), 'updated_at' => current_time('mysql',1) ], ['id' => $id]);
                otm_points_service()->set_points_for_submission( (int)$row->user_id, (int)$row->task_id, (int)$id, (int)$row->awarded_points );
                do_action('otm_submission_approved', (int)$row->user_id, ['task_id'=>(int)$row->task_id,'submission_id'=>(int)$id,'points'=>(int)$row->awarded_points]);
            } else if ( $action === 'reject' ) {
                $wpdb->update($table, [ 'status' => 'rejected', 'approved_at' => null, 'updated_at' => current_time('mysql',1) ], ['id' => $id]);
                otm_points_service()->set_points_for_submission( (int)$row->user_id, (int)$row->task_id, (int)$id, 0 );
                do_action('otm_submission_rejected', (int)$row->user_id, ['task_id'=>(int)$row->task_id,'submission_id'=>(int)$id,'status'=>'rejected']);
            } else if ( $action === 'set_points' && $bulk_points !== null ) {
                $wpdb->update($table, [ 'awarded_points' => $bulk_points, 'updated_at' => current_time('mysql',1) ], ['id' => $id]);
                // Only adjust totals immediately if already approved
                if ( $row->status === 'approved' ) {
                    otm_points_service()->set_points_for_submission( (int)$row->user_id, (int)$row->task_id, (int)$id, (int)$bulk_points );
                }
            }
        }
        update_option('otm_cache_buster', time());
    }

    public function prepare_items() {
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';

        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? (strtoupper($_GET['order'])==='ASC'?'ASC':'DESC') : 'DESC';

        $where = [];
        $params = [];
        if ( $this->task_filter > 0 ) { $where[] = 'task_id = %d'; $params[] = $this->task_filter; }
        if ( $this->status_filter && in_array($this->status_filter, ['submitted','approved','rejected','changes_requested'], true) ) { $where[] = 'status = %s'; $params[] = $this->status_filter; }
        $where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

        $total_items = (int) $wpdb->get_var( $params ? $wpdb->prepare("SELECT COUNT(*) FROM $table $where_sql", $params) : "SELECT COUNT(*) FROM $table" );

        $offset = ($paged-1) * $per_page;
        $sql = "SELECT * FROM $table $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $params2 = $params; $params2[] = $per_page; $params2[] = $offset;
        $this->items = $wpdb->get_results( $wpdb->prepare($sql, $params2) );

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'id'];
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
}


