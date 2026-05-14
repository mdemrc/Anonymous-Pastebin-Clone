<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавление поля is_pinned в таблицу pastes</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #151529;
            color: #fff;
        }
        .container {
            background-color: #1d1e3a;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        h1 {
            color: #00ff9d;
            text-align: center;
        }
        .success {
            color: #00ff9d;
            padding: 10px;
            background-color: rgba(0, 255, 157, 0.1);
            border-radius: 4px;
            margin: 10px 0;
        }
        .error {
            color: #ff6b6b;
            padding: 10px;
            background-color: rgba(255, 107, 107, 0.1);
            border-radius: 4px;
            margin: 10px 0;
        }
        .button {
            background-color: #00ff9d;
            color: #151529;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            display: block;
            margin: 20px auto;
            text-decoration: none;
            text-align: center;
            width: 200px;
        }
    </style>
</head>
<body>
    <h1>Добавление поля is_pinned в таблицу pastes</h1>
    
    <div class="container">
        <?php
        if (isset($_GET['run']) && $_GET['run'] == 'true') {
            try {
                require_once 'includes/config.php';
                
                // Проверяем, существует ли уже колонка is_pinned
                $stmt = $pdo->prepare("SHOW COLUMNS FROM pastes LIKE 'is_pinned'");
                $stmt->execute();
                $column_exists = $stmt->fetch();
                
                if (!$column_exists) {
                    // Добавляем колонку is_pinned
                    $pdo->exec("ALTER TABLE pastes ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0");
                    echo '<div class="success">Колонка is_pinned успешно добавлена в таблицу pastes.</div>';
                    
                    // Создаем индекс для быстрого поиска закрепленных паст
                    $pdo->exec("CREATE INDEX idx_is_pinned ON pastes (is_pinned)");
                    echo '<div class="success">Индекс idx_is_pinned успешно создан.</div>';
                } else {
                    echo '<div class="error">Колонка is_pinned уже существует в таблице pastes.</div>';
                }
                
                // Проверяем количество закрепленных паст
                $countPinnedQuery = "SELECT COUNT(*) FROM pastes WHERE is_pinned = 1";
                $pinnedCount = $pdo->query($countPinnedQuery)->fetchColumn();
                echo '<div class="success">Текущее количество закрепленных паст: ' . $pinnedCount . '</div>';
                
                echo '<a href="index.php" class="button">Вернуться на главную</a>';
            } catch (PDOException $e) {
                echo '<div class="error">Ошибка при выполнении запроса: ' . $e->getMessage() . '</div>';
                echo '<a href="add_pinned_field.php" class="button">Попробовать снова</a>';
            }
        } else {
            ?>
            <p>Этот скрипт добавит колонку is_pinned в таблицу pastes и создаст индекс для быстрого поиска закрепленных паст.</p>
            <p>Нажмите кнопку ниже, чтобы запустить скрипт:</p>
            <a href="add_pinned_field.php?run=true" class="button">Запустить скрипт</a>
            <?php
        }
        ?>
    </div>
</body>
</html>
