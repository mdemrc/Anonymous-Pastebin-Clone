<?php
// Large paste support (100k+ lines) - Runtime PHP settings
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
set_time_limit(300);

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Проверяем, передан ли ID пасты
$paste_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$paste_id) {
    header('Location: index.php');
    exit;
}

$userId = null;
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
}

incrementViews($paste_id, $userId);

// Получаем информацию о пасте
// Получаем информацию о пасте
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.emoji, u.name_color, u.telegram, u.discord, u.website,
           COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
           COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
    FROM pastes p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);
$totalViews = $paste['views'];

// Если паста не найдена, перенаправляем на главную страницу
if (!$paste) {
    header('Location: index.php');
    exit;
}

// Получаем ранг пользователя, если паста принадлежит пользователю
$userRank = null;
if ($paste['user_id']) {
    $userRank = getUserRank($paste['user_id']);
}

// Проверяем доступ к приватной пасте
if ($paste['visibility'] === 'private') {
    if (!isLoggedIn() || ($_SESSION['user_id'] != $paste['user_id'] && !isAdmin())) {
        header('Location: index.php');
        exit;
    }
}

// Получаем количество лайков и дизлайков
$likes_up = getLikeCount($paste_id, 'up');
$likes_down = getLikeCount($paste_id, 'down');

// Добавляем в массив пасты
$paste['likes_up'] = $likes_up;
$paste['likes_down'] = $likes_down;

// Get current user's vote status
$userVote = null;
if (isLoggedIn()) {
    $userVote = getUserVote($paste_id, $_SESSION['user_id']);
}

// Получаем закрепленные элементы (если есть) - теперь массивы
$pinnedBanners = getPinnedBanners();
$pinnedTexts = getPinnedTexts();

// Получаем ID закрепленных баннеров для исключения из случайных
$pinnedBannerIds = array_map(function($b) { return (int)$b['id']; }, $pinnedBanners);
$pinnedTextIds = array_map(function($t) { return (int)$t['id']; }, $pinnedTexts);

// Получаем случайные баннеры
$banners = getRandomBanners();
// Исключаем закрепленные баннеры из случайных
if (!empty($pinnedBannerIds)) {
    $banners = array_values(array_filter($banners, function($b) use ($pinnedBannerIds) {
        return !in_array((int)$b['id'], $pinnedBannerIds);
    }));
}
// Рассчитываем сколько случайных баннеров показать (всего 2 минус закрепленные)
$randomBannerCount = max(0, 2 - count($pinnedBanners));
$banners = array_slice($banners, 0, $randomBannerCount);

// Получаем случайные текстовые баннеры
$bannerTexts = getRandomBannerTexts();
if (!empty($pinnedTextIds)) {
    $bannerTexts = array_values(array_filter($bannerTexts, function($t) use ($pinnedTextIds) {
        return !in_array((int)$t['id'], $pinnedTextIds);
    }));
}
// Рассчитываем сколько случайных текстов показать (всего 2 минус закрепленные)
$randomTextCount = max(0, 2 - count($pinnedTexts));
$bannerTexts = array_slice($bannerTexts, 0, $randomTextCount);

// Подключаем header
require_once 'includes/header.php';
?>

<?php
// Helper to produce full paste URL for Copy URL action
if (!function_exists('fullPasteUrl')) {
    function fullPasteUrl($id) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        return $scheme . '://' . $host . $path . '/view.php?id=' . urlencode($id);
    }
}
?>

