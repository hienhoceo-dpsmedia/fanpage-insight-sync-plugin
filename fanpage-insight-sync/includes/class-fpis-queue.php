<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPIS_Queue {

	public static function maybe_enqueue( $id ) {
		$insight = FPIS_DB::get_insight( $id );
		if ( ! $insight ) return;

		if ( $insight->ai_status === 'pending' || $insight->ai_status === 'failed' ) {
			if ( $insight->ai_attempts < 3 ) {
				$queue = get_option( 'fpis_queue', [] );
				if ( ! in_array( $id, $queue ) ) {
					$queue[] = $id;
					update_option( 'fpis_queue', $queue );
				}
			}
		}
	}

	public static function process_queue() {
		$queue = get_option( 'fpis_queue', [] );
		if ( empty( $queue ) ) return;

		$batch_size = 5;
		$to_process = array_slice( $queue, 0, $batch_size );
		$remaining = array_slice( $queue, $batch_size );

		foreach ( $to_process as $id ) {
			self::process_item( $id );
		}

		update_option( 'fpis_queue', $remaining );
	}

	public static function process_item( $id ) {
		$insight = FPIS_DB::get_insight( $id );
		if ( ! $insight || empty( $insight->thong_tin_url ) ) return;

		global $wpdb;
		$table_name = FPIS_DB::get_table_name();

		// Increment attempts
		$wpdb->update( $table_name, [ 'ai_attempts' => $insight->ai_attempts + 1 ], [ 'id' => $id ] );

		// Resolve Image
		$image_url = FPIS_AI::resolve_image_url( $insight->thong_tin_url );
		if ( ! $image_url ) {
			$wpdb->update( $table_name, [ 'ai_error' => 'Could not resolve image URL', 'ai_status' => 'failed' ], [ 'id' => $id ] );
			return;
		}

		$wpdb->update( $table_name, [ 'resolved_image_url' => $image_url ], [ 'id' => $id ] );

		// Call AI
		$result = FPIS_AI::analyze_image( $image_url );
		if ( is_wp_error( $result ) ) {
			$wpdb->update( $table_name, [ 'ai_error' => $result->get_error_message(), 'ai_status' => 'failed' ], [ 'id' => $id ] );
			return;
		}

		// Update with AI results
		$update_data = [
			'ai_status'      => 'completed',
			'analyzed_at'    => current_time( 'mysql' ),
			'ai_raw_response' => json_encode( $result ),
			'dominant'       => $result['gender']['dominant'] ?? null,
			'male_pct'       => $result['gender']['male_pct'] ?? null,
			'female_pct'     => $result['gender']['female_pct'] ?? null,
			'age_top_group'  => $result['age']['top_group'] ?? null,
			'age_dist_json'  => json_encode( $result['age']['distribution'] ?? [] ),
			'generation'     => implode( ', ', $result['age']['generation'] ?? [] ),
			'top_cities_json' => json_encode( $result['location']['top_cities'] ?? [] ),
			'top_countries_json' => json_encode( $result['location']['top_countries'] ?? [] ),
			'region_focus'   => $result['location']['region_focus'] ?? null,
			'has_international' => (int) ($result['location']['has_international'] ?? 0),
			'suitable_json'   => json_encode( $result['fit']['suitable_industries'] ?? [] ),
			'unsuitable_json' => json_encode( $result['fit']['unsuitable_industries'] ?? [] ),
			'tags_json'       => json_encode( $result['fit']['tags'] ?? [] ),
			'description'     => $result['description'] ?? '',
		];

		$wpdb->update( $table_name, $update_data, [ 'id' => $id ] );
	}
}
