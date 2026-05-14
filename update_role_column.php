<?php
require_once 'includes/config.php';

// Включаем вывод ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Обновление структуры таблицы users</h1>";

try {
    // Проверяем, существует ли столбец role
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleColumnExists = $stmt->rowCount() > 0;
    
    if ($roleColumnExists) {
        // Если столбец существует, изменяем его размер
        $pdo->exec("ALTER TABLE users MODIFY COLUMN `role` VARCHAR(50)");
        echo "<p>Столбец 'role' успешно изменен на VARCHAR(50)</p>";
    } else {
        // Если столбца нет, добавляем его
        $pdo->exec("ALTER TABLE users ADD COLUMN `role` VARCHAR(50) DEFAULT NULL");
        echo "<p>Столбец 'role' успешно добавлен (VARCHAR(50))</p>";
    }
    
    // Проверяем текущую структуру таблицы
    $stmt = $pdo->query("DESCRIBE users");
    echo "<h2>Текущая структура таблицы users:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Тестируем вставку значения
    $testUserId = 1; // ID тестового пользователя
    $stmt = $pdo->prepare("UPDATE users SET role = 'administrator' WHERE id = ?");
    $result = $stmt->execute([$testUserId]);
    
    if ($result) {
        echo "<p style='color: green;'>Тестовое обновление роли успешно выполнено</p>";
        
        // Проверяем, что значение сохранилось корректно
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$testUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p>Текущая роль пользователя с ID {$testUserId}: " . htmlspecialchars($user['role'] ?? 'NULL') . "</p>";
    } else {
        echo "<p style='color: red;'>Ошибка при обновлении роли</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Ошибка: " . htmlspecialchars($e->getMessage()) . "</p>";
}
