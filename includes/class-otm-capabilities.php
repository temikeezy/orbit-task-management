<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Capabilities {
    public static function add_roles() {
        // Define custom capabilities
        $caps = [
            'otm_submit_tasks' => __('Submit Tasks', 'otm'),
            'otm_view_own_submissions' => __('View Own Submissions', 'otm'),
            'otm_manage_tasks' => __('Manage Tasks', 'otm'),
            'otm_view_stream_submissions' => __('View Stream Submissions', 'otm'),
            'otm_moderate_submissions' => __('Moderate Submissions', 'otm'),
            'otm_adjust_points' => __('Adjust Points', 'otm'),
            'otm_manage_settings' => __('Manage OTM Settings', 'otm'),
        ];

        // Add roles with capabilities
        add_role('otm_intern', __('OTM Intern', 'otm'), [
            'read' => true, 
            'otm_submit_tasks' => true, 
            'otm_view_own_submissions' => true
        ]);
        
        add_role('otm_moderator', __('OTM Moderator', 'otm'), [
            'read' => true, 
            'otm_submit_tasks' => true, 
            'otm_manage_tasks' => true, 
            'otm_view_stream_submissions' => true, 
            'otm_moderate_submissions' => true, 
            'otm_adjust_points' => true
        ]);
        
        add_role('otm_admin', __('OTM Admin', 'otm'), [
            'read' => true, 
            'otm_submit_tasks' => true, 
            'otm_manage_tasks' => true, 
            'otm_view_stream_submissions' => true, 
            'otm_moderate_submissions' => true, 
            'otm_adjust_points' => true, 
            'otm_manage_settings' => true
        ]);

        // Add capabilities to administrator
        $admin = get_role('administrator');
        if ($admin) {
            foreach (array_keys($caps) as $cap) {
                $admin->add_cap($cap);
            }
        }

        // Add capabilities to editor (can manage tasks but not moderate)
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('otm_submit_tasks');
            $editor->add_cap('otm_view_own_submissions');
            $editor->add_cap('otm_manage_tasks');
        }
    }

    public static function remove_roles() {
        remove_role('otm_intern');
        remove_role('otm_moderator');
        remove_role('otm_admin');
        
        // Remove capabilities from administrator
        $admin = get_role('administrator');
        if ($admin) {
            $caps = ['otm_submit_tasks','otm_view_own_submissions','otm_manage_tasks','otm_view_stream_submissions','otm_moderate_submissions','otm_adjust_points','otm_manage_settings'];
            foreach ($caps as $cap) {
                $admin->remove_cap($cap);
            }
        }

        // Remove capabilities from editor
        $editor = get_role('editor');
        if ($editor) {
            $editor->remove_cap('otm_submit_tasks');
            $editor->remove_cap('otm_view_own_submissions');
            $editor->remove_cap('otm_manage_tasks');
        }
    }
}
