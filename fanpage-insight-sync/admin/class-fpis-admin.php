<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPIS_Admin {

	public function add_plugin_admin_menu() {
		add_menu_page(
			'Fanpage Insights',
			'Fanpage Insights',
			'manage_options',
			'fpis-insights',
			[ $this, 'display_insights_page' ],
			'dashicons-chart-area',
			30
		);

		add_submenu_page(
			'fpis-insights',
			'All Insights',
			'All Insights',
			'manage_options',
			'fpis-insights',
			[ $this, 'display_insights_page' ]
		);

		add_submenu_page(
			'fpis-insights',
			'Sheet Sources',
			'Sheet Sources',
			'manage_options',
			'fpis-sources',
			[ $this, 'display_sources_page' ]
		);

		add_submenu_page(
			'fpis-insights',
			'Settings',
			'Settings',
			'manage_options',
			'fpis-settings',
			[ $this, 'display_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting( 'fpis_settings_group', 'fpis_ai_endpoint' );
		register_setting( 'fpis_settings_group', 'fpis_ai_model' );
		register_setting( 'fpis_settings_group', 'fpis_ai_api_key' );
		register_setting( 'fpis_settings_group', 'fpis_ai_prompt' );
		register_setting( 'fpis_settings_group', 'fpis_ai_max_tokens' );
		register_setting( 'fpis_settings_group', 'fpis_ai_timeout' );
		register_setting( 'fpis_settings_group', 'fpis_contact_zalo' );
		register_setting( 'fpis_settings_group', 'fpis_contact_label' );
		register_setting( 'fpis_settings_group', 'fpis_contact_show_public' );
	}

	public function display_insights_page() {
		require_once FPIS_PATH . 'admin/views/insights-page.php';
	}

	public function display_sources_page() {
		$this->handle_source_actions();
		require_once FPIS_PATH . 'admin/views/sources-page.php';
	}

	private function handle_source_actions() {
		if ( ! isset( $_POST['fpis_add_source_submit'] ) && ! isset( $_GET['action'] ) ) return;

		if ( isset( $_POST['fpis_add_source_submit'] ) ) {
			check_admin_referer( 'fpis_add_source' );
			$url = sanitize_text_field( $_POST['sheet_url'] );
			$parsed = FPIS_Sync::parse_sheet_url( $url );
			
			if ( $parsed['sheet_id'] ) {
				$sources = get_option( 'fpis_sources', [] );
				$new_source = [
					'id'         => uniqid(),
					'label'      => sanitize_text_field( $_POST['label'] ),
					'sheet_id'   => $parsed['sheet_id'],
					'gid'        => $parsed['gid'],
					'header_row' => (int) $_POST['header_row'],
					'enabled'    => true,
					'column_map' => [
						'page_name'    => [ 'header' => 'TÊN FANPAGE',   'unit' => 'raw' ],
						'link_fanpage' => [ 'header' => 'LINK FANPAGE',  'unit' => 'raw' ],
						'follow'       => [ 'header' => 'FOLLOW',        'unit' => 'raw' ],
						'thong_tin'    => [ 'header' => 'THÔNG TIN',     'unit' => 'raw' ],
						'gioi_tinh'    => [ 'header' => 'GIỚI TÍNH (%)', 'unit' => 'raw' ],
						'ghi_chu'      => [ 'header' => 'GHI CHÚ',       'unit' => 'raw' ],
						'tinh_trang'   => [ 'header' => 'TÌNH TRẠNG',    'unit' => 'raw' ],
					]
				];
				$sources[] = $new_source;
				update_option( 'fpis_sources', $sources );
				echo '<div class="updated"><p>Source added successfully.</p></div>';
			}
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'sync' ) {
			$id = $_GET['id'];
			$sources = get_option( 'fpis_sources', [] );
			foreach ( $sources as $source ) {
				if ( $source['id'] == $id ) {
					FPIS_Sync::sync_source( $source );
					echo '<div class="updated"><p>Sync completed.</p></div>';
					break;
				}
			}
		}
	}

	public function display_settings_page() {
		require_once FPIS_PATH . 'admin/views/settings-page.php';
	}
}
