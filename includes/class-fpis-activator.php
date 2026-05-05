<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPIS_Activator {

	public static function activate() {
		self::create_table();
		self::create_default_page();
		self::schedule_cron();
	}

	private static function create_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'fpis_insights';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			link_fanpage   VARCHAR(512)    NOT NULL,
			page_name      VARCHAR(255)    DEFAULT NULL,
			follow_count   BIGINT          DEFAULT NULL,
			follow_raw     VARCHAR(32)     DEFAULT NULL,
			calculated_price BIGINT        DEFAULT NULL,
			price_tier_json  TEXT          DEFAULT NULL,
			platform       VARCHAR(32)     DEFAULT 'facebook',
			raw_gender     VARCHAR(64)     DEFAULT NULL,
			male_pct       FLOAT           DEFAULT NULL,
			female_pct     FLOAT           DEFAULT NULL,
			dominant       VARCHAR(16)     DEFAULT NULL,
			age_top_group  VARCHAR(16)     DEFAULT NULL,
			age_dist_json  LONGTEXT        DEFAULT NULL,
			generation     VARCHAR(128)    DEFAULT NULL,
			top_cities_json    LONGTEXT    DEFAULT NULL,
			top_countries_json LONGTEXT    DEFAULT NULL,
			region_focus   VARCHAR(32)     DEFAULT NULL,
			has_international  TINYINT(1)  DEFAULT 0,
			suitable_json      LONGTEXT    DEFAULT NULL,
			unsuitable_json    LONGTEXT    DEFAULT NULL,
			tags_json          LONGTEXT    DEFAULT NULL,
			description        LONGTEXT    DEFAULT NULL,
			ghi_chu        TEXT            DEFAULT NULL,
			tinh_trang     VARCHAR(64)     DEFAULT NULL,
			thong_tin_url  VARCHAR(512)    DEFAULT NULL,
			resolved_image_url VARCHAR(512) DEFAULT NULL,
			ai_raw_response    LONGTEXT    DEFAULT NULL,
			ai_error           TEXT        DEFAULT NULL,
			ai_attempts    TINYINT         DEFAULT 0,
			ai_status      VARCHAR(16)     DEFAULT 'pending',
			is_hidden      TINYINT(1)      DEFAULT 0,
			is_manual_edit TINYINT(1)      DEFAULT 0,
			synced_at      DATETIME        DEFAULT NULL,
			analyzed_at    DATETIME        DEFAULT NULL,
			created_at     DATETIME        DEFAULT CURRENT_TIMESTAMP,
			updated_at     DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_link_fanpage (link_fanpage(255)),
			KEY idx_dominant (dominant),
			KEY idx_region (region_focus),
			KEY idx_follow (follow_count)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static function create_default_page() {
		if ( get_option( 'fpis_frontend_page_id' ) ) {
			return;
		}

		$page_id = wp_insert_post( [
			'post_title'   => 'Danh sách Fanpage',
			'post_content' => '[fanpage_insights]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_name'    => 'fanpage-insights',
		] );

		if ( $page_id ) {
			update_option( 'fpis_frontend_page_id', $page_id );
		}
	}

	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'fpis_daily_sync' ) ) {
			wp_schedule_event( time(), 'daily', 'fpis_daily_sync' );
		}
		if ( ! wp_next_scheduled( 'fpis_process_queue' ) ) {
			wp_schedule_event( time(), 'every_five_minutes', 'fpis_process_queue' );
		}
	}
}
