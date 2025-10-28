<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GamiPress automation for OTM
 * - Awards achievements/ranks when OTM events fire
 * - Provides an admin action to re-evaluate weekly tops and ranks
 *
 * All calls are guarded to run only if GamiPress is present and automation is enabled.
 */
class OTM_Gamipress_Automation {

    public static function init() {
        // Admin action to re-evaluate awards
        add_action('admin_post_otm_gp_recalculate', [__CLASS__, 'handle_recalculate']);

        // Runtime automation only if enabled and GamiPress available
        if ( ! self::is_enabled() || ! self::has_gp() ) return;

        // First submission achievement, Active/Event ranks on approval
        add_action('otm_submission_approved', [__CLASS__, 'on_submission_approved'], 10, 2);

        // Weekly top is handled on demand by recalc, or could be scheduled
    }

    private static function is_enabled() : bool {
        $opts = get_option('otm_settings', []);
        return ! empty($opts['gp_auto_enabled']);
    }

    private static function has_gp() : bool {
        return function_exists('gamipress_award_points_to_user') || class_exists('GamiPress');
    }

    private static function get_opt($key, $default = null) {
        $opts = get_option('otm_settings', []);
        return isset($opts[$key]) ? $opts[$key] : $default;
    }

    /**
     * Handle submission approval: potential awards
     * @param int $user_id
     * @param array $context {task_id, submission_id, points}
     */
    public static function on_submission_approved( $user_id, $context ) {
        if ( ! self::has_gp() ) return;

        $task_id = isset($context['task_id']) ? (int) $context['task_id'] : 0;
        if ( ! $task_id ) return;

        // 1) First submission achievement
        $ach_first = (int) self::get_opt('gp_ach_first_id', 0);
        if ( $ach_first > 0 && function_exists('gamipress_award_achievement_to_user') ) {
            // Awarding again is safe; GamiPress will prevent duplicates
            gamipress_award_achievement_to_user( $user_id, $ach_first );
        }

        // 2) Active Orbit rank (points + submissions threshold)
        $rank_active = (int) self::get_opt('gp_rank_active_id', 0);
        if ( $rank_active > 0 && function_exists('gamipress_award_rank_to_user') ) {
            $min_points = (int) self::get_opt('gp_thr_active_points', 100);
            $min_subs = (int) self::get_opt('gp_thr_active_subs', 3);
            $total_points = otm_points_service()->get_user_total( $user_id );
            $total_approved = self::get_user_approved_count( $user_id );
            if ( $total_points >= $min_points && $total_approved >= $min_subs ) {
                gamipress_award_rank_to_user( $user_id, $rank_active );
            }
        }

        // 3) Event Orbit rank (participation in mapped Event streams)
        $rank_event = (int) self::get_opt('gp_rank_event_id', 0);
        if ( $rank_event > 0 && function_exists('gamipress_award_rank_to_user') ) {
            $event_ids_raw = (string) self::get_opt('gp_event_stream_ids', '');
            $event_ids = array_filter(array_map('intval', array_map('trim', explode(',', $event_ids_raw))));
            if ( $event_ids ) {
                if ( self::user_has_approved_in_streams( $user_id, $event_ids ) ) {
                    gamipress_award_rank_to_user( $user_id, $rank_event );
                }
            }
        }
    }

    /**
     * Admin-run recalculation: weekly top and evaluate ranks
     */
    public static function handle_recalculate() {
        if ( ! current_user_can('otm_manage_settings') ) wp_die('Insufficient permissions');
        check_admin_referer('otm_gp_recalculate');

        if ( self::has_gp() ) {
            self::award_weekly_top();
            // Optionally iterate through users with points and re-check Active/Event ranks
            self::reevaluate_ranks_for_all();
        }

        wp_safe_redirect( wp_get_referer() ?: admin_url('admin.php?page=otm-settings') );
        exit;
    }

    private static function award_weekly_top() {
        $ach_weekly_top = (int) self::get_opt('gp_ach_weekly_top_id', 0);
        if ( $ach_weekly_top <= 0 || ! function_exists('gamipress_award_achievement_to_user') ) return;

        $rows = otm_points_service()->get_top_users([
            'scope' => 'global',
            'week' => 'current',
            'limit' => 3,
        ]);
        foreach ( (array) $rows as $r ) {
            $uid = (int) $r['user_id'];
            if ( $uid ) {
                gamipress_award_achievement_to_user( $uid, $ach_weekly_top );
            }
        }
    }

    private static function reevaluate_ranks_for_all() {
        global $wpdb; $tot = $wpdb->prefix . 'otm_points_total';
        $user_ids = $wpdb->get_col("SELECT user_id FROM $tot WHERE total_points > 0");
        if ( empty($user_ids) ) return;

        $rank_active = (int) self::get_opt('gp_rank_active_id', 0);
        $rank_event = (int) self::get_opt('gp_rank_event_id', 0);
        $event_ids_raw = (string) self::get_opt('gp_event_stream_ids', '');
        $event_ids = array_filter(array_map('intval', array_map('trim', explode(',', $event_ids_raw))));
        $min_points = (int) self::get_opt('gp_thr_active_points', 100);
        $min_subs = (int) self::get_opt('gp_thr_active_subs', 3);

        foreach ( $user_ids as $uid ) {
            $uid = (int) $uid;
            if ( $rank_active > 0 && function_exists('gamipress_award_rank_to_user') ) {
                $total_points = otm_points_service()->get_user_total( $uid );
                $total_approved = self::get_user_approved_count( $uid );
                if ( $total_points >= $min_points && $total_approved >= $min_subs ) {
                    gamipress_award_rank_to_user( $uid, $rank_active );
                }
            }
            if ( $rank_event > 0 && $event_ids && function_exists('gamipress_award_rank_to_user') ) {
                if ( self::user_has_approved_in_streams( $uid, $event_ids ) ) {
                    gamipress_award_rank_to_user( $uid, $rank_event );
                }
            }
        }
    }

    private static function get_user_approved_count( $user_id ) : int {
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $cnt = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id=%d AND status='approved'", $user_id));
        return (int) $cnt;
    }

    private static function user_has_approved_in_streams( $user_id, array $stream_ids ) : bool {
        if ( empty($stream_ids) ) return false;
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions'; $posts = $wpdb->posts; $pm = $wpdb->postmeta;
        $in = implode(',', array_map('intval', $stream_ids));
        $sql = "SELECT 1 FROM $table s
                JOIN $posts p ON p.ID = s.task_id AND p.post_type='otm_task'
                JOIN $pm m ON m.post_id = p.ID AND m.meta_key = '_otm_stream_id'
                WHERE s.user_id = %d AND s.status='approved' AND CAST(m.meta_value AS UNSIGNED) IN ($in) LIMIT 1";
        $found = $wpdb->get_var( $wpdb->prepare( $sql, $user_id ) );
        return ! empty($found);
    }
}


