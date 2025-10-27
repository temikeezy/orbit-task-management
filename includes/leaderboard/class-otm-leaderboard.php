<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Leaderboard {
    public static function init() {
        add_shortcode('otm_leaderboard', [__CLASS__, 'shortcode']);
    }
    public static function shortcode($atts) {
        $atts = shortcode_atts([
            'scope' => 'global', // global|stream
            'stream_id' => '',
            'week' => 'current', // current|all
            'limit' => 20,
            'show_total' => 1,
        ], $atts, 'otm_leaderboard');

        $args = [
            'scope' => $atts['scope'],
            'stream_id' => $atts['stream_id'] ? intval($atts['stream_id']) : 0,
            'week' => $atts['week'],
            'limit' => max(1, intval($atts['limit']))
        ];

        $rows = otm_points_service()->get_top_users($args);
        ob_start();
        echo '<div class="otm-leaderboard"><table class="otm-table"><thead><tr>';
        echo '<th>Pos</th><th>User</th><th>'.($atts['week']==='current'?'Week Pts':'Pts').'</th>';
        if (intval($atts['show_total'])) echo '<th>Total</th>';
        echo '</tr></thead><tbody>';
        $pos = 1;
        foreach ($rows as $r) {
            $user = get_user_by('id', $r['user_id']);
            echo '<tr>';
            echo '<td>'.intval($pos++).'</td>';
            echo '<td>'.esc_html($user ? $user->display_name : ('#'.$r['user_id'])).'</td>';
            echo '<td>'.intval($r['week_points']).'</td>';
            if (intval($atts['show_total'])) echo '<td>'.intval($r['total_points']).'</td>';
            echo '</tr>';
        }
        if ($pos === 1) echo '<tr><td colspan="4">No data yet.</td></tr>';
        echo '</tbody></table></div>';
        return ob_get_clean();
    }
}
