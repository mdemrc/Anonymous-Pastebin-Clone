<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Включаем вывод ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Проверяем, что пользователь авторизован
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Необходимо авторизоваться'
    ]);
    exit;
}

// Получаем данные из запроса
$data = json_decode(file_get_contents('php://input'), true);
$paste_id = isset($data['paste_id']) ? (int)$data['paste_id'] : 0;
$type = isset($data['type']) ? $data['type'] : '';

// Проверяем корректность данных
if (!$paste_id || !in_array($type, ['like', 'dislike'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Некорректные данные'
    ]);
    exit;
}

try {
    // Проверяем существование пасты
    $stmt = $pdo->prepare("SELECT id FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Паста не найдена');
    }
    
    // Проверяем, есть ли уже лайк/дизлайк от этого пользователя
    $stmt = $pdo->prepare("
        SELECT id, type 
        FROM paste_likes 
        WHERE paste_id = ? AND user_id = ?
    ");
    $stmt->execute([$paste_id, $_SESSION['user_id']]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['type'] === $type) {
            // Если тот же тип - удаляем (снимаем лайк/дизлайк)
            $stmt = $pdo->prepare("DELETE FROM paste_likes WHERE id = ?");
            $stmt->execute([$existing['id']]);
        } else {
            // Если другой тип - обновляем
            $stmt = $pdo->prepare("UPDATE paste_likes SET type = ? WHERE id = ?");
            $stmt->execute([$type, $existing['id']]);
        }
    } else {
        // Добавляем новый лайк/дизлайк
        $stmt = $pdo->prepare("
            INSERT INTO paste_likes (paste_id, user_id, type) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$paste_id, $_SESSION['user_id'], $type]);
    }
    
    // Получаем обновленные данные
    $likes = getLikeCount($paste_id, 'like');
    $dislikes = getLikeCount($paste_id, 'dislike');
    
    // Получаем текущий выбор пользователя
    $stmt = $pdo->prepare("
        SELECT type 
        FROM paste_likes 
        WHERE paste_id = ? AND user_id = ?
    ");
    $stmt->execute([$paste_id, $_SESSION['user_id']]);
    $user_like = $stmt->fetchColumn();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'dislikes' => $dislikes,
        'user_like' => $user_like
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
