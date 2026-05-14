<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check authorization and admin rights
if (!isLoggedIn() || !canPinPastes()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get search term
$search = isset($_POST['search']) ? sanitizeInput($_POST['search']) : '';

if (empty($search)) {
    echo json_encode(['success' => true, 'pastes' => []]);
    exit;
}

try {
    // Search all public pastes that are not pinned
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.created_at, p.views, u.username, u.emoji, u.name_color
        FROM pastes p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.visibility = 'public' 
        AND p.is_pinned = 0 
        AND p.title LIKE ?
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    
    $stmt->execute(["%$search%"]);
    $pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Clean up the data for JSON response
    $cleanPastes = [];
    foreach ($pastes as $paste) {
        $cleanPastes[] = [
            'id' => (int)$paste['id'],
            'title' => htmlspecialchars($paste['title']),
            'username' => $paste['username'] ? htmlspecialchars($paste['username']) : null,
            'views' => (int)($paste['views'] ?? 0),
            'created_at' => $paste['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'pastes' => $cleanPastes,
        'count' => count($cleanPastes)
    ]);
    
} catch (Exception $e) {
    error_log("Search pastes error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>
