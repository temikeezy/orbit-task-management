<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Widget_My_Submissions extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'otm_my_submissions',
            __('OTM: My Submissions','otm'),
            ['description' => __('Shows recent submissions by the logged-in user','otm')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        $title = ! empty($instance['title']) ? $instance['title'] : __('My Submissions','otm');
        echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];

        if ( ! is_user_logged_in() ) {
            echo '<p><a href="'.esc_url(wp_login_url()).'">'.esc_html__('Log in to view your submissions','otm').'</a></p>';
            echo $args['after_widget']; return;
        }
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $uid = get_current_user_id();
        $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT 5", $uid) );
        if ( empty($rows) ) {
            echo '<p>'.esc_html__('No submissions yet.','otm').'</p>';
        } else {
            echo '<ul class="otm-my-submissions">';
            foreach ($rows as $r) {
                $task = get_post($r->task_id);
                $title = $task ? $task->post_title : ('#'.$r->task_id);
                $status = ucfirst($r->status);
                echo '<li><a href="'.esc_url( get_permalink($r->task_id) ).'">'.esc_html($title).'</a> — <span class="otm-badge otm-badge-'.esc_attr($r->status).'">'.esc_html($status).'</span></li>';
            }
            echo '</ul>';
        }

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = ! empty($instance['title']) ? $instance['title'] : __('My Submissions','otm');
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:','otm'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );
        return $instance;
    }

    public static function register() { register_widget(__CLASS__); }
}
