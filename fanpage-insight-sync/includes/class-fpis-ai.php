<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FPIS_AI {

	public static function resolve_image_url( string $page_url ): ?string {
		$response = wp_remote_get( $page_url, [
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			'timeout'    => 15,
		]);
		if ( is_wp_error( $response ) ) return null;
		
		$html = wp_remote_retrieve_body( $response );
		if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m ) || 
			 preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m ) ) {
			return strtok( $m[1], '?' );
		}
		return null;
	}

	public static function analyze_image( string $image_url ) {
		$endpoint = get_option( 'fpis_ai_endpoint' );
		$api_key = get_option( 'fpis_ai_api_key' );
		$model = get_option( 'fpis_ai_model', 'claude-sonnet-4-20250514' );
		$prompt_template = get_option( 'fpis_ai_prompt' );

		if ( empty( $endpoint ) || empty( $api_key ) ) {
			return new WP_Error( 'missing_config', 'AI endpoint or API key not configured' );
		}

		$prompt = str_replace( '{image_url}', $image_url, $prompt_template );

		$body = [
			'model'     => $model,
			'kind'      => 'text',
			'image_url' => $image_url,
			'messages'  => [
				[ 'role' => 'user', 'content' => $prompt ]
			]
		];

		$response = wp_remote_post( $endpoint, [
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode( $body ),
			'timeout' => (int) get_option( 'fpis_ai_timeout', 30 ),
		]);

		if ( is_wp_error( $response ) ) return $response;

		$res_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $res_body, true );

		// Handle response formats (generic support)
		$content = '';
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = $data['choices'][0]['message']['content'];
		} elseif ( isset( $data['content'][0]['text'] ) ) {
			$content = $data['content'][0]['text'];
		}

		if ( empty( $content ) ) {
			return new WP_Error( 'empty_response', 'AI returned empty content' );
		}

		// Try to extract JSON
		if ( preg_match( '/\{.*\}/s', $content, $matches ) ) {
			$json_str = $matches[0];
			$result = json_decode( $json_str, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $result;
			}
		}

		return new WP_Error( 'invalid_json', 'AI response did not contain valid JSON' );
	}
}
