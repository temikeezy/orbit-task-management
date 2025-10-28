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
}
