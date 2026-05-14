<?php
require_once 'includes/config.php';

try {
    // Проверяем, существует ли столбец default_visibility
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'default_visibility'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Добавляем столбец default_visibility, если он не существует
        $sql = "ALTER TABLE users ADD COLUMN default_visibility ENUM('public', 'private') DEFAULT 'public' AFTER role";
        $pdo->exec($sql);
        echo "<h1>Столбец default_visibility успешно добавлен в таблицу users!</h1>";
    } else {
        echo "<h1>Столбец default_visibility уже существует в таблице users.</h1>";
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
