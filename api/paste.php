<?php
/**
 * example.com API - Create Paste Endpoint
 * 
 * This API allows authenticated users to create pastes programmatically.
 * 
 * Endpoint: GET /api/paste.php
 * 
 * Query Parameters:
 *   - api_key: string (required) - Your API key
 *   - title: string (optional, default: "Untitled Paste")
 *   - content: string (required) - URL encoded content
 *   - syntax: string (optional, default: "text")
 *   - visibility: string (optional, "public"|"private"|"unlisted", default: "public")
 *   - password: string (required if visibility is "private")
 *   - expiration: string (optional, "never"|"30min"|"1hour"|"12hours"|"1day"|"3days"|"1month")
 * 
 * Alternative: Use 'data' parameter with base64 encoded JSON
 *   - api_key: string (required)
 *   - data: base64 encoded JSON with all paste data
 * 
 * Response (JSON):
 *   Success: { "success": true, "paste_id": 123, "url": "https://example.com/view.php?id=123" }
 *   Error: { "success": false, "error": "Error message" }
 * 
 * Example:
 *   GET /api/paste.php?api_key=YOUR_KEY&title=My+Paste&content=Hello+World&syntax=text&visibility=public
 */

// Set JSON content type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get API key from query string or header
$apiKeyFromQuery = isset($_GET['api_key']) ? $_GET['api_key'] : null;

// Also check header as fallback
if (!$apiKeyFromQuery) {
    $headers = getallheaders();
    if (isset($headers['X-API-Key'])) {
        $apiKeyFromQuery = $headers['X-API-Key'];
    } elseif (isset($headers['X-Api-Key'])) {
        $apiKeyFromQuery = $headers['X-Api-Key'];
    }
}

// Parse input data
$data = [];

if (isset($_GET['data'])) {
    // Base64 encoded JSON method
    $decoded = base64_decode($_GET['data'], true);
    if ($decoded !== false) {
        $data = json_decode($decoded, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid base64 JSON in data parameter.']);
            exit;
        }
    }
} elseif (isset($_GET['content'])) {
    // Direct query parameters method
    $data = [
        'title' => isset($_GET['title']) ? urldecode($_GET['title']) : 'Untitled Paste',
        'content' => urldecode($_GET['content']),
        'syntax' => isset($_GET['syntax']) ? $_GET['syntax'] : 'text',
        'visibility' => isset($_GET['visibility']) ? $_GET['visibility'] : 'public',
        'expiration' => isset($_GET['expiration']) ? $_GET['expiration'] : 'never',
        'password' => isset($_GET['password']) ? $_GET['password'] : null
    ];
}

// Check if we have data
if (empty($data) || empty($data['content'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required parameters. Use: ?api_key=KEY&content=YOUR_CONTENT or ?api_key=KEY&data=BASE64_JSON',
        'example' => '/api/paste.php?api_key=YOUR_KEY&title=My+Paste&content=Hello+World&syntax=text'
    ]);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Security configurations
$banned_titlewords = ['Eminem'];
$banned_words = ['https://pasteflash.com/', 'new method released today', 'https://gofile.io/d/jD2vxU'];

// Rate limiting settings
$API_RATE_LIMIT = 5;         // requests per minute
$API_DAILY_LIMIT = 30;       // requests per day

/**
 * Send JSON response and exit
 */
function jsonResponse($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $data));
    exit;
}

/**
 * Validate API key and return user
 */
