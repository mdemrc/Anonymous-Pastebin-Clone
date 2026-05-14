<?php
require_once 'includes/config.php';

try {
    // Проверяем, существуют ли столбцы для контактной информации
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'telegram'");
    $telegramExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'discord'");
    $discordExists = $stmt->rowCount() > 0;
    
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'website'");
    $websiteExists = $stmt->rowCount() > 0;
    
    // Добавляем столбцы, если они не существуют
    if (!$telegramExists) {
        $sql = "ALTER TABLE users ADD COLUMN telegram VARCHAR(255) DEFAULT NULL";
        $pdo->exec($sql);
        echo "<p>Столбец telegram успешно добавлен в таблицу users!</p>";
    } else {
        echo "<p>Столбец telegram уже существует в таблице users.</p>";
    }
    
    if (!$discordExists) {
        $sql = "ALTER TABLE users ADD COLUMN discord VARCHAR(255) DEFAULT NULL";
        $pdo->exec($sql);
        echo "<p>Столбец discord успешно добавлен в таблицу users!</p>";
    } else {
        echo "<p>Столбец discord уже существует в таблице users.</p>";
    }
    
    if (!$websiteExists) {
        $sql = "ALTER TABLE users ADD COLUMN website VARCHAR(255) DEFAULT NULL";
        $pdo->exec($sql);
        echo "<p>Столбец website успешно добавлен в таблицу users!</p>";
    } else {
        echo "<p>Столбец website уже существует в таблице users.</p>";
    }
    
    // Проверяем структуру таблицы после обновления
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
