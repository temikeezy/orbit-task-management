<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_BuddyBoss_Integration {
    
    public static function init() {
        if ( ! OTM_BB::is_active() ) return;
        
        // Activity integration
        add_action( 'otm_points_awarded', [__CLASS__, 'post_activity_on_approval'], 10, 3 );
        add_action( 'save_post_otm_task', [__CLASS__, 'post_activity_on_task_create'], 10, 2 );
        
        // Notifications
        add_action( 'otm_submission_approved', [__CLASS__, 'send_approval_notification'], 10, 2 );
        add_action( 'otm_submission_rejected', [__CLASS__, 'send_rejection_notification'], 10, 2 );
        
        // Group integration
        add_action( 'otm_task_created', [__CLASS__, 'notify_group_on_task'], 10, 2 );
    }
    
    /**
     * Post activity when submission is approved
     */
    public static function post_activity_on_approval( $user_id, $points, $context ) {
        if ( empty( $context['task_id'] ) || empty( $context['submission_id'] ) ) return;
        
        $task_id = $context['task_id'];
        $task = get_post( $task_id );
        if ( ! $task ) return;
        
        $stream_id = get_post_meta( $task_id, '_otm_stream_id', true );
        if ( ! $stream_id ) return;
        
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) return;
        
        $activity_content = sprintf(
            __( '%s completed task "%s" and earned %d points!', 'otm' ),
            $user->display_name,
            $task->post_title,
            $points
        );
        
        // Post to group activity stream
        if ( function_exists( 'bp_activity_add' ) ) {
            bp_activity_add( [
                'action' => $activity_content,
                'content' => sprintf( __( 'Task: %s', 'otm' ), $task->post_content ),
                'component' => 'groups',
                'type' => 'otm_task_completed',
                'primary_id' => $stream_id,
                'user_id' => $user_id,
                'item_id' => $stream_id,
                'secondary_item_id' => $task_id,
            ] );
        }
    }
    
    /**
     * Post activity when task is created
     */
    public static function post_activity_on_task_create( $post_id, $post ) {
        if ( $post->post_status !== 'publish' ) return;
        
        $stream_id = get_post_meta( $post_id, '_otm_stream_id', true );
        if ( ! $stream_id ) return;
        
        $max_points = get_post_meta( $post_id, '_otm_max_points', true );
        $deadline = get_post_meta( $post_id, '_otm_deadline', true );
        
        $activity_content = sprintf(
            __( 'New task "%s" is now available!', 'otm' ),
            $post->post_title
        );
        
        $content_parts = [ $post->post_content ];
        if ( $max_points ) {
            $content_parts[] = sprintf( __( 'Max points: %d', 'otm' ), $max_points );
        }
        if ( $deadline ) {
            $content_parts[] = sprintf( __( 'Deadline: %s', 'otm' ), $deadline );
        }
        
        if ( function_exists( 'bp_activity_add' ) ) {
            bp_activity_add( [
                'action' => $activity_content,
                'content' => implode( '<br>', $content_parts ),
                'component' => 'groups',
                'type' => 'otm_task_created',
                'primary_id' => $stream_id,
                'user_id' => get_current_user_id(),
                'item_id' => $stream_id,
                'secondary_item_id' => $post_id,
            ] );
        }
    }
    
    /**
     * Send notification when submission is approved
     */
    public static function send_approval_notification( $user_id, $context ) {
        if ( ! function_exists( 'bp_notifications_add_notification' ) ) return;
        
        $task_id = $context['task_id'] ?? 0;
        $task = get_post( $task_id );
        if ( ! $task ) return;
        
        bp_notifications_add_notification( [
            'user_id' => $user_id,
            'item_id' => $task_id,
            'secondary_item_id' => $context['submission_id'] ?? 0,
            'component_name' => 'otm',
            'component_action' => 'submission_approved',
            'date_notified' => bp_core_current_time(),
            'is_new' => 1,
        ] );
    }
    
    /**
     * Send notification when submission is rejected
     */
    public static function send_rejection_notification( $user_id, $context ) {
        if ( ! function_exists( 'bp_notifications_add_notification' ) ) return;
        
        $task_id = $context['task_id'] ?? 0;
        $task = get_post( $task_id );
        if ( ! $task ) return;
        
        bp_notifications_add_notification( [
            'user_id' => $user_id,
            'item_id' => $task_id,
            'secondary_item_id' => $context['submission_id'] ?? 0,
            'component_name' => 'otm',
            'component_action' => 'submission_rejected',
            'date_notified' => bp_core_current_time(),
            'is_new' => 1,
        ] );
    }
    
    /**
     * Format notification content
     */
    public static function format_notification( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
        $task = get_post( $item_id );
        if ( ! $task ) return '';
        
        switch ( $action ) {
            case 'submission_approved':
                $text = sprintf( __( 'Your submission for "%s" was approved!', 'otm' ), $task->post_title );
                break;
            case 'submission_rejected':
                $text = sprintf( __( 'Your submission for "%s" needs changes.', 'otm' ), $task->post_title );
                break;
            default:
                return '';
        }
        
        if ( $format === 'string' ) {
            return $text;
        } else {
            return [
                'text' => $text,
                'link' => get_permalink( $task->ID ),
            ];
        }
    }
}
