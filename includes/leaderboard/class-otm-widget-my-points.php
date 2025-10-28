<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Widget_My_Points extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'otm_my_points',
            __('OTM: My Points','otm'),
            ['description' => __('Shows the logged-in user\'s points and weekly total','otm')]
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];
        $title = ! empty($instance['title']) ? $instance['title'] : __('My Points','otm');
        echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];

        if ( ! is_user_logged_in() ) {
            echo '<p><a href="'.esc_url(wp_login_url()).'">'.esc_html__('Log in to view your points','otm').'</a></p>';
            echo $args['after_widget']; return;
        }

        $uid = get_current_user_id();
        $total = otm_points_service()->get_user_total( $uid );
        // compute current week points via service method if available
        if ( method_exists(otm_points_service(), 'get_user_week_total') ) {
            // derive range from native service if available
            $range = null;
            if ( method_exists(otm_points_service(), 'current_week_range') ) {
                // private in native; fallback compute here
                $start_day = OTM_Settings::get('week_starts', 'sunday');
                $ts = current_time('timestamp', 1);
                $w = (int) gmdate('w', $ts);
                $offset = ($start_day === 'monday') ? (($w+6)%7) : $w;
                $start_ts = strtotime('-'.$offset.' days', $ts);
                $end_ts = strtotime('+6 days 23:59:59', $start_ts);
                $range = ['start' => gmdate('Y-m-d 00:00:00', $start_ts), 'end' => gmdate('Y-m-d 23:59:59', $end_ts)];
            } else {
                $start_day = OTM_Settings::get('week_starts', 'sunday');
                $ts = current_time('timestamp', 1);
                $w = (int) gmdate('w', $ts);
                $offset = ($start_day === 'monday') ? (($w+6)%7) : $w;
                $start_ts = strtotime('-'.$offset.' days', $ts);
                $end_ts = strtotime('+6 days 23:59:59', $start_ts);
                $range = ['start' => gmdate('Y-m-d 00:00:00', $start_ts), 'end' => gmdate('Y-m-d 23:59:59', $end_ts)];
            }
            $week_pts = otm_points_service()->get_user_week_total( $uid, $range );
        } else {
            $week_pts = 0;
        }

        echo '<div class="otm-my-points">';
        echo '<p><strong>'.esc_html__('Total Points:','otm').'</strong> '.intval($total).'</p>';
        echo '<p><strong>'.esc_html__('This Week:','otm').'</strong> '.intval($week_pts).'</p>';
        echo '</div>';

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = ! empty($instance['title']) ? $instance['title'] : __('My Points','otm');
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


