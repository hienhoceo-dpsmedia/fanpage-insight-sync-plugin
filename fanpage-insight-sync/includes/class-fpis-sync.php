<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPIS_Sync {

	public static function run_sync() {
		$sources = get_option( 'fpis_sources', [] );
		if ( empty( $sources ) ) return;

		foreach ( $sources as $source ) {
			if ( ! $source['enabled'] ) continue;
			self::sync_source( $source );
		}
	}

	public static function sync_source( $source ) {
		$csv_url = sprintf(
			'https://docs.google.com/spreadsheets/d/%s/export?format=csv&gid=%s',
			$source['sheet_id'],
			$source['gid']
		);

		$response = wp_remote_get( $csv_url );
		if ( is_wp_error( $response ) ) return;

		$body = wp_remote_retrieve_body( $response );
		$lines = explode( "\n", $body );
		
		$header_row_idx = $source['header_row'] - 1;
		if ( ! isset( $lines[ $header_row_idx ] ) ) return;

		$headers = str_getcsv( $lines[ $header_row_idx ] );
		$column_map = $source['column_map'];

		// Validate headers
		foreach ( $column_map as $key => $map ) {
			if ( ! in_array( $map['header'], $headers ) ) {
				// Log mapping error
				return;
			}
		}

		$data_start_row = $source['header_row'];
		for ( $i = $data_start_row; $i < count( $lines ); $i++ ) {
			if ( empty( trim( $lines[ $i ] ) ) ) continue;
			
			$row_data = str_getcsv( $lines[ $i ] );
			if ( count( $row_data ) < count( $headers ) ) continue;

			$mapped_row = [];
			foreach ( $column_map as $internal_key => $map ) {
				$col_idx = array_search( $map['header'], $headers );
				$raw_val = $row_data[ $col_idx ] ?? '';
				
				if ( $internal_key === 'follow' ) {
					$mapped_row['follow_raw'] = $raw_val;
					$mapped_row['follow_count'] = self::normalize_follow( $raw_val, $map['unit'] );
				} else {
					$mapped_row[ $internal_key ] = $raw_val;
				}
			}

			if ( empty( $mapped_row['link_fanpage'] ) ) continue;

			// Prepare for DB
			$db_data = [
				'link_fanpage' => trim( strtolower( $mapped_row['link_fanpage'] ) ),
				'page_name'    => $mapped_row['page_name'] ?? '',
				'follow_count' => $mapped_row['follow_count'] ?? 0,
				'follow_raw'   => $mapped_row['follow_raw'] ?? '',
				'ghi_chu'      => $mapped_row['ghi_chu'] ?? '',
				'tinh_trang'   => $mapped_row['tinh_trang'] ?? '',
				'thong_tin_url' => $mapped_row['thong_tin'] ?? '',
			];

			// Pricing
			$price = self::calculate_price( $db_data['follow_count'], $source['pricing'] ?? [] );

			$db_data['calculated_price'] = $price;
			$db_data['price_tier_json'] = json_encode( $source['pricing']['tiers'] ?? [] );

			$id = FPIS_DB::upsert_insight( $db_data );
			
			// If new or needs analysis, enqueue
			if ( ! empty( $db_data['thong_tin_url'] ) ) {
				FPIS_Queue::maybe_enqueue( $id );
			}
		}
	}

	public static function normalize_follow( string $raw, string $unit = 'raw' ): int {
		$raw = trim( $raw );

		if ( $unit === 'thousands' ) {
			$float = (float) str_replace( ',', '.', $raw );
			return (int) round( $float * 1000 );
		}

		if ( preg_match( '/^[\d.]+$/', $raw ) ) {
			return (int) str_replace( '.', '', $raw );
		}

		return (int) preg_replace( '/[^\d]/', '', $raw );
	}

	public static function calculate_price( int $follow_count, array $pricing ): ?int {
		if ( empty( $pricing['tiers'] ) ) return null;
		$multiplier = isset( $pricing['multiplier'] ) ? (float) $pricing['multiplier'] : 1.0;

		foreach ( $pricing['tiers'] as $tier ) {
			$in_range = $follow_count >= $tier['min']
				&& ( $tier['max'] === null || $follow_count <= $tier['max'] );
			if ( $in_range ) {
				$raw_price = (int) ( $tier['base'] + floor( $follow_count / 1000 ) * $tier['per_1k'] );
				return (int) ( $raw_price * $multiplier );
			}
		}
		return null;
	}

	public static function parse_sheet_url( string $url ): array {
		preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $sid );
		preg_match( '#[#&?]gid=(\d+)#', $url, $gid );
		return [
			'sheet_id' => $sid[1] ?? null,
			'gid'      => $gid[1] ?? '0',
		];
	}
}
