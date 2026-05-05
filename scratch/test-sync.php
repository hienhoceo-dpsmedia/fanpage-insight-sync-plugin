<?php
/**
 * Manual test script for Fanpage Insight Sync
 * Usage: Run this in a WP environment or mock WP constants.
 */

// Since this is a scratch script, I'll mock the config if WP is not loaded
if ( ! defined( 'ABSPATH' ) ) {
    echo "This script must be run within a WordPress environment.";
    exit;
}

require_once FPIS_PATH . 'includes/class-fpis-db.php';
require_once FPIS_PATH . 'includes/class-fpis-sync.php';
require_once FPIS_PATH . 'includes/class-fpis-queue.php';

$test_source = [
    'id'         => 'test-huu-dai',
    'label'      => 'Test Huu Dai Sheet',
    'sheet_id'   => '1ysbSEX6tlIYsToLB7lXaWM6KUt9U32Tyfp1wQJIGWIo',
    'gid'        => '1644614207',
    'header_row' => 11,
    'column_map' => [
        'page_name'    => [ 'header' => 'TÊN FANPAGE',   'unit' => 'raw' ],
        'link_fanpage' => [ 'header' => 'LINK FANPAGE',  'unit' => 'raw' ],
        'follow'       => [ 'header' => 'FOLLOW',        'unit' => 'raw' ],
        'thong_tin'    => [ 'header' => 'THÔNG TIN',     'unit' => 'raw' ],
        'gioi_tinh'    => [ 'header' => 'GIỚI TÍNH (%)', 'unit' => 'raw' ],
        'ghi_chu'      => [ 'header' => 'GHI CHÚ',       'unit' => 'raw' ],
        'tinh_trang'   => [ 'header' => 'TÌNH TRẠNG',    'unit' => 'raw' ],
    ],
    'enabled'    => true
];

echo "Starting sync for: " . $test_source['label'] . "\n";
FPIS_Sync::sync_source( $test_source );
echo "Sync completed.\n";

$results = FPIS_DB::get_insights([ 'per_page' => 5 ]);
echo "First 5 results:\n";
print_r( $results['data'] );