<style>
    /* Общие стили для текста */
    p, span, div, a, h1, h2, h3, h4, h5, h6, li, td, th {
        font-family: 'Source Code Pro', monospace;
        font-size: 16px;
    }
    
    /* Стили для заголовка */
    .paste-title {
        font-size: 24px !important;
        font-weight: bold !important;
        margin-bottom: 4px !important;
        width: auto !important;
        display: inline-block !important;
        font-family: 'Source Code Pro', monospace !important;
    }
    
    /* CodeMirror стили только для view.php */
    .editor-container .CodeMirror {
        height: 100% !important;
        width: 100% !important;
        border-radius: 8px;
        font-family: 'Source Code Pro', monospace !important;
        font-size: 14px !important;
        line-height: 1.6 !important;
        background: #1d1e3a !important;
        overflow: hidden !important;
        border: none !important;
        white-space: pre !important;
    }
    
    /* Принудительно устанавливаем размер для CodeMirror */
    .CodeMirror.cm-s-default.CodeMirror-wrap {
        height: 524px !important;
        min-height: 524px !important;
        width: 964px !important;
    }
    
            .default-gradient-1 {
            background-image: linear-gradient(to right, #ff0000, #ff3366, #ff66cc, #ff99ff, #ff66cc, #ff3366, #ff0000);
            background-size: 200% auto;
            animation: shine 2s linear infinite;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: bold;
        }
        
        .default-gradient-2 {
            background-image: linear-gradient(to right, #00ffff, #00bfff, #0080ff, #8a2be2, #ff00ff);
            background-size: 200% auto;
            animation: shine 2s linear infinite;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: bold;
        }

    .editor-container .CodeMirror-wrap {
        white-space: pre !important;
    }

    .editor-container .CodeMirror-line {
        white-space: pre !important;
    }

    .editor-container .CodeMirror-linenumber {
        background-color: transparent !important;
        border-right: none !important;
        color: rgba(255, 255, 255, 0.5) !important;
        padding: 0 8px !important;
    }

    .editor-container .CodeMirror-gutters {
        background-color: transparent !important;
        border-right: none !important;
        width: 50px !important;
    }

    .editor-container .CodeMirror-selected {
        background: rgba(255, 255, 255, 0.1) !important;
    }

    .editor-container .CodeMirror-cursor {
        border-left: 2px solid transparent !important;
    }

    /* Цвета синтаксиса */
    .editor-container .cm-s-material-palenight .cm-comment { color: #676e95 !important; }
    .editor-container .cm-s-material-palenight .cm-keyword { color: #c792ea !important; }
    .editor-container .cm-s-material-palenight .cm-operator { color: #89ddff !important; }
    .editor-container .cm-s-material-palenight .cm-string { color: #c3e88d !important; }
    .editor-container .cm-s-material-palenight .cm-number { color: #ff9cac !important; }
    .editor-container .cm-s-material-palenight .cm-def { color: #82aaff !important; }
    .editor-container .cm-s-material-palenight .cm-variable { color: #fff !important; }
    .editor-container .cm-s-material-palenight .cm-variable-2 { color: #fff !important; }
    .editor-container .cm-s-material-palenight .cm-variable-3 { color: #fff !important; }
    .editor-container .cm-s-material-palenight .cm-property { color: #fff !important; }
    .editor-container .cm-s-material-palenight .cm-atom { color: #ff9cac !important; }
    .editor-container .cm-s-material-palenight .cm-tag { color: #ff9cac !important; }
    .editor-container .cm-s-material-palenight .cm-attribute { color: #c792ea !important; }

    /* Стили для скроллбаров */
    .editor-container .CodeMirror-vscrollbar {
        display: none !important;
    }

    .editor-container .CodeMirror-hscrollbar {
        height: 10px !important;
    }

    .editor-container .CodeMirror-scrollbar-filler {
        display: none !important;
    }

    .editor-container .CodeMirror-scroll {
        overflow: auto !important;
        margin-right: 0 !important;
        margin-bottom: 0 !important;
    }

    /* Настройка внешнего вида скроллбаров */
    .editor-container .CodeMirror ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    .editor-container .CodeMirror ::-webkit-scrollbar-track {
        background: transparent;
    }

    .editor-container .CodeMirror ::-webkit-scrollbar-thumb {
        background: #00ff9d;
        border-radius: 5px;
    }

    .editor-container .CodeMirror ::-webkit-scrollbar-thumb:hover {
        background: #32ffb6;
    }

    .editor-container .CodeMirror ::-webkit-scrollbar-corner {
        background: transparent;
    }

    /* Link styling inside paste content: green + underline */
    .paste-content a,
    .paste-content a:visited {
        color: #00ff9d !important;
        text-decoration: underline !important;
    }
    .paste-content a:hover,
    .paste-content a:active,
    .paste-content a:focus {
        color: #32ffb6 !important;
        text-decoration: underline !important;
        outline: none !important;
    }

    /* Отступы для кода */
    .editor-container .CodeMirror-lines {
        padding: 8px 0 !important;
    }

    .editor-container .CodeMirror-line {
        padding: 0 8px !important;
    }

    /* Стили для textarea */
    .editor-container textarea#code-editor {
        background: #1d1e3a;
        color: #fff;
        font-family: 'Source Code Pro', monospace;
        font-size: 14px;
        line-height: 1.6;
        padding: 8px;
        border: none !important;
        border-radius: 8px;
        resize: none;
    }

    /* Стили для контейнера с кодом */
    .editor-container {
        width: 964px !important;
        height: 524px !important;
        min-height: 524px !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        background: #1d1e3a !important;
        display: flex !important;
        position: relative !important;
        margin-top: 10px !important;
    }

    .editor-container .CodeMirror {
        flex: 1 !important;
        height: 524px !important;
        min-height: 524px !important;
        border-radius: 8px;
        font-family: 'Source Code Pro', monospace !important;
        font-size: 14px !important;
        line-height: 1.6 !important;
        background: #1d1e3a !important;
        overflow: hidden !important;
        border: none !important;
        white-space: pre !important;
    }

    /* Дополнительный скроллбар */
    .editor-container .extra-scroll {
        width: 10px !important;
        height: 100% !important;
        overflow-y: scroll !important;
        background: transparent !important;
        position: absolute !important;
        right: 0 !important;
        top: 0 !important;
        z-index: 2 !important;
    }

    .editor-container .extra-scroll::-webkit-scrollbar {
        width: 10px;
    }

    .editor-container .extra-scroll::-webkit-scrollbar-track {
        background: transparent;
    }

    .editor-container .extra-scroll::-webkit-scrollbar-thumb {
        background: #00ff9d;
        border-radius: 5px;
    }

    .editor-container .extra-scroll::-webkit-scrollbar-thumb:hover {
        background: #32ffb6;
    }

    .editor-container .extra-scroll-content {
        height: 1000px;
    }

    /* Стили для левого блока */
    .pasteattributes {
        width: 400px !important;
        height: auto !important; /* Высота по содержимому */
        background: #1d1e3a !important;
        border-radius: 8px !important;
        padding: 20px !important;
        border: none !important;
        box-shadow: none !important;
        position: sticky !important;
        top: 24px !important;
        margin-top: 40px !important; /* Уменьшаем отступ сверху */
    }

    /* Стили для имени пользователя */
    .paste-username {
        margin-bottom: 16px !important;
        font-size: 18px !important;
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: bold !important;
    }

    .paste-username .emoji {
        margin-right: 8px !important;
        font-size: 20px !important;
    }

    .paste-username a {
        color: #00ff9d !important;
        text-decoration: none !important;
        transition: opacity 0.2s ease !important;
        font-weight: bold !important;
        font-family: 'Source Code Pro', monospace !important;
    }

    .paste-username a:hover {
        opacity: 0.8 !important;
    }

    /* Стили для блоков статистики */
    .paste-stats {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 12px !important;
        margin-top: 20px !important;
    }

    .stat-block {
        background: rgba(255, 255, 255, 0.05) !important;
        border-radius: 6px !important;
        padding: 12px !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
    }

    .contact-block {
        background: rgba(255, 255, 255, 0.05) !important;
        border-radius: 6px !important;
        padding: 16px !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 12px !important;
        grid-column: 1 / -1 !important; /* Span across all columns */
        margin-bottom: 20px !important;
    }

    .contact-item {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 10px 12px !important;
        background: rgba(0, 255, 157, 0.08) !important;
        border-radius: 8px !important;
        border: 1px solid rgba(0, 255, 157, 0.2) !important;
        transition: all 0.3s ease !important;
        text-decoration: none !important;
    }

    .contact-item:hover {
        background: rgba(0, 255, 157, 0.15) !important;
        border-color: rgba(0, 255, 157, 0.4) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(0, 255, 157, 0.2) !important;
    }

    .contact-item i {
        font-size: 18px !important;
        color: #00ff9d !important;
        width: 20px !important;
        text-align: center !important;
    }

    .contact-item a {
        color: #ffffff !important;
        text-decoration: none !important;
        font-weight: 500 !important;
        font-family: 'Source Code Pro', monospace !important;
        flex: 1 1 auto !important;
        min-width: 0 !important; /* allow flex child to shrink */
        max-width: 100% !important;
        white-space: normal !important; /* no single-line constraint */
        overflow-wrap: anywhere !important; /* wrap long URLs */
        word-break: break-word !important;
        line-height: 1.4 !important;
    }

    .contact-item span {
        color: #ffffff !important;
        font-weight: 500 !important;
        font-family: 'Source Code Pro', monospace !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        max-width: 100% !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        word-break: break-word !important;
        line-height: 1.4 !important;
    }

    .stat-title {
        font-size: 16px !important;
        font-weight: bold !important;
        color: #00ff9d !important;
        font-family: 'Source Code Pro', monospace !important;
    }

    .stat-value {
        font-size: 16px !important;
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
    }

    /* Стили для кнопок */
    .paste-actions {
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
        margin-top: 10px !important; /* Уменьшаем отступ сверху для чужих паст */
        margin-bottom: 16px !important;
    }
    
    /* Стили для кнопок действий при просмотре своей пасты */
    .paste-actions.own-paste {
        margin-top: 30px !important; /* Меньший отступ для своих паст */
        display: grid !important;
        grid-template-columns: 1fr 1fr !important; /* Две кнопки в ряд */
        gap: 10px !important;
    }
    
    /* 2x2 utility actions grid (Copy URL/Text, View Raw, Download) */
    .action-grid-2x2 {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 10px !important;
        margin-top: 10px !important;
    }
    .paste-actions.own-paste .action-grid-2x2 { grid-column: span 2 !important; }

    .paste-button {
        width: 100% !important;
        padding: 15px !important;
        background: #00ff9d !important;
        border: none !important;
        border-radius: 8px !important;
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-decoration: none !important;
        gap: 8px !important;
        font-size: 16px !important;
        transition: opacity 0.2s ease !important;
        font-weight: 800 !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
        letter-spacing: 0.5px !important;
    }

    /* Secondary minimal buttons for utility actions */
    .paste-button.secondary {
        background: transparent !important;
        border: 1px solid #2a2b52 !important;
        color: #ffffff !important;
        padding: 12px !important;
        font-weight: 600 !important;
        text-shadow: none !important;
    }
    .paste-button.secondary:hover {
        background: #191a36 !important;
        opacity: 1 !important;
        border-color: #3a3d6e !important;
    }

    /* Accent colors for the four actions */
    /* Unified green accent for all four buttons */
    .paste-button.secondary.accent-green,
    .paste-button.secondary.accent-teal,
    .paste-button.secondary.accent-indigo,
    .paste-button.secondary.accent-blue {
        border-color: #2f7a41 !important;
    }
    .paste-button.secondary.accent-green:hover,
    .paste-button.secondary.accent-teal:hover,
    .paste-button.secondary.accent-indigo:hover,
    .paste-button.secondary.accent-blue:hover {
        background: rgba(47, 122, 65, 0.12) !important;
        border-color: #49a45f !important;
    }
    .paste-button.secondary.accent-green i,
    .paste-button.secondary.accent-teal i,
    .paste-button.secondary.accent-indigo i,
    .paste-button.secondary.accent-blue i {
        color: #34d27b !important;
    }

    .paste-button:hover {
        opacity: 0.8 !important;
    }

    .paste-button i {
        color: #fff !important;
        font-size: 14px !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
    }

    /* Active state for like buttons */
    .like-button.active:nth-child(1) {
        background: rgba(0, 255, 157, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
    }

    .like-button.active:nth-child(3) {
        background: rgba(255, 69, 58, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
    }

    /* Стили для лайков */
    .paste-likes {
        display: flex !important;
        gap: 24px !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 24px 0 0 0 !important; /* Remove bottom margin */
    }

    .like-button {
        width: 70px !important;
        height: 70px !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        color: #fff !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.3s ease !important;
    }

    .like-button:hover {
        opacity: 0.8 !important;
    }

    /* Like button hover - Green */
    .like-button:nth-child(1):hover {
        background: rgba(0, 255, 157, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
        transition: all 0.3s ease !important;
    }

    /* Dislike button hover - Red */
    .like-button:nth-child(3):hover {
        background: rgba(255, 69, 58, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
        transition: all 0.3s ease !important;
    }

    .like-button img {
        width: 38px !important;
        height: 38px !important;
        filter: invert(1) !important;
    }

    .likes-count {
        color: #fff !important;
        font-size: 28px !important;
        font-family: 'Source Code Pro', monospace !important;
        min-width: 70px !important;
        text-align: center !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: bold !important;
    }

    /* Стили для названия файла */
    .file-name {
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
        font-size: 24px !important;
        font-weight: bold !important;
        text-align: left !important;
        width: auto !important;
        margin: 0 0 32px -300px !important;
        text-transform: uppercase !important;
        letter-spacing: 1.5px !important;
        padding: 24px 0 !important;
        display: inline-block !important;
        position: static !important;
    }

    /* Зеленая линия под названием файла */
    .green-underline {
        background: #00ff9d !important;
        height: 3px !important;
        width: 100% !important;
        margin: 12px 0 0 0 !important;
        text-align: left !important;
        position: static !important;
    }

    /* Контейнер с названием файла и редактором */
    .paste-view-container {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        width: 100% !important;
        margin-top: 32px !important;
    }

    .editor-container {
        width: 100% !important;
    }

    body {
        padding-top: 48px;
    }


    
    .banner-text a {
        text-decoration: none;
        font-family: 'Source Code Pro', monospace;
    }
    
    .banner-text .font-bold {
        font-weight: 900 !important;
        font-family: 'Source Code Pro', monospace;
    }
    
    /* Стили для баннеров с автоматическим изменением размера */
    .banner-container {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px; /* Увеличиваем отступ между баннерами */
        width: 100%;
        margin: 5px auto;
    }
    
    .banner {
        border-radius: 0px;
        overflow: hidden;
        transition: transform 0.3s ease;
        display: block;
        margin: 0 !important; /* Убираем отступы через margin */
        padding: 0; /* Убираем внутренние отступы */
        width: 440px; /* Фиксированная ширина */
        height: 111px; /* Фиксированная высота */
    }
    
    .banner img {
        width: 100%; /* Заполняем всю ширину контейнера */
        height: 100%; /* Заполняем всю высоту контейнера */
        object-fit: cover; /* Масштабируем изображение, чтобы заполнить контейнер */
        display: block;
    }

    /* Стили для левого блока */
    .pasteattributes {
        width: 400px !important;
        height: auto !important; /* Высота по содержимому */
        background: #1d1e3a !important;
        border-radius: 8px !important;
        padding: 20px !important;
        border: none !important;
        box-shadow: none !important;
        position: sticky !important;
        top: 24px !important;
        margin-top: 40px !important; /* Уменьшаем отступ сверху */
    }

    /* Стили для имени пользователя */
    .paste-username {
        margin-bottom: 16px !important;
        font-size: 18px !important;
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: bold !important;
    }

    .paste-username .emoji {
        margin-right: 8px !important;
        font-size: 20px !important;
    }

    .paste-username a {
        color: #00ff9d !important;
        text-decoration: none !important;
        transition: opacity 0.2s ease !important;
        font-weight: bold !important;
        font-family: 'Source Code Pro', monospace !important;
    }

    .paste-username a:hover {
        opacity: 0.8 !important;
    }

    /* Стили для блоков статистики */
    .paste-stats {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 12px !important;
        margin-top: 20px !important;
    }

    .stat-block {
        background: rgba(255, 255, 255, 0.05) !important;
        border-radius: 6px !important;
        padding: 12px !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
    }

    .contact-block {
        background: rgba(255, 255, 255, 0.05) !important;
        border-radius: 6px !important;
        padding: 16px !important;
        display: flex !important;
        flex-direction: column !important;
        gap: 12px !important;
        grid-column: 1 / -1 !important; /* Span across all columns */
        margin-bottom: 20px !important;
    }

    .contact-item {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 10px 12px !important;
        background: rgba(0, 255, 157, 0.08) !important;
        border-radius: 8px !important;
        border: 1px solid rgba(0, 255, 157, 0.2) !important;
        transition: all 0.3s ease !important;
        text-decoration: none !important;
    }

    .contact-item:hover {
        background: rgba(0, 255, 157, 0.15) !important;
        border-color: rgba(0, 255, 157, 0.4) !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(0, 255, 157, 0.2) !important;
    }

    .contact-item i {
        font-size: 18px !important;
        color: #00ff9d !important;
        width: 20px !important;
        text-align: center !important;
    }

    .contact-item a {
        color: #ffffff !important;
        text-decoration: none !important;
        font-weight: 500 !important;
        font-family: 'Source Code Pro', monospace !important;
        flex: 1 !important;
    }

    .contact-item span {
        color: #ffffff !important;
        font-weight: 500 !important;
        font-family: 'Source Code Pro', monospace !important;
        flex: 1 !important;
    }

    .stat-title {
        font-size: 16px !important;
        font-weight: bold !important;
        color: #00ff9d !important;
        font-family: 'Source Code Pro', monospace !important;
    }

    .stat-value {
        font-size: 16px !important;
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
    }

    /* Стили для кнопок */
    .paste-actions {
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
        margin-top: 10px !important; /* Уменьшаем отступ сверху для чужих паст */
        margin-bottom: 16px !important;
    }
    
    /* Стили для кнопок действий при просмотре своей пасты */
    .paste-actions.own-paste {
        margin-top: 30px !important; /* Меньший отступ для своих паст */
        display: grid !important;
        grid-template-columns: 1fr 1fr !important; /* Две кнопки в ряд */
        gap: 10px !important;
    }
    
    /* Кнопки View Raw и Download всегда в одну колонку */
    .paste-actions.own-paste .view-download-buttons {
        grid-column: span 2 !important; /* Занимает всю ширину */
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
        margin-top: 15px !important;
    }

    /* Стили для кнопок View Raw и Download при просмотре чужой пасты */
    .view-download-buttons-other {
        display: flex !important;
        flex-direction: column !important;
        gap: 8px !important;
    }

    .paste-button {
        width: 100% !important;
        padding: 15px !important;
        background: #00ff9d !important;
        border: none !important;
        border-radius: 8px !important;
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        text-decoration: none !important;
        gap: 8px !important;
        font-size: 16px !important;
        transition: opacity 0.2s ease !important;
        font-weight: 800 !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
        letter-spacing: 0.5px !important;
    }

    .paste-button:hover {
        opacity: 0.8 !important;
    }

    .paste-button i {
        color: #fff !important;
        font-size: 14px !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2) !important;
    }

    /* Стили для лайков */
    .paste-likes {
        display: flex !important;
        gap: 24px !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 24px 0 0 0 !important; /* Remove bottom margin */
    }

    .like-button {
        width: 70px !important;
        height: 70px !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        color: #fff !important;
        cursor: pointer !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.3s ease !important;
    }

    .like-button:hover {
        opacity: 0.8 !important;
    }

    /* Like button hover - Green */
    .like-button:nth-child(1):hover {
        background: rgba(0, 255, 157, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
        transition: all 0.3s ease !important;
    }

    /* Dislike button hover - Red */
    .like-button:nth-child(3):hover {
        background: rgba(255, 69, 58, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
        transition: all 0.3s ease !important;
    }

    /* Active state for like buttons */
    .like-button.active:nth-child(1) {
        background: rgba(0, 255, 157, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
    }

    .like-button.active:nth-child(3) {
        background: rgba(255, 69, 58, 0.2) !important;
        border-radius: 12px !important;
        transform: scale(1.1) !important;
    }

    .like-button img {
        width: 38px !important;
        height: 38px !important;
        filter: invert(1) !important;
    }

    .likes-count {
        color: #fff !important;
        font-size: 28px !important;
        font-family: 'Source Code Pro', monospace !important;
        min-width: 70px !important;
        text-align: center !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-weight: bold !important;
    }

    /* Стили для названия файла */
    .file-name {
        color: #fff !important;
        font-family: 'Source Code Pro', monospace !important;
        font-size: 24px !important;
        font-weight: bold !important;
        text-align: left !important;
        width: auto !important;
        margin: 0 0 32px -300px !important;
        text-transform: uppercase !important;
        letter-spacing: 1.5px !important;
        padding: 24px 0 !important;
        display: inline-block !important;
        position: static !important;
    }

    /* Зеленая линия под названием файла */
    .green-underline {
        background: #00ff9d !important;
        height: 3px !important;
        width: 100% !important;
        margin: 12px 0 0 0 !important;
        text-align: left !important;
        position: static !important;
    }

    /* Контейнер с названием файла и редактором */
    .paste-view-container {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        width: 100% !important;
        margin-top: 32px !important;
    }

    .editor-container {
        width: 100% !important;
    }

    body {
        padding-top: 48px;
    }

    
    .banner-text a {
        text-decoration: none;
        font-family: 'Source Code Pro', monospace;
    }
    
    .banner-text .font-bold {
        font-weight: 900 !important;
        font-family: 'Source Code Pro', monospace;
    }
                    /* Username Styles on reqeusts of ONEMILI by @AustinnXD */
.lrefunder {
    background: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/onyx-bg.gif);
    text-shadow: 0.5px 0.5px 5px;
    -webkit-animation: hueanim 10s infinite linear;
    color: #0FF;
    font-weight: bold
}

.floraiN {
    background: linear-gradient(90deg,#1ff3fb 0,#37ef40 100%,#fff);
    background-clip: border-box;
    -webkit-background-clip: text;
    text-shadow: 0 0 5px #16e695
}

.CEO_rank {
    color: #ffffff;
    text-shadow: 1px 1px 10px #ffffff;
}

.customrank_zyzz {
    position: relative;
    -webkit-text-fill-color: transparent;
    background-clip: border-box,border-box,text;
    background-image: url(https://cdn.patched.to/custom_group_zyzz/bg1.webp),url(https://cdn.patched.to/custom_group_zyzz/thunder.gif),linear-gradient(-45deg,#ff8000 0%,#ff8000 10%,#ff8000 20%,#ff8000 30%,#ff8000 40%,#ff8000 50%,#fff 60%,#ff8000 70%,#ff8000 80%,#ff8000 90%,#ff8000 100%);
    background-size: 2em,6em,30em;
    letter-spacing: 1px;
    animation: zyzz 1.5s linear infinite,colorRotate 10s linear infinite,glow 2s ease-in-out infinite
}

@keyframes zyzz {
    0% {
        background-position: 0%,0%,-20em 0
    }

    100% {
        background-position: 0%,0%,30em 0
    }
}

@keyframes colorRotate {
    0% {
        filter: hue-rotate(0deg)
    }

    100% {
        filter: hue-rotate(360deg)
    }
}

@keyframes glow {
    0% {
        text-shadow: 0 0 10px #ff8000,0 0 20px #ff8000,0 0 30px #ff8000
    }

    50% {
        text-shadow: 0 0 20px #ff8000,0 0 30px #ff8000,0 0 40px #ff8000,0 0 50px #ff8000,0 0 60px #ff8000
    }

    100% {
        text-shadow: 0 0 10px #ff8000,0 0 20px #ff8000,0 0 30px #ff8000
    }
}

.onemili {
    -webkit-background-clip: text !important;
    text-shadow: 0 0 5px #c8378d;
    background: linear-gradient(to right, #7a00ff, #ff0000, #00ff15);
    -webkit-text-fill-color: transparent;
    color: #FF512F;
    font-weight: 700;
}

.dreams {
    background-clip: border-box;
    -webkit-background-clip: text;
    text-shadow: 0 0 5px #4e1336;
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif),linear-gradient(90deg,#ed0d0d 0,#590e4b 100%,#fff)
}

.heaven {
    background-clip: border-box;
    -webkit-background-clip: text;
    text-shadow: 0 0 5px #c8378d;
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif),linear-gradient(90deg,#e4f918 0,#ed11ff 100%,#fff)
}

.contributor_rank,.features_tborder td.trow1:nth-child(6):before {
    color: #FFDF00;
    text-shadow: 0px 0px 3px #090909
}

.premium_rank,.features_tborder td.trow1:nth-child(4):before {
    color: #4DD5D5;
    text-shadow: 0px 0px 5px #090909
}

.features_tborder td.trow1:nth-child(2):before,.supreme_rank {
    color: #54FF9F;
    text-shadow: 1px 1px 10px #39c70d;
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif)
}

.godlike_rank,.features_tborder td.trow1:nth-child(5):before {
    background: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif);
    color: #CF2D9F;
    text-shadow: 0px 0px 8px #77185B
}

.coder_rank {
    color: #a868ed;
    font-weight: bold;
    text-shadow: 0px 0px 5px #090909
}

.infinity_rank,.features_tborder td.trow1:nth-child(3):before {
    color: #ff99b1;
    text-shadow: 0px 0px 5px #ff85a2
}

.shine_rank {
    text-shadow: 0px 0px 3px #0e0101;
    color: #8f55dc;
    font-weight: bold;
    border-bottom: 1px dotted
}

.dev_rank {
    color: #c61aff;
    text-shadow: 0px 0px 5px #090909;
    font-weight: bold
}

.reverser_rank {
    background: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif);
    color: #8ed7d4;
    border-bottom: 1px dotted
}

.section_mod_rank {
    color: #26f6a5;
    text-shadow: 1px 1px 3px #000
}

.admin_rank {
    text-shadow: 0px 0px 5px #0c0c0c;
    color: #D8185C;
    font-weight: bold;
    border-bottom: 1px dashed
}

.heaven_rank {
    background-clip: border-box;
    -webkit-background-clip: text;
    text-shadow: 0 0 5px #c8378d;
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif),linear-gradient(90deg,#e4f918 0,#ed11ff 100%,#fff);
    -webkit-text-fill-color: transparent;
    color: #FF512F;
    font-weight: 700
}

.mods_rank {
    color: #3b8ed6;
    font-weight: bold;
    text-shadow: 0px 0px 5px #090909
}

.trial_mod_rank {
    color: #028e6b;
    font-weight: bold;
    text-shadow: 0px 0px 5px #090909
}

.retired_rank {
    color: #80ea8d;
    text-shadow: 0px 0px 5px #090909
}

.disinfector_rank {
    text-shadow: 0px 0px 5px #0c0c0c;
    color: #91abf6
}

.kings_rank_old {
    color: #FFD700;
    font-weight: bold;
    text-shadow: 1px 1px 6px #F00;
    text-decoration: underline;
    text-decoration-style: dotted;
    background-image: url(https://web.archive.org/web/20220205011522im_/https://i.postimg.cc/RqrRMH6N/ezgif-com-resize-17.gif)
}

.kings_rank:before {
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif);
    position: absolute;
    content: "";
    height: 100%;
    width: 100%;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    display: block;
    z-index: 1
}

.kings_rank {
    position: relative;
    background: linear-gradient(178deg,#ffd700,#ff2a00,#ffd700);
    background-size: 600% 600%;
    font-weight: bold;
    -webkit-animation: AnimationName 4s ease infinite;
    -moz-animation: AnimationName 4s ease infinite;
    animation: AnimationName 4s ease infinite;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    border-bottom: 1px dotted #ffd700
}

@keyframes AnimationName {
    0% {
        background-position: 48% 0%;
        border-bottom: 1px dotted #ff2a00
    }

    50% {
        background-position: 53% 100%;
        border-bottom: 1px dotted #ffd700
    }

    100% {
        background-position: 48% 0%;
        border-bottom: 1px dotted #ff2a00
    }
}

.sparkles {
    background: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif)
}

@keyframes gradient-text {
    0% {
        background-position: 0% 50%
    }

    25% {
        background-position: 100% 50%
    }

    50% {
        background-position: 75% 25%
    }

    75% {
        background-position: 25% 75%
    }

    100% {
        background-position: 0% 50%
    }
}

*/@keyframes glitch-effect {
    0% {
        clip: rect(25px,9999px,65px,0);
        -webkit-filter: brightness(0.5);
        filter: brightness(0.5)
    }

    5% {
        clip: rect(41px,9999px,4px,0);
        filter: brightness(0.5);
        filter: brightness(0.5)
    }

    10% {
        clip: rect(52px,9999px,15px,0);
        filter: brightness(0.5);
        filter: brightness(0.5)
    }

    40% {
        clip: rect(12px,9999px,2px,0);
        filter: brightness(0.5);
        filter: brightness(0.5)
    }

    45% {
        clip: rect(16px,9999px,71px,0);
        filter: brightness(0.5);
        filter: brightness(0.5)
    }

    100% {
        clip: rect(92px,9999px,78px,0);
        filter: brightness(0.5);
        filter: brightness(0.5)
    }
}

@keyframes hueanim {
    from {
        -webkit-filter: hue-rotate(0deg)
    }

    to {
        -webkit-filter: hue-rotate(360deg)
    }
}

.new_refunder {
    background: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/onyx-bg.gif);
    position: relative;
    margin: 0 auto;
    color: #ffb100;
    font-family: "Roboto",sans-serif;
    font-weight: 600;
    text-align: center;
    letter-spacing: 0.01em;
    transform: scale3d(1,1,1);
    text-shadow: 0.5px 0.5px 2px #ff8300;
    border-bottom: 1px dotted;
    -webkit-animation: hueanim 10s linear infinite
}

.new_refunder:before {
    left: 7px;
    text-shadow: 1px 0 #696969;
    animation: glitch-effect 3s infinite linear alternate-reverse;
    color: #ffc136
}

.new_refunder:after {
    left: 3px;
    text-shadow: -1px 0 #708090;
    animation: glitch-effect 2s infinite linear alternate-reverse;
    color: #ffc749
}

.new_refunder::before,.new_refunder::after {
    content: attr(data-text);
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    overflow: hidden;
    color: gold;
    clip: rect(0,900px,0,0)
}

.rainbow_name {
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/onyx-bg.gif);
    color: red;
    text-shadow: 0 0 5px red
}

@keyframes mellowanimation {
    from {
        filter: hue-rotate(-360deg)
    }

    to {
        filter: hue-rotate(360deg)
    }
}

.mellowanimation > * {
    background-clip: border-box;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    color: #FF512F;
    font-weight: 700;
    text-shadow: 0px 0px 5px #727309;
    background-image: linear-gradient(90deg,#f00 0%,#29ff00 100%,#fff);
    animation: mellowanimation 5s infinite linear
}

.mellowanimation {
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif)
}

.mod_rank {
    color: #3b8ed6;
    text-shadow: 0px 0px 5px #090909
}

.new_refunder_placeholder {
    background: linear-gradient(45deg,rgba(255,255,255,1) 2%,rgba(255,255,255,1) 25%,rgba(248,1,1,1) 35%,rgba(248,1,1,1) 65%,rgba(255,255,255,1) 75%,rgba(255,255,255,1) 100%);
    animation: anim 5s ease-in-out infinite;
    text-shadow: 0px 0px 6px #f21e1ea3;
    color: #fff;
    font-weight: bold;
    background-size: 300%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent
}

@keyframes anim {
    0% {
        background-position: 0 0
    }

    50% {
        background-position: 100% 100%
    }

    100% {
        background-position: 0 0
    }
}

/* .onemili_ug {
    font-weight: bold;
    background-position: .2em -.07em !important;
    !i;!;position: relative;
    text-shadow: 0px 0 1em #56a15e;
    -webkit-text-fill-color: transparent;
    -webkit-background-clip: text,border-box,text;
    background-image: url(https://i.imgur.com/tDkmDXm.gif), url(https://i.imgur.com/1jpPYQG.gif), linear-gradient(90deg, rgb(255 255 255) 0%, rgb(0 255 104) 25%, rgb(255 255 255) 51%, rgb(255 255 255) 75%, rgb(255 249 249) 100%);
    background-size: 3em,5em,15em;
    animation: onemili_ug-anim 16s linear infinite;
}

@keyframes onemili_ug-anim {
    0% {
        background-position: 0 0,0%,-40em 0
    }

    100% {
        background-position: 0 0,0%,40em 0
    }
}

.onemili_ug:before {
    background-image: url(https://media4.giphy.com/media/v1.Y2lkPTc5MGI3NjExaHhpaG5zdzBieHVuaTVycW84ZnR5NXloajZuaHo5OWp5dmVhbGhoMCZlcD12MV9zdGlja2Vyc19zZWFyY2gmY3Q9cw/HQp9pMIlKV6zQiBlBi/giphy.webp);
    background-size: 102%;
    position: absolute;
    width: 46px;
    z-index: 6;
    height: 25px;
    left: 1rem;
    transform: rotate(0deg) translateY(-50%);
    content: '';
} */

.onemili_ug {
    font-weight: bold;
    position: relative;
    background-position: .2em -.07em;
    text-shadow: 0 0 1em rgb(201 179 179 / 63%);
    -webkit-text-fill-color: transparent;
    -webkit-background-clip: text, border-box, text;
    background-image: 
        url(https://i.imgur.com/tDkmDXm.gif), 
        url(https://i.imgur.com/1jpPYQG.gif), 
        linear-gradient(90deg, #c53030, #2f855a); /* Only red and green */
    background-size: 3em, 5em, 15em;
    background-position: 0 0, 0 0, 0 0;
    /* Two animations: moving background layers and a gentle floating text effect */
    /*animation: onemili_ug-anim 16s linear infinite, */
    /*           textFloat 5s infinite ease-in-out;*/
}

/* Animate background layers (including the money-inspired gradient) to create a dynamic flow */
@keyframes onemili_ug-anim {
    0% {
        background-position: 0 0, 0 0, 0 0;
    }
    100% {
        background-position: 0 0, 100% 0, 40em 0;
    }
}

/* A gentle floating animation for a randomized text movement effect */
@keyframes textFloat {
    0%   { transform: translate(0, 0); }
    25%  { transform: translate(2px, -2px); }
    50%  { transform: translate(-2px, 2px); }
    75%  { transform: translate(1px, -1px); }
    100% { transform: translate(0, 0); }
}

/* Adjust the pseudo-element to mimic a money emblem or red ribbon */
.onemili_ug:before {
    background-image: url();
    background-size: 102%;
    position: absolute;
    width: 46px;
    height: 25px;
    left: 0rem;
    z-index: 6;
    transform: rotate(0deg) translateY(-50%);
    content: '';
}


.redxdezn {
    color: red;
    background: url(https://i.postimg.cc/HsTDJTK2/bg1.webp);
    background-size: 3em, 5em, 15em;
}

    .emoji img,
    .emoji i {
        height: 24px !important;
    }

img[src$="king.gif"] {
        margin-bottom: 8px !important;
    }
    img[src$="moon.webp"] {
        margin-bottom: 8px !important;
    }
    img[src$="ethereum.png"] {
        margin-bottom: 8px !important;
    }
a[href*="user.php"] span span {
    font-size: 20px !important;
    margin-bottom: 5px;
}

.CodeMirror {
    margin-left: -20px;
}


</style>
<!-- <div class="pasteattributes">
                                        <h3 class="paste-info larger"><a href="/web/20241215193943/https://paste.fo/user/VAVE"><span class="ug-98 ui-99 large-ui">VAVE</span></a></h3>
                    
                                        <h3 class="paste-info">Contact</h3>
                    <h4 class="profileattribute" style="text-align: center; margin: 0px 0px 12px 0px; display: flex; align-items: center; justify-content: center; font-size: 20px;"><img style="height: 28px; margin: 0px 10px 0px 0px;" src="/web/20241215193943im_/https://paste.fo/assets/svg/cracked.php"> <a target="_blank" class="cio cio-91" style="text-decoration: none;" href="https://web.archive.org/web/20241215193943/https://cracked.io/member.php?action=profile&amp;uid=482032">VAVE</a></h4>                                                                                        <h4 class="paste-info" style="text-align: center; margin: 0px 0px 7px 0px;"><i class="fa-solid fa-link"></i> <a target="_blank" style="text-decoration: underline;" href="https://web.archive.org/web/20241215193943/https://vave.li/">vave.li</a></h4>                    
                    
                    <div class="paste-about">
                        <h4 class="paste-info"><i class="fa-solid fa-eye"></i><div><span>Views</span> <span class="about-value">24725</span></div></h4>
                        <h4 class="paste-info"><i class="fa-regular fa-eye"></i> <div><span>Visibility</span> <span class="about-value">Public</span></div></h4>
                        <h4 class="paste-info"><i class="fa-regular fa-clock"></i> <div><span>Expires</span> <span class="about-value">Never</span></div></h4>
                        <h4 class="paste-info"><i class="fa-solid fa-calendar-days"></i> <div><span>Created</span> <span class="about-value">August 2022</span></div></h4> 
                    </div>

                                        <a href="/web/20241215193943/https://paste.fo/raw/changelog" class="util-button" title="View Raw" target="_blank"><i class="fa-regular fa-file-lines"></i> View Raw</a>
                    <a href="/web/20241215193943/https://paste.fo/raw/changelog?download" class="util-button" title="Download" target="_blank"><i class="fa-solid fa-download"></i> Download</a>
                    
                    <div class="ratings">
                        <div class="h-captcha" data-sitekey="9c54b617-bd43-4858-a8c9-83ce00be8180" data-callback="onLike" data-hcaptcha-source-id="button[data-hcaptcha-widget-id='0f3zeeeeeeec']" style="display: none;"><iframe allow="autoplay 'self'; fullscreen 'self'" aria-hidden="true" data-hcaptcha-widget-id="0f3zeeeeeeec" data-hcaptcha-response="" src="https://web.archive.org/web/20241215193632/https://newassets.hcaptcha.com/captcha/v1/94cdacf/static/hcaptcha.html#frame=checkbox-invisible" style="display: none;"></iframe><textarea id="g-recaptcha-response-0f3zeeeeeeec" name="g-recaptcha-response" style="display: none;"></textarea><textarea id="h-captcha-response-0f3zeeeeeeec" name="h-captcha-response" style="display: none;"></textarea></div><button class="h-captcha ratebutton" data-sitekey="9c54b617-bd43-4858-a8c9-83ce00be8180" data-callback="onLike" data-hcaptcha-widget-id="0f3zeeeeeeec"><img src="/web/20241215193943im_/https://paste.fo/assets/svg/thumbs-up-regular.svg"></button>
                        <h3 class="currentrating">3</h3>
                        <div class="h-captcha" data-sitekey="9c54b617-bd43-4858-a8c9-83ce00be8180" data-callback="onDislike" data-hcaptcha-source-id="button[data-hcaptcha-widget-id='1h1e7777777']" style="display: none;"><iframe allow="autoplay 'self'; fullscreen 'self'" aria-hidden="true" data-hcaptcha-widget-id="1h1e7777777" data-hcaptcha-response="" src="https://web.archive.org/web/20241215193632/https://newassets.hcaptcha.com/captcha/v1/94cdacf/static/hcaptcha.html#frame=checkbox-invisible" style="display: none;"></iframe><textarea id="g-recaptcha-response-1h1e7777777" name="g-recaptcha-response" style="display: none;"></textarea><textarea id="h-captcha-response-1h1e7777777" name="h-captcha-response" style="display: none;"></textarea></div><button class="h-captcha ratebutton" data-sitekey="9c54b617-bd43-4858-a8c9-83ce00be8180" data-callback="onDislike" data-hcaptcha-widget-id="1h1e7777777"><img src="/web/20241215193943im_/https://paste.fo/assets/svg/thumbs-down-regular.svg"></button>
                    </div>
                </div> -->

<!-- Pinned + Random Banners -->
<div class="banner-container flex justify-center gap-1 my-4 flex-wrap mt-16">
    <?php foreach ($pinnedBanners as $pinnedBanner): ?>
        <div class="banner">
            <a href="redirect.php?type=banner&id=<?php echo $pinnedBanner['id']; ?>&url=<?php echo urlencode($pinnedBanner['url']); ?>" target="_blank">
                <img src="<?php echo htmlspecialchars($pinnedBanner['image_path']); ?>" alt="Banner">
            </a>
        </div>
    <?php endforeach; ?>
    <?php foreach ($banners as $banner): ?>
        <div class="banner">
            <a href="redirect.php?type=banner&id=<?php echo $banner['id']; ?>&url=<?php echo urlencode($banner['url']); ?>" target="_blank">
                <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Banner">
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Pinned + Random Text Banners -->
<div class="banner-text-container flex flex-col items-center gap-1 mt-0 mb-0">
    <?php foreach ($pinnedTexts as $idx => $pinnedText): ?>
        <div class="banner-text">
            <a href="redirect.php?type=text&id=<?php echo $pinnedText['id']; ?>&url=<?php echo urlencode($pinnedText['url']); ?>" target="_blank">
                <?php $pinnedClass = !empty($pinnedText['style']) ? $pinnedText['style'] : (($idx % 2 === 0) ? 'default-gradient-1' : 'default-gradient-2'); ?>
                <span class="font-bold text-lg <?php echo htmlspecialchars($pinnedClass); ?>">
                    <?php echo htmlspecialchars($pinnedText['text']); ?>
                </span>
            </a>
        </div>
    <?php endforeach; ?>
    <?php $i = count($pinnedTexts); foreach ($bannerTexts as $bannerText): ?>
        <div class="banner-text">
            <a href="redirect.php?type=text&id=<?php echo $bannerText['id']; ?>&url=<?php echo urlencode($bannerText['url']); ?>" target="_blank">
                <?php $class = !empty($bannerText['style']) ? $bannerText['style'] : (($i % 2 === 0) ? 'default-gradient-1' : 'default-gradient-2'); ?>
                <span class="font-bold text-lg <?php echo htmlspecialchars($class); ?>">
                    <?php echo htmlspecialchars($bannerText['text']); ?>
                </span>
            </a>
        </div>
    <?php $i++; endforeach; ?>
</div>

<!-- Название файла (новый div) -->
<div class="paste-filename" style="text-align: center; margin: 20px 0; color: #fff; font-size: 24px; font-weight: bold;">
    <?php echo htmlspecialchars($paste['title']); ?>
    <div style="height: 2px; background-color: #00ff9d; width: 500px; margin: 5px auto;"></div>
</div>

<div class="wrapper" style="margin-top: -40px;">
    <div class="flex mt-0 gap-8 p-6">
        <!-- Левая колонка - информация о пасте -->
        <?php
        // Определяем высоту блока в зависимости от роли пользователя
        $attributesHeight = '100%'; // Стандартная высота
        
        // Если пользователь авторизован
        if (isLoggedIn()) {
            // Если паста принадлежит текущему пользователю
            if (isset($_SESSION['user_id']) && isset($paste['user_id']) && $_SESSION['user_id'] == $paste['user_id']) {
                $attributesHeight = '100%'; // Увеличенная высота для своих паст
            }
            // Если пользователь - администратор
            else if (isAdmin()) {
                $attributesHeight = '100%'; // Увеличиваем на 100px для админов
            }
            // Проверяем, является ли пользователь staff или developer
            else if (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && isset($user['role']) && ($user['role'] === 'staff' || $user['role'] === 'developer')) {
                    $attributesHeight = '100%'; // Увеличиваем на 100px для staff и developer
                }
            }
        }
        ?>
        <div class="pasteattributes">
            <!-- Имя пользователя -->
            <div class="paste-username">
                <?php if ($paste['user_id']): ?>
                    <?php $pasteUser = getUserById($paste['user_id']); ?>
                    <?php if ($pasteUser): ?>
                        <?php $userRank = getUserRank($pasteUser['id']); ?>
                        <?php $classxD = getNameColor($pasteUser['id']); ?>
                        <a href="user.php?id=<?php echo $pasteUser['id']; ?>" <?php if (!empty($paste['name_color'])): ?>style="color: <?php echo htmlspecialchars($paste['name_color']); ?> !important;"<?php endif; ?>>
                            <?php
                                // Merge custom name color class with rank class and include style if provided
                                $attrParts = [];
                                $classes = [];
                                if (!empty($classxD)) { $classes[] = $classxD; }
                                if (!empty($userRank['username_class'])) { $classes[] = $userRank['username_class']; }
                                if (!empty($classes)) { $attrParts[] = 'class="' . htmlspecialchars(implode(' ', $classes)) . '"'; }
                                if (!empty($userRank['username_style'])) { $attrParts[] = 'style="' . htmlspecialchars($userRank['username_style']) . '"'; }
                                $wrapperAttr = implode(' ', $attrParts);
                            ?>
                            <span <?php echo $wrapperAttr; ?> style="font-size: 1.5rem !important;">
                                <?php echo !empty($userRank['username_prefix']) ? $userRank['username_prefix'] . ' ' : ''; ?>
                                <?php echo htmlspecialchars($pasteUser['username']); ?>
                                <?php echo !empty($userRank['username_suffix']) ? ' ' . $userRank['username_suffix'] : ''; ?>
                            </span>
                            <?php if (!empty($userRank['html'])) { echo ' ' . $userRank['html']; } ?>
                            <?php if (!empty($paste['emoji'])): ?>
                                <span class="emoji"><?php echo displayUserEmoji($paste['emoji']); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <i class="fa-solid fa fa-user-secret" style="color: #00ff9d; margin-right: 8px;"></i>
                        <span>Unknown user</span>
                    <?php endif; ?>
                <?php else: ?>
                    <i class="fa-solid fa fa-user-secret" style="color: #00ff9d; margin-right: 8px;"></i>
                    <span style="color: #00ff9d;">Anonymous</span>
                <?php endif; ?>
            </div>

            <!-- Блок статистики -->
            <div class="paste-stats">
                <?php if ($paste['user_id'] && (!empty($paste['telegram']) || !empty($paste['discord']) || !empty($paste['website']))): ?>
                <!-- Контактная информация пользователя -->
                <div class="contact-block">
                        <div class="stat-title">
                            <i class="fa-solid fa-id-card" style="color: #00ff9d; margin-right: 8px;"></i>
                        Contact
                        </div>
                    
                    <div class="paste-contact-header text-textColor font-medium mb-2 text-center">
                        
                    </div>
                    <div class="paste-contact-info" style="display: flex; flex-direction: column; gap: 12px;">
                        <?php if (!empty($paste['telegram'])): ?>
                        <?php
                            $tgRaw = $paste['telegram'];
                            // Extract handle from possible forms
                            $handle = $tgRaw;
                            if (preg_match('~^(?:https?://)?(?:t\.me|telegram\.me)/([A-Za-z0-9_]{3,})$~i', $tgRaw, $m)) {
                                $handle = '@' . $m[1];
                            } elseif ($tgRaw[0] !== '@') {
                                $handle = '@' . ltrim($tgRaw, '@');
                            }
                            $handleClean = ltrim($handle, '@');
                            $tgUrl = 'https://t.me/' . rawurlencode($handleClean);
                        ?>
                        <a href="<?php echo htmlspecialchars($tgUrl); ?>" target="_blank" class="contact-item">
                            <i class="fab fa-telegram"></i>
                            <span><?php echo htmlspecialchars($tgUrl); ?></span>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($paste['discord'])): ?>
                        <div class="contact-item">
                            <i class="fab fa-discord"></i>
                            <span><?php echo htmlspecialchars($paste['discord']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($paste['website'])): ?>
                        <a href="<?php echo htmlspecialchars($paste['website']); ?>" target="_blank" class="contact-item">
                            <i class="fas fa-globe"></i>
                            <span>
                                <?php 
                                // Отображаем ссылку без https:// или http://
                                $website_display = preg_replace('#^https?://#', '', htmlspecialchars($paste['website']));
                                echo $website_display;
                                ?>
                            </span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Views -->
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fa-solid fa-eye"></i>
                        Views
                    </div>
                    <div class="stat-value"><?php echo number_format($paste['views']); ?></div>
                </div>

                <!-- Visibility -->
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fa-regular fa-eye"></i>
                        Visibility
                    </div>
                    <div class="stat-value"><?php echo ucfirst(strtolower($paste['visibility'])); ?></div>
                </div>

                <!-- Expires -->
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fa-regular fa-clock"></i>
                        Expires
                    </div>
                    <div class="stat-value"><?php echo $paste['expires_at'] ? date('d.m.Y H:i', strtotime($paste['expires_at'])) : 'Never'; ?></div>
                </div>

                <!-- Created -->
                <div class="stat-block">
                    <div class="stat-title">
                        <i class="fa-solid fa-calendar-days"></i>
                        Created
                    </div>
                    <div class="stat-value"><?php 
                        $created_time = strtotime($paste['created_at']);
                        $now = time();
                        $diff = $now - $created_time;
                        
                        if ($diff < 60) {
                            echo "just now";
                        } elseif ($diff < 3600) {
                            $mins = floor($diff / 60);
                            echo $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
                        } elseif ($diff < 86400) {
                            $hours = floor($diff / 3600);
                            echo $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
                        } elseif ($diff < 604800) {
                            $days = floor($diff / 86400);
                            echo $days . " day" . ($days > 1 ? "s" : "") . " ago";
                        } elseif ($diff < 2592000) {
                            $weeks = floor($diff / 604800);
                            echo $weeks . " week" . ($weeks > 1 ? "s" : "") . " ago";
                        } else {
                            echo date('F j, Y', $created_time);
                        }
                    ?></div>
                </div>
            </div>

            <!-- Hidden textarea for precise copy text -->
            <textarea id="hidden-paste-content" style="position:absolute; left:-9999px; top:-9999px; opacity:0; height:1px; width:1px;">
<?php echo $paste['content']; ?>
</textarea>

            <!-- Кнопки действий -->
            <div class="paste-actions <?php if (isLoggedIn() && $_SESSION['user_id'] == $paste['user_id']): ?>own-paste<?php endif; ?>">
                <?php if (isLoggedIn() && (canModeratePastes() || $_SESSION['user_id'] == $paste['user_id'])): ?>
                    <a href="edit.php?id=<?php echo $paste['id']; ?>" class="paste-button" style="background-color: #FF8C00;">
                        <i class="fa-solid fa-edit"></i>
                        Edit
                    </a>
                    <a href="delete.php?id=<?php echo $paste['id']; ?>" class="paste-button" style="background-color: #FF0000;" onclick="return confirm('Are you sure you want to delete this paste?');">
                        <i class="fa-solid fa-trash"></i>
                        Delete
                    </a>
                    <?php if (canPinPastes()): ?>
                        <?php $isPinned = isPastePinned($paste['id']); ?>
                        <button id="pin-button" class="paste-button" style="background-color: <?php echo $isPinned ? '#4B0082' : '#9400D3'; ?>;" onclick="togglePinPaste(<?php echo $paste['id']; ?>, '<?php echo $isPinned ? 'unpin' : 'pin'; ?>')">
                            <i class="fa-solid <?php echo $isPinned ? 'fa-thumbtack fa-rotate-90' : 'fa-thumbtack'; ?>"></i>
                            <?php echo $isPinned ? 'Unpin' : 'Pin'; ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Unified 2x2 action grid for everyone -->
                <div class="action-grid-2x2">
                    <button type="button" class="paste-button secondary accent-green" onclick="copyToClipboard('<?php echo htmlspecialchars(fullPasteUrl($paste['id'])); ?>', this)">
                        <i class="fa-solid fa-link"></i>
                        Copy URL
                    </button>
                    <a href="raw.php?id=<?php echo $paste['id']; ?>" class="paste-button secondary accent-green">
                        <i class="fa-solid fa-code"></i>
                        View Raw
                    </a>
                    <button type="button" class="paste-button secondary accent-green" onclick="copyPasteText(this)">
                        <i class="fa-regular fa-copy"></i>
                        Copy Text
                    </button>
                    <a href="download.php?id=<?php echo $paste['id']; ?>&filename=<?php echo htmlspecialchars($paste['title']); ?>" class="paste-button secondary accent-green">
                        <i class="fa-solid fa-download"></i>
                        Download
                    </a>
                </div>
            </div>

            <!-- Лайки -->
            <div class="paste-likes">
                <?php 
                $isOwnPaste = isLoggedIn() && isset($_SESSION['user_id']) && $paste['user_id'] == $_SESSION['user_id'];
                $hasLiked = $userVote === 'like';
                $hasDisliked = $userVote === 'dislike';
                
                if (!$isOwnPaste): 
                ?>
                    <button class="like-button <?php echo $hasLiked ? 'active' : ''; ?>" onclick="likePaste(<?php echo $paste['id']; ?>, 'up')">
                        <img src="assets/icons/thumbs-up-regular.svg" alt="Like">
                    </button>
                <?php else: ?>
                    <button class="like-button disabled" title="Вы не можете оценивать собственные посты" style="opacity: 0.5; cursor: not-allowed;">
                        <img src="assets/icons/thumbs-up-regular.svg" alt="Like">
                    </button>
                <?php endif; ?>
                
                <span class="likes-count"><?php 
                    // Calculate rating from paste_likes table only (same as top.php)
                    // Don't include anonymous session likes for consistency
                    $rating = ($paste['likes'] ?? 0) - ($paste['dislikes'] ?? 0);
                    echo $rating;
                ?></span>
                
                <?php if (!$isOwnPaste): ?>
                    <button class="like-button <?php echo $hasDisliked ? 'active' : ''; ?>" onclick="likePaste(<?php echo $paste['id']; ?>, 'down')">
                        <img src="assets/icons/thumbs-down-regular.svg" alt="Dislike">
                    </button>
                <?php else: ?>
                    <button class="like-button disabled" title="Вы не можете оценивать собственные посты" style="opacity: 0.5; cursor: not-allowed;">
                        <img src="assets/icons/thumbs-down-regular.svg" alt="Dislike">
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Password Modal (hidden by default) -->
<div id="passwordModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: #1d1e3a; padding: 30px; border-radius: 10px; max-width: 400px; width: 100%;">
        <h3 style="color: #00ff9d; margin-bottom: 20px; text-align: center;">This paste is private</h3>
        <form id="passwordForm" style="display: flex; flex-direction: column; gap: 15px;">
            <input type="password" name="password" placeholder="Enter password" required 
                   style="padding: 10px; background: #222; border: 1px solid #333; border-radius: 5px; color: white;">
            <input type="hidden" name="paste_id" value="<?php echo $paste['id']; ?>">
            <button type="submit" style="padding: 10px; background: #00ff9d; color: #111; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
                Access Paste
            </button>
        </form>
        <div id="passwordError" style="color: #ff3333; margin-top: 10px; text-align: center; display: none;"></div>
    </div>
</div>

        <!-- Правая колонка - содержимое пасты -->
<!-- Right column - paste content -->
<div class="paste-view-container" style="width: 100%; min-width: 1000px; max-width: 1000px; height: 60vh; overflow: hidden; display: flex; align-items: center; margin: 0;">
    <div class="editor-container" style="width: 100%; min-width: 1000px; max-width: 1000px; height: 100%; padding: 0; box-sizing: border-box; display: flex; flex-direction: column; border-radius: 8px; background: #222;">
        <?php if ($paste['visibility'] === 'private' && !isset($_SESSION['paste_access'][$paste['id']])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('passwordModal').style.display = 'flex';
                });
            </script>
            <div style="padding: 20px; text-align: center; font-size: 16px; color: #f1f1f1;">
                This paste is private. Please enter the password to view it.
            </div>
        <?php else: ?>
            <div class="paste-content" style="background: #1d1e3a; color: #f1f1f1; padding: 10px; border-radius: 8px; font-family: 'Source Code Pro', monospace; font-size: 14px; line-height: 1.6; display: flex; flex-direction: column; height: 100%; width: 100%; box-sizing: border-box; margin: 0;">
                <?php 
                if (!empty($paste['expires_at']) && strtotime($paste['expires_at']) <= time()) {
                    echo "<div style='padding: 20px; text-align: center; font-size: 16px; color: #f1f1f1;'>This paste has expired.</div>";
                } else {
                    // Only render content if not private or password is verified
                    // Image extensions for inline preview
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
                    
                    function makeClickableLinks($text, $imageExtensions) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Check if this line contains only an image URL (for larger display)
    $trimmedText = trim($text);
    $isImageOnly = false;
    foreach ($imageExtensions as $ext) {
        if (preg_match('/^https?:\/\/[^\s<>"]+\.' . $ext . '(\?.*)?$/i', $trimmedText)) {
            $isImageOnly = true;
            break;
        }
    }
    
    // If line is only an image URL, show URL text + large clickable image below
    if ($isImageOnly) {
        return '<a href="' . $trimmedText . '" target="_blank" rel="noopener noreferrer" style="color: #00ff9d; text-decoration: underline;">' . $trimmedText . '</a><br><a href="' . $trimmedText . '" target="_blank" rel="noopener noreferrer" style="display: block;"><img src="' . $trimmedText . '" alt="Image" style="max-width: 100%; max-height: 400px; border-radius: 8px; margin: 5px 0; cursor: pointer;" onerror="this.style.display=\'none\'"></a>';
    }

    // Replace image URLs with URL text + clickable inline thumbnail images
    foreach ($imageExtensions as $ext) {
        $text = preg_replace(
            '/(https?:\/\/[^\s<>"]+\.' . $ext . ')(\?[^\s<>"]*)?/i',
            '<a href="$1$2" target="_blank" rel="noopener noreferrer" style="color: #00ff9d; text-decoration: underline;">$1$2</a> <a href="$1$2" target="_blank" rel="noopener noreferrer" style="display: inline-block; vertical-align: middle;"><img src="$1$2" alt="Image" style="max-height: 80px; max-width: 150px; border-radius: 4px; margin: 2px; vertical-align: middle;" onerror="this.parentElement.style.display=\'none\'"></a>',
            $text
        );
    }

    // Match remaining full links with scheme (http/https)
    $text = preg_replace(
        '/\b(https?:\/\/[^\s<]+)/i',
        '<a href="$1" target="_blank" rel="noopener noreferrer" style="color: #00ff9d; text-decoration: underline;">$1</a>',
        $text
    );

    // Match scheme-less links like www.example.com
    $text = preg_replace(
        '/\b(www\.[^\s<]+)/i',
        '<a href="http://$1" target="_blank" rel="noopener noreferrer" style="color: #00ff9d; text-decoration: underline;">$1</a>',
        $text
    );

    return $text;
}


                    $lines = explode("\n", $paste['content']);
                    
                    // For syntax highlighting, we'll use both methods:
                    // 1. Raw text for CodeMirror (hidden)
                    // echo '<textarea id="code-editor" style="display: none;">' . htmlspecialchars($paste['content']) . '</textarea>';
                    
                    // 2. Formatted lines for display
                    foreach ($lines as $lineNumber => $lineContent) {
                        $processedContent = makeClickableLinks($lineContent, $imageExtensions);

                        echo '<div class="line-container" style="display: flex; gap: 10px; align-items: flex-start; font-size: 14px; line-height: 1.6; word-wrap: break-word; margin-bottom: 2px;">';
                        echo '<span class="line-number" style="color: #3a9e72; user-select: none; text-align: right; padding-right: 1px; flex-shrink: 0;">' . ($lineNumber + 1) . '</span>';
                        echo '<span class="line-content" style="flex-grow: 1; color: #dcdcdc;">' . nl2br($processedContent) . '</span>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
            
            <script>
            // Initialize CodeMirror only if paste is accessible
            // document.addEventListener('DOMContentLoaded', function() {
            //     var editor = CodeMirror.fromTextArea(document.getElementById("code-editor"), {
            //         lineNumbers: true,
            //         mode: '<?php echo htmlspecialchars($paste['syntax']); ?>',
            //         theme: "default",
            //         lineWrapping: true,
            //         viewportMargin: Infinity,
            //         tabSize: 4,
            //         indentUnit: 4,
            //         indentWithTabs: true,
            //         readOnly: true,
            //         gutters: ["CodeMirror-linenumbers"]
            //     });
            //     editor.setSize('100%', '100%');
                
            //     // // Hide the raw text lines when CodeMirror is initialized
            //     // document.querySelectorAll('.line-container').forEach(function(el) {
            //     //     el.style.display = 'none';
            //     // });
            // });
            </script>
        <?php endif; ?>
    </div>
</div>

    </div>
</div>

    </div>
</div>
<script>
// Copy helper with tiny feedback
function copyToClipboard(text, el){
    if (!navigator.clipboard) {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) { console.error('Copy failed', e); }
        document.body.removeChild(ta);
        flash(el);
        return;
    }
    navigator.clipboard.writeText(text).then(function(){ flash(el); }).catch(function(e){
        console.error('Clipboard API failed', e);
        // fallback
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(err) { console.error('execCommand copy failed', err); }
        document.body.removeChild(ta);
        flash(el);
    });
}
function flash(el){
    if(!el) return;
    const original = el.innerHTML;
    el.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
    setTimeout(function(){ el.innerHTML = original; }, 1200);
}
function copyPasteText(el){
    var hidden = document.getElementById('hidden-paste-content');
    if (!hidden) { return; }
    // Use value as-is to preserve raw content (no HTML decoding issues)
    var text = hidden.value || hidden.innerText || '';
    copyToClipboard(text, el);
}
document.addEventListener('DOMContentLoaded', function() {
    // Handle password form submission
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const errorElement = document.getElementById('passwordError');
        
        fetch('verify_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide modal and reload page to show content
                document.getElementById('passwordModal').style.display = 'none';
                window.location.reload();
            } else {
                // Show error message
                errorElement.textContent = data.message || 'Incorrect password';
                errorElement.style.display = 'block';
            }
        })
        .catch(error => {
            errorElement.textContent = 'An error occurred. Please try again.';
            errorElement.style.display = 'block';
        });
    });

    // Initialize CodeMirror if paste is accessible
    <?php if ($paste['visibility'] !== 'private' || isset($_SESSION['paste_access'][$paste['id']])): ?>
    var editor = CodeMirror.fromTextArea(document.getElementById("code-editor"), {
        lineNumbers: true,
        mode: '<?php echo $paste['syntax']; ?>',
        theme: "default",
        lineWrapping: false,
        viewportMargin: 50,
        tabSize: 4,
        indentUnit: 4,
        indentWithTabs: true,
        readOnly: true,
        gutters: ["CodeMirror-linenumbers"],
        maxHighlightLength: 10000,
        workDelay: 200
    });
    editor.setSize('100%', '100%');
    <?php endif; ?>
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var editor = CodeMirror.fromTextArea(document.getElementById("code-editor"), {
        lineNumbers: true,
        mode: detectLanguage('<?php echo addslashes($paste['syntax']); ?>'),
        theme: "default",
        lineWrapping: false,
        viewportMargin: 50,
        tabSize: 4,
        indentUnit: 4,
        indentWithTabs: true,
        readOnly: true,
        gutters: ["CodeMirror-linenumbers"],
        maxHighlightLength: 10000,
        workDelay: 200
    });

    // Set size after initialization
    editor.setSize(964, 524);

    // Функция определения языка программирования
    function detectLanguage(syntax) {
        var modeMap = {
            'javascript': 'javascript',
            'js': 'javascript',
            'php': 'php',
            'python': 'python',
            'py': 'python',
            'sql': 'sql',
            'xml': 'xml',
            'css': 'css',
            'shell': 'shell',
            'bash': 'shell',
            'html': 'htmlmixed',
            'clike': 'clike'
        };

        return modeMap[syntax.toLowerCase()] || 'text';
    }
    

});

function likePaste(pasteId, type) {
    console.log('Отправка запроса:', { pasteId, type }); // Отладка

    fetch('ajax/like_paste.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `paste_id=${pasteId}&type=${type}`
    })
    .then(response => {
        console.log('Получен ответ:', response); // Отладка
        return response.json();
    })
    .then(data => {
        console.log('Данные:', data); // Отладка
        if (data.success) {
            // Обновляем счетчик
            const likesCount = document.querySelector('.likes-count');
            const total = data.total;
            likesCount.textContent = total;
            
            // Update button active states
            const likeButtons = document.querySelectorAll('.paste-likes .like-button:not(.disabled)');
            const likeBtn = likeButtons[0]; // First button is like
            const dislikeBtn = likeButtons[1]; // Second button is dislike
            
            if (likeBtn && dislikeBtn) {
                if (type === 'up') {
                    // Toggle like button
                    if (likeBtn.classList.contains('active')) {
                        likeBtn.classList.remove('active');
                    } else {
                        likeBtn.classList.add('active');
                        dislikeBtn.classList.remove('active');
                    }
                } else {
                    // Toggle dislike button
                    if (dislikeBtn.classList.contains('active')) {
                        dislikeBtn.classList.remove('active');
                    } else {
                        dislikeBtn.classList.add('active');
                        likeBtn.classList.remove('active');
                    }
                }
            }
        } else {
            console.error('Ошибка:', data.error);
            alert(data.error || 'Произошла ошибка при обработке лайка');
        }
    })
    .catch(error => {
        console.error('Ошибка запроса:', error);
        alert('Произошла ошибка при отправке запроса');
    });
}

// Функция для закрепления/открепления пасты
function togglePinPaste(pasteId, action) {
    fetch('ajax/pin_paste.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `paste_id=${pasteId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Обновляем внешний вид кнопки
            const pinButton = document.getElementById('pin-button');
            if (action === 'pin') {
                pinButton.style.backgroundColor = '#4B0082';
                pinButton.innerHTML = '<i class="fa-solid fa-thumbtack fa-rotate-90"></i> Unpin';
                pinButton.onclick = function() { togglePinPaste(pasteId, 'unpin'); };
            } else {
                pinButton.style.backgroundColor = '#9400D3';
                pinButton.innerHTML = '<i class="fa-solid fa-thumbtack"></i> Pin';
                pinButton.onclick = function() { togglePinPaste(pasteId, 'pin'); };
            }
            
            // Показываем сообщение об успехе
            alert(data.message);
        } else {
            // Показываем сообщение об ошибке
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Ошибка:', error);
        alert('Произошла ошибка при выполнении операции');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
