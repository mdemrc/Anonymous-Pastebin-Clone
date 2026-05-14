<?php
function getRandomBanners($count = 2) {
    global $pdo;
    
    // Получаем случайные активные баннеры
    $stmt = $pdo->prepare("
        SELECT * FROM banners 
        WHERE active = 1 
        ORDER BY RAND() 
        LIMIT ?
    ");
    $stmt->execute([$count]);
    return $stmt->fetchAll();
}

function getRandomBannerTexts($count = 2) {
    global $pdo;
    
    // Получаем случайные активные рекламные тексты
    $stmt = $pdo->prepare("
        SELECT * FROM banner_texts 
        WHERE active = 1 
        ORDER BY RAND() 
        LIMIT ?
    ");
    $stmt->execute([$count]);
    return $stmt->fetchAll();
}
?>