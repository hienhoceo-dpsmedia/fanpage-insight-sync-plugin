<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPIS_DB {

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'fpis_insights';
	}

	public static function upsert_insight( $data ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$link_fanpage = $data['link_fanpage'];
		
		// Check if exists
		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE link_fanpage = %s",
			$link_fanpage
		) );

		if ( $exists ) {
			// Update
			// Only update certain fields if not manual edit
			$is_manual = $wpdb->get_var( $wpdb->prepare(
				"SELECT is_manual_edit FROM $table_name WHERE id = %d",
				$exists
			) );

			$update_data = [
				'page_name'    => $data['page_name'],
				'follow_count' => $data['follow_count'],
				'follow_raw'   => $data['follow_raw'],
				'ghi_chu'      => $data['ghi_chu'],
				'tinh_trang'   => $data['tinh_trang'],
				'thong_tin_url' => $data['thong_tin_url'],
				'synced_at'    => current_time( 'mysql' ),
			];

			if ( ! $is_manual ) {
				// Add AI fields if needed or handled by queue
			}

			$wpdb->update( $table_name, $update_data, [ 'id' => $exists ] );
			return $exists;
		} else {
			// Insert
			$insert_data = array_merge( $data, [
				'synced_at'  => current_time( 'mysql' ),
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			] );

			$wpdb->insert( $table_name, $insert_data );
			return $wpdb->insert_id;
		}
	}

	public static function get_insights( $args = [] ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$query = "SELECT * FROM $table_name WHERE is_hidden = 0";
		$where = [];
		$params = [];

		if ( ! empty( $args['search'] ) ) {
			$where[] = "(page_name LIKE %s OR link_fanpage LIKE %s)";
			$s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $s;
			$params[] = $s;
		}

		if ( ! empty( $args['dominant'] ) && $args['dominant'] !== 'all' ) {
			$where[] = "dominant = %s";
			$params[] = $args['dominant'];
		}

		if ( ! empty( $args['region'] ) && $args['region'] !== 'all' ) {
			$where[] = "region_focus = %s";
			$params[] = $args['region'];
		}

		if ( ! empty( $where ) ) {
			$query .= " AND " . implode( " AND ", $where );
		}

		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ($query) as t", $params ) );

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$page = isset( $args['page'] ) ? (int) $args['page'] : 1;
		$offset = ( $page - 1 ) * $per_page;

		$query .= " ORDER BY follow_count DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		return [
			'total' => (int) $total,
			'pages' => ceil( $total / $per_page ),
			'data'  => $results,
		];
	}

	public static function get_insight( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
	}
}
