<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_REST {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        $ns = 'otm/v1';
        register_rest_route($ns, '/tasks', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_tasks'],
            'permission_callback' => '__return_true',
            'args' => [ 'stream_id' => ['type'=>'integer','required'=>false] ]
        ]);
        register_rest_route($ns, '/my-submissions', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_my_submissions'],
            'permission_callback' => function(){ return is_user_logged_in(); },
        ]);
        register_rest_route($ns, '/leaderboard', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_leaderboard'],
            'permission_callback' => '__return_true',
            'args' => [
                'scope' => ['type'=>'string','enum'=>['global','stream'],'required'=>false],
                'stream_id' => ['type'=>'integer','required'=>false],
                'week' => ['type'=>'string','enum'=>['current','all'],'required'=>false],
                'limit' => ['type'=>'integer','required'=>false],
            ]
        ]);
        register_rest_route($ns, '/my-points', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_my_points'],
            'permission_callback' => function(){ return is_user_logged_in(); },
        ]);
    }

    public static function get_tasks( WP_REST_Request $req ) {
        $args = [
            'post_type' => 'otm_task',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        $stream = absint($req->get_param('stream_id'));
        if ( $stream ) {
            $args['meta_query'] = [[ 'key' => '_otm_stream_id', 'value' => $stream, 'compare' => '=' ]];
        }
        $q = new WP_Query($args);
        $out = [];
        foreach ($q->posts as $p) {
            $out[] = [
                'id' => $p->ID,
                'title' => get_the_title($p),
                'content' => apply_filters('the_content', $p->post_content),
                'max_points' => (int) get_post_meta($p->ID, '_otm_max_points', true),
                'deadline' => get_post_meta($p->ID, '_otm_deadline', true),
                'stream_id' => (int) get_post_meta($p->ID, '_otm_stream_id', true),
            ];
        }
        return rest_ensure_response($out);
    }

    public static function get_my_submissions( WP_REST_Request $req ) {
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT 100", get_current_user_id() ), ARRAY_A );
        return rest_ensure_response($rows);
    }

    public static function get_leaderboard( WP_REST_Request $req ) {
        $args = [
            'scope' => $req->get_param('scope') ?: 'global',
            'stream_id' => absint($req->get_param('stream_id')),
            'week' => $req->get_param('week') ?: 'current',
            'limit' => $req->get_param('limit') ? absint($req->get_param('limit')) : 20,
        ];
        $rows = otm_points_service()->get_top_users($args);
        return rest_ensure_response($rows);
    }

    public static function get_my_points( WP_REST_Request $req ) {
        $uid = get_current_user_id();
        $total = otm_points_service()->get_user_total($uid);
        return rest_ensure_response([ 'total_points' => (int) $total ]);
    }
}


