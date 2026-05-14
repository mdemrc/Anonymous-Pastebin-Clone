<?php
/**
 * API Test Endpoint - Check request method and headers
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Collect all debug info
$rawInput = file_get_contents('php://input');
$headers = [];

// Get all headers in a cross-platform way
if (function_exists('getallheaders')) {
    $headers = getallheaders();
} else {
    foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) === 'HTTP_') {
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            $headers[$header] = $value;
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'API test endpoint working',
    'debug' => [
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
        'query_string' => $_SERVER['QUERY_STRING'] ?? '',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'raw_input_length' => strlen($rawInput),
        'raw_input_preview' => substr($rawInput, 0, 500),
        'has_api_key' => isset($headers['X-API-Key']) || isset($headers['X-Api-Key']) || isset($headers['x-api-key']),
        'headers' => $headers,
        'post_data' => $_POST,
        'get_data' => $_GET,
        'server' => [
            'http_host' => $_SERVER['HTTP_HOST'] ?? 'not set',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'not set',
            'http_x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'not set',
            'http_cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'not set',
            'http_cf_ray' => $_SERVER['HTTP_CF_RAY'] ?? 'not set'
        ]
    ]
], JSON_PRETTY_PRINT);
