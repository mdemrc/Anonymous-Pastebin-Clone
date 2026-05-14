<?php
// Start session before any output
session_start();

// Large paste support (100k+ lines) - Runtime PHP settings
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');
set_time_limit(300);

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Configs - preserved all your security settings
$banned_uid = [0]; // IDs of banned users
$banned_titlewords = ['Eminem']; 
$banned_words = ['https://pasteflash.com/', 'new method released today', 'https://gofile.io/d/jD2vxU'];
$banned_ip = ['192.168.100.1', '192.168.100.2'];
$is_anon_allowed = false;  // If false, anonymous users cannot paste

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check banned IP before any processing - preserved
$ip = $_SERVER['REMOTE_ADDR'];
if (in_array($ip, $banned_ip)) {
    setFlashMessage('error', 'Your IP is banned from using this pastebin.');
    header('Location: index.php');
    exit;
}

// If the user is logged in, check if the user ID is banned - preserved
if (isLoggedIn() && in_array($_SESSION['user_id'], $banned_uid)) {
    setFlashMessage('error', 'You have been banned from using this pastebin.');
    header('Location: index.php');
    exit;
}

// Block anonymous users if not allowed - preserved
if (!$is_anon_allowed && (!isLoggedIn() || empty($_SESSION['user_id']))) {
    echo '<script>
             alert("Login or Register to use example.com.");
             window.location.href = "index.php";
          </script>';
    exit;
}

// CSRF Token Validation - preserved
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Security error. Please try again.');
        header('Location: index.php');
        exit;
    }

    // Anti-spam check (Rate Limit) - preserved
    if (!checkRateLimit('create_paste_' . $ip, 5, 60)) {
        setFlashMessage('error', 'Too many requests. Please wait and try again.');
        header('Location: index.php');
        exit;
    }

    // Sanitize input data - preserved
    $title = isset($_POST['title']) && !empty($_POST['title']) ? sanitizeInput($_POST['title']) : 'Untitled Paste';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $syntax = isset($_POST['syntax']) ? sanitizeInput($_POST['syntax']) : 'text';
    $visibility = isset($_POST['visibility']) ? sanitizeInput($_POST['visibility']) : 'public';
    $expiration = isset($_POST['expiration']) ? $_POST['expiration'] : 'never';

    // Handle password for private pastes - NEW
    $password = null;
    if ($visibility === 'private') {
        if (empty($_POST['password'])) {
            setFlashMessage('error', 'Password is required for private pastes.');
            header('Location: index.php');
            exit;
        }
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    // Check banned words in the title - preserved
    foreach ($banned_titlewords as $banned) {
        if (stripos($title, $banned) !== false) {
            setFlashMessage('error', 'Your title contains banned words by admin.');
            header('Location: index.php');
            exit;
        }
    }

    // Check banned words in the paste content - preserved
    foreach ($banned_words as $banned) {
        if (stripos($content, $banned) !== false) {
            setFlashMessage('error', 'Your paste contains banned words by admin.');
            header('Location: index.php');
            exit;
        }
    }

    // Check if content is empty - preserved
    if (empty($content)) {
        setFlashMessage('error', 'Paste content cannot be empty.');
        header('Location: index.php');
        exit;
    }

    // Check the daily paste limit - preserved
    $currentDate = date('Y-m-d');
    $query = "SELECT COUNT(*) AS paste_count FROM pastes WHERE user_id = ? AND DATE(created_at) = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $currentDate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $pasteCountToday = $row['paste_count'];
    $dailyLimit = 100;

    if ($pasteCountToday >= $dailyLimit) {
        setFlashMessage('error', 'You have reached the daily limit of pastes. Please try again tomorrow.');
        header('Location: index.php');
        exit;
    }

    // Convert expiration time - preserved
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

    // Determine user ID - preserved
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

    try {
        // Insert paste with password field - MODIFIED
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
            $user_id
        ]);

        if ($success) {
            $paste_id = $pdo->lastInsertId();
            header('Location: view.php?id=' . $paste_id);
            exit;
        } else {
            throw new Exception("Database execute failed");
        }
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        setFlashMessage('error', 'Error creating paste. Please try again.');
        header('Location: index.php');
        exit;
    }
} else {
    // Redirect if not a POST request - preserved
    header('Location: index.php');
    exit;
}