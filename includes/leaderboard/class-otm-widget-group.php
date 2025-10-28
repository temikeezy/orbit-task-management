<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Widget_Group_Leaderboard extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'otm_group_leaderboard',
            __('OTM Group Leaderboard', 'otm'),
            ['description' => __('Display leaderboard for current BuddyBoss group', 'otm')]
        );
    }
    
    public function widget($args, $instance) {
        if ( ! OTM_BB::is_active() ) return;
        
        $group_id = OTM_BB::current_group_id();
        if ( ! $group_id ) return;
        
        $title = ! empty($instance['title']) ? $instance['title'] : __('Group Leaderboard', 'otm');
        $limit = ! empty($instance['limit']) ? absint($instance['limit']) : 10;
        $week = ! empty($instance['week']) ? $instance['week'] : 'current';
        
        echo $args['before_widget'];
        if ( $title ) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        
        $leaderboard_args = [
            'scope' => 'stream',
            'stream_id' => $group_id,
            'week' => $week,
            'limit' => $limit,
        ];
        
        $rows = otm_points_service()->get_top_users($leaderboard_args);
        
        if ( empty($rows) ) {
            echo '<p>' . __('No submissions yet.', 'otm') . '</p>';
        } else {
            echo '<div class="otm-group-leaderboard">';
            echo '<table class="otm-table">';
            echo '<thead><tr>';
            echo '<th>' . __('Pos', 'otm') . '</th>';
            echo '<th>' . __('User', 'otm') . '</th>';
            echo '<th>' . ($week === 'current' ? __('Week Pts', 'otm') : __('Pts', 'otm')) . '</th>';
            echo '</tr></thead><tbody>';
            
            $pos = 1;
            foreach ($rows as $r) {
                $user = get_user_by('id', $r['user_id']);
                $avatar = get_avatar( $r['user_id'], 24 );
                echo '<tr>';
                echo '<td>' . intval($pos++) . '</td>';
                echo '<td>' . $avatar . ' ' . esc_html($user ? $user->display_name : ('#' . $r['user_id'])) . '</td>';
                echo '<td>' . intval($r['week_points']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        }
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = ! empty($instance['title']) ? $instance['title'] : '';
        $limit = ! empty($instance['limit']) ? $instance['limit'] : 10;
        $week = ! empty($instance['week']) ? $instance['week'] : 'current';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'otm'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of users to show:', 'otm'); ?></label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($limit); ?>" size="3">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('week'); ?>"><?php _e('Time period:', 'otm'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('week'); ?>" name="<?php echo $this->get_field_name('week'); ?>">
                <option value="current" <?php selected($week, 'current'); ?>><?php _e('Current Week', 'otm'); ?></option>
                <option value="all" <?php selected($week, 'all'); ?>><?php _e('All Time', 'otm'); ?></option>
            </select>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? absint($new_instance['limit']) : 10;
        $instance['week'] = (!empty($new_instance['week'])) ? sanitize_text_field($new_instance['week']) : 'current';
        return $instance;
    }
    
    public static function register() {
        register_widget(__CLASS__);
    }
}
