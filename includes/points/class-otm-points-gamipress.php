<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Points_GamiPress implements OTM_Points_Service_Interface {

	/** @var OTM_Points_Service_Interface */
	private $native;

	public function __construct( OTM_Points_Service_Interface $native ) {
		$this->native = $native;
	}

	private function points_type() {
		return apply_filters( 'otm_gamipress_points_type', 'points' );
	}

	private function should_sync() {
		return (bool) apply_filters( 'otm_gamipress_sync_enabled', true );
	}

	public function award_points( $user_id, $points, $context = [] ) {
		$this->native->award_points( $user_id, $points, $context );

		if ( $this->should_sync() && function_exists( 'gamipress_award_points_to_user' ) && $points !== 0 ) {
			$pt = $this->points_type();
			$desc = isset( $context['log_description'] ) ? $context['log_description'] : __( 'Task points (OTM)', 'otm' );
			$args = [
				'task_id'       => isset( $context['task_id'] ) ? $context['task_id'] : null,
				'submission_id' => isset( $context['submission_id'] ) ? $context['submission_id'] : null,
			];
			gamipress_award_points_to_user( (int) $user_id, (int) $points, $pt, $desc, $args );
		}

		do_action( 'otm_points_awarded', $user_id, $points, $context );
	}

	public function set_points_to( $user_id, $points, $context = [] ) {
		$this->native->set_points_to( $user_id, $points, $context );
	}

	public function set_points_for_submission( $user_id, $task_id, $submission_id, $points ) {
		$this->native->set_points_for_submission( $user_id, $task_id, $submission_id, $points );
	}

	public function get_user_total( $user_id ) {
		return $this->native->get_user_total( $user_id );
	}

	public function get_user_week_total( $user_id, $week_range ) {
		return $this->native->get_user_week_total( $user_id, $week_range );
	}

	public function get_top_users( $args ) {
		return $this->native->get_top_users( $args );
	}
}


