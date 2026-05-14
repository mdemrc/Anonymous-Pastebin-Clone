<?php
// Запуск сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_USER', 'paste1');
define('DB_PASS', 'BXZ8GyeNXHBf6k3Z');
define('DB_NAME', 'paste');

// Настройки сайта
define('SITE_URL', 'https://example.com/');
define('SITE_NAME', 'example.com');
define('ADMIN_EMAIL', 'admin@example.com');

// reCAPTCHA configuration
define('RECAPTCHA_SITE_KEY', '6LffaB0rAAAAAZGrEnJcQ6WwGNNILZcGvQ8sO3R');
define('RECAPTCHA_SECRET_KEY', '6LffaB0rAAAAADegFrvAN1ETuJ4ahzJ-giDeAtkJ');

// Подключение к базе данных через PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}
