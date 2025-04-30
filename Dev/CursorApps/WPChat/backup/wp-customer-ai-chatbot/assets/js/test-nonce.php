<?php
// Simple test script to validate nonce functionality

// Define the key functions that might be missing in this environment
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        // Create a simplified nonce for testing
        $user = 1; // Assume user ID 1
        $token = session_id();
        $tick = ceil(time() / (86400)); // 24-hour lifespan
        return substr(hash('sha256', $tick . '|' . $action . '|' . $user . '|' . $token), -12, 10);
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        $nonce = '';
        
        // Extract nonce from $_REQUEST
        if ($query_arg && isset($_REQUEST[$query_arg])) {
            $nonce = $_REQUEST[$query_arg];
        } elseif (isset($_REQUEST['_wpnonce'])) {
            $nonce = $_REQUEST['_wpnonce'];
        }
        
        // Verify the nonce using the same logic as wp_create_nonce
        $user = 1; // Assume user ID 1
        $token = session_id();
        $tick = ceil(time() / (86400)); // 24-hour lifespan
        $expected = substr(hash('sha256', $tick . '|' . $action . '|' . $user . '|' . $token), -12, 10);
        
        $valid = ($nonce === $expected);
        
        if (!$valid && $die) {
            die('-1');
        }
        
        return $valid;
    }
}

// Start a session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create a nonce for testing
$test_nonce = wp_create_nonce('wcac_chatbot_nonce');

// Output some diagnostic info
header('Content-Type: application/json');
echo json_encode([
    'nonce' => $test_nonce,
    'session_id' => session_id(),
    'time' => time(),
    'tick' => ceil(time() / (86400))
]); 