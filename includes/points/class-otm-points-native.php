<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Points_Native implements OTM_Points_Service_Interface {

    private function current_week_range() {
        $start_day = OTM_Settings::get('week_starts', 'sunday');
        $ts = current_time('timestamp', 1);
        $w = (int) gmdate('w', $ts);
        $offset = ($start_day === 'monday') ? (($w+6)%7) : $w;
        $start_ts = strtotime('-'.$offset.' days', $ts);
        $end_ts = strtotime('+6 days 23:59:59', $start_ts);
        return ['start' => gmdate('Y-m-d 00:00:00', $start_ts), 'end' => gmdate('Y-m-d 23:59:59', $end_ts)];
    }

    public function award_points( $user_id, $points, $context = [] ) {
        global $wpdb; $table = $wpdb->prefix . 'otm_points_log';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'points' => $points,
            'task_id' => isset($context['task_id']) ? $context['task_id'] : null,
            'submission_id' => isset($context['submission_id']) ? $context['submission_id'] : null,
            'approved_at' => isset($context['approved_at']) ? $context['approved_at'] : current_time('mysql', 1),
            'created_by' => get_current_user_id() ?: null,
            'created_at' => current_time('mysql', 1),
        ]);
        $this->recalc_user_total($user_id);
    }

    public function set_points_to( $user_id, $points, $context = [] ) {
        $current = $this->get_user_total($user_id);
        $delta = $points - $current;
        if ($delta !== 0) $this->award_points($user_id, $delta, $context);
    }

    public function set_points_for_submission( $user_id, $task_id, $submission_id, $points ) {
        global $wpdb; $table = $wpdb->prefix . 'otm_points_log';
        $existing = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(points),0) FROM $table WHERE user_id=%d AND submission_id=%d", $user_id, $submission_id));
        $delta = $points - intval($existing);
        if ($delta === 0) return;
        $this->award_points($user_id, $delta, ['task_id' => $task_id, 'submission_id' => $submission_id]);
    }

    private function recalc_user_total($user_id) {
        global $wpdb;
        $log = $wpdb->prefix . 'otm_points_log';
        $tot = $wpdb->prefix . 'otm_points_total';
        $sum = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(points),0) FROM $log WHERE user_id=%d", $user_id));
        $exists = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $tot WHERE user_id=%d", $user_id));
        if ($exists) {
            $wpdb->update($tot, ['total_points' => intval($sum), 'updated_at' => current_time('mysql', 1)], ['user_id' => $user_id]);
        } else {
            $wpdb->insert($tot, ['user_id' => $user_id, 'total_points' => intval($sum), 'updated_at' => current_time('mysql', 1)]);
        }
    }

    public function get_user_total( $user_id ) {
        global $wpdb; $tot = $wpdb->prefix . 'otm_points_total';
        $sum = $wpdb->get_var($wpdb->prepare("SELECT total_points FROM $tot WHERE user_id=%d", $user_id));
        return intval($sum ? $sum : 0);
    }

    public function get_user_week_total( $user_id, $week_range ) {
        global $wpdb; $log = $wpdb->prefix . 'otm_points_log';
        $sum = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(points),0) FROM $log WHERE user_id=%d AND approved_at BETWEEN %s AND %s", $user_id, $week_range['start'], $week_range['end']));
        return intval($sum ? $sum : 0);
    }

    private function cache_key($args) {
        $buster = get_option('otm_cache_buster', 1);
        return 'otm_lb_' . md5( wp_json_encode($args) . '|' . $buster );
    }

    public function get_top_users( $args ) {
        global $wpdb;
        $limit = max(1, intval(isset($args['limit'])?$args['limit']:20));
        $week = isset($args['week']) ? $args['week'] : 'current';
        $scope = isset($args['scope']) ? $args['scope'] : 'global';
        $stream_id = isset($args['stream_id']) ? intval($args['stream_id']) : 0;

        $cache_key = $this->cache_key($args);
        $cached = get_transient($cache_key);
        if ( $cached !== false ) return $cached;

        $log = $wpdb->prefix . 'otm_points_log';
        $posts = $wpdb->posts;
        $pm = $wpdb->postmeta;

        $rows = [];

        if ($scope === 'stream' && $stream_id > 0) {
            if ($week === 'all') {
                $sql = $wpdb->prepare(
                    "SELECT l.user_id, COALESCE(SUM(l.points),0) AS total_points
                     FROM $log l
                     JOIN $posts p ON p.ID = l.task_id AND p.post_type='otm_task'
                     JOIN $pm m ON m.post_id = p.ID AND m.meta_key = '_otm_stream_id' AND m.meta_value = %d
                     GROUP BY l.user_id
                     ORDER BY total_points DESC
                     LIMIT %d", $stream_id, $limit
                );
                $rows = $wpdb->get_results($sql, ARRAY_A);
                $out = [];
                foreach ($rows as $r) {
                    $out[] = ['user_id' => intval($r['user_id']), 'week_points' => intval($r['total_points']), 'total_points' => intval($r['total_points'])];
                }
                set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS);
                return $out;
            } else {
                $range = $this->current_week_range();
                $sql = $wpdb->prepare(
                    "SELECT l.user_id,
                            COALESCE(SUM(CASE WHEN l.approved_at BETWEEN %s AND %s THEN l.points ELSE 0 END),0) AS week_points,
                            COALESCE(SUM(l.points),0) AS total_points
                     FROM $log l
                     JOIN $posts p ON p.ID = l.task_id AND p.post_type='otm_task'
                     JOIN $pm m ON m.post_id = p.ID AND m.meta_key = '_otm_stream_id' AND m.meta_value = %d
                     GROUP BY l.user_id
                     HAVING week_points > 0
                     ORDER BY week_points DESC
                     LIMIT %d",
                    $range['start'], $range['end'], $stream_id, $limit
                );
                $rows = $wpdb->get_results($sql, ARRAY_A);
                $out = [];
                foreach ($rows as $r) {
                    $out[] = ['user_id' => intval($r['user_id']), 'week_points' => intval($r['week_points']), 'total_points' => intval($r['total_points'])];
                }
                set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS);
                return $out;
            }
        }

        if ($week === 'all') {
            $tot = $wpdb->prefix . 'otm_points_total';
            $rows = $wpdb->get_results($wpdb->prepare("SELECT user_id, total_points FROM $tot ORDER BY total_points DESC LIMIT %d", $limit), ARRAY_A);
            $out = [];
            foreach ($rows as $r) {
                $out[] = ['user_id' => intval($r['user_id']), 'week_points' => intval($r['total_points']), 'total_points' => intval($r['total_points'])];
            }
            set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS);
            return $out;
        }

        $range = $this->current_week_range();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.user_id, COALESCE(SUM(l.points),0) as week_points, t.total_points
             FROM $log l 
             LEFT JOIN {$wpdb->prefix}otm_points_total t ON t.user_id = l.user_id
             WHERE l.approved_at BETWEEN %s AND %s
             GROUP BY l.user_id
             ORDER BY week_points DESC
             LIMIT %d",
            $range['start'], $range['end'], $limit
        ), ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['user_id' => intval($r['user_id']), 'week_points' => intval($r['week_points']), 'total_points' => intval(isset($r['total_points'])?$r['total_points']:0)];
        }
        set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS);
        return $out;
    }
}
