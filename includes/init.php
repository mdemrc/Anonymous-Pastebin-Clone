<?php
// Подключаем основные файлы
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Настройки сессии
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Try persistent auto-login once per request if no active session
if (!isset($_SESSION['user_id'])) {
    if (function_exists('attemptAutoLoginFromCookie')) {
        attemptAutoLoginFromCookie();
    }
}
?>
