<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Frontend {
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
        add_shortcode('otm_task_submit', [__CLASS__, 'shortcode_submit']);
        add_shortcode('otm_task_create', [__CLASS__, 'shortcode_create']);
        add_action('admin_post_nopriv_otm_submit_task', [__CLASS__, 'handle_submit']);
        add_action('admin_post_otm_submit_task', [__CLASS__, 'handle_submit']);
        add_action('admin_post_otm_create_task', [__CLASS__, 'handle_create']);
        add_filter('template_include', [__CLASS__, 'template_loader']);
        add_action('admin_notices', [__CLASS__, 'admin_create_notice']);
    }
    public static function register_assets() { wp_register_style('otm-frontend', OTM_URL . 'assets/css/frontend.css', [], OTM_VERSION); wp_register_script('otm-frontend', OTM_URL . 'assets/js/frontend.js', [], OTM_VERSION, true); }
    private static function can_submit_task($task_id, $user_id) {
        $gid = intval(get_post_meta($task_id, '_otm_stream_id', true));
        $require_membership = (bool) OTM_Settings::get('require_membership', false);
        if ( $require_membership && $gid && function_exists('groups_is_user_member') ) {
            return groups_is_user_member($user_id, $gid);
        }
        return true;
    }
    public static function shortcode_submit($atts) {
        if ( ! is_singular('otm_task') ) {
            return '<p>Please open the task page to submit.</p>';
        }
        // Moderators/Admins should not submit tasks; they only create & moderate.
        if ( current_user_can('otm_manage_tasks') || current_user_can('otm_moderate_submissions') || current_user_can('manage_options') ) { return ''; }
        // Moderators/Admins should not submit tasks; they only create & moderate.
        if ( current_user_can('otm_manage_tasks') || current_user_can('otm_moderate_submissions') || current_user_can('manage_options') ) {
            return '';
        }
        wp_enqueue_style('otm-frontend'); wp_enqueue_script('otm-frontend');
        $atts = shortcode_atts(['task_id'=>get_the_ID()], $atts, 'otm_task_submit');
        $task_id = intval($atts['task_id']);
        if ( ! is_user_logged_in() ) return '<p>Please log in to submit.</p>';
        if ( ! $task_id || get_post_type($task_id) !== 'otm_task' ) return '<p>Invalid task.</p>';
        // Membership requirement (if BuddyBoss active and settings require membership)
        $require_membership = (bool) OTM_Settings::get('require_membership', false);
        $gid = intval(get_post_meta($task_id, '_otm_stream_id', true));
        if ( $require_membership && $gid && function_exists('groups_is_user_member') ) {
            if ( ! groups_is_user_member(get_current_user_id(), $gid) ) {
                $join_url = function_exists('bp_get_group_permalink') && function_exists('groups_get_group') ? bp_get_group_permalink( groups_get_group($gid) ) : home_url();
                return '<div class="otm-card"><p>You must join this Stream to submit.</p><p><a class="otm-btn" href="'.esc_url($join_url).'">Join Stream</a></p></div>';
            }
        }
        if ( ! self::can_submit_task($task_id, get_current_user_id()) ) return '<p>You must be a member of this Stream to submit.</p>';

        // Existing submission check
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE task_id=%d AND user_id=%d", $task_id, get_current_user_id()));
        if ( $existing ) {
            $status = esc_html($existing->status);
            $pts = intval($existing->awarded_points);
            return '<div class="otm-existing"><p><strong>Submission status:</strong> ' . $status . ' ' . ($pts? '(Points: '.$pts.')' : '') . '</p></div>';
        }

        $formats = (array) get_post_meta($task_id, '_otm_formats', true);
        $max_points = intval(get_post_meta($task_id, '_otm_max_points', true));
        $deadline = esc_html(get_post_meta($task_id, '_otm_deadline', true));
        $allowed = [];
        foreach (['text'=>'Text','url'=>'URL','file'=>'File'] as $k=>$label) { if (!empty($formats[$k])) { $allowed[] = $label; } }
        echo '<div class="otm-task-meta"><p><strong>Max Points:</strong> '.($max_points?$max_points:0).'</p>'.($deadline? '<p><strong>Deadline:</strong> '.$deadline.'</p>' : '').'<p><strong>Allowed fields:</strong> '.( $allowed ? esc_html(implode(', ',$allowed)) : '—' ).'</p></div>';
        ob_start(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="otm-form">
            <input type="hidden" name="action" value="otm_submit_task">
            <?php wp_nonce_field('otm_submit_task_'.$task_id); ?>
            <input type="hidden" name="task_id" value="<?php echo esc_attr($task_id); ?>">
            <?php if (!empty($formats['text'])): ?>
                <p><label>Text</label><textarea name="text_content" rows="5" class="widefat"></textarea></p>
            <?php endif; ?>
            <?php if (!empty($formats['url'])): ?>
                <p><label>URL</label><input type="url" name="url" class="widefat" placeholder="https://"></p>
            <?php endif; ?>
            <?php if (!empty($formats['file'])): ?>
                <p><label>File</label><input type="file" name="file"></p>
            <?php endif; ?>
            <p><button class="button button-primary">Submit Task</button></p>
        </form>
        <?php
        return ob_get_clean();
    }
    public static function handle_submit() {
        if ( ! is_user_logged_in() ) wp_die('Please log in');
        $task_id = absint(isset($_POST['task_id'])?$_POST['task_id']:0);
        check_admin_referer('otm_submit_task_'.$task_id);
        $user_id = get_current_user_id();
        if ( ! $task_id || get_post_type($task_id) !== 'otm_task' ) wp_die('Invalid task');
        // Block after deadline
        $deadline = get_post_meta($task_id, '_otm_deadline', true);
        if ( $deadline ) {
            $deadline_ts = strtotime($deadline.' UTC');
            if ( $deadline_ts && current_time('timestamp', true) > $deadline_ts ) {
                wp_die('The deadline for this task has passed.');
            }
        }
        // Membership requirement
        $require_membership = (bool) OTM_Settings::get('require_membership', false);
        $gid = intval(get_post_meta($task_id, '_otm_stream_id', true));
        if ( $require_membership && $gid && function_exists('groups_is_user_member') && ! groups_is_user_member($user_id, $gid) ) {
            wp_die('You must join this Stream to submit.');
        }
        if ( ! self::can_submit_task($task_id, $user_id) ) wp_die('Not a member of Stream');
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE task_id=%d AND user_id=%d", $task_id, $user_id));
        if ( $exists ) { wp_redirect( wp_get_referer() ?: home_url() ); exit; }
        $text = isset($_POST['text_content']) ? wp_kses_post($_POST['text_content']) : null;
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : null;
        $files_json = null;
        if ( ! empty($_FILES['file']['name']) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $up = wp_handle_upload($_FILES['file'], ['test_form' => false]);
            if ( empty($up['error']) ) { $files_json = wp_json_encode([$up['url']]); }
        }
        $wpdb->insert($table, [
            'task_id' => $task_id,
            'user_id' => $user_id,
            'text_content' => $text,
            'urls_json' => $url ? wp_json_encode([$url]) : null,
            'files_json' => $files_json,
            'status' => 'submitted',
            'awarded_points' => 0,
            'created_at' => current_time('mysql', 1),
        ]);
        wp_redirect( get_permalink( $task_id ) . '?submitted=1' ); exit;
    }
    public static function shortcode_create($atts) {
        wp_enqueue_style('otm-frontend'); wp_enqueue_script('otm-frontend');
        if ( ! current_user_can('otm_manage_tasks') ) return '<p>Insufficient permissions.</p>';
        $atts = shortcode_atts(['stream_id'=>0], $atts, 'otm_task_create');
        $stream_id = intval($atts['stream_id']);
        ob_start(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="otm-form">
            <input type="hidden" name="action" value="otm_create_task">
            <?php wp_nonce_field('otm_create_task'); ?>
            <p><label>Title <input type="text" name="title" class="widefat" required></label></p>
            <p><label>Description <textarea name="content" rows="6" class="widefat" required></textarea></label></p>
            <p><label>Stream (Group ID) <input type="number" name="stream_id" value="<?php echo esc_attr($stream_id ? $stream_id : 0); ?>" required></label></p>
            <p><label>Max Points <input type="number" name="max_points" value="10" min="0"></label></p>
            <p><label>Deadline (Y-m-d H:i) <input type="text" name="deadline" placeholder="2025-10-12 23:59"></label></p>
            <p>Allowed Submission Fields:<br/>
                <label><input type="checkbox" name="formats[text]" value="1" checked> Text</label>
                <label><input type="checkbox" name="formats[url]" value="1"> URL</label>
                <label><input type="checkbox" name="formats[file]" value="1"> File</label>
            </p>
            <p><button class="button button-primary">Create Task</button></p>
        </form>
        <?php
        return ob_get_clean();
    }
    public static function handle_create() {
        if ( ! current_user_can('otm_manage_tasks') ) wp_die('Insufficient permissions');
        check_admin_referer('otm_create_task');
        $post_id = wp_insert_post([
            'post_type' => 'otm_task',
            'post_title' => sanitize_text_field(isset($_POST['title'])?$_POST['title']:''),
            'post_content' => wp_kses_post(isset($_POST['content'])?$_POST['content']:''),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);
        update_post_meta($post_id, '_otm_stream_id', absint(isset($_POST['stream_id'])?$_POST['stream_id']:0));
        update_post_meta($post_id, '_otm_max_points', absint(isset($_POST['max_points'])?$_POST['max_points']:0));
        // Normalize deadline from datetime-local to Y-m-d H:i
        $deadline_raw = isset($_POST['deadline']) ? sanitize_text_field($_POST['deadline']) : '';
        if ( $deadline_raw ) {
            // Expecting format YYYY-MM-DDTHH:MM
            $deadline_ts = strtotime($deadline_raw);
            $deadline_val = $deadline_ts ? gmdate('Y-m-d H:i', $deadline_ts) : $deadline_raw;
        } else {
            $deadline_val = '';
        }
        update_post_meta($post_id, '_otm_deadline', $deadline_val);
        $formats = ['text'=>!empty($_POST['formats']['text']),'url'=>!empty($_POST['formats']['url']),'file'=>!empty($_POST['formats']['file'])];
        update_post_meta($post_id, '_otm_formats', $formats);
        // Success message with View Task link
        $view_link = get_permalink($post_id);
        $redirect = add_query_arg([
            'otm_created' => 1,
            'view_task' => rawurlencode($view_link),
        ], admin_url('post.php?action=edit&post='.$post_id));
        wp_redirect( $redirect ); exit;
    }

    public static function template_loader( $template ) {
        if ( is_singular('otm_task') ) {
            wp_enqueue_style('otm-frontend');
            wp_enqueue_script('otm-frontend');
            // 1) Theme override: /otm/single-otm_task.php
            $theme_path = trailingslashit( get_stylesheet_directory() ) . 'otm/single-otm_task.php';
            if ( file_exists( $theme_path ) ) {
                return $theme_path;
            }
            // 2) Plugin template fallback
            $plugin_template = OTM_DIR . 'includes/frontend/templates/single-otm_task.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public static function admin_create_notice() {
        if ( ! is_admin() ) return;
        if ( empty($_GET['otm_created']) || empty($_GET['view_task']) ) return;
        if ( ! function_exists('get_current_screen') ) return;
        $screen = get_current_screen();
        if ( ! $screen || $screen->base !== 'post' ) return;
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ( ! $post_id || get_post_type($post_id) !== 'otm_task' ) return;
        $view_link = esc_url_raw( wp_unslash( $_GET['view_task'] ) );
        if ( ! $view_link ) return;
        echo '<div class="notice notice-success is-dismissible"><p>'.sprintf(
            '%s <a href="%s" target="_blank" rel="noopener">%s</a>.',
            esc_html__('Task created successfully.', 'otm'),
            esc_url($view_link),
            esc_html__('View Task', 'otm')
        ).'</p></div>';
    }
}
