<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Task_CPT {
    public static function init() {
        add_action('init', [__CLASS__, 'register']);
        add_action('add_meta_boxes', [__CLASS__, 'metaboxes']);
        add_action('save_post_otm_task', [__CLASS__, 'save'], 10, 2);
    }
    public static function register() {
        register_post_type('otm_task', [
            'label' => __('OTM Tasks','otm'),
            'labels' => [
                'name' => __('OTM Tasks','otm'),
                'singular_name' => __('OTM Task','otm'),
                'menu_name' => __('OTM Tasks','otm'),
                'name_admin_bar' => __('Task','otm'),
                'add_new' => __('Add Task','otm'),
                'add_new_item' => __('Add New Task','otm'),
                'new_item' => __('New Task','otm'),
                'edit_item' => __('Edit Task','otm'),
                'view_item' => __('View Task','otm'),
                'all_items' => __('All Tasks','otm'),
                'search_items' => __('Search Tasks','otm'),
                'parent_item_colon' => __('Parent Tasks:','otm'),
                'not_found' => __('No tasks found.','otm'),
                'not_found_in_trash' => __('No tasks found in Trash.','otm'),
            ],
            'public' => true,
            'has_archive' => 'tasks',
            'rewrite' => [ 'slug' => 'tasks', 'with_front' => false ],
            'show_ui' => true,
            'menu_icon' => 'dashicons-yes-alt',
            'supports' => ['title','editor','author'],
            'capability_type' => 'post',
            'show_in_rest' => true,
        ]);
    }
    public static function metaboxes() {
        add_meta_box('otm_task_meta', __('Task Details','otm'), [__CLASS__, 'render_meta'], 'otm_task', 'normal', 'high');
        add_meta_box('otm_task_submissions', __('Submissions','otm'), [__CLASS__, 'render_submissions_meta'], 'otm_task', 'normal', 'default');
    }
    public static function render_meta($post) {
        $gid = get_post_meta($post->ID, '_otm_stream_id', true);
        $max = get_post_meta($post->ID, '_otm_max_points', true);
        $deadline = get_post_meta($post->ID, '_otm_deadline', true);
        $formats = (array) get_post_meta($post->ID, '_otm_formats', true);
        wp_nonce_field('otm_task_meta','otm_task_nonce');
        ?>
        <p><label>Stream (Group)</label>
        <?php if ( OTM_BB::is_active() ) {
            $groups = OTM_BB::groups_for_dropdown();
            echo '<select name="otm_stream_id">';
            echo '<option value="0">'.esc_html__('No Stream','otm').'</option>';
            foreach ( $groups as $id => $name ) {
                $selected = selected( (int)$gid, (int)$id, false );
                echo '<option value="'.esc_attr($id).'" '.$selected.'>'.esc_html($name).'</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="number" name="otm_stream_id" value="'.esc_attr($gid).'" min="0" />';
            echo '<p class="description">'.esc_html__('BuddyBoss/BuddyPress not active — enter numeric Stream ID.','otm').'</p>';
        } ?>
        </p>
        <p><label>Max Points <input type="number" name="otm_max_points" value="<?php echo esc_attr($max ? $max : (int) OTM_Settings::get('default_max_points', 10)); ?>" min="0" /></label></p>
        <p><label>Deadline <input type="datetime-local" name="otm_deadline" value="<?php echo esc_attr( $deadline ? date('Y-m-d\TH:i', strtotime($deadline)) : '' ); ?>" /></label></p>
        <p>Allowed Submission Fields:<br/>
            <label><input type="checkbox" name="otm_formats[text]" value="1" <?php checked(!empty($formats['text'])); ?> /> Text</label>
            <label><input type="checkbox" name="otm_formats[url]" value="1" <?php checked(!empty($formats['url'])); ?> /> URL</label>
            <label><input type="checkbox" name="otm_formats[file]" value="1" <?php checked(!empty($formats['file'])); ?> /> File</label>
        </p>
        <?php
    }
    public static function save($post_id, $post) {
        if ( ! isset($_POST['otm_task_nonce']) || ! wp_verify_nonce($_POST['otm_task_nonce'], 'otm_task_meta') ) return;
        update_post_meta($post_id, '_otm_stream_id', absint(isset($_POST['otm_stream_id'])?$_POST['otm_stream_id']:0));
        update_post_meta($post_id, '_otm_max_points', absint(isset($_POST['otm_max_points'])?$_POST['otm_max_points']:0));
        // Normalize datetime-local back to Y-m-d H:i
        $deadline_raw = isset($_POST['otm_deadline']) ? sanitize_text_field($_POST['otm_deadline']) : '';
        if ( $deadline_raw ) {
            $deadline_ts = strtotime($deadline_raw);
            $deadline_val = $deadline_ts ? gmdate('Y-m-d H:i', $deadline_ts) : $deadline_raw;
        } else {
            $deadline_val = '';
        }
        update_post_meta($post_id, '_otm_deadline', $deadline_val);
        $formats = ['text'=>!empty($_POST['otm_formats']['text']),'url'=>!empty($_POST['otm_formats']['url']),'file'=>!empty($_POST['otm_formats']['file'])];
        update_post_meta($post_id, '_otm_formats', $formats);
    }

    public static function render_submissions_meta($post) {
        global $wpdb;
        $table = $wpdb->prefix . 'otm_submissions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE task_id=%d ORDER BY created_at DESC LIMIT 20", $post->ID));

        echo '<div class="otm-task-submissions-metabox">';
        echo '<p><a class="button" href="'.esc_url( admin_url('admin.php?page=otm-submissions&task_id='.$post->ID) ).'">'.esc_html__('Open full submissions manager','otm').'</a></p>';

        if ( empty($rows) ) {
            echo '<p>'.esc_html__('No submissions yet.','otm').'</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>#</th><th>'.esc_html__('User','otm').'</th><th>'.esc_html__('Status','otm').'</th><th>'.esc_html__('Points','otm').'</th><th>'.esc_html__('Submitted','otm').'</th><th>'.esc_html__('Content','otm').'</th><th>'.esc_html__('Action','otm').'</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $user = get_user_by('id', $r->user_id);
            echo '<tr>';
            echo '<td>'.intval($r->id).'</td>';
            echo '<td>'.esc_html($user ? $user->display_name : ('#'.$r->user_id)).'</td>';
            echo '<td>'.esc_html(ucfirst($r->status)).'</td>';
            echo '<td>'.intval($r->awarded_points).'</td>';
            echo '<td><time datetime="'.esc_attr($r->created_at).'">'.esc_html(human_time_diff(strtotime($r->created_at), current_time('timestamp'))).' '.esc_html__('ago','otm').'</time></td>';
            echo '<td style="max-width:280px;">';
            $summary_parts = [];
            if ( ! empty($r->text_content) ) {
                $summary_parts[] = wp_kses_post( wp_trim_words( $r->text_content, 20, '…' ) );
            }
            if ( ! empty($r->urls_json) ) {
                $urls = json_decode($r->urls_json, true);
                if ( is_array($urls) && ! empty($urls) ) {
                    $first = esc_url($urls[0]);
                    $summary_parts[] = '<a href="'.esc_url($first).'" target="_blank" rel="noopener">'.esc_html__('Link','otm').'</a>';
                }
            }
            if ( ! empty($r->files_json) ) {
                $files = json_decode($r->files_json, true);
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
            wp_nonce_field('otm_score_submission_'.$r->id);
            echo '<input type="hidden" name="submission_id" value="'.intval($r->id).'" />';
            echo '<input type="number" name="points" value="'.intval($r->awarded_points).'" min="0" max="1000" style="width:90px;margin-right:6px" />';
            echo '<select name="status" style="margin-right:6px">';
            echo '<option value="submitted" '.selected($r->status, 'submitted', false).'>'.esc_html__('Pending','otm').'</option>';
            echo '<option value="approved" '.selected($r->status, 'approved', false).'>'.esc_html__('Approve','otm').'</option>';
            echo '<option value="rejected" '.selected($r->status, 'rejected', false).'>'.esc_html__('Reject','otm').'</option>';
            echo '<option value="changes_requested" '.selected($r->status, 'changes_requested', false).'>'.esc_html__('Request Changes','otm').'</option>';
            echo '</select>';
            echo '<input type="submit" class="button button-small button-primary" value="'.esc_attr__('Update','otm').'" />';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }
}
