<?php
// Отображение ошибок для отладки
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Настройки базы данных - используйте те же, что и в config.php на хостинге
$db_host = 'localhost';
$db_user = 'root'; // Замените на имя пользователя БД на хостинге
$db_pass = ''; // Замените на пароль БД на хостинге
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

// SQL запрос для создания таблицы banners
$sql = "
CREATE TABLE IF NOT EXISTS `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_path` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `is_external` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    // Выполняем SQL запрос
    $pdo->exec($sql);
    echo "<h2>Таблица 'banners' успешно создана или уже существует!</h2>";
    
    // Проверяем, есть ли уже записи в таблице
    $stmt = $pdo->query("SELECT COUNT(*) FROM banners");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "<p>Добавление примеров баннеров...</p>";
        
        // Добавляем примеры баннеров
        $insertSql = "
        INSERT INTO `banners` (`image_path`, `url`, `active`, `is_external`) VALUES
        ('assets/images/banners/banner1.jpg', 'https://example.com', 1, 0),
        ('assets/images/banners/banner2.jpg', 'https://example.org', 1, 0);
        ";
        
        $pdo->exec($insertSql);
        echo "<p>Примеры баннеров добавлены!</p>";
    } else {
        echo "<p>В таблице уже есть {$count} записей.</p>";
    }
    
} catch(PDOException $e) {
    echo "<h2>Ошибка при создании таблицы 'banners':</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// SQL запрос для создания таблицы banner_texts
$sql_texts = "
CREATE TABLE IF NOT EXISTS `banner_texts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `text` varchar(255) NOT NULL,
  `url` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

try {
    // Выполняем SQL запрос
    $pdo->exec($sql_texts);
    echo "<h2>Таблица 'banner_texts' успешно создана или уже существует!</h2>";
    
    // Проверяем, есть ли уже записи в таблице
    $stmt = $pdo->query("SELECT COUNT(*) FROM banner_texts");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "<p>Добавление примеров текстовых баннеров...</p>";
        
        // Добавляем примеры текстовых баннеров
        $insertSql = "
        INSERT INTO `banner_texts` (`text`, `url`, `active`) VALUES
        ('Check out our new features!', 'https://example.com/features', 1),
        ('Join our community today!', 'https://example.org/community', 1);
        ";
        
        $pdo->exec($insertSql);
        echo "<p>Примеры текстовых баннеров добавлены!</p>";
    } else {
        echo "<p>В таблице уже есть {$count} записей.</p>";
    }
    
} catch(PDOException $e) {
    echo "<h2>Ошибка при создании таблицы 'banner_texts':</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

echo "<p><a href='index.php'>Вернуться на главную страницу</a></p>";
?>
