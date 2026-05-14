<?php
require_once 'includes/config.php';

try {
    // Проверяем существование таблицы banners
    $checkTable = $pdo->query("SHOW TABLES LIKE 'banners'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if (!$tableExists) {
        // Создаем таблицу banners, если она не существует
        $sql = "
        CREATE TABLE banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            is_external TINYINT(1) DEFAULT 0,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($sql);
        echo "Table 'banners' successfully created!<br>";
    } else {
        // Проверяем наличие столбца is_external
        $checkColumn = $pdo->query("SHOW COLUMNS FROM banners LIKE 'is_external'");
        $columnExists = $checkColumn->rowCount() > 0;
        
        if (!$columnExists) {
            // Добавляем столбец is_external, если он не существует
            $sql = "ALTER TABLE banners ADD COLUMN is_external TINYINT(1) DEFAULT 0 AFTER url";
            $pdo->exec($sql);
            echo "Column 'is_external' successfully added to 'banners' table!<br>";
        } else {
            echo "Column 'is_external' already exists in 'banners' table.<br>";
        }
        
        // Проверяем наличие столбца updated_at
        $checkColumn = $pdo->query("SHOW COLUMNS FROM banners LIKE 'updated_at'");
        $columnExists = $checkColumn->rowCount() > 0;
        
        if (!$columnExists) {
            // Добавляем столбец updated_at, если он не существует
            $sql = "ALTER TABLE banners ADD COLUMN updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP";
            $pdo->exec($sql);
            echo "Column 'updated_at' successfully added to 'banners' table!<br>";
        } else {
            echo "Column 'updated_at' already exists in 'banners' table.<br>";
        }
    }
    
    echo "Banner table structure updated successfully!";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "<br>");
}

// Перенаправление на страницу администрирования баннеров
echo "<br><a href='admin/banners.php'>Return to Banner Management</a>";
?>
