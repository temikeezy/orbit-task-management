<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists('BP_Group_Extension') && ! class_exists('OTM_Group_Leaderboard_Tab') ) {
    class OTM_Group_Leaderboard_Tab extends BP_Group_Extension {
        function __construct() {
            $this->name = __('Leaderboard', 'otm');
            $this->slug = 'otm-leaderboard';
            $this->visibility = 'public';
            $this->enable_nav_item = true;
        }
        public function display( $group_id = null ) {
            wp_enqueue_style('otm-frontend'); wp_enqueue_script('otm-frontend');
            $range = isset($_GET['range']) ? sanitize_text_field($_GET['range']) : 'weekly';
            if ( ! in_array($range, array('weekly','overall'), true) ) { $range = 'weekly'; }
            echo '<div class="bp-wrap otm-leaderboard">';
            echo '<div class="bb-head-actions bb-group-head">';
            echo '<h2 class="screen-heading">'.esc_html__('Leaderboard','otm').'</h2>';
            echo '</div>';
            echo '<nav class="bp-navs bp-subnavs" role="navigation" aria-label="Sub Menu"><ul class="subnav">';
            foreach ( array('weekly'=>__('Weekly','otm'), 'overall'=>__('Overall','otm')) as $key=>$label ) {
                $class = $range===$key ? ' class="current"' : '';
                echo '<li'.$class.'><a href="'.esc_url( add_query_arg('range',$key) ).'">'.esc_html($label).'</a></li>';
            }
            echo '</ul></nav>';
            // Optional intro/description
            echo '<p class="otm-lead">'.esc_html__('Track top contributors in this Stream. Weekly resets each Monday (configurable).','otm').'</p>';
            echo '<div class="otm-board">';
            echo '<table class="otm-table"><thead><tr>';
            echo '<th class="col-pos">#</th><th class="col-user">'.esc_html__('User','otm').'</th><th class="col-week">'.esc_html__('This Week','otm').'</th><th class="col-total">'.esc_html__('Total','otm').'</th>';
            echo '</tr></thead><tbody>';
            // Placeholder rows for now; data integration later
            echo '<tr class="otm-empty-row"><td>🥇</td><td><span class="otm-muted">'.esc_html__('No data yet','otm').'</span></td><td>—</td><td>—</td></tr>';
            echo '<tr class="otm-empty-row"><td>🥈</td><td class="otm-muted">'.esc_html__('Be the first to submit','otm').'</td><td>—</td><td>—</td></tr>';
            echo '<tr class="otm-empty-row"><td>🥉</td><td class="otm-muted">'.esc_html__('Leaderboard updates weekly','otm').'</td><td>—</td><td>—</td></tr>';
            echo '</tbody></table>';
            echo '</div>';
            echo '</div>';
        }
    }
}


