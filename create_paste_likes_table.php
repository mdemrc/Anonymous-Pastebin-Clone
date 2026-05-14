<?php
// Включаем отображение ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Настройки базы данных - используйте те же, что и в config.php на хостинге
$db_host = 'localhost';
$db_user = 'paste1'; // Имя пользователя БД на хостинге
$db_pass = 'Fx4Kf4sjdXFAErWY'; // Пароль БД на хостинге
$db_name = 'paste'; // Имя БД на хостинге

// Подключение к базе данных напрямую, без использования config.php
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>Подключение к базе данных успешно установлено.</p>";
} catch (PDOException $e) {
    die("<p>Ошибка подключения к базе данных<br>" . $e->getMessage() . "</p>");
}

// Проверяем существование таблицы paste_likes
$tableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'paste_likes'");
    $tableExists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    echo "<p>Ошибка при проверке таблицы: " . $e->getMessage() . "</p>";
}

// Если таблица не существует, создаем ее
if (!$tableExists) {
    try {
        $sql = "CREATE TABLE paste_likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            paste_id INT NOT NULL,
            user_id INT NOT NULL,
            type ENUM('like', 'dislike') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_paste_user (paste_id, user_id)
        )";
        
        $pdo->exec($sql);
        echo "<p>Таблица paste_likes успешно создана.</p>";
    } catch (PDOException $e) {
        echo "<p>Ошибка при создании таблицы paste_likes: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Таблица paste_likes уже существует.</p>";
}

// Проверяем, что таблица pastes существует
$pastesTableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'pastes'");
    $pastesTableExists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    echo "<p>Ошибка при проверке таблицы pastes: " . $e->getMessage() . "</p>";
}

// Если таблица pastes не существует, создаем ее
if (!$pastesTableExists) {
    try {
        $sql = "CREATE TABLE pastes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            user_id INT NULL,
            visibility ENUM('public', 'private', 'unlisted') DEFAULT 'public',
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (visibility),
            INDEX (created_at)
        )";
        
        $pdo->exec($sql);
        echo "<p>Таблица pastes успешно создана.</p>";
    } catch (PDOException $e) {
        echo "<p>Ошибка при создании таблицы pastes: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>Таблица pastes уже существует.</p>";
}

echo "<p>Все операции завершены.</p>";
?>
