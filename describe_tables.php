<?php
// Подключаем инициализацию
require_once 'includes/init.php';

// Получаем структуру таблицы users
$stmt = $pdo->prepare("DESCRIBE users");
$stmt->execute();
$usersColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Структура таблицы users:</h2>";
echo "<pre>";
print_r($usersColumns);
echo "</pre>";

// Получаем структуру таблицы pastes
$stmt = $pdo->prepare("DESCRIBE pastes");
$stmt->execute();
$pastesColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Структура таблицы pastes:</h2>";
echo "<pre>";
print_r($pastesColumns);
echo "</pre>";
