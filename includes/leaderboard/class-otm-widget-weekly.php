<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Widget_Weekly extends WP_Widget {
    public static function register() { add_action('widgets_init', function(){ register_widget(__CLASS__); }); }
    public function __construct() { parent::__construct('otm_weekly_leaderboard', 'OTM Weekly Leaderboard', ['description' => 'Shows the current week leaderboard']); }
    public function form($instance) {
        $title = esc_attr(isset($instance['title']) ? $instance['title'] : 'This Week');
        $limit = intval(isset($instance['limit']) ? $instance['limit'] : 10);
        echo '<p><label>Title <input class="widefat" name="'.$this->get_field_name('title').'" value="'.$title.'"></label></p>';
        echo '<p><label>Limit <input type="number" name="'.$this->get_field_name('limit').'" value="'.$limit.'"></label></p>';
    }
    public function update($new, $old) { return ['title'=>sanitize_text_field(isset($new['title'])?$new['title']:''),'limit'=>intval(isset($new['limit'])?$new['limit']:10)]; }
    public function widget($args, $instance) {
        echo $args['before_widget'];
        if (!empty($instance['title'])) echo $args['before_title'] . esc_html($instance['title']) . $args['after_title'];
        echo do_shortcode('[otm_leaderboard week="current" limit="'.intval(isset($instance['limit'])?$instance['limit']:10).'"]');
        echo $args['after_widget'];
    }
}
