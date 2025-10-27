<?php
if ( ! defined( 'ABSPATH' ) ) exit;

interface OTM_Points_Service_Interface {
    public function award_points( $user_id, $points, $context = [] );
    public function set_points_to( $user_id, $points, $context = [] );
    public function set_points_for_submission( $user_id, $task_id, $submission_id, $points );
    public function get_user_total( $user_id );
    public function get_user_week_total( $user_id, $week_range );
    public function get_top_users( $args );
}
