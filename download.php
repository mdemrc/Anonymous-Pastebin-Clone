<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверяем, передан ли ID пасты
$paste_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$paste_id) {
    header('Location: index.php');
    exit;
}

// Получаем информацию о пасте
$paste = getPasteById($paste_id);

// Если паста не найдена
if (!$paste) {
    header('Location: index.php');
    exit;
}

// Проверяем доступ к приватной пасте
if ($paste['visibility'] === 'private') {
    if (!isLoggedIn() || ($_SESSION['user_id'] != $paste['user_id'] && !isAdmin())) {
        header('Location: index.php');
        exit;
    }
}

// Формируем имя файла
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $paste['title']) . '.txt';

// Устанавливаем заголовки для скачивания
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($paste['content']));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Выводим содержимое пасты
echo $paste['content'];
exit;
