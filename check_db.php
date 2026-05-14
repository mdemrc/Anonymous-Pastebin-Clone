<?php
require_once 'includes/config.php';

try {
    // Проверяем существующие таблицы
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Существующие таблицы:</h2>";
    echo "<pre>";
    print_r($tables);
    echo "</pre>";
    
    // Проверяем структуру таблицы users, если она существует
    if (in_array('users', $tables)) {
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h2>Структура таблицы users:</h2>";
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    } else {
        echo "<p>Таблица users не существует!</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2>Ошибка:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
