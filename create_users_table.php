<?php
// Отображение ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Настройки базы данных - используйте те же, что и в config.php на хостинге
$db_host = 'localhost';
$db_user = 'paste1'; // Замените на имя пользователя БД на хостинге
$db_pass = 'Fx4Kf4sjdXFAErWY'; // Замените на пароль БД на хостинге
$db_name = 'paste'; // Замените на имя БД на хостинге (обратите внимание, что в ошибке указано 'paste', а не 'paste_to')

// Подключение к базе данных напрямую, без использования config.php
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "<p>Подключение к базе данных успешно установлено.</p>";
    
} catch(PDOException $e) {
    die("<h1>Ошибка подключения к базе данных</h1><p>" . $e->getMessage() . "</p>");
}

// SQL запрос для создания таблицы users
$sql = "
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `is_banned` tinyint(1) NOT NULL DEFAULT 0,
  `ban_reason` varchar(255) DEFAULT NULL,
  `telegram` varchar(255) DEFAULT NULL,
  `discord` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `emoji` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    // Выполняем SQL запрос
    $pdo->exec($sql);
    echo "<h2>Таблица 'users' успешно создана или уже существует!</h2>";
    
    // Проверяем, есть ли уже записи в таблице
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "<p>Добавление администратора по умолчанию...</p>";
        
        // Добавляем администратора по умолчанию
        $adminUsername = 'admin';
        $plainPassword = getenv('ADMIN_DEFAULT_PASSWORD') ?: bin2hex(random_bytes(8));
        $adminPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $adminEmail = 'admin@example.com';
        
        $insertSql = "
        INSERT INTO `users` (`username`, `password`, `email`, `role`, `emoji`) VALUES
        (?, ?, ?, 'administrator', 'crown');
        ";
        
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([$adminUsername, $adminPassword, $adminEmail]);
        echo "<p>Администратор по умолчанию добавлен!</p>";
        echo "<p>Login: admin</p>";
        echo "<p>Password: " . htmlspecialchars($plainPassword) . "</p>";
        echo "<p><strong>Important:</strong> change the administrator password right after the first login.</p>";
    } else {
        echo "<p>В таблице уже есть {$count} записей.</p>";
    }
    
} catch(PDOException $e) {
    echo "<h2>Ошибка при создании таблицы 'users':</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// SQL запрос для создания таблицы pastes, если она не существует
$sql_pastes = "
CREATE TABLE IF NOT EXISTS `pastes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `visibility` enum('public','private','unlisted') NOT NULL DEFAULT 'public',
  `views` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pastes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    // Выполняем SQL запрос
    $pdo->exec($sql_pastes);
    echo "<h2>Таблица 'pastes' успешно создана или уже существует!</h2>";
} catch(PDOException $e) {
    echo "<h2>Ошибка при создании таблицы 'pastes':</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// SQL запрос для создания таблицы likes, если она не существует
$sql_likes = "
CREATE TABLE IF NOT EXISTS `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paste_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('like','dislike') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `paste_user_unique` (`paste_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`paste_id`) REFERENCES `pastes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    // Выполняем SQL запрос
    $pdo->exec($sql_likes);
    echo "<h2>Таблица 'likes' успешно создана или уже существует!</h2>";
} catch(PDOException $e) {
    echo "<h2>Ошибка при создании таблицы 'likes':</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

echo "<p><a href='index.php'>Вернуться на главную страницу</a></p>";
?>
