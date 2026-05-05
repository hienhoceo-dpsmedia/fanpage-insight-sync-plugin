<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1>Sheet Sources</h1>
    
    <?php if ( $step === 1 ) : ?>
    <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
        <h2>Step 1: Add New Source</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'fpis_fetch_headers' ); ?>
            <input type="hidden" name="fpis_step" value="2">
            <table class="form-table">
                <tr>
                    <th>Label</th>
                    <td><input type="text" name="label" class="regular-text" placeholder="e.g. TikTok List" required></td>
                </tr>
                <tr>
                    <th>Sheet URL</th>
                    <td><input type="text" name="sheet_url" class="large-text" placeholder="Paste full Google Sheet URL here" required></td>
                </tr>
                <tr>
                    <th>Header Row</th>
                    <td><input type="number" name="header_row" value="1" min="1" class="small-text"></td>
                </tr>
            </table>
            <?php submit_button( 'Fetch Headers', 'primary', 'fpis_fetch_submit' ); ?>
        </form>
    </div>
    <?php elseif ( $step === 2 ) : ?>
    <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
        <h2>Step 2: Map Columns for "<?php echo esc_html( $source_data['label'] ); ?>"</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'fpis_save_source' ); ?>
            <input type="hidden" name="label" value="<?php echo esc_attr( $source_data['label'] ); ?>">
            <input type="hidden" name="sheet_id" value="<?php echo esc_attr( $source_data['sheet_id'] ); ?>">
            <input type="hidden" name="gid" value="<?php echo esc_attr( $source_data['gid'] ); ?>">
            <input type="hidden" name="header_row" value="<?php echo esc_attr( $source_data['header_row'] ); ?>">
            
            <table class="form-table">
                <?php 
                $fields = [
                    'page_name'    => 'Page Name',
                    'link_fanpage' => 'Link Fanpage (Unique Key)',
                    'follow'       => 'Follower Count',
                    'thong_tin'    => 'Image URL (Thong Tin)',
                    'gioi_tinh'    => 'Gender Raw String',
                    'ghi_chu'      => 'Notes (Ghi Chu)',
                    'tinh_trang'   => 'Status (Tinh Trang)'
                ];
                foreach ( $fields as $key => $label ) : ?>
                <tr>
                    <th><?php echo $label; ?></th>
                    <td>
                        <select name="map_<?php echo $key; ?>">
                            <option value="">-- Skip --</option>
                            <?php foreach ( $headers as $h ) : ?>
                                <option value="<?php echo esc_attr( $h ); ?>"><?php echo esc_html( $h ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ( $key === 'follow' ) : ?>
                            <select name="unit_follow">
                                <option value="raw">Raw Number (dots as thousands)</option>
                                <option value="thousands">In Thousands (K)</option>
                            </select>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php submit_button( 'Save Source', 'primary', 'fpis_save_source' ); ?>
            <a href="?page=fpis-sources" class="button">Cancel</a>
        </form>
    </div>
    <?php endif; ?>

    <hr>

    <h2>Active Sources</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Label</th>
                <th>Sheet ID / GID</th>
                <th>Header Row</th>
                <th>Mapping Summary</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sources = get_option( 'fpis_sources', [] );
            if ( empty( $sources ) ) : ?>
                <tr><td colspan="5">No sources found.</td></tr>
            <?php else : 
                foreach ( $sources as $id => $source ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $source['label'] ); ?></strong></td>
                    <td><small><?php echo esc_html( $source['sheet_id'] ); ?> / <?php echo esc_html( $source['gid'] ); ?></small></td>
                    <td><?php echo esc_html( $source['header_row'] ); ?></td>
                    <td>
                        <small>
                            Link: <?php echo esc_html( $source['column_map']['link_fanpage']['header'] ); ?><br>
                            Follow: <?php echo esc_html( $source['column_map']['follow']['header'] ); ?>
                        </small>
                    </td>
                    <td>
                        <a href="?page=fpis-sources&action=sync&id=<?php echo $source['id']; ?>" class="button button-small">Sync Now</a>
                        <a href="?page=fpis-sources&action=delete&id=<?php echo $source['id']; ?>" class="button button-small button-link-delete" onclick="return confirm('Delete this source?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
