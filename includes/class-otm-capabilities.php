<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Capabilities {
    public static function add_roles() {
        add_role('otm_intern', 'OTM Intern', ['read' => true, 'otm_submit_tasks' => true, 'otm_view_own_submissions' => true]);
        add_role('otm_moderator', 'OTM Moderator', ['read' => true, 'otm_submit_tasks' => true, 'otm_manage_tasks' => true, 'otm_view_stream_submissions' => true, 'otm_moderate_submissions' => true, 'otm_adjust_points' => true]);
        add_role('otm_admin', 'OTM Admin', ['read' => true, 'otm_submit_tasks' => true, 'otm_manage_tasks' => true, 'otm_view_stream_submissions' => true, 'otm_moderate_submissions' => true, 'otm_adjust_points' => true, 'otm_manage_settings' => true]);

        $admin = get_role('administrator');
        if ($admin) {
            foreach (['otm_submit_tasks','otm_view_own_submissions','otm_manage_tasks','otm_view_stream_submissions','otm_moderate_submissions','otm_adjust_points','otm_manage_settings'] as $cap) {
                $admin->add_cap($cap);
            }
        }
    }
}
