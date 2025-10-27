<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }
    public static function menu() {
        add_menu_page('OTM', 'OTM', 'otm_manage_settings', 'otm-dashboard', [__CLASS__, 'dashboard'], 'dashicons-yes', 56);
        add_submenu_page('otm-dashboard', 'Settings', 'Settings', 'otm_manage_settings', 'otm-settings', [__CLASS__, 'render']);
        add_submenu_page('otm-dashboard', 'Submissions', 'Submissions', 'otm_moderate_submissions', 'otm-submissions', ['OTM_Submissions','render']);
    }
    public static function assets($hook) {
        if ( strpos($hook, 'otm') !== false ) {
            wp_enqueue_style('otm-admin', OTM_URL . 'assets/css/admin.css', [], OTM_VERSION);
            wp_enqueue_script('otm-admin', OTM_URL . 'assets/js/admin.js', ['jquery'], OTM_VERSION, true);
        }
    }
    public static function register() {
        register_setting('otm_settings_group', 'otm_settings');
        add_settings_section('otm_general', __('General', 'otm'), '__return_false', 'otm-settings');
        add_settings_field('otm_week', __('Week Settings', 'otm'), [__CLASS__, 'field_week'], 'otm-settings', 'otm_general');
        add_settings_field('otm_defaults', __('Task Defaults', 'otm'), [__CLASS__, 'field_defaults'], 'otm-settings', 'otm_general');
        add_settings_field('otm_labels', __('Labels', 'otm'), [__CLASS__, 'field_labels'], 'otm-settings', 'otm_general');
    }
    public static function get($key, $default=null) {
        $opts = get_option('otm_settings', []);
        return isset($opts[$key]) ? $opts[$key] : $default;
    }
    public static function dashboard() {
        global $wpdb;
        
        // Get stats
        $tasks_count = wp_count_posts('otm_task');
        $total_tasks = $tasks_count->publish;
        
        $submissions_table = $wpdb->prefix . 'otm_submissions';
        $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table");
        $pending_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table WHERE status = 'submitted'");
        $approved_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $submissions_table WHERE status = 'approved'");
        
        $points_table = $wpdb->prefix . 'otm_points_total';
        $total_points_awarded = $wpdb->get_var("SELECT SUM(total_points) FROM $points_table");
        
        $users_with_points = $wpdb->get_var("SELECT COUNT(*) FROM $points_table WHERE total_points > 0");
        
        echo '<div class="wrap">';
        echo '<h1>OTM Dashboard</h1>';
        echo '<div class="otm-dashboard-stats">';
        
        echo '<div class="otm-stat-card">';
        echo '<h3>'.esc_html__('Tasks','otm').'</h3>';
        echo '<div class="otm-stat-number">'.intval($total_tasks).'</div>';
        echo '<p>'.esc_html__('Total published tasks','otm').'</p>';
        echo '</div>';
        
        echo '<div class="otm-stat-card">';
        echo '<h3>'.esc_html__('Submissions','otm').'</h3>';
        echo '<div class="otm-stat-number">'.intval($total_submissions).'</div>';
        echo '<p>'.esc_html__('Total submissions','otm').'</p>';
        echo '<div class="otm-stat-breakdown">';
        echo '<span class="otm-badge pending">'.intval($pending_submissions).' '.esc_html__('Pending','otm').'</span>';
        echo '<span class="otm-badge approved">'.intval($approved_submissions).' '.esc_html__('Approved','otm').'</span>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="otm-stat-card">';
        echo '<h3>'.esc_html__('Points','otm').'</h3>';
        echo '<div class="otm-stat-number">'.intval($total_points_awarded ?: 0).'</div>';
        echo '<p>'.esc_html__('Total points awarded','otm').'</p>';
        echo '<div class="otm-stat-breakdown">';
        echo '<span class="otm-badge">'.intval($users_with_points).' '.esc_html__('users with points','otm').'</span>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        
        // Quick actions
        echo '<div class="otm-dashboard-actions">';
        echo '<h2>'.esc_html__('Quick Actions','otm').'</h2>';
        echo '<p><a href="'.esc_url(admin_url('post-new.php?post_type=otm_task')).'" class="button button-primary">'.esc_html__('Create New Task','otm').'</a></p>';
        echo '<p><a href="'.esc_url(admin_url('admin.php?page=otm-submissions')).'" class="button">'.esc_html__('Review Submissions','otm').'</a></p>';
        echo '<p><a href="'.esc_url(admin_url('admin.php?page=otm-settings')).'" class="button">'.esc_html__('Settings','otm').'</a></p>';
        echo '</div>';
        
        echo '</div>';
    }
    public static function render() {
        ?>
        <div class="wrap">
            <h1>OTM Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('otm_settings_group');
                do_settings_sections('otm-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    public static function field_week() {
        $opts = get_option('otm_settings', []);
        $start = isset($opts['week_starts']) ? $opts['week_starts'] : 'sunday';
        ?>
        <label>Week starts on:
            <select name="otm_settings[week_starts]">
                <option value="sunday" <?php selected($start,'sunday'); ?>>Sunday</option>
                <option value="monday" <?php selected($start,'monday'); ?>>Monday</option>
            </select>
        </label>
        <?php
    }
    public static function field_labels() {
        $opts = get_option('otm_settings', []);
        $rename = !empty($opts['rename_groups']);
        $sing = isset($opts['stream_singular']) ? $opts['stream_singular'] : 'Stream';
        $plur = isset($opts['stream_plural']) ? $opts['stream_plural'] : 'Streams';
        ?>
        <p><label><input type="checkbox" name="otm_settings[rename_groups]" value="1" <?php checked($rename, true); ?> /> Replace "Groups" with "Streams" in UI (if BuddyBoss/BuddyPress active)</label></p>
        <p>
            <input type="text" name="otm_settings[stream_singular]" value="<?php echo esc_attr($sing); ?>" placeholder="Stream">
            <input type="text" name="otm_settings[stream_plural]" value="<?php echo esc_attr($plur); ?>" placeholder="Streams">
        </p>
        <?php
    }

    public static function field_defaults() {
        $opts = get_option('otm_settings', []);
        $default_max = isset($opts['default_max_points']) ? (int)$opts['default_max_points'] : 10;
        $require_membership = !empty($opts['require_membership']);
        ?>
        <p><label>Default Max Points <input type="number" name="otm_settings[default_max_points]" value="<?php echo esc_attr($default_max); ?>" min="0" style="width:120px"></label></p>
        <p><label><input type="checkbox" name="otm_settings[require_membership]" value="1" <?php checked($require_membership, true); ?> /> Require group membership to submit (BuddyBoss/BuddyPress only)</label></p>
        <?php
    }
}
