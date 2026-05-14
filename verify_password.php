<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paste_id = $_POST['paste_id'] ?? 0;
    $password = $_POST['password'] ?? '';
    
    // Get paste from database
    $stmt = $pdo->prepare("SELECT password FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    $paste = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($paste && password_verify($password, $paste['password'])) {
        $_SESSION['paste_access'][$paste_id] = true;
        $response['success'] = true;
    } else {
        $response['message'] = 'Incorrect password';
    }
}

header('Content-Type: application/json');
echo json_encode($response);