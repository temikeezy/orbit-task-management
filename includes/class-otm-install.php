<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Install {
    const DB_VERSION = '0.1.1';

    public static function activate() {
        self::create_tables();
        add_option('otm_db_version', self::DB_VERSION);
        add_option('otm_cache_buster', time());
        // Flush rewrite rules to register otm_task archives/singles
        self::flush_rewrite();
    }
    public static function deactivate() {
        // Keep data by default
    }
    public static function maybe_upgrade() {
        $ver = get_option('otm_db_version');
        if ( $ver !== self::DB_VERSION ) {
            self::create_tables();
            update_option('otm_db_version', self::DB_VERSION);
        }
    }
    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $sub = $wpdb->prefix . 'otm_submissions';
        $sql_sub = "CREATE TABLE $sub (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED NULL,
            text_content LONGTEXT NULL,
            urls_json LONGTEXT NULL,
            files_json LONGTEXT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'submitted',
            awarded_points INT NOT NULL DEFAULT 0,
            moderator_id BIGINT UNSIGNED NULL,
            moderator_note TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            approved_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id),
            KEY status (status),
            KEY approved_at (approved_at)
        ) $charset;";

        $log = $wpdb->prefix . 'otm_points_log';
        $sql_log = "CREATE TABLE $log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            points INT NOT NULL,
            task_id BIGINT UNSIGNED NULL,
            submission_id BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY approved_at (approved_at)
        ) $charset;";

        $tot = $wpdb->prefix . 'otm_points_total';
        $sql_total = "CREATE TABLE $tot (
            user_id BIGINT UNSIGNED NOT NULL,
            total_points BIGINT NOT NULL DEFAULT 0,
            updated_at DATETIME NULL,
            PRIMARY KEY (user_id)
        ) $charset;";

        dbDelta($sql_sub);
        dbDelta($sql_log);
        dbDelta($sql_total);
    }

    private static function flush_rewrite() {
        // Ensure CPT is registered before flushing
        if ( function_exists('OTM_Task_CPT::register') ) {
            OTM_Task_CPT::register();
        } else if ( class_exists('OTM_Task_CPT') ) {
            OTM_Task_CPT::register();
        }
        flush_rewrite_rules();
    }
}
