<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Assets {
    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register' ], 5 );
        add_action( 'bp_enqueue_scripts', [ __CLASS__, 'register' ], 5 );
        add_action( 'wp', [ __CLASS__, 'maybe_enqueue' ] );
        add_action( 'bp_actions', [ __CLASS__, 'maybe_enqueue' ] );
    }
    public static function register() {
        $css = OTM_URL . 'assets/css/frontend.css';
        $js  = OTM_URL . 'assets/js/frontend.js';
        $css_ver = ( defined('OTM_DIR') && file_exists( OTM_DIR . 'assets/css/frontend.css' ) ) ? @filemtime( OTM_DIR . 'assets/css/frontend.css' ) : OTM_VERSION;
        $js_ver  = ( defined('OTM_DIR') && file_exists( OTM_DIR . 'assets/js/frontend.js' ) ) ? @filemtime( OTM_DIR . 'assets/js/frontend.js' ) : OTM_VERSION;
        if ( ! wp_style_is( 'otm-frontend', 'registered' ) ) {
            wp_register_style( 'otm-frontend', $css, [], $css_ver );
        }
        if ( ! wp_script_is( 'otm-frontend', 'registered' ) ) {
            wp_register_script( 'otm-frontend', $js, [], $js_ver, true );
        }
    }
    public static function maybe_enqueue() {
        if ( is_singular('otm_task') ) {
            wp_enqueue_style( 'otm-frontend' );
            wp_enqueue_script( 'otm-frontend' );
            return;
        }
        if ( function_exists('bp_is_group') && bp_is_group() ) {
            $action = function_exists('bp_current_action') ? bp_current_action() : '';
            $otm_tabs = [ 'otm-tasks', 'otm-create', 'otm-moderation', 'otm-leaderboard' ];
            if ( in_array( $action, $otm_tabs, true ) ) {
                wp_enqueue_style( 'otm-frontend' );
                wp_enqueue_script( 'otm-frontend' );
            }
        }
    }
}



