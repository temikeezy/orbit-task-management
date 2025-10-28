<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists('BP_Group_Extension') && ! class_exists('OTM_Group_Create_Tab') ) {
    class OTM_Group_Create_Tab extends BP_Group_Extension {
        public function __construct() {
            $this->name = __('Create', 'otm');
            $this->slug = 'otm-create';
            $this->visibility = 'public';
            $this->enable_nav_item = true; // expose sub-tab
            $this->nav_item_position = 36;
        }
        public function show_tab() {
            return is_user_logged_in() && current_user_can('otm_manage_tasks');
        }
        public function display( $group_id = null ) {
            if ( ! current_user_can('otm_manage_tasks') ) { status_header(403); echo '<div class="bp-wrap"><div class="bp-feedback error"><span class="bp-icon"></span><p>'.esc_html__('Insufficient permissions.','otm').'</p></div></div>'; return; }
            wp_enqueue_style('otm-frontend'); wp_enqueue_script('otm-frontend');
            $gid = OTM_BB::current_group_id() ?: (int) $group_id;

            echo '<div class="bp-wrap">';
            echo '<div class="bb-head-actions bb-group-head">';
            echo '<h2 class="screen-heading">'. esc_html__('Create Task', 'otm') .'</h2>';
            echo '</div>';

            echo '<div class="bb-content">';
            echo '<form class="standard-form" method="post" action="'. esc_url( admin_url('admin-post.php') ) .'" enctype="multipart/form-data">';
            echo '<input type="hidden" name="action" value="otm_create_task" />';
            if ( function_exists('wp_nonce_field') ) { wp_nonce_field('otm_create_task'); }

            if ( OTM_BB::is_active() ) {
                $groups = OTM_BB::groups_for_dropdown();
                echo '<label>'. esc_html__( 'Stream (Group)', 'otm' ) .'</label>';
                echo '<select name="stream_id">';
                foreach ( $groups as $id => $name ) {
                    $selected = selected( (int)$gid, (int)$id, false );
                    echo '<option value="'.esc_attr($id).'" '.$selected.'>'.esc_html($name).'</option>';
                }
                echo '</select>';
            } else {
                echo '<label>'. esc_html__( 'Stream (Group ID)', 'otm' ) .'</label>';
                echo '<input type="number" name="stream_id" value="0" min="0" />';
                echo '<p class="description">'. esc_html__('BuddyBoss/BuddyPress not active — enter numeric Stream ID.', 'otm') .'</p>';
            }

            echo '<label>'. esc_html__( 'Title', 'otm' ) .'</label><input type="text" name="title" required />';
            echo '<label>'. esc_html__( 'Description', 'otm' ) .'</label><textarea name="content" rows="6"></textarea>';
            echo '<label>'. esc_html__( 'Max Points', 'otm' ) .'</label><input type="number" name="max_points" value="'. esc_attr( (int) OTM_Settings::get('default_max_points', 10) ) .'" min="0" />';
            $default_dt = date('Y-m-d\TH:i', strtotime('+7 days', current_time('timestamp')));
            echo '<label>'. esc_html__( 'Deadline', 'otm' ) .'</label><input type="datetime-local" name="deadline" value="'. esc_attr( $default_dt ) .'" />';
            echo '<fieldset><legend>'. esc_html__( 'Allowed Submission Fields', 'otm' ) .'</legend>';
            echo '<label><input type="checkbox" name="formats[text]" value="1" checked /> '. esc_html__( 'Text', 'otm' ) .'</label> ';
            echo '<label><input type="checkbox" name="formats[url]" value="1" /> '. esc_html__( 'URL', 'otm' ) .'</label> ';
            echo '<label><input type="checkbox" name="formats[file]" value="1" /> '. esc_html__( 'File', 'otm' ) .'</label>';
            echo '</fieldset>';
            echo '<button class="button bb-primary-button">'. esc_html__( 'Create Task', 'otm' ) .'</button>';
            echo '</form>';
            echo '</div></div>';
        }
    }
}
