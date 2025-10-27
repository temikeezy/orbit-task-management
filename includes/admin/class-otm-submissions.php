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
        $task_filter = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;
        if ( ! current_user_can('otm_moderate_submissions') ) wp_die('Insufficient permissions');
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $sql = "SELECT * FROM $table";
        if ( $task_filter ) {
            $sql .= $wpdb->prepare(" WHERE task_id=%d", $task_filter);
        }
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        $rows = $wpdb->get_results($sql);
        echo '<div class="wrap"><h1>Submissions (Latest 50)'.($task_filter? ' &mdash; Task #'.intval($task_filter):'').'</h1>';
        echo '<form method="get" action="" id="otm-submissions-filter" style="margin:10px 0;">';
        echo '<input type="hidden" name="page" value="otm-submissions">';
        echo '<label>Filter by Task ID: <input type="number" name="task_id" value="'.($task_filter?$task_filter:'').'" style="width:120px"></label> ';
        echo '<button class="button">Apply</button> ';
        if ($task_filter) { echo '<a class="button" href="'.esc_url( admin_url('admin.php?page=otm-submissions') ).'">Clear</a>'; }
        echo '</form>';echo '<table class="widefat"><thead><tr><th>ID</th><th>Task</th><th>User</th><th>Status</th><th>Points</th><th>Actions</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $user = get_user_by('id', $r->user_id);
            $task = get_post($r->task_id);
            echo '<tr>';
            echo '<td>'.intval($r->id).'</td>';
            echo '<td>'.esc_html($task ? $task->post_title : ('#'.$r->task_id)).'</td>';
            echo '<td>'.esc_html($user ? $user->display_name : ('#'.$r->user_id)).'</td>';
            echo '<td>'.esc_html($r->status).'</td>';
            echo '<td>'.intval($r->awarded_points).'</td>';
            echo '<td>';
            echo '<form method="post" action="'.admin_url('admin-post.php').'">';
            echo '<input type="hidden" name="action" value="otm_score_submission" />';
            wp_nonce_field('otm_score_submission_'.$r->id);
            echo '<input type="hidden" name="submission_id" value="'.intval($r->id).'" />';
            echo '<input type="number" name="points" value="'.intval($r->awarded_points).'" style="width:80px" /> ';
            echo '<select name="status"><option value="approved">Approve</option><option value="rejected">Reject</option><option value="changes_requested">Request changes</option></select> ';
            echo '<input type="submit" class="button button-primary" value="Update">';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        if (empty($rows)) echo '<tr><td colspan="6">No submissions yet.</td></tr>';
        echo '</tbody></table></div>';
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
