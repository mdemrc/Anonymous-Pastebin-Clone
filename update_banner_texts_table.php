<?php
require_once 'includes/config.php';

try {
    // Проверяем существование таблицы banner_texts
    $checkTable = $pdo->query("SHOW TABLES LIKE 'banner_texts'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if (!$tableExists) {
        // Создаем таблицу banner_texts, если она не существует
        $sql = "
        CREATE TABLE banner_texts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            text VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($sql);
        echo "Таблица 'banner_texts' успешно создана!<br>";
        
        // Добавляем несколько примеров текстовых баннеров
        $sql = "
        INSERT INTO banner_texts (text, url, active) VALUES 
        ('Лучший хостинг для ваших проектов - HostPro.com', 'https://hostpro.com', 1),
        ('Купите премиум аккаунт и получите доступ ко всем функциям', 'https://example.com/premium', 1);
        ";
        $pdo->exec($sql);
        echo "Примеры текстовых баннеров добавлены!<br>";
    } else {
        echo "Таблица 'banner_texts' уже существует.<br>";
        
        // Проверяем наличие столбца updated_at
        $checkColumn = $pdo->query("SHOW COLUMNS FROM banner_texts LIKE 'updated_at'");
        $columnExists = $checkColumn->rowCount() > 0;
        
        if (!$columnExists) {
            // Добавляем столбец updated_at, если он не существует
            $sql = "ALTER TABLE banner_texts ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP";
            $pdo->exec($sql);
            echo "Столбец 'updated_at' успешно добавлен в таблицу 'banner_texts'!<br>";
        } else {
            echo "Столбец 'updated_at' уже существует в таблице 'banner_texts'.<br>";
        }
    }
    
    echo "Структура таблицы текстовых баннеров обновлена успешно!";
    
} catch (PDOException $e) {
    die("Ошибка: " . $e->getMessage() . "<br>");
}

// Перенаправление на страницу администрирования
echo "<br><a href='admin/banners.php'>Вернуться к управлению баннерами</a>";
?>
