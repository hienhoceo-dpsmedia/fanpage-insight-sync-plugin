<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
	<h1>FPIS Global Settings</h1>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'fpis_settings_group' );
		do_settings_sections( 'fpis_settings_group' );
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">AI API Endpoint</th>
				<td><input type="text" name="fpis_ai_endpoint" value="<?php echo esc_attr( get_option( 'fpis_ai_endpoint' ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">AI API Key</th>
				<td><input type="password" name="fpis_ai_api_key" value="<?php echo esc_attr( get_option( 'fpis_ai_api_key' ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">AI Model</th>
				<td><input type="text" name="fpis_ai_model" value="<?php echo esc_attr( get_option( 'fpis_ai_model', 'claude-sonnet-4-20250514' ) ); ?>" class="regular-text" /></td>
			</tr>
			<tr valign="top">
				<th scope="row">AI Prompt Template</th>
				<td><textarea name="fpis_ai_prompt" rows="10" cols="50" class="large-text"><?php echo esc_textarea( get_option( 'fpis_ai_prompt' ) ); ?></textarea>
				<p class="description">Use {image_url} as placeholder.</p></td>
			</tr>
			<tr valign="top">
				<th scope="row">Zalo Contact URL</th>
				<td><input type="text" name="fpis_contact_zalo" value="<?php echo esc_attr( get_option( 'fpis_contact_zalo' ) ); ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