function validateApiKey($pdo, $apiKey) {
    if (empty($apiKey) || strlen($apiKey) !== 64) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_key = ? AND is_banned = 0 LIMIT 1");
    $stmt->execute([$apiKey]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Check API rate limit
 */
function checkApiRateLimit($pdo, $userId, $rateLimit, $dailyLimit) {
    $stmt = $pdo->prepare("SELECT api_requests_count, api_last_request FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $now = time();
    $lastRequest = $user['api_last_request'] ? strtotime($user['api_last_request']) : 0;
    $requestCount = (int)$user['api_requests_count'];
    
    if ($now - $lastRequest > 60) {
        $requestCount = 0;
    }
    
    if ($requestCount >= $rateLimit) {
        return ['allowed' => false, 'error' => 'Rate limit exceeded. Maximum ' . $rateLimit . ' requests per minute.'];
    }
    
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pastes WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$userId, $today]);
    $dailyCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($dailyCount >= $dailyLimit) {
        return ['allowed' => false, 'error' => 'Daily limit exceeded. Maximum ' . $dailyLimit . ' pastes per day.'];
    }
    
    $stmt = $pdo->prepare("UPDATE users SET api_requests_count = ?, api_last_request = NOW() WHERE id = ?");
    $stmt->execute([$requestCount + 1, $userId]);
    
    return ['allowed' => true];
}

// ============================================
// MAIN API LOGIC
// ============================================

// Validate API key
if (!$apiKeyFromQuery) {
    jsonResponse(false, ['error' => 'Missing API key. Add api_key parameter to URL.'], 401);
}

$user = validateApiKey($pdo, $apiKeyFromQuery);
if (!$user) {
    jsonResponse(false, ['error' => 'Invalid API key or account is banned.'], 401);
}

// Check rate limit
$rateLimitCheck = checkApiRateLimit($pdo, $user['id'], $API_RATE_LIMIT, $API_DAILY_LIMIT);
if (!$rateLimitCheck['allowed']) {
    jsonResponse(false, ['error' => $rateLimitCheck['error']], 429);
}

// Extract and sanitize fields
$title = isset($data['title']) && !empty(trim($data['title'])) 
    ? sanitizeInput(trim($data['title'])) 
    : 'Untitled Paste';

$content = $data['content'];

$syntax = isset($data['syntax']) ? sanitizeInput($data['syntax']) : 'text';
$allowedSyntax = ['text', 'php', 'javascript', 'python', 'java', 'csharp', 'cpp', 'css', 'html', 'sql', 'json', 'xml', 'markdown', 'bash', 'ruby', 'go', 'rust', 'typescript'];
if (!in_array($syntax, $allowedSyntax)) {
    $syntax = 'text';
}

$visibility = isset($data['visibility']) ? strtolower($data['visibility']) : 'public';
if (!in_array($visibility, ['public', 'private', 'unlisted'])) {
    $visibility = 'public';
}

$expiration = isset($data['expiration']) ? $data['expiration'] : 'never';
$allowedExpiration = ['never', '30min', '1hour', '12hours', '1day', '3days', '1month'];
if (!in_array($expiration, $allowedExpiration)) {
    $expiration = 'never';
}

// Handle password for private pastes
$password = null;
if ($visibility === 'private') {
    if (empty($data['password'])) {
        jsonResponse(false, ['error' => 'Password is required for private pastes.'], 400);
    }
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
}

// Check banned words in title
foreach ($banned_titlewords as $banned) {
    if (stripos($title, $banned) !== false) {
        jsonResponse(false, ['error' => 'Title contains banned words.'], 400);
    }
}

// Check banned words in content
foreach ($banned_words as $banned) {
    if (stripos($content, $banned) !== false) {
        jsonResponse(false, ['error' => 'Content contains banned words.'], 400);
    }
}

// Content length validation (GET has URL length limits, so we allow up to 64KB)
if (strlen($content) > 65536) {
    jsonResponse(false, ['error' => 'Content too large for GET request. Maximum 64KB.'], 400);
}

if (strlen($title) > 255) {
    jsonResponse(false, ['error' => 'Title too long. Maximum 255 characters.'], 400);
}

// Calculate expiration time
$expires_at = null;
if ($expiration !== 'never') {
    $seconds = [
        '30min' => 1800,
        '1hour' => 3600,
        '12hours' => 43200,
        '1day' => 86400,
        '3days' => 259200,
        '1month' => 2592000,
    ][$expiration] ?? 0;
    
    if ($seconds > 0) {
        $expires_at = date('Y-m-d H:i:s', time() + $seconds + (5 * 3600));
    }
}

// Create paste
try {
    $stmt = $pdo->prepare("
        INSERT INTO pastes (
            title, 
            content, 
            syntax, 
            visibility, 
            password,
            expires_at, 
            user_id, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW() + INTERVAL 5 HOUR)
    ");
    
    $success = $stmt->execute([
        $title,
        $content,
        $syntax,
        $visibility,
        $password,
        $expires_at,
        $user['id']
    ]);

    if ($success) {
        $pasteId = $pdo->lastInsertId();
        
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'example.com';
        $baseUrl = $scheme . '://' . $host;
        $pasteUrl = $baseUrl . '/view.php?id=' . $pasteId;
        
        jsonResponse(true, [
            'paste_id' => (int)$pasteId,
            'url' => $pasteUrl,
            'title' => $title,
            'syntax' => $syntax,
            'visibility' => $visibility,
            'expiration' => $expiration,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception("Database insert failed");
    }
    
} catch (PDOException $e) {
    error_log("API Error: " . $e->getMessage());
    jsonResponse(false, ['error' => 'Database error. Please try again.'], 500);
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    jsonResponse(false, ['error' => 'Server error. Please try again.'], 500);
}
