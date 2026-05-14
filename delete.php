<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверяем, авторизован ли пользователь
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Проверяем, передан ли ID пасты
$paste_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$paste_id) {
    header('Location: index.php');
    exit;
}

// Получаем информацию о пасте
$stmt = $pdo->prepare("SELECT * FROM pastes WHERE id = ?");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

// Если паста не найдена, перенаправляем на главную страницу
if (!$paste) {
    header('Location: index.php');
    exit;
}

// Проверяем права на удаление пасты
// Пользователь может удалить пасту, если:
// 1. Он является владельцем пасты
// 2. Он имеет права на модерацию паст (Administrator, Staff, Developer)
if ($_SESSION['user_id'] != $paste['user_id'] && !canModeratePastes()) {
    header('Location: view.php?id=' . $paste_id);
    exit;
}

// Удаляем пасту
$stmt = $pdo->prepare("DELETE FROM pastes WHERE id = ?");
$stmt->execute([$paste_id]);

// Удаляем лайки и дизлайки для этой пасты
$stmt = $pdo->prepare("DELETE FROM paste_likes WHERE paste_id = ?");
$stmt->execute([$paste_id]);

// Записываем в лог информацию об удалении
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$log_message = "Paste ID: $paste_id deleted by User ID: $user_id ($username)";

if (canModeratePastes() && $_SESSION['user_id'] != $paste['user_id']) {
    $log_message .= " [MODERATION ACTION]";
}

// Создаем директорию для логов, если она не существует
$log_dir = __DIR__ . '/logs';
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Запись в лог
$log_file = fopen($log_dir . '/paste_deletions.log', 'a');
fwrite($log_file, date('Y-m-d H:i:s') . " - " . $log_message . "\n");
fclose($log_file);

// Перенаправляем на главную страницу с сообщением об успешном удалении
$_SESSION['flash_message'] = "Paste successfully deleted.";
header('Location: index.php');
exit;
?>
