<?php
// Включаем отображение ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Подключаем init.php используя абсолютный путь
$root = dirname(dirname(__FILE__));
require_once $root . '/includes/init.php';

// Получаем данные из POST запроса
$paste_id = isset($_POST['paste_id']) ? (int)$_POST['paste_id'] : 0;
$type = isset($_POST['type']) ? $_POST['type'] : '';

// Логируем входящие данные
error_log("POST data: " . print_r($_POST, true));

// Проверяем корректность типа
if (!in_array($type, ['up', 'down'])) {
    echo json_encode(['success' => false, 'error' => 'Неверный тип']);
    exit;
}

// Проверяем, что пользователь не пытается лайкнуть свою собственную пасту
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT user_id FROM pastes WHERE id = ?");
    $stmt->execute([$paste_id]);
    $paste_owner_id = $stmt->fetchColumn();
    
    if ($paste_owner_id && $paste_owner_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'Вы не можете оценивать собственные посты']);
        exit;
    }
}

// Для анонимных пользователей используем сессию
if (!isLoggedIn()) {
    if (!isset($_SESSION['anonymous_likes'])) {
        $_SESSION['anonymous_likes'] = [];
    }
    
    // Преобразуем тип для хранения
    $like_type = $type === 'up' ? 'like' : 'dislike';
    $key = $paste_id . '_' . $like_type;
    
    // Проверяем противоположный голос
    $opposite_type = $type === 'up' ? 'dislike' : 'like';
    $opposite_key = $paste_id . '_' . $opposite_type;
    
    // Проверяем текущий голос
    $has_current_vote = isset($_SESSION['anonymous_likes'][$key]);
    $has_opposite_vote = isset($_SESSION['anonymous_likes'][$opposite_key]);
    
    // Определяем изменение рейтинга
    $rating_change = 0;
    
    // Если есть противоположный голос - убираем его
    if ($has_opposite_vote) {
        unset($_SESSION['anonymous_likes'][$opposite_key]);
        // Если убираем дизлайк, то +1, если убираем лайк, то -1
        $rating_change += ($opposite_type === 'dislike') ? 1 : -1;
    }
    
    // Если уже есть такой голос - убираем его
    if ($has_current_vote) {
        unset($_SESSION['anonymous_likes'][$key]);
        // Если убираем лайк, то -1, если убираем дизлайк, то +1
        $rating_change += ($like_type === 'like') ? -1 : 1;
    } 
    // Ставим новый голос только если не было текущего
    else if (!$has_current_vote) {
        $_SESSION['anonymous_likes'][$key] = true;
        // Если добавляем лайк, то +1, если добавляем дизлайк, то -1
        $rating_change += ($like_type === 'like') ? 1 : -1;
    }
    
    // Обновляем рейтинг пасты в базе данных
    if ($rating_change != 0) {
        $stmt = $pdo->prepare("UPDATE pastes SET rating = rating + ? WHERE id = ?");
        $stmt->execute([$rating_change, $paste_id]);
    }
    
    // Возвращаем обновленные счетчики
    $likes = getLikeCount($paste_id, 'up');
    $dislikes = getLikeCount($paste_id, 'down');
    
    echo json_encode([
        'success' => true,
        'likes_up' => $likes,
        'likes_down' => $dislikes,
        'total' => $likes - $dislikes
    ]);
    exit;
}

// Для авторизованных пользователей используем базу данных
$like_type = $type === 'up' ? 'like' : 'dislike';
$result = togglePasteLike($paste_id, $_SESSION['user_id'], $like_type);

if ($result) {
    $likes = getLikeCount($paste_id, 'up');
    $dislikes = getLikeCount($paste_id, 'down');
    
    // Обновляем общий рейтинг в таблице pastes
    $total = $likes - $dislikes;
    $stmt = $pdo->prepare("UPDATE pastes SET rating = ? WHERE id = ?");
    $stmt->execute([$total, $paste_id]);
    
    echo json_encode([
        'success' => true,
        'likes_up' => $likes,
        'likes_down' => $dislikes,
        'total' => $total
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при обработке лайка']);
}
