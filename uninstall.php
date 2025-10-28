<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Drop custom tables (ignore if they don't exist)
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}otm_submissions" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}otm_points_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}otm_points_total" );

// Remove plugin options if present
delete_option( 'otm_settings' );
delete_option( 'otm_db_version' );
delete_option( 'otm_cache_buster' );

<?php
/**
 * Uninstall routine for ORBIT Task Management
 * 
 * This file is executed when the plugin is deleted via WordPress admin.
 * It provides options to clean up plugin data.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Include the main plugin file to access classes
require_once plugin_dir_path( __FILE__ ) . 'includes/class-otm-install.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-otm-capabilities.php';

// Get cleanup preference (default to true for safety)
$cleanup_data = get_option( 'otm_cleanup_on_uninstall', true );

if ( $cleanup_data ) {
    global $wpdb;
    
    // Drop custom tables
    $tables = [
        $wpdb->prefix . 'otm_submissions',
        $wpdb->prefix . 'otm_points_log', 
        $wpdb->prefix . 'otm_points_total'
    ];
    
    foreach ( $tables as $table ) {
        $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
    }
    
    // Remove options
    $options = [
        'otm_settings',
        'otm_db_version',
        'otm_cache_buster',
        'otm_cleanup_on_uninstall'
    ];
    
    foreach ( $options as $option ) {
        delete_option( $option );
    }
    
    // Remove transients
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_otm_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_otm_%'" );
    
    // Remove user roles and capabilities
    OTM_Capabilities::remove_roles();
    
    // Clear any scheduled hooks
    wp_clear_scheduled_hook( 'otm_weekly_cleanup' );
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
