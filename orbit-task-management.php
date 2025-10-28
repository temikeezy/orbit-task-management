<?php
/**
 * Plugin Name: ORBIT Task Management
 * Description: Minimal tasks, submissions, scoring, and leaderboards (global & per stream) with native points. BuddyBoss/BuddyPress optional.
 * Version: 0.1.5
 * Author: ORBIT
 * Requires at least: 5.6
 * Requires PHP: 7.0
 * Text Domain: otm
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OTM_VERSION', '0.1.7' );
define( 'OTM_FILE', __FILE__ );
define( 'OTM_DIR', plugin_dir_path( __FILE__ ) );
define( 'OTM_URL', plugin_dir_url( __FILE__ ) );

require_once OTM_DIR . 'includes/__init__.php';
require_once OTM_DIR . 'includes/support/class-otm-assets.php';

register_activation_hook( __FILE__, ['OTM_Install', 'activate'] );
register_deactivation_hook( __FILE__, ['OTM_Install', 'deactivate'] );

add_action('plugins_loaded', function() {
    // Load text domain for translations
    load_plugin_textdomain('otm', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    OTM_Install::maybe_upgrade();
    OTM_Assets::init();
    OTM_Settings::init();
    OTM_Capabilities::add_roles();
    OTM_Task_CPT::init();
    OTM_Submissions::init();
    OTM_Frontend::init();
    add_action('admin_post_otm_submit_reply_front', ['OTM_Frontend','handle_reply_front']);
    add_action('admin_post_otm_export_submissions', ['OTM_Submissions','handle_export']);
    OTM_Leaderboard::init();
    OTM_Widget_Weekly::register();
    OTM_Widget_Overall::register();
    if ( class_exists('OTM_Widget_My_Points') ) OTM_Widget_My_Points::register();
    if ( class_exists('OTM_Widget_My_Submissions') ) OTM_Widget_My_Submissions::register();
    OTM_Groups_Labels::init();
    // BuddyBoss/BuddyPress group tabs (if available)
    if ( function_exists('bp_register_group_extension') || class_exists('BP_Group_Extension') ) {
        OTM_Group_Extension::init();
        // Group tabs are fully registered inside the group extension bootstrap (bp_include)
    }
    // BuddyBoss integration (if available)
    if ( class_exists('OTM_BuddyBoss_Integration') ) {
        OTM_BuddyBoss_Integration::init();
    }
    if ( class_exists('OTM_Gamipress_Automation') ) {
        OTM_Gamipress_Automation::init();
    }
    if ( class_exists('OTM_Privacy') ) {
        OTM_Privacy::init();
    }
    if ( class_exists('OTM_REST') ) {
        OTM_REST::init();
    }
}, 1);

/** Points service singleton (native or GamiPress-decorated) */
function otm_points_service() {
    static $service = null;
    if ( $service ) return $service;

    require_once OTM_DIR . 'includes/points/class-otm-points-native.php';
    $native = new OTM_Points_Native();

    if (
        apply_filters( 'otm_gamipress_integration_enabled', true )
        && ( function_exists( 'gamipress' ) || class_exists( 'GamiPress' ) || function_exists( 'gamipress_award_points_to_user' ) )
    ) {
        require_once OTM_DIR . 'includes/points/class-otm-points-gamipress.php';
        $service = new OTM_Points_GamiPress( $native );
    } else {
        $service = $native;
    }

    return $service;
}
