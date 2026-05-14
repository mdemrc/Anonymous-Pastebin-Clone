<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Выход из системы
clearPersistentLogin();
session_destroy();

// Перезапускаем сессию для новых данных
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Устанавливаем сообщение об успешном выходе
setFlashMessage('success', 'Вы успешно вышли из системы');

// Перенаправляем на страницу входа
header('Location: login.php');
exit;
?>
