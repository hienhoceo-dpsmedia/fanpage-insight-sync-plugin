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
		$step = isset( $_POST['fpis_step'] ) ? (int) $_POST['fpis_step'] : 1;
		$headers = [];
		$source_data = [];

		if ( $step === 2 ) {
			check_admin_referer( 'fpis_fetch_headers' );
			$url = sanitize_text_field( $_POST['sheet_url'] );
			$parsed = FPIS_Sync::parse_sheet_url( $url );
			if ( $parsed['sheet_id'] ) {
				$source_data = [
					'label'      => sanitize_text_field( $_POST['label'] ),
					'sheet_id'   => $parsed['sheet_id'],
					'gid'        => $parsed['gid'],
					'header_row' => (int) $_POST['header_row'],
				];
				$headers = FPIS_Sync::get_csv_headers( $source_data['sheet_id'], $source_data['gid'], $source_data['header_row'] );
			}
		}

		if ( isset( $_POST['fpis_save_source'] ) ) {
			check_admin_referer( 'fpis_save_source' );
			$sources = get_option( 'fpis_sources', [] );
			$column_map = [];
			foreach ( [ 'page_name', 'link_fanpage', 'follow', 'thong_tin', 'gioi_tinh', 'ghi_chu', 'tinh_trang' ] as $field ) {
				$column_map[ $field ] = [
					'header' => sanitize_text_field( $_POST['map_' . $field] ),
					'unit'   => sanitize_text_field( $_POST['unit_' . $field] ?? 'raw' )
				];
			}

			$new_source = [
				'id'         => uniqid(),
				'label'      => sanitize_text_field( $_POST['label'] ),
				'sheet_id'   => sanitize_text_field( $_POST['sheet_id'] ),
				'gid'        => sanitize_text_field( $_POST['gid'] ),
				'header_row' => (int) $_POST['header_row'],
				'enabled'    => true,
				'column_map' => $column_map,
				'pricing'    => [ 'tiers' => [] ]
			];
			$sources[] = $new_source;
			update_option( 'fpis_sources', $sources );
			echo '<div class="updated"><p>Source saved successfully.</p></div>';
			$step = 1;
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

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' ) {
			$id = $_GET['id'];
			$sources = get_option( 'fpis_sources', [] );
			$sources = array_filter( $sources, fn($s) => $s['id'] != $id );
			update_option( 'fpis_sources', $sources );
			echo '<div class="updated"><p>Source deleted.</p></div>';
		}

		require_once FPIS_PATH . 'admin/views/sources-page.php';
	}

	public function display_settings_page() {
		require_once FPIS_PATH . 'admin/views/settings-page.php';
	}
}
