<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1>All Fanpage Insights</h1>
    <p>View and manage all synced fanpage data.</p>

    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get">
                <input type="hidden" name="page" value="fpis-insights">
                <input type="text" name="search" value="<?php echo esc_attr( $_GET['search'] ?? '' ); ?>" placeholder="Search pages...">
                <input type="submit" class="button" value="Filter">
            </form>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Page Name</th>
                <th>Followers</th>
                <th>Region</th>
                <th>Gender</th>
                <th>Price</th>
                <th>AI Status</th>
                <th>Last Sync</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $args = [
                'search' => $_GET['search'] ?? '',
                'page'   => $_GET['paged'] ?? 1,
                'per_page' => 20
            ];
            $results = FPIS_DB::get_insights( $args );
            
            if ( empty( $results['data'] ) ) : ?>
                <tr><td colspan="7">No data found.</td></tr>
            <?php else : 
                foreach ( $results['data'] as $row ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $row->page_name ); ?></strong><br><small><?php echo esc_html( $row->link_fanpage ); ?></small></td>
                    <td><?php echo number_format( $row->follow_count ); ?></td>
                    <td><?php echo esc_html( $row->region_focus ?: 'N/A' ); ?></td>
                    <td><?php echo $row->female_pct ? $row->female_pct . '% Female' : 'N/A'; ?></td>
                    <td><?php echo $row->calculated_price ? number_format( $row->calculated_price ) . ' VND' : 'N/A'; ?></td>
                    <td>
                        <span class="status-badge <?php echo esc_attr( $row->ai_status ); ?>">
                            <?php echo esc_html( strtoupper( $row->ai_status ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( $row->synced_at ); ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<style>
.status-badge { padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
.status-badge.completed { background: #d1fae5; color: #065f46; }
.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.failed { background: #fee2e2; color: #991b1b; }
</style>
