<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Проверяем, что запрос пришел методом POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

// Проверяем авторизацию
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Необходима авторизация']);
    exit;
}

// Получаем данные из POST запроса
$pasteId = filter_input(INPUT_POST, 'paste_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);

// Проверяем корректность данных
if (!$pasteId || !in_array($rating, [-1, 1])) {
    http_response_code(400);
    echo json_encode(['error' => 'Некорректные данные']);
    exit;
}

// Получаем ID текущего пользователя
$userId = $_SESSION['user_id'];

try {
    // Начинаем транзакцию
    $pdo->beginTransaction();

    // Проверяем существование паста
    $stmt = $pdo->prepare("SELECT id FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    if (!$stmt->fetch()) {
        throw new Exception('Паст не найден');
    }

    // Проверяем, голосовал ли уже пользователь
    $stmt = $pdo->prepare("SELECT rating FROM paste_ratings WHERE paste_id = ? AND user_id = ?");
    $stmt->execute([$pasteId, $userId]);
    $existingRating = $stmt->fetch();

    if ($existingRating) {
        // Если рейтинг такой же - удаляем голос
        if ($existingRating['rating'] == $rating) {
            $stmt = $pdo->prepare("DELETE FROM paste_ratings WHERE paste_id = ? AND user_id = ?");
            $stmt->execute([$pasteId, $userId]);
            $ratingChange = -$rating;
        } else {
            // Если рейтинг противоположный - обновляем
            $stmt = $pdo->prepare("UPDATE paste_ratings SET rating = ? WHERE paste_id = ? AND user_id = ?");
            $stmt->execute([$rating, $pasteId, $userId]);
            $ratingChange = $rating * 2; // Умножаем на 2, так как меняем с -1 на 1 или наоборот
        }
    } else {
        // Добавляем новый голос
        $stmt = $pdo->prepare("INSERT INTO paste_ratings (paste_id, user_id, rating) VALUES (?, ?, ?)");
        $stmt->execute([$pasteId, $userId, $rating]);
        $ratingChange = $rating;
    }

    // Обновляем общий рейтинг паста
    $stmt = $pdo->prepare("UPDATE pastes SET rating = rating + ? WHERE id = ?");
    $stmt->execute([$ratingChange, $pasteId]);

    // Получаем обновленный рейтинг
    $stmt = $pdo->prepare("SELECT rating FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    $newRating = $stmt->fetch()['rating'];

    // Фиксируем транзакцию
    $pdo->commit();

    // Возвращаем успешный ответ
    echo json_encode([
        'success' => true,
        'rating' => $newRating
    ]);

} catch (Exception $e) {
    // В случае ошибки откатываем транзакцию
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
