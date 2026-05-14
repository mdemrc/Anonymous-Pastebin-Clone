<?php
// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Версия PHP
$required_php_version = '7.4.0';
if (version_compare(PHP_VERSION, $required_php_version, '<')) {
    die('Требуется PHP версии ' . $required_php_version . ' или выше. Текущая версия: ' . PHP_VERSION);
}

// Настройки отображения ошибок (временно для отладки)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Настройки базы данных для XAMPP
if (!defined('DB_HOST')) define('DB_HOST', 'localhost'); 
if (!defined('DB_USER')) define('DB_USER', 'paste1');
if (!defined('DB_PASS')) define('DB_PASS', 'BXZ8GyeNXHBf6k3Z'); // Обычно пароль пустой в стандартной установке XAMPP
if (!defined('DB_NAME')) define('DB_NAME', 'paste');

// Настройки сайта для локальной разработки
if (!defined('SITE_URL')) define('SITE_URL', 'https://example.com');
if (!defined('SITE_NAME')) define('SITE_NAME', 'example.com');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'admin@example.com');

// Подключение к базе данных через PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5, // Добавляем таймаут
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch(PDOException $e) {
    // Запись ошибки в лог для отладки
    error_log("Ошибка подключения к БД: " . $e->getMessage());
    
    // Вывод подробной информации об ошибке (только для отладки)
    echo "<h1>Ошибка подключения к базе данных</h1>";
    echo "<p>Детали ошибки: " . $e->getMessage() . "</p>";
    echo "<p>PHP версия: " . PHP_VERSION . "</p>";
    echo "<p>PDO доступен: " . (class_exists('PDO') ? 'Да' : 'Нет') . "</p>";
    echo "<p>Расширения PHP: " . implode(', ', get_loaded_extensions()) . "</p>";
    die();
}
