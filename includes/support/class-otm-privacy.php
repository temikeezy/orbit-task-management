<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Privacy {

    public static function init() {
        add_filter( 'wp_privacy_personal_data_exporters', [ __CLASS__, 'register_exporters' ] );
        add_filter( 'wp_privacy_personal_data_erasers', [ __CLASS__, 'register_erasers' ] );
    }

    public static function register_exporters( $exporters ) {
        $exporters['otm_submissions'] = [
            'exporter_friendly_name' => __( 'OTM Submissions', 'otm' ),
            'callback' => [ __CLASS__, 'export_submissions' ],
        ];
        $exporters['otm_points'] = [
            'exporter_friendly_name' => __( 'OTM Points', 'otm' ),
            'callback' => [ __CLASS__, 'export_points' ],
        ];
        return $exporters;
    }

    public static function register_erasers( $erasers ) {
        $erasers['otm_submissions'] = [
            'eraser_friendly_name' => __( 'OTM Submissions', 'otm' ),
            'callback' => [ __CLASS__, 'erase_submissions' ],
        ];
        return $erasers;
    }

    public static function export_submissions( $email_address, $page = 1 ) {
        $user = get_user_by('email', $email_address);
        if ( ! $user ) return [ 'data' => [], 'done' => true ];
        $limit = 100; $offset = ($page-1)*$limit;
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user->ID, $limit, $offset), ARRAY_A );
        $data = [];
        foreach ( $rows as $r ) {
            $data[] = [
                'group_id' => 'otm-submissions',
                'group_label' => __( 'OTM Submissions', 'otm' ),
                'item_id' => 'otm-sub-'.$r['id'],
                'data' => [
                    [ 'name' => __( 'Task ID', 'otm' ), 'value' => $r['task_id'] ],
                    [ 'name' => __( 'Status', 'otm' ), 'value' => $r['status'] ],
                    [ 'name' => __( 'Awarded Points', 'otm' ), 'value' => $r['awarded_points'] ],
                    [ 'name' => __( 'Text', 'otm' ), 'value' => $r['text_content'] ],
                    [ 'name' => __( 'URLs', 'otm' ), 'value' => $r['urls_json'] ],
                    [ 'name' => __( 'Files', 'otm' ), 'value' => $r['files_json'] ],
                    [ 'name' => __( 'Created', 'otm' ), 'value' => $r['created_at'] ],
                ],
            ];
        }
        $done = count($rows) < $limit;
        return [ 'data' => $data, 'done' => $done ];
    }

    public static function export_points( $email_address, $page = 1 ) {
        $user = get_user_by('email', $email_address);
        if ( ! $user ) return [ 'data' => [], 'done' => true ];
        $limit = 100; $offset = ($page-1)*$limit;
        global $wpdb; $table = $wpdb->prefix . 'otm_points_log';
        $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d", $user->ID, $limit, $offset), ARRAY_A );
        $data = [];
        foreach ( $rows as $r ) {
            $data[] = [
                'group_id' => 'otm-points',
                'group_label' => __( 'OTM Points', 'otm' ),
                'item_id' => 'otm-pts-'.$r['id'],
                'data' => [
                    [ 'name' => __( 'Points', 'otm' ), 'value' => $r['points'] ],
                    [ 'name' => __( 'Task ID', 'otm' ), 'value' => $r['task_id'] ],
                    [ 'name' => __( 'Submission ID', 'otm' ), 'value' => $r['submission_id'] ],
                    [ 'name' => __( 'Approved At', 'otm' ), 'value' => $r['approved_at'] ],
                    [ 'name' => __( 'Created At', 'otm' ), 'value' => $r['created_at'] ],
                ],
            ];
        }
        $done = count($rows) < $limit;
        return [ 'data' => $data, 'done' => $done ];
    }

    public static function erase_submissions( $email_address, $page = 1 ) {
        $user = get_user_by('email', $email_address);
        if ( ! $user ) return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
        $limit = 20; $offset = ($page-1)*$limit;
        global $wpdb; $table = $wpdb->prefix . 'otm_submissions';
        $rows = $wpdb->get_results( $wpdb->prepare("SELECT id FROM $table WHERE user_id=%d ORDER BY id ASC LIMIT %d OFFSET %d", $user->ID, $limit, $offset), ARRAY_A );
        $removed = false;
        foreach ( $rows as $r ) {
            $wpdb->delete( $table, [ 'id' => (int)$r['id'] ] );
            $removed = true;
        }
        $done = count($rows) < $limit;
        return [ 'items_removed' => $removed, 'items_retained' => false, 'messages' => [], 'done' => $done ];
    }
}

// Initialize privacy tools
OTM_Privacy::init();
