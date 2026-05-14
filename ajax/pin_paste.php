<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Проверяем, авторизован ли пользователь и имеет ли он права на закрепление паст
if (!isLoggedIn() || !canPinPastes()) {
    echo json_encode([
        'success' => false,
        'message' => 'У вас нет прав для выполнения этого действия'
    ]);
    exit;
}

// Проверяем, переданы ли необходимые параметры
if (!isset($_POST['paste_id']) || !isset($_POST['action'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Неверные параметры запроса'
    ]);
    exit;
}

$pasteId = intval($_POST['paste_id']);
$action = $_POST['action'];

// Проверяем существование пасты
$paste = getPasteById($pasteId);
if (!$paste) {
    echo json_encode([
        'success' => false,
        'message' => 'Паста не найдена'
    ]);
    exit;
}

// Выполняем действие в зависимости от запроса
if ($action === 'pin') {
    $result = pinPaste($pasteId);
} elseif ($action === 'unpin') {
    $result = unpinPaste($pasteId);
} else {
    $result = [
        'success' => false,
        'message' => 'Неверное действие'
    ];
}

// Возвращаем результат
echo json_encode($result);
