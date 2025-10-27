<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Register group tabs safely */
function otm_register_group_extensions() {
    if ( ! class_exists('BP_Group_Extension') ) return;

    if ( ! class_exists('OTM_Group_Tab') ) {
        class OTM_Group_Tab extends BP_Group_Extension {
            function __construct() {
                $this->name = __('Tasks', 'otm');
                $this->slug = 'otm-tasks';
                $this->visibility = 'public';
                $this->enable_nav_item = true;
            }
            public function display( $group_id = null ) {
                $gid = function_exists('bp_get_current_group_id') ? bp_get_current_group_id() : (int)$group_id;
                echo '<div class="bp-wrap">';
                echo '<div class="bb-head-actions bb-group-head">';
                echo '<h2 class="screen-heading">'.esc_html__('Tasks','otm').'</h2>';
                if ( current_user_can('otm_manage_tasks') && function_exists('groups_get_current_group') && function_exists('bp_get_group_permalink') ) {
                    $create_url = trailingslashit( bp_get_group_permalink( groups_get_current_group() ) ) . 'otm-create/';
                    echo '<div class="actions"><a class="button bb-primary-button" href="'.esc_url($create_url).'">+ '.esc_html__('Create Task','otm').'</a></div>';
                }
                echo '</div>';
                $q = new WP_Query([
                    'post_type' => 'otm_task',
                    'posts_per_page' => 20,
                    'meta_key' => '_otm_stream_id',
                    'meta_value' => $gid,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ]);
                
                if ( $q->have_posts() ) {
                    echo '<ul class="item-list bb-forums-list bb-lists">';
                    while ( $q->have_posts() ) { $q->the_post();
                        $tid = get_the_ID();
                        $max_points = intval(get_post_meta($tid, '_otm_max_points', true));
                        $deadline = esc_html(get_post_meta($tid, '_otm_deadline', true));
                        $formats = (array) get_post_meta($tid, '_otm_formats', true);
                        $allowed = array(); foreach (array('text'=>'Text','url'=>'URL','file'=>'File') as $k=>$label){ if(!empty($formats[$k])) $allowed[]=$label; }
                        echo '<li class="bb-card bb-card--list">';
                        echo '<h3 class="entry-title"><a href="'.esc_url(get_permalink($tid)).'">'.esc_html(get_the_title()).'</a></h3>';
                        echo '<div class="bb-meta">';
                        echo '<span class="otm-badge">'.sprintf(esc_html__('Max: %d pts','otm'), ($max_points?$max_points:0)).'</span>';
                        if ($allowed) echo '<span class="otm-badge">'.esc_html__('Allowed:','otm').' '.esc_html(implode(', ',$allowed)).'</span>';
                        if ($deadline) echo '<span class="otm-badge">'.esc_html__('Deadline:','otm').' '.esc_html($deadline).'</span>';
                        echo '<span class="otm-badge">'.esc_html( OTM_BB::get_stream_name( $gid ) ).'</span>';
                        echo '</div>';
                        echo '<div class="action">';
                        echo '<a class="button primary" href="'.esc_url( get_permalink($tid) ).'">'.esc_html__('Open Task','otm').'</a>';
                        if ( current_user_can('otm_manage_tasks') || current_user_can('otm_moderate_submissions') || current_user_can('manage_options') ) {
                            echo ' <a class="button" href="'.esc_url( admin_url('admin.php?page=otm-submissions&task_id='.$tid) ).'">'.esc_html__('View Submissions (admin)','otm').'</a>';
                            echo ' <a class="button" href="'.esc_url( admin_url('post.php?action=edit&post='.$tid) ).'">'.esc_html__('Edit Task','otm').'</a>';
                        }
                        echo '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<div class="bp-feedback info"><span class="bp-icon"></span><p>'.esc_html__('Sorry, there were no tasks found.','otm').'</p></div>';
                }
                wp_reset_postdata();
                echo '</div>';
            }
        }
    }

    if ( ! class_exists('OTM_Group_Moderation_Tab') ) {
        class OTM_Group_Moderation_Tab extends BP_Group_Extension {
            function __construct() {
                $this->name = __('Moderation', 'otm');
                $this->slug = 'otm-moderation';
                $this->visibility = 'public';
                $this->enable_nav_item = true;
            }
            public function display( $group_id = null ) {
                if ( ! current_user_can('otm_moderate_submissions') ) { status_header(403); echo '<div class="bp-wrap"><div class="bp-feedback error"><span class="bp-icon"></span><p>'.esc_html__('Insufficient permissions.','otm').'</p></div></div>'; return; }
                wp_enqueue_style('otm-frontend'); wp_enqueue_script('otm-frontend');
                $gid = function_exists('bp_get_current_group_id') ? bp_get_current_group_id() : (int)$group_id;
                $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'pending';
                $valid = array('all','pending','approved','rejected','changes_requested'); if (!in_array($status,$valid,true)) $status='pending';
                echo '<div class="bp-wrap">';
                echo '<nav class="bp-navs bp-subnavs"><ul class="subnav">';
                foreach (array('pending','approved','rejected','all') as $st) {
                    $class = $status===$st? ' class="current"' : '';
                    $url = esc_url( add_query_arg('status',$st) );
                    echo '<li'.$class.'><a href="'.$url.'">'.esc_html( ucfirst($st==='all'?'All':$st) ).'</a></li>';
                }
                echo '</ul></nav>';

                global $wpdb; $sub = $wpdb->prefix.'otm_submissions'; $pm = $wpdb->prefix.'postmeta'; $p = $wpdb->posts;
                $where = $wpdb->prepare("WHERE pm.meta_key = '_otm_stream_id' AND pm.meta_value = %d", $gid);
                if ( $status !== 'all' ) {
                    $where .= $wpdb->prepare(" AND s.status = %s", $status);
                }
                $paged = max(1, absint(isset($_GET['otm_page'])?$_GET['otm_page']:1)); $per=20; $off=($paged-1)*$per;
                $sql = "SELECT SQL_CALC_FOUND_ROWS s.*, p.post_title FROM {$sub} s INNER JOIN {$p} p ON p.ID=s.task_id INNER JOIN {$pm} pm ON pm.post_id=s.task_id {$where} ORDER BY s.created_at DESC LIMIT %d OFFSET %d";
                $rows = $wpdb->get_results( $wpdb->prepare($sql, $per, $off) );
                $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');

                if ( $rows ) {
                    echo '<ul class="item-list bb-forums-list bb-lists">';
                    foreach ( $rows as $r ) {
                        $user = get_user_by('id', $r->user_id);
                        $created_ts = strtotime($r->created_at.' UTC');
                        echo '<li class="bb-card bb-card--list">';
                        echo '<div class="bb-meta">';
                        echo get_avatar( (int)$r->user_id, 48 );
                        echo ' <strong><a href="'.esc_url( get_author_posts_url( (int)$r->user_id ) ).'">'.esc_html( $user ? $user->display_name : ('#'.$r->user_id) ).'</a></strong>';
                        echo ' · <time datetime="'.esc_attr( gmdate('c',$created_ts) ).'">'.esc_html( human_time_diff($created_ts, current_time('timestamp',true)) ).' '.__('ago','otm').'</time>';
                        echo ' <span class="otm-badge">'.esc_html( ucfirst($r->status) ).'</span>';
                        echo '</div>';
                        echo '<h3 class="entry-title"><a href="'.esc_url( get_permalink( (int)$r->task_id ) ).'">'.esc_html( $r->post_title ).'</a></h3>';
                        echo '<div class="action">';
                        echo '<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'" style="display:inline">';
                        echo '<input type="hidden" name="action" value="otm_mod_approve" />';
                        wp_nonce_field('otm_mod_'.$r->id);
                        echo '<input type="hidden" name="submission_id" value="'.intval($r->id).'" />';
                        echo '<label>'.esc_html__('Points','otm').' <input type="number" name="points" min="0" value="'.intval($r->awarded_points).'" style="width:80px" /></label> ';
                        echo '<button class="button primary">'.esc_html__('Approve','otm').'</button>';
                        echo '</form> ';
                        echo '<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'" style="display:inline">';
                        echo '<input type="hidden" name="action" value="otm_mod_reject" />';
                        wp_nonce_field('otm_mod_'.$r->id);
                        echo '<input type="hidden" name="submission_id" value="'.intval($r->id).'" />';
                        echo '<button class="button">'.esc_html__('Reject','otm').'</button>';
                        echo '</form> ';
                        echo '<form method="post" action="'.esc_url( admin_url('admin-post.php') ).'" style="display:inline">';
                        echo '<input type="hidden" name="action" value="otm_mod_request" />';
                        wp_nonce_field('otm_mod_'.$r->id);
                        echo '<input type="hidden" name="submission_id" value="'.intval($r->id).'" />';
                        echo '<button class="button">'.esc_html__('Request Changes','otm').'</button>';
                        echo '</form>';
                        echo '</div>';
                        // Collapsible details
                        echo '<details style="margin-top:8px">';
                        echo '<summary>'.esc_html__('View submission','otm').'</summary>';
                        if ( ! empty($r->text_content) ) echo wp_kses_post( wpautop( $r->text_content ) );
                        $urls = $r->urls_json ? json_decode($r->urls_json,true):array(); if ($urls){ echo '<p>'; foreach($urls as $u){ echo '<a href="'.esc_url($u).'" target="_blank" rel="nofollow noopener">'.esc_html($u).'</a> '; } echo '</p>'; }
                        $files = $r->files_json ? json_decode($r->files_json,true):array(); if ($files){ echo '<p>'; foreach($files as $f){ echo '<a href="'.esc_url($f).'" target="_blank" rel="nofollow noopener">'.esc_html__('Download file','otm').'</a> '; } echo '</p>'; }
                        echo '</details>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    if ( $total > $per ) { $pages = (int) ceil($total/$per); echo '<nav class="pagination">'; for($i=1;$i<=$pages;$i++){ $cls = $i===$paged? ' class="current"' : ''; echo '<a'.$cls.' href="'.esc_url( add_query_arg('otm_page',$i) ).'">'.intval($i).'</a> '; } echo '</nav>'; }
                } else {
                    echo '<div class="bp-feedback info"><span class="bp-icon"></span><p>'.esc_html__('No submissions found for this filter.','otm').'</p></div>';
                }
                echo '</div>';
            }
        }
    }

    if ( ! class_exists('OTM_Group_Leaderboard_Tab') ) {
        class OTM_Group_Leaderboard_Tab extends BP_Group_Extension {
            function __construct() {
                $this->name = __('Leaderboard', 'otm');
                $this->slug = 'otm-leaderboard';
                $this->visibility = 'public';
                $this->enable_nav_item = true;
            }
            public function display( $group_id = null ) {
                $gid = function_exists('bp_get_current_group_id') ? bp_get_current_group_id() : (int)$group_id;
                echo '<h3>This Week</h3>';
                echo do_shortcode('[otm_leaderboard scope="stream" stream_id="'.$gid.'" week="current" limit="20" show_total="1"]');
                echo '<hr/><h3>Overall</h3>';
                echo do_shortcode('[otm_leaderboard scope="stream" stream_id="'.$gid.'" week="all" limit="20" show_total="1"]');
            }
        }
    }

    if ( function_exists('bp_register_group_extension') ) {
        // Ensure Create tab class is loaded
        if ( ! class_exists('OTM_Group_Create_Tab') && file_exists( OTM_DIR . 'includes/groups/class-otm-group-create.php' ) ) {
            require_once OTM_DIR . 'includes/groups/class-otm-group-create.php';
        }
        bp_register_group_extension( 'OTM_Group_Tab' );
        bp_register_group_extension( 'OTM_Group_Moderation_Tab' );
        bp_register_group_extension( 'OTM_Group_Leaderboard_Tab' );
        if ( class_exists('OTM_Group_Create_Tab') ) {
            bp_register_group_extension( 'OTM_Group_Create_Tab' );
        }
    }
}
add_action('bp_include', 'otm_register_group_extensions');

// Back-compat wrapper for main loader
if ( ! class_exists('OTM_Group_Extension') ) {
    class OTM_Group_Extension {
        public static function init() {
            add_action('bp_include', 'otm_register_group_extensions');
        }
    }
}

/** Hide the Create tab from the visible group navigation, but keep the route working */
function otm_hide_create_nav_item() {
    if ( function_exists('bp_is_group') && bp_is_group() && function_exists('bp_core_remove_subnav_item') ) {
        if ( function_exists('bp_get_current_group_slug') ) {
            bp_core_remove_subnav_item( bp_get_current_group_slug(), 'otm-create', 'groups' );
        }
    }
}
add_action('bp_actions', 'otm_hide_create_nav_item', 20);
