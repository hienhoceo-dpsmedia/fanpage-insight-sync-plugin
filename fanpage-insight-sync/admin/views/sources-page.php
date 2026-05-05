<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1>Sheet Sources</h1>
    <p>Manage Google Sheet sources for fanpage insights.</p>
    
    <div class="card" style="max-width: 100%; margin-top: 20px; padding: 20px;">
        <h2>Add New Source</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'fpis_add_source' ); ?>
            <table class="form-table">
                <tr>
                    <th>Label</th>
                    <td><input type="text" name="label" class="regular-text" placeholder="e.g. Huu Dai Fanpage List" required></td>
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
            <?php submit_button( 'Add Source', 'primary', 'fpis_add_source_submit' ); ?>
        </form>
    </div>

    <hr>

    <h2>Active Sources</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Label</th>
                <th>Sheet ID</th>
                <th>GID</th>
                <th>Header Row</th>
                <th>Enabled</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sources = get_option( 'fpis_sources', [] );
            if ( empty( $sources ) ) : ?>
                <tr><td colspan="6">No sources found.</td></tr>
            <?php else : 
                foreach ( $sources as $id => $source ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $source['label'] ); ?></strong></td>
                    <td><?php echo esc_html( $source['sheet_id'] ); ?></td>
                    <td><?php echo esc_html( $source['gid'] ); ?></td>
                    <td><?php echo esc_html( $source['header_row'] ); ?></td>
                    <td><?php echo $source['enabled'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="?page=fpis-sources&action=sync&id=<?php echo $id; ?>" class="button button-small">Sync Now</a>
                        <a href="?page=fpis-sources&action=delete&id=<?php echo $id; ?>" class="button button-small button-link-delete">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
