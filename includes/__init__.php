<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once OTM_DIR . 'includes/class-otm-install.php';
require_once OTM_DIR . 'includes/class-otm-capabilities.php';
require_once OTM_DIR . 'includes/class-otm-settings.php';
require_once OTM_DIR . 'includes/class-otm-groups-labels.php';
require_once OTM_DIR . 'includes/support/class-otm-bb.php';

require_once OTM_DIR . 'includes/cpt/class-otm-task.php';
require_once OTM_DIR . 'includes/admin/class-otm-submissions.php';

require_once OTM_DIR . 'includes/leaderboard/class-otm-leaderboard.php';
require_once OTM_DIR . 'includes/leaderboard/class-otm-widget-weekly.php';
require_once OTM_DIR . 'includes/leaderboard/class-otm-widget-overall.php';

require_once OTM_DIR . 'includes/points/class-otm-points-service.php';
require_once OTM_DIR . 'includes/points/class-otm-points-native.php';

require_once OTM_DIR . 'includes/frontend/class-otm-frontend.php';
require_once OTM_DIR . 'includes/groups/class-otm-group-extension.php';
