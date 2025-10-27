<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OTM_Groups_Labels {
    public static function init() { add_filter('gettext', [__CLASS__, 'replace_strings'], 20, 3); }
    private static function enabled() {
        $opts = get_option('otm_settings', []);
        return !empty($opts['rename_groups']);
    }
    private static function singular() {
        $opts = get_option('otm_settings', []);
        return isset($opts['stream_singular']) ? $opts['stream_singular'] : 'Stream';
    }
    private static function plural() {
        $opts = get_option('otm_settings', []);
        return isset($opts['stream_plural']) ? $opts['stream_plural'] : 'Streams';
    }
    public static function replace_strings($translated, $text, $domain) {
        if ( ! self::enabled() ) return $translated;
        // Simple replacements; won't cover every string but keeps it safe.
        $map = ['Group' => self::singular(),'Groups' => self::plural(),'group' => strtolower(self::singular()),'groups' => strtolower(self::plural())];
        if ( isset($map[$text]) ) return $map[$text];
        return $translated;
    }
}
