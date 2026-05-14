<?php
require_once 'includes/config.php';

try {
    // Проверяем, существует ли столбец last_login
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Добавляем столбец last_login, если он не существует
        $sql = "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER created_at";
        $pdo->exec($sql);
        echo "<h1>Столбец last_login успешно добавлен в таблицу users!</h1>";
    } else {
        echo "<h1>Столбец last_login уже существует в таблице users.</h1>";
    }
    
    // Проверяем структуру таблицы
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Текущая структура таблицы users:</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<h1>Ошибка:</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
