<?php
require_once 'includes/config.php';

// Проверяем, существует ли уже поле is_banned
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_banned'");
$exists = $stmt->fetchColumn();

if (!$exists) {
    // Добавляем поле is_banned в таблицу users
    $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) NOT NULL DEFAULT 0");
    echo "Поле is_banned успешно добавлено в таблицу users.";
} else {
    echo "Поле is_banned уже существует в таблице users.";
}

// Проверяем, существует ли поле ban_reason
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'ban_reason'");
$exists = $stmt->fetchColumn();

if (!$exists) {
    // Добавляем поле ban_reason в таблицу users
    $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason VARCHAR(255) DEFAULT NULL");
    echo "<br>Поле ban_reason успешно добавлено в таблицу users.";
} else {
    echo "<br>Поле ban_reason уже существует в таблице users.";
}
?>
