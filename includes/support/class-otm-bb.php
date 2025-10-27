<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_BB {
    public static function is_active() : bool {
        return function_exists('groups_get_group') && function_exists('bp_get_current_group_id');
    }

    public static function current_group_id() : int {
        return self::is_active() ? (int) bp_get_current_group_id() : 0;
    }

    public static function get_stream_name( $group_id ) {
        $group_id = absint( $group_id );
        if ( $group_id <= 0 ) {
            return __( 'Stream', 'otm' );
        }
        if ( function_exists( 'groups_get_group' ) ) {
            $group = groups_get_group( array( 'group_id' => $group_id ) );
            if ( $group && ! empty( $group->name ) ) {
                return (string) $group->name;
            }
        }
        return sprintf( __( 'Stream #%d', 'otm' ), $group_id );
    }

    public static function get_group_id() {
        if ( function_exists('bp_get_current_group_id') ) {
            return (int) bp_get_current_group_id();
        }
        return 0;
    }

    public static function stream_name( int $group_id ) : string {
        return self::get_stream_name( $group_id );
    }

    public static function groups_for_dropdown() : array {
        if ( ! self::is_active() ) return [];
        $resp = groups_get_groups([
            'per_page'    => 999,
            'page'        => 1,
            'show_hidden' => true,
            'orderby'     => 'name',
            'order'       => 'ASC',
        ]);
        $out = [];
        if ( ! empty( $resp['groups'] ) ) {
            foreach ( $resp['groups'] as $g ) {
                $out[ (int) $g->id ] = $g->name;
            }
        }
        return $out;
    }
}


