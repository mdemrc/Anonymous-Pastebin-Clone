<?php
require_once 'includes/config.php';

try {
    // Проверяем существование таблицы banner_texts
    $checkTable = $pdo->query("SHOW TABLES LIKE 'banner_texts'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if ($tableExists) {
        // Получаем все текстовые баннеры
        $stmt = $pdo->query("SELECT * FROM banner_texts");
        $bannerTexts = $stmt->fetchAll();
        
        echo "<h2>Текстовые баннеры:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Текст</th><th>URL</th><th>Активен</th></tr>";
        
        foreach ($bannerTexts as $bannerText) {
            echo "<tr>";
            echo "<td>" . $bannerText['id'] . "</td>";
            echo "<td>" . $bannerText['text'] . "</td>";
            echo "<td>" . $bannerText['url'] . "</td>";
            echo "<td>" . ($bannerText['active'] ? 'Да' : 'Нет') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Таблица 'banner_texts' не существует. Нужно создать её.";
        
        // Создаем таблицу banner_texts
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
        echo "<p>Таблица 'banner_texts' успешно создана!</p>";
        
        // Добавляем несколько примеров текстовых баннеров
        $sql = "
        INSERT INTO banner_texts (text, url, active) VALUES 
        ('Лучший хостинг для ваших проектов - HostPro.com', 'https://hostpro.com', 1),
        ('Купите премиум аккаунт и получите доступ ко всем функциям', 'https://example.com/premium', 1);
        ";
        $pdo->exec($sql);
        echo "<p>Примеры текстовых баннеров добавлены!</p>";
        
        // Показываем добавленные баннеры
        $stmt = $pdo->query("SELECT * FROM banner_texts");
        $bannerTexts = $stmt->fetchAll();
        
        echo "<h2>Добавленные текстовые баннеры:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Текст</th><th>URL</th><th>Активен</th></tr>";
        
        foreach ($bannerTexts as $bannerText) {
            echo "<tr>";
            echo "<td>" . $bannerText['id'] . "</td>";
            echo "<td>" . $bannerText['text'] . "</td>";
            echo "<td>" . $bannerText['url'] . "</td>";
            echo "<td>" . ($bannerText['active'] ? 'Да' : 'Нет') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} catch (PDOException $e) {
    die("Ошибка: " . $e->getMessage() . "<br>");
}
?>
