<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $post, $wpdb; $task_id = get_the_ID();
$stream_id = intval(get_post_meta($task_id, '_otm_stream_id', true));
$max_points = intval(get_post_meta($task_id, '_otm_max_points', true));
if ( ! $max_points ) { $max_points = (int) OTM_Settings::get('default_max_points', 10); }
$deadline = get_post_meta($task_id, '_otm_deadline', true);
$formats = (array) get_post_meta($task_id, '_otm_formats', true);
$table = $wpdb->prefix . 'otm_submissions';

get_header();
?>
<div class="otm-wrap">
    <article id="post-<?php echo esc_attr($task_id); ?>" <?php post_class('otm-card'); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php echo esc_html( get_the_title() ); ?></h1>
            <div class="entry-meta">
                <span class="otm-badge"><?php echo esc_html( get_the_author() ); ?></span>
                <span class="otm-badge"><time datetime="<?php echo esc_attr( get_the_date('c') ); ?>"><?php echo esc_html( get_the_date() ); ?></time></span>
                <?php if ( $stream_id ) : ?>
                    <span class="otm-badge"><?php echo esc_html( OTM_BB::get_stream_name( $stream_id ) ); ?></span>
                <?php endif; ?>
            </div>
        </header>
        <div class="entry-content">
            <?php echo apply_filters('the_content', get_the_content()); ?>
            <div class="otm-meta-badges">
                <span class="otm-badge"><?php echo esc_html__('Max points','otm'); ?>: <?php echo intval($max_points); ?></span>
                <?php if ( $deadline ) : ?><span class="otm-badge"><?php echo esc_html__('Deadline','otm'); ?>: <?php echo esc_html($deadline); ?></span><?php endif; ?>
                <?php
                $allowed = array(); foreach (array('text'=>'Text','url'=>'URL','file'=>'File') as $k=>$label){ if(!empty($formats[$k])) $allowed[]=$label; }
                ?>
                <span class="otm-badge"><?php echo esc_html__('Allowed','otm'); ?>: <?php echo $allowed ? esc_html(implode(', ',$allowed)) : '—'; ?></span>
            </div>
        </div>
    </article>

    <?php if ( isset($_GET['submitted']) && $_GET['submitted'] ) : ?>
        <div class="otm-card" role="status"><p><?php echo esc_html__('Your submission was received.','otm'); ?></p></div>
    <?php endif; ?>

    <section class="otm-thread">
        <h2><?php echo esc_html__('Submissions','otm'); ?></h2>
        <?php
        $paged = max(1, absint(get_query_var('paged') ?: (isset($_GET['otm_page'])?$_GET['otm_page']:1)));
        $per_page = 20;
        $offset = ($paged-1) * $per_page;
        $items = $wpdb->get_results( $wpdb->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM {$table} WHERE task_id=%d ORDER BY created_at DESC LIMIT %d OFFSET %d", $task_id, $per_page, $offset) );
        $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
        if ( $items ) : ?>
            <ul class="otm-thread-list">
                <?php foreach ( $items as $it ) : $user = get_user_by('id', $it->user_id); ?>
                    <li class="otm-thread-item">
                        <div class="otm-thread-avatar"><?php echo get_avatar( (int) $it->user_id, 48 ); ?></div>
                        <div class="otm-thread-body">
                            <div class="otm-thread-meta">
                                <strong><a href="<?php echo esc_url( get_author_posts_url( (int) $it->user_id ) ); ?>"><?php echo esc_html( $user ? $user->display_name : ('#'.$it->user_id) ); ?></a></strong>
                                <span class="sep">·</span>
                                <?php
                                $created_ts = strtotime($it->created_at.' UTC');
                                $human = human_time_diff( $created_ts, current_time('timestamp', true) );
                                ?>
                                <time datetime="<?php echo esc_attr( gmdate('c', $created_ts) ); ?>"><?php echo esc_html( $human . ' ' . __('ago','otm') ); ?></time>
                                <span class="otm-badge status"><?php echo esc_html( ucfirst($it->status) ); ?></span>
                                <?php if ( $it->status === 'approved' ) : ?>
                                    <span class="otm-badge points"><?php echo intval($it->awarded_points); ?> <?php echo esc_html__('pts','otm'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="otm-thread-content">
                                <?php if ( ! empty($it->text_content) ) : ?>
                                    <div class="otm-text"><?php echo esc_html__('Text submission provided','otm'); ?></div>
                                <?php endif; ?>
                                <?php $urls = $it->urls_json ? json_decode($it->urls_json, true) : array(); if ( $urls ) : ?>
                                    <div class="otm-urls"><?php echo esc_html__('URL(s) provided','otm'); ?></div>
                                <?php endif; ?>
                                <?php $files = $it->files_json ? json_decode($it->files_json, true) : array(); if ( $files ) : ?>
                                    <div class="otm-files"><?php echo esc_html__('File(s) uploaded','otm'); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ( $total > $per_page ) : $num_pages = (int) ceil($total / $per_page); ?>
                <nav class="otm-pagination">
                    <?php for ($i=1; $i<=$num_pages; $i++) : ?>
                        <a class="<?php echo $i===$paged?'current':''; ?>" href="<?php echo esc_url( add_query_arg('otm_page', $i, get_permalink($task_id)) ); ?>"><?php echo intval($i); ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php else : ?>
            <p><?php echo esc_html__('No submissions yet. Be the first to submit.','otm'); ?></p>
        <?php endif; ?>
    </section>

    <section class="otm-submit">
        <?php
        // Show eligibility or existing submission card
        $can_show_form = is_user_logged_in() && ! current_user_can('otm_moderate_submissions') && ! current_user_can('otm_manage_tasks') && ! current_user_can('manage_options');
        $user_id = get_current_user_id();
        $existing = $can_show_form ? $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$table} WHERE task_id=%d AND user_id=%d", $task_id, $user_id) ) : null;
        $require_membership = (bool) OTM_Settings::get('require_membership', false);
        $deadline_passed = false;
        if ( $deadline ) {
            $deadline_ts = strtotime($deadline.' UTC');
            $deadline_passed = $deadline_ts && current_time('timestamp', true) > $deadline_ts;
        }
        ?>
        <?php if ( $existing ) : ?>
            <div class="otm-card">
                <p><?php echo esc_html__('You submitted on','otm'); ?> <?php echo esc_html( get_date_from_gmt( $existing->created_at, get_option('date_format').' '.get_option('time_format') ) ); ?>.
                <?php echo esc_html__('Status','otm'); ?>: <strong><?php echo esc_html( ucfirst($existing->status) ); ?></strong>.
                <?php if ( $existing->status === 'approved' ) : ?><?php echo esc_html__('Points','otm'); ?>: <strong><?php echo intval($existing->awarded_points); ?></strong><?php endif; ?>
                </p>
            </div>
        <?php elseif ( ! $can_show_form ) : ?>
            <div class="otm-card"><p><?php echo esc_html__('Log in as an intern to submit.','otm'); ?></p></div>
        <?php elseif ( $deadline_passed ) : ?>
            <div class="otm-card"><p><?php echo esc_html__('The deadline for this task has passed.','otm'); ?></p></div>
        <?php elseif ( $require_membership && $stream_id && function_exists('groups_is_user_member') && ! groups_is_user_member($user_id, $stream_id) ) : ?>
            <?php $join_url = function_exists('bp_get_group_permalink') && function_exists('groups_get_group') ? bp_get_group_permalink( groups_get_group($stream_id) ) : home_url(); ?>
            <div class="otm-card"><p><?php echo esc_html__('You must join this Stream to submit.','otm'); ?> <a class="otm-btn" href="<?php echo esc_url($join_url); ?>"><?php echo esc_html__('Join Stream','otm'); ?></a></p></div>
        <?php else : ?>
            <?php echo do_shortcode('[otm_task_submit]'); ?>
        <?php endif; ?>
    </section>
</div>
<?php get_footer(); ?>


