<?php
/**
 * Test AI Analysis with user-provided endpoint.
 */

if ( ! defined( 'ABSPATH' ) ) {
    // Mocking WordPress functions for a standalone test if needed, 
    // but better to run in WP environment.
    echo "Please run this within a WordPress environment (e.g., via wp-cli or a temp plugin page).";
    exit;
}

require_once FPIS_PATH . 'includes/class-fpis-ai.php';

// Configure settings for the test
update_option( 'fpis_ai_endpoint', 'https://desktop-0153vfv.tail738706.ts.net/v1/chat/completions' );
update_option( 'fpis_ai_api_key', 'anything' );
update_option( 'fpis_ai_model', 'gpt' );

$test_image_url = 'https://i.imgur.com/MQcf41j.png';
$prompt_template = get_option( 'fpis_ai_prompt' );

echo "Testing AI Analysis...\n";
echo "Endpoint: " . get_option( 'fpis_ai_endpoint' ) . "\n";
echo "Image: " . $test_image_url . "\n";

$result = FPIS_AI::analyze_image( $test_image_url );

if ( is_wp_error( $result ) ) {
    echo "Error: " . $result->get_error_message() . "\n";
} else {
    echo "AI Response:\n";
    print_r( $result );
}
