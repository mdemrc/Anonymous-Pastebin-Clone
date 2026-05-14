<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Получаем ID пасты из URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

// Получаем информацию о пасте
$paste = getPasteById($id);

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

// Проверяем, нужно ли скачать файл
if (isset($_GET['download'])) {
    $filename = $paste['title'] ? $paste['title'] . '.txt' : 'paste_' . $id . '.txt';
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    header('Content-Type: text/plain; charset=utf-8');
}

// Выводим контент пасты
echo $paste['content'];
