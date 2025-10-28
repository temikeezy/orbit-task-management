<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Submissions {
    public static function init() {
        add_action('admin_post_otm_score_submission', [__CLASS__, 'handle_score']);
        // Frontend moderation actions (non-AJAX)
        add_action('admin_post_otm_mod_approve', [__CLASS__, 'handle_mod_approve']);
        add_action('admin_post_otm_mod_reject', [__CLASS__, 'handle_mod_reject']);
        add_action('admin_post_otm_mod_request', [__CLASS__, 'handle_mod_request']);
    }
    
    public static function render() {
        if ( ! current_user_can('otm_moderate_submissions') ) {
            wp_die('Insufficient permissions');
        }
        
        global $wpdb;
        
        // Get filter parameters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $task_filter = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 20;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        
        // Validate per_page
        $allowed_per_page = [10, 20, 50, 100];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 20;
        }
        
        // Validate orderby
        $allowed_orderby = ['id', 'created_at', 'status', 'awarded_points', 'task_title', 'user_name'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at';
        }
        
        // Validate order
        if (!in_array(strtoupper($order), ['ASC', 'DESC'])) {
            $order = 'DESC';
        }
        
        // Build the query
        $table = $wpdb->prefix . 'otm_submissions';
        $posts_table = $wpdb->posts;
        $users_table = $wpdb->users;
        
        $where_conditions = [];
        $join_conditions = [];
        $params = [];
        
        // Add task filter
        if ($task_filter > 0) {
            $where_conditions[] = "s.task_id = %d";
            $params[] = $task_filter;
        }
        
        // Add status filter
        if ($status_filter && in_array($status_filter, ['submitted', 'approved', 'rejected', 'changes_requested'])) {
            $where_conditions[] = "s.status = %s";
            $params[] = $status_filter;
        }
        
        // Add search functionality
        if ($search) {
            $where_conditions[] = "(p.post_title LIKE %s OR u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Build WHERE clause
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Build ORDER BY clause
        $orderby_clause = '';
        switch ($orderby) {
            case 'task_title':
                $orderby_clause = 'ORDER BY p.post_title ' . $order;
                break;
            case 'user_name':
                $orderby_clause = 'ORDER BY u.display_name ' . $order;
                break;
            default:
                $orderby_clause = 'ORDER BY s.' . $orderby . ' ' . $order;
        }
        
        // Get total count for pagination
        $count_sql = "
            SELECT COUNT(*)
            FROM $table s
            LEFT JOIN $posts_table p ON p.ID = s.task_id
            LEFT JOIN $users_table u ON u.ID = s.user_id
            $where_clause
        ";
        
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        
        $total_items = $wpdb->get_var($count_sql);
        $total_pages = ceil($total_items / $per_page);
        
        // Calculate offset
        $offset = ($paged - 1) * $per_page;
        
        // Get submissions with pagination
        $sql = "
            SELECT s.*, p.post_title as task_title, u.display_name, u.user_login, u.user_email
            FROM $table s
            LEFT JOIN $posts_table p ON p.ID = s.task_id
            LEFT JOIN $users_table u ON u.ID = s.user_id
            $where_clause
            $orderby_clause
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $per_page;
        $params[] = $offset;
        $sql = $wpdb->prepare($sql, $params);
        
        $submissions = $wpdb->get_results($sql);
        
        // Get available tasks for filter dropdown
        $tasks = get_posts([
            'post_type' => 'otm_task',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        // Get status counts for filter tabs
        $status_counts = [];
        $statuses = ['submitted', 'approved', 'rejected', 'changes_requested'];
        foreach ($statuses as $status) {
            $count_sql = "SELECT COUNT(*) FROM $table WHERE status = %s";
            $status_counts[$status] = $wpdb->get_var($wpdb->prepare($count_sql, $status));
        }
        
        // Render the page
        echo '<div class="wrap otm-submissions-wrap">';
        echo '<h1 class="wp-heading-inline">'.esc_html__('Submissions','otm').'</h1>';
        
        // Add new task button
        echo '<a href="'.esc_url(admin_url('post-new.php?post_type=otm_task')).'" class="page-title-action">'.esc_html__('Add New Task','otm').'</a>';
        
        echo '<hr class="wp-header-end">';
        
        // Filters and search
        echo '<div class="otm-submissions-filters">';
        echo '<form method="get" action="" class="otm-filter-form">';
        echo '<input type="hidden" name="page" value="otm-submissions">';
        
        // Search box
        echo '<div class="otm-search-box">';
        echo '<label for="otm-search" class="screen-reader-text">'.esc_html__('Search submissions','otm').'</label>';
        echo '<input type="search" id="otm-search" name="s" value="'.esc_attr($search).'" placeholder="'.esc_attr__('Search tasks, users...','otm').'" class="otm-search-input">';
        echo '<input type="submit" class="button" value="'.esc_attr__('Search','otm').'">';
        echo '</div>';
        
        // Filter controls
        echo '<div class="otm-filter-controls">';
        
        // Task filter dropdown
        echo '<select name="task_id" class="otm-task-filter">';
        echo '<option value="">'.esc_html__('All Tasks','otm').'</option>';
        foreach ($tasks as $task) {
            $selected = selected($task_filter, $task->ID, false);
            echo '<option value="'.esc_attr($task->ID).'" '.$selected.'>'.esc_html($task->post_title).'</option>';
        }
        echo '</select>';
        
        // Status filter
        echo '<select name="status" class="otm-status-filter">';
        echo '<option value="">'.esc_html__('All Statuses','otm').'</option>';
        echo '<option value="submitted" '.selected($status_filter, 'submitted', false).'>'.esc_html__('Pending','otm').' ('.$status_counts['submitted'].')</option>';
        echo '<option value="approved" '.selected($status_filter, 'approved', false).'>'.esc_html__('Approved','otm').' ('.$status_counts['approved'].')</option>';
        echo '<option value="rejected" '.selected($status_filter, 'rejected', false).'>'.esc_html__('Rejected','otm').' ('.$status_counts['rejected'].')</option>';
        echo '<option value="changes_requested" '.selected($status_filter, 'changes_requested', false).'>'.esc_html__('Changes Requested','otm').' ('.$status_counts['changes_requested'].')</option>';
        echo '</select>';
        
        // Per page selector
        echo '<select name="per_page" class="otm-per-page">';
        foreach ($allowed_per_page as $num) {
            $selected = selected($per_page, $num, false);
            echo '<option value="'.esc_attr($num).'" '.$selected.'>'.sprintf(esc_html__('%d per page','otm'), $num).'</option>';
        }
        echo '</select>';
        
        echo '<input type="submit" class="button" value="'.esc_attr__('Filter','otm').'">';
        
        // Clear filters
        if ($search || $task_filter || $status_filter) {
            echo '<a href="'.esc_url(admin_url('admin.php?page=otm-submissions')).'" class="button">'.esc_html__('Clear','otm').'</a>';
        }
        
        echo '</div>';
        echo '</form>';
        echo '</div>';
        
        // Results summary
        if ($total_items > 0) {
            $start = $offset + 1;
            $end = min($offset + $per_page, $total_items);
            echo '<div class="otm-results-summary">';
            echo sprintf(esc_html__('Showing %1$d to %2$d of %3$d submissions','otm'), $start, $end, $total_items);
            echo '</div>';
        }
        
        // Submissions table
        echo '<table class="wp-list-table widefat fixed striped submissions">';
        echo '<thead>';
        echo '<tr>';
        
        // Sortable column headers
        $sortable_columns = [
            'id' => 'ID',
            'task_title' => 'Task',
            'user_name' => 'User',
            'status' => 'Status',
            'awarded_points' => 'Points',
            'created_at' => 'Submitted'
        ];
        
        foreach ($sortable_columns as $column => $label) {
            $current_order = ($orderby === $column) ? $order : 'ASC';
            $new_order = ($orderby === $column && $order === 'ASC') ? 'DESC' : 'ASC';
            $sort_url = add_query_arg([
                'page' => 'otm-submissions',
                's' => $search,
                'task_id' => $task_filter,
                'status' => $status_filter,
                'per_page' => $per_page,
                'orderby' => $column,
                'order' => $new_order,
                'paged' => 1
            ], admin_url('admin.php'));
            
            $class = ($orderby === $column) ? 'sorted ' . strtolower($order) : 'sortable';
            echo '<th class="'.$class.'">';
            echo '<a href="'.esc_url($sort_url).'">';
            echo esc_html($label);
            if ($orderby === $column) {
                echo '<span class="dashicons dashicons-arrow-'.($order === 'ASC' ? 'up' : 'down').'"></span>';
            }
            echo '</a>';
            echo '</th>';
        }
        
        echo '<th>Content</th><th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if (empty($submissions)) {
            echo '<tr><td colspan="7" class="no-items">'.esc_html__('No submissions found.','otm').'</td></tr>';
        } else {
            foreach ($submissions as $submission) {
            echo '<tr>';
                echo '<td>'.intval($submission->id).'</td>';
                echo '<td>';
                if ($submission->task_title) {
                    echo '<a href="'.esc_url(get_edit_post_link($submission->task_id)).'">'.esc_html($submission->task_title).'</a>';
                } else {
                    echo '<em>'.esc_html__('Task deleted','otm').'</em>';
                }
                echo '</td>';
                echo '<td>';
                if ($submission->display_name) {
                    echo '<a href="'.esc_url(get_edit_user_link($submission->user_id)).'">'.esc_html($submission->display_name).'</a>';
                    echo '<br><small>'.esc_html($submission->user_email).'</small>';
                } else {
                    echo '<em>'.esc_html__('User deleted','otm').'</em>';
                }
                echo '</td>';
                echo '<td>';
                $status_class = 'otm-status-' . $submission->status;
                echo '<span class="otm-status-badge '.$status_class.'">'.esc_html(ucfirst($submission->status)).'</span>';
                echo '</td>';
                echo '<td>'.intval($submission->awarded_points).'</td>';
                echo '<td>';
                echo '<time datetime="'.esc_attr($submission->created_at).'">'.esc_html(human_time_diff(strtotime($submission->created_at), current_time('timestamp'))).' '.esc_html__('ago','otm').'</time>';
                echo '</td>';
                echo '<td style="max-width:280px;">';
                $summary_parts = [];
                if ( ! empty($submission->text_content) ) {
                    $summary_parts[] = wp_kses_post( wp_trim_words( $submission->text_content, 20, '…' ) );
                }
                if ( ! empty($submission->urls_json) ) {
                    $urls = json_decode($submission->urls_json, true);
                    if ( is_array($urls) && ! empty($urls) ) {
                        $first = esc_url($urls[0]);
                        $summary_parts[] = '<a href="'.esc_url($first).'" target="_blank" rel="noopener">'.esc_html__('Link','otm').'</a>';
                    }
                }
                if ( ! empty($submission->files_json) ) {
                    $files = json_decode($submission->files_json, true);
                    if ( is_array($files) && ! empty($files) ) {
                        $links = [];
                        foreach ( $files as $f ) { $links[] = '<a href="'.esc_url($f).'" target="_blank" rel="noopener">'.esc_html__('File','otm').'</a>'; }
                        $summary_parts[] = implode(' , ', $links);
                    }
                }
                echo $summary_parts ? implode(' • ', $summary_parts) : '<em>'.esc_html__('No content','otm').'</em>';
                echo '</td>';
            echo '<td>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="otm-action-form">';
            echo '<input type="hidden" name="action" value="otm_score_submission" />';
                wp_nonce_field('otm_score_submission_'.$submission->id);
                echo '<input type="hidden" name="submission_id" value="'.intval($submission->id).'" />';
                echo '<div class="otm-action-controls">';
                echo '<input type="number" name="points" value="'.intval($submission->awarded_points).'" min="0" max="1000" class="otm-points-input" />';
                echo '<select name="status" class="otm-status-select">';
                echo '<option value="submitted" '.selected($submission->status, 'submitted', false).'>'.esc_html__('Pending','otm').'</option>';
                echo '<option value="approved" '.selected($submission->status, 'approved', false).'>'.esc_html__('Approve','otm').'</option>';
                echo '<option value="rejected" '.selected($submission->status, 'rejected', false).'>'.esc_html__('Reject','otm').'</option>';
                echo '<option value="changes_requested" '.selected($submission->status, 'changes_requested', false).'>'.esc_html__('Request Changes','otm').'</option>';
                echo '</select>';
                echo '<input type="submit" class="button button-primary button-small" value="'.esc_attr__('Update','otm').'">';
                echo '</div>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Pagination
        if ($total_pages > 1) {
            echo '<div class="tablenav">';
            echo '<div class="tablenav-pages">';
            
            $pagination_args = [
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $paged,
                'type' => 'plain'
            ];
            
            // Preserve filters in pagination
            $current_url = add_query_arg([
                'page' => 'otm-submissions',
                's' => $search,
                'task_id' => $task_filter,
                'status' => $status_filter,
                'per_page' => $per_page,
                'orderby' => $orderby,
                'order' => $order
            ], admin_url('admin.php'));
            
            $pagination_args['base'] = $current_url . '&paged=%#%';
            
            echo paginate_links($pagination_args);
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    public static function handle_score() {
        if ( ! current_user_can('otm_moderate_submissions') ) wp_die('Insufficient permissions');
        $id = absint(isset($_POST['submission_id'])?$_POST['submission_id']:0);
        check_admin_referer('otm_score_submission_'.$id);
        $points = intval(isset($_POST['points'])?$_POST['points']:0);
        $status = sanitize_text_field(isset($_POST['status'])?$_POST['status']:'approved');

        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
        if ( ! $row ) wp_die('Submission not found');

        $approved_at = ($status === 'approved') ? current_time('mysql', 1) : null;
        $wpdb->update($table, [
            'awarded_points' => $points,
            'status' => $status,
            'moderator_id' => get_current_user_id(),
            'approved_at' => $approved_at,
            'updated_at' => current_time('mysql', 1),
        ], ['id' => $id]);

        if ( $status === 'approved' ) {
            otm_points_service()->set_points_for_submission( (int)$row->user_id, (int)$row->task_id, (int)$id, (int)$points );
            if ( function_exists('gamipress_trigger_event') ) {
                gamipress_trigger_event( 'otm_submission_approved', (int) $row->user_id, [
                    'task_id' => (int) $row->task_id,
                    'submission_id' => (int) $id,
                ] );
            }
        } else {
            otm_points_service()->set_points_for_submission( (int)$row->user_id, (int)$row->task_id, (int)$id, 0 );
        }
        // Invalidate leaderboard cache by bumping cache buster
        update_option('otm_cache_buster', time());

        wp_redirect( admin_url('admin.php?page=otm-submissions') ); exit;
    }
    
    private static function update_submission_status_points($id, $status, $points) {
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $id));
        if ( ! $row ) wp_die('Submission not found');
        $approved_at = ($status === 'approved') ? current_time('mysql', 1) : null;
        $wpdb->update($table, [
            'awarded_points' => (int)$points,
            'status' => $status,
            'moderator_id' => get_current_user_id(),
            'approved_at' => $approved_at,
            'updated_at' => current_time('mysql', 1),
        ], ['id' => $id]);
        if ( $status === 'approved' ) {
            otm_points_service()->set_points_for_submission( (int)$row->user_id, (int)$row->task_id, (int)$row->id, (int)$points );
        } else {
            otm_points_service()->set_points_for_submission( (int)$row->user_id, (int)$row->task_id, (int)$row->id, 0 );
        }
        update_option('otm_cache_buster', time());
    }
    
    public static function handle_mod_approve() {
        if ( ! current_user_can('otm_moderate_submissions') ) wp_die('Insufficient permissions');
        $id = absint(isset($_POST['submission_id'])?$_POST['submission_id']:0);
        check_admin_referer('otm_mod_'.$id);
        $points = intval(isset($_POST['points'])?$_POST['points']:0);
        self::update_submission_status_points($id, 'approved', $points);
        wp_safe_redirect( wp_get_referer() ?: admin_url('admin.php?page=otm-submissions') ); exit;
    }
    
    public static function handle_mod_reject() {
        if ( ! current_user_can('otm_moderate_submissions') ) wp_die('Insufficient permissions');
        $id = absint(isset($_POST['submission_id'])?$_POST['submission_id']:0);
        check_admin_referer('otm_mod_'.$id);
        self::update_submission_status_points($id, 'rejected', 0);
        wp_safe_redirect( wp_get_referer() ?: admin_url('admin.php?page=otm-submissions') ); exit;
    }
    
    public static function handle_mod_request() {
        if ( ! current_user_can('otm_moderate_submissions') ) wp_die('Insufficient permissions');
        $id = absint(isset($_POST['submission_id'])?$_POST['submission_id']:0);
        check_admin_referer('otm_mod_'.$id);
        self::update_submission_status_points($id, 'changes_requested', 0);
        wp_safe_redirect( wp_get_referer() ?: admin_url('admin.php?page=otm-submissions') ); exit;
    }
}