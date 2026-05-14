<?php
// Проверяем, определены ли уже константы, чтобы избежать повторного подключения config.php
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Функции для работы с пользователями
 */
function createUser($username, $password, $email) {
    global $pdo;
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, created_at) VALUES (?, ?, ?, 'user', NOW())");
    return $stmt->execute([$username, $hash, $email]);
}

function getUserById($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    // Проверяем, есть ли у пользователя настройка default_visibility
    // Если нет, устанавливаем значение по умолчанию 'public'
    if ($user && !isset($user['default_visibility'])) {
        $user['default_visibility'] = 'public';
    }
    
    return $user;
}

function getUserByUsername($username) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

/**
 * Получает пользователя по email
 */
function getUserByEmail($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function loginUser($username, $password) {
    $user = getUserByUsername($username);
    
    if ($user && password_verify($password, $user['password'])) {
        // Проверяем, не забанен ли пользователь
        if (isset($user['is_banned']) && $user['is_banned'] == 1) {
            // Пользователь забанен, возвращаем false и устанавливаем сообщение об ошибке
            $banReason = !empty($user['ban_reason']) ? ': ' . $user['ban_reason'] : '';
            setFlashMessage('error', 'Your account has been banned' . $banReason);
            return false;
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        
        // Обновляем время последнего входа
        try {
            updateLastLogin($user['id']);
        } catch (Exception $e) {
            // Игнорируем ошибку обновления времени последнего входа
            // Это не должно мешать пользователю войти в систему
        }
        
        return true;
    }
    return false;
}

/**
 * Persistent login (Remember Me) helpers
 */

/**
 * Create a persistent session for the given user and set a secure cookie.
 * Returns true on success.
 * @param int $days Default 3650 (10 years) for "remember forever" functionality
 */
function createPersistentLogin(int $userId, int $days = 3650): bool {
    global $pdo;

    // Generate identifiers
    $persistentId = bin2hex(random_bytes(16));
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $expiresAt = (new DateTime("+{$days} days"))->format('Y-m-d H:i:s');

    // Store in existing sessions table, using data column as JSON
    $data = json_encode(['token_hash' => $tokenHash], JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare("INSERT INTO sessions (id, user_id, ip_address, user_agent, created_at, expires_at, data) VALUES (?, ?, ?, ?, NOW(), ?, ?)");
    $ok = $stmt->execute([$persistentId, $userId, $ip, $userAgent, $expiresAt, $data]);
    if (!$ok) {
        return false;
    }

    // Compose cookie value as id.token
    $cookieValue = $persistentId . ':' . $rawToken;

    // Set secure, httponly cookie for the site root
    $params = [
        'expires' => time() + 60 * 60 * 24 * $days,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie('remember_token', $cookieValue, $params);
    return true;
}

/**
 * Attempt auto-login from persistent cookie. Returns true if user logged in.
 */
function attemptAutoLoginFromCookie(): bool {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    if (empty($_COOKIE['remember_token'])) {
        return false;
    }

    global $pdo;
    $cookie = $_COOKIE['remember_token'];
    if (strpos($cookie, ':') === false) {
        return false;
    }
    [$persistentId, $rawToken] = explode(':', $cookie, 2);

    // Load session row
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$persistentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }

    // Verify token
    $data = json_decode($row['data'] ?? '{}', true) ?: [];
    $tokenHash = $data['token_hash'] ?? null;
    if (!$tokenHash || !password_verify($rawToken, $tokenHash)) {
        return false;
    }

    // Set session for the user
    $_SESSION['user_id'] = (int)$row['user_id'];
    // Load role and username for convenience
    $user = getUserById((int)$row['user_id']);
    if ($user) {
        $_SESSION['username'] = $user['username'] ?? null;
        $_SESSION['role'] = $user['role'] ?? null;
    }

    // Rotate token to mitigate replay
    rotatePersistentLogin($persistentId);
    return true;
}

/**
 * Rotate the token for an existing persistent session and update cookie.
 * @param int $days Default 3650 (10 years) for "remember forever" functionality
 */
function rotatePersistentLogin(string $persistentId, int $days = 3650): void {
    global $pdo;
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);
    $expiresAt = (new DateTime("+{$days} days"))->format('Y-m-d H:i:s');

    $data = json_encode(['token_hash' => $tokenHash], JSON_UNESCAPED_SLASHES);
    $stmt = $pdo->prepare("UPDATE sessions SET data = ?, expires_at = ? WHERE id = ?");
    $stmt->execute([$data, $expiresAt, $persistentId]);

    $cookieValue = $persistentId . ':' . $rawToken;
    $params = [
        'expires' => time() + 60 * 60 * 24 * $days,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    setcookie('remember_token', $cookieValue, $params);
}

/**
 * Clear persistent login cookie and DB entry if present.
 */
function clearPersistentLogin(): void {
    global $pdo;
    if (!empty($_COOKIE['remember_token']) && strpos($_COOKIE['remember_token'], ':') !== false) {
        [$persistentId] = explode(':', $_COOKIE['remember_token'], 2);
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->execute([$persistentId]);
    }

    // Clear cookie
    setcookie('remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Обновляет время последнего входа пользователя
 */
function updateLastLogin($userId) {
    global $pdo;
    
    // Проверяем, существует ли столбец last_login
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'");
        $columnExists = $stmt->rowCount() > 0;
        
        if ($columnExists) {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            return $stmt->execute([$userId]);
        }
        return false;
    } catch (Exception $e) {
        // Если возникла ошибка, просто возвращаем false
        return false;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Проверяет, является ли текущий пользователь администратором
 * Administrator и Developer имеют полный доступ к админке
 */
function isAdmin() {
    // Проверка старого формата роли admin
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Проверка новых ролей
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && isset($user['role'])) {
            // Developer и Administrator имеют полный доступ к админке
            return in_array($user['role'], ['developer', 'administrator']);
        }
    }
    
    return false;
}

/**
 * Проверяет, имеет ли текущий пользователь права на модерацию паст
 * Administrator, Staff и Developer могут редактировать/удалять пасты
 */
function canModeratePastes() {
    // Проверка старого формата роли admin
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    // Проверка новых ролей
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && isset($user['role'])) {
            // Administrator, Staff и Developer могут модерировать пасты
            return in_array($user['role'], ['developer', 'administrator', 'staff']);
        }
    }
    
    return false;
}

function logout() {
    session_destroy();
    session_start();
}

/**
 * Функции для работы с пастами
 */
function createPaste($title, $content, $userId = null, $visibility = 'public') {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO pastes (title, content, user_id, visibility, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    return $stmt->execute([$title, $content, $userId, $visibility]);
}

function getPasteById($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.emoji, u.name_color,
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
        FROM pastes p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.id = ?
    ");
    
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// function incrementViews($pasteId) {
//     global $pdo;
    
//     $stmt = $pdo->prepare("
//         UPDATE pastes 
//         SET views = views + 1 
//         WHERE id = ?
//     ");
    
//     return $stmt->execute([$pasteId]);
// }

function incrementViews($pasteId, $userId) {
    global $pdo;
    
    // Session-based view tracking to prevent abuse
    if (!isset($_SESSION['viewed_pastes'])) {
        $_SESSION['viewed_pastes'] = array();
    }
    
    // Check if this paste was already viewed in this session
    if (in_array($pasteId, $_SESSION['viewed_pastes'])) {
        return false; // Already counted in this session
    }
    
    // For logged-in users, also check database to prevent multiple views
    if ($userId) {
        $checkStmt = $pdo->prepare("
            SELECT 1 FROM viewers 
            WHERE paste_id = ? AND user_id = ?
        ");
        $checkStmt->execute([$pasteId, $userId]);
        
        if ($checkStmt->fetch() !== false) {
            return false; // Already viewed by this user
        }
    }
    
    // Additional IP-based rate limiting (prevent rapid view spam)
    $userIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $sessionKey = 'view_rate_limit_' . md5($userIP . $pasteId);
    
    if (isset($_SESSION[$sessionKey]) && (time() - $_SESSION[$sessionKey]) < 30) {
        return false; // Rate limited - must wait 30 seconds between views of same paste
    }
    
    try {
        $pdo->beginTransaction();
        
        // Increment view count
        $updateStmt = $pdo->prepare("
            UPDATE pastes 
            SET views = views + 1 
            WHERE id = ?
        ");
        $updateStmt->execute([$pasteId]);
        
        // Track logged-in user view
        if ($userId) {
            $insertStmt = $pdo->prepare("
                INSERT INTO viewers (paste_id, user_id)
                VALUES (?, ?)
            ");
            $insertStmt->execute([$pasteId, $userId]);
        }
        
        // Add to session tracking
        $_SESSION['viewed_pastes'][] = $pasteId;
        $_SESSION[$sessionKey] = time();
        
        $pdo->commit();
        
        // Check for automatic rank upgrade for paste owner AFTER transaction is complete
        $ownerStmt = $pdo->prepare("SELECT user_id FROM pastes WHERE id = ?");
        $ownerStmt->execute([$pasteId]);
        $pasteOwnerId = $ownerStmt->fetchColumn();
        
        if ($pasteOwnerId) {
            // Trigger rank check for paste owner (this will auto-upgrade if thresholds are met)
            getUserRank($pasteOwnerId);
        }
        
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in incrementViews: " . $e->getMessage());
        return false;
    }
}

function getUserPastes($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, 
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
        FROM pastes p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getRecentPastes($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.emoji, u.name_color,
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
        FROM pastes p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.visibility = 'public'
        ORDER BY p.created_at DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/*
* The old function which idk what type of sorting use to return data! so Skipping.
*/

// function getTopPastes($limit = 10) {
//     global $pdo;
    
//     $stmt = $pdo->prepare("
//         SELECT p.*, u.username, u.emoji, u.name_color,
//         COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
//         COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
//         FROM pastes p
//         LEFT JOIN users u ON p.user_id = u.id
//         WHERE p.visibility = 'public'
//         ORDER BY 
//             (SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like') DESC,
//             p.views DESC,
//             p.created_at DESC
//         LIMIT ?
//     ");
    
//     $stmt->execute([$limit]);
//     return $stmt->fetchAll();
// }

// The New function to return sorted data as most views on top

function getTopPastes($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.emoji, u.name_color,
               COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) AS likes,
               COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) AS dislikes
        FROM pastes p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.visibility = 'public'
        ORDER BY p.views DESC
        LIMIT ?
    ");
    
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}


function togglePasteLike($pasteId, $userId, $type) {
    global $pdo;
    
    // Проверяем, что пользователь не пытается лайкнуть свою собственную пасту
    $stmt = $pdo->prepare("SELECT user_id FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    $pasteOwnerId = $stmt->fetchColumn();
    
    if ($pasteOwnerId && $pasteOwnerId == $userId) {
        return false; // Пользователь не может лайкать свои собственные посты
    }
    
    // Определяем противоположный тип
    $oppositeType = $type === 'like' ? 'dislike' : 'like';
    
    // Проверяем существующий голос
    $stmt = $pdo->prepare("
        SELECT id, type FROM paste_likes 
        WHERE paste_id = ? AND user_id = ?
    ");
    $stmt->execute([$pasteId, $userId]);
    $existingVote = $stmt->fetch();
    
    // Начинаем транзакцию
    $pdo->beginTransaction();
    
    try {
        // Если есть противоположный голос - удаляем его
        $stmt = $pdo->prepare("
            DELETE FROM paste_likes 
            WHERE paste_id = ? AND user_id = ? AND type = ?
        ");
        $stmt->execute([$pasteId, $userId, $oppositeType]);
        
        if ($existingVote) {
            if ($existingVote['type'] === $type) {
                // Удаляем голос, если нажата та же кнопка
                $stmt = $pdo->prepare("
                    DELETE FROM paste_likes 
                    WHERE id = ?
                ");
                $stmt->execute([$existingVote['id']]);
            }
        } else {
            // Добавляем новый голос
            $stmt = $pdo->prepare("
                INSERT INTO paste_likes (paste_id, user_id, type)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$pasteId, $userId, $type]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in togglePasteLike: " . $e->getMessage());
        return false;
    }
}

function getUserVote($pasteId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT type 
        FROM paste_likes 
        WHERE paste_id = ? AND user_id = ?
    ");
    
    $stmt->execute([$pasteId, $userId]);
    return $stmt->fetchColumn();
}

function getAdPerformanceStats() {
    global $pdo;
    
    $stats = [];
    
    // Banner stats
    $stmt = $pdo->query("
        SELECT 
            SUM(views) as total_views,
            SUM(clicks) as total_clicks,
            SUM(CASE WHEN price IS NOT NULL THEN price ELSE 0 END) as total_earned,
            COUNT(*) as total_banners
        FROM banners
        WHERE active = 1
    ");
    $bannerStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate CTR
    $bannerStats['ctr'] = ($bannerStats['total_views'] > 0) 
        ? round(($bannerStats['total_clicks'] / $bannerStats['total_views']) * 100, 2)
        : 0;
    
    // Text banner stats
    $stmt = $pdo->query("
        SELECT 
            SUM(views) as total_views,
            SUM(clicks) as total_clicks,
            COUNT(*) as total_banners
        FROM banner_texts
        WHERE active = 1
    ");
    $textStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate CTR for text banners
    $textStats['ctr'] = ($textStats['total_views'] > 0) 
        ? round(($textStats['total_clicks'] / $textStats['total_views']) * 100, 2)
        : 0;
    
    // Views by day (last 30 days) - using your viewers table
    $stmt = $pdo->query("
        SELECT 
            DATE(timestamp) as day,
            COUNT(*) as paste_views
        FROM viewers
        WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(timestamp)
        ORDER BY day
    ");
    $viewsByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'banners' => $bannerStats,
        'text_banners' => $textStats,
        'views_by_day' => $viewsByDay
    ];
}

function getTopPerformingAds($limit = 5) {
    global $pdo;
    
    // Top banners by CTR
    $stmt = $pdo->prepare("
        SELECT id, url, image_path, views, clicks,
               ROUND((clicks/views)*100, 2) as ctr
        FROM banners
        WHERE views > 0
        ORDER BY ctr DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $topBanners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top text banners by CTR
    $stmt = $pdo->prepare("
        SELECT id, url, text as content, views, clicks,
               ROUND((clicks/views)*100, 2) as ctr
        FROM banner_texts
        WHERE views > 0
        ORDER BY ctr DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $topTextBanners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'top_banners' => $topBanners,
        'top_text_banners' => $topTextBanners
    ];
}

function incrementBannerClicks($id, $type = 'banner') {
    global $pdo;
    
    try {
        $table = ($type === 'banner') ? 'banners' : 'banner_texts';
        
        $stmt = $pdo->prepare("
            UPDATE {$table} 
            SET clicks = IFNULL(clicks, 0) + 1 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error incrementing {$type} clicks: " . $e->getMessage());
        return false;
    }
}

/**
 * Функции для работы с баннерами
 */
function getRandomBanners($limit = 2) {
    global $pdo;
    
    $currentDateTime = date('Y-m-d H:i:s');
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM banners 
            WHERE active = 1 
            AND (expires_at IS NULL OR expires_at > ?)
            ORDER BY RAND() 
            LIMIT ?
        ");
        $stmt->execute([$currentDateTime, $limit]);
        $banners = $stmt->fetchAll();
        
        foreach ($banners as $banner) {
            $updateStmt = $pdo->prepare("
                UPDATE banners 
                SET views = IFNULL(views, 0) + 1 
                WHERE id = ?
            ");
            $updateStmt->execute([$banner['id']]);
            
            error_log("Updating views for banner ID: " . $banner['id'] . 
                     ", Rows affected: " . $updateStmt->rowCount());
        }
        
        return $banners;
    } catch (PDOException $e) {
        error_log("Error in getRandomBanners: " . $e->getMessage());
        return [];
    }
}

function getRandomBannerTexts($limit = 2) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM banner_texts 
            WHERE active = 1 
            ORDER BY RAND() 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $bannerTexts = $stmt->fetchAll();
        // Normalize legacy-encoded rows: decode once so output escaping doesn't double-encode
        foreach ($bannerTexts as &$bt) {
            if (isset($bt['text'])) {
                $bt['text'] = html_entity_decode($bt['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (isset($bt['url'])) {
                $bt['url'] = html_entity_decode($bt['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            if (isset($bt['style'])) {
                $bt['style'] = html_entity_decode($bt['style'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        
        foreach ($bannerTexts as $bannerText) {
            $updateStmt = $pdo->prepare("
                UPDATE banner_texts 
                SET views = IFNULL(views, 0) + 1 
                WHERE id = ?
            ");
            $updateStmt->execute([$bannerText['id']]);
            
            error_log("Updating views for banner_text ID: " . $bannerText['id'] . 
                     ", Rows affected: " . $updateStmt->rowCount());
        }
        
        return $bannerTexts;
    } catch (PDOException $e) {
        error_log("Error in getRandomBannerTexts: " . $e->getMessage());
        return [];
    }
}

/**
 * Pinned banner/text helpers (file-based, no schema changes)
 * Now supports multiple pinned banners and texts
 */
function getPinnedIds(): array {
    $file = __DIR__ . '/../data/pinned_ads.json';
    if (!file_exists($file)) {
        return ['banner_ids' => [], 'text_ids' => []];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['banner_ids' => [], 'text_ids' => []];
    }
    
    // Backward compatibility: convert single id to array
    $bannerIds = [];
    $textIds = [];
    
    if (isset($data['banner_ids']) && is_array($data['banner_ids'])) {
        $bannerIds = $data['banner_ids'];
    } elseif (isset($data['banner_id']) && $data['banner_id']) {
        $bannerIds = [$data['banner_id']];
    }
    
    if (isset($data['text_ids']) && is_array($data['text_ids'])) {
        $textIds = $data['text_ids'];
    } elseif (isset($data['text_id']) && $data['text_id']) {
        $textIds = [$data['text_id']];
    }
    
    return [
        'banner_ids' => $bannerIds,
        'text_ids' => $textIds,
    ];
}

function setPinnedBanner(?int $bannerId, bool $toggle = true): bool {
    $current = getPinnedIds();
    
    if ($bannerId === null) {
        // Clear all pinned banners
        $current['banner_ids'] = [];
    } elseif ($toggle) {
        // Toggle: if already pinned, remove; otherwise add
        $key = array_search($bannerId, $current['banner_ids']);
        if ($key !== false) {
            unset($current['banner_ids'][$key]);
            $current['banner_ids'] = array_values($current['banner_ids']); // Re-index
        } else {
            $current['banner_ids'][] = $bannerId;
        }
    } else {
        // Just add if not exists
        if (!in_array($bannerId, $current['banner_ids'])) {
            $current['banner_ids'][] = $bannerId;
        }
    }
    
    return savePinned($current);
}

function setPinnedText(?int $textId, bool $toggle = true): bool {
    $current = getPinnedIds();
    
    if ($textId === null) {
        // Clear all pinned texts
        $current['text_ids'] = [];
    } elseif ($toggle) {
        // Toggle: if already pinned, remove; otherwise add
        $key = array_search($textId, $current['text_ids']);
        if ($key !== false) {
            unset($current['text_ids'][$key]);
            $current['text_ids'] = array_values($current['text_ids']); // Re-index
        } else {
            $current['text_ids'][] = $textId;
        }
    } else {
        // Just add if not exists
        if (!in_array($textId, $current['text_ids'])) {
            $current['text_ids'][] = $textId;
        }
    }
    
    return savePinned($current);
}

function isBannerPinned(int $bannerId): bool {
    $ids = getPinnedIds();
    return in_array($bannerId, $ids['banner_ids']);
}

function isTextPinned(int $textId): bool {
    $ids = getPinnedIds();
    return in_array($textId, $ids['text_ids']);
}

function savePinned(array $data): bool {
    $file = __DIR__ . '/../data/pinned_ads.json';
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $json = json_encode([
        'banner_ids' => $data['banner_ids'] ?? [],
        'text_ids' => $data['text_ids'] ?? [],
        'updated_at' => date('c'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($file, $json) !== false;
}

function getPinnedBanner(): ?array {
    // Backward compatibility: returns first pinned banner or null
    $banners = getPinnedBanners();
    return !empty($banners) ? $banners[0] : null;
}

function getPinnedBanners(): array {
    $ids = getPinnedIds();
    if (empty($ids['banner_ids'])) return [];
    global $pdo;
    
    $placeholders = implode(',', array_fill(0, count($ids['banner_ids']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM banners WHERE id IN ($placeholders) AND active = 1");
    $stmt->execute($ids['banner_ids']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Maintain the order from pinned_ads.json
    $ordered = [];
    foreach ($ids['banner_ids'] as $id) {
        foreach ($rows as $row) {
            if ((int)$row['id'] === (int)$id) {
                $ordered[] = $row;
                break;
            }
        }
    }
    return $ordered;
}

function getPinnedText(): ?array {
    // Backward compatibility: returns first pinned text or null
    $texts = getPinnedTexts();
    return !empty($texts) ? $texts[0] : null;
}

function getPinnedTexts(): array {
    $ids = getPinnedIds();
    if (empty($ids['text_ids'])) return [];
    global $pdo;
    
    $placeholders = implode(',', array_fill(0, count($ids['text_ids']), '?'));
    $stmt = $pdo->prepare("SELECT * FROM banner_texts WHERE id IN ($placeholders) AND active = 1");
    $stmt->execute($ids['text_ids']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalize legacy encoding and maintain order
    $ordered = [];
    foreach ($ids['text_ids'] as $id) {
        foreach ($rows as $row) {
            if ((int)$row['id'] === (int)$id) {
                // Normalize legacy encoding
                foreach (['text','url','style'] as $k) {
                    if (isset($row[$k])) {
                        $row[$k] = html_entity_decode($row[$k], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
                $ordered[] = $row;
                break;
            }
        }
    }
    return $ordered;
}

function validateBannerImage($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file';
        return $errors;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        $errors[] = 'Only JPG, PNG and GIF images are allowed';
    }
    
    if ($file['type'] == 'image/gif') {
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'GIF file size should not exceed 10MB';
        }
    } else {
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'File size should not exceed 2MB';
        }
    }
    
    // Убираем проверку размеров, разрешаем любые размеры
    // Check image dimensions
    // $imageInfo = getimagesize($file['tmp_name']);
    // if ($imageInfo[0] !== 440 || $imageInfo[1] !== 111) {
    //     $errors[] = 'Image dimensions must be 440x111 pixels';
    // }
    
    return $errors;
}

// function addBanner($imagePath, $url, $isExternal = 0) {
//     global $pdo;
    
//     $stmt = $pdo->prepare("
//         INSERT INTO banners (image_path, url, is_external, active, created_at) 
//         VALUES (?, ?, ?, 1, NOW())
//     ");
//     return $stmt->execute([$imagePath, $url, $isExternal]);
// }

function addBanner($imagePath, $url, $isExternal = 0, $buyerUsername = null, $expiresAt = null, $extraInfo = null, $expiresChoice = null, $price = 0.00) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO banners (
                image_path, 
                url, 
                is_external, 
                active, 
                created_at,
                expires_at,
                buyer_username,
                extra_info,
                expires_choice,
                price
            ) VALUES (?, ?, ?, 1, NOW(), ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $imagePath,
            $url,
            $isExternal,
            $expiresAt,
            $buyerUsername,
            $extraInfo,
            $expiresChoice,
            $price
        ]);
    } catch (PDOException $e) {
        error_log("Database error adding banner: " . $e->getMessage());
        return false;
    }
}

// function updateBanner($id, $url, $active, $imagePath = null, $isExternal = null) {
//     global $pdo;
    
//     // If image path is provided, update it too
//     if ($imagePath !== null && $isExternal !== null) {
//         $stmt = $pdo->prepare("
//             UPDATE banners 
//             SET url = ?, active = ?, image_path = ?, is_external = ?
//             WHERE id = ?
//         ");
//         return $stmt->execute([$url, $active, $imagePath, $isExternal, $id]);
//     } else {
//         $stmt = $pdo->prepare("
//             UPDATE banners 
//             SET url = ?, active = ?
//             WHERE id = ?
//         ");
//         return $stmt->execute([$url, $active, $id]);
//     }
// }
function updateBanner($id, $url, $active, $imagePath = null, $isExternal = null, $buyerUsername = null, $expiresAt = null, $extraInfo = null, $expiresChoice = null, $price = null) {
    global $pdo;
    
    try {
        $query = "UPDATE banners SET url = ?, active = ?";
        $params = [$url, $active];
        
        if ($imagePath !== null && $isExternal !== null) {
            $query .= ", image_path = ?, is_external = ?";
            array_push($params, $imagePath, $isExternal);
        }
        
        $query .= ", buyer_username = ?, expires_at = ?, extra_info = ?, expires_choice = ?, price = ?, updated_at = NOW()";
        array_push($params, $buyerUsername, $expiresAt, $extraInfo, $expiresChoice, $price);
        
        $query .= " WHERE id = ?";
        array_push($params, $id);
        
        $stmt = $pdo->prepare($query);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating banner: " . $e->getMessage());
        return false;
    }
}

function deleteBanner($id) {
    global $pdo;
    
    // Получаем путь к файлу
    $stmt = $pdo->prepare("SELECT image_path FROM banners WHERE id = ?");
    $stmt->execute([$id]);
    $banner = $stmt->fetch();
    
    if ($banner && file_exists('../' . $banner['image_path'])) {
        unlink('../' . $banner['image_path']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM banners WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Функции для работы с рекламными текстами
 */
function addBannerText($text, $url, $style = '') {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO banner_texts (text, url, style, active, created_at) 
        VALUES (?, ?, ?, 1, NOW())
    ");
    return $stmt->execute([$text, $url, $style]);
}

function updateBannerText($id, $text, $url, $style, $active) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE banner_texts 
        SET text = ?, url = ?, style = ?, active = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    return $stmt->execute([$text, $url, $style, $active, $id]);
}

function deleteBannerText($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM banner_texts WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Функции для работы со статистикой
 */
function getBannerStats() {
    global $pdo;
    
    $stats = [];
    
    // Общее количество активных баннеров
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM banners WHERE active = 1");
    $stats['active_banners'] = $stmt->fetch()['count'];
    
    // Общее количество активных текстов
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM banner_texts WHERE active = 1");
    $stats['active_texts'] = $stmt->fetch()['count'];
    
    return $stats;
}

function getUserStats() {
    global $pdo;
    
    $stats = [];
    
    // Общее количество пользователей
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Пользователи за последние 24 часа
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['new_users_24h'] = $stmt->fetch()['count'];
    
    // Топ 5 пользователей по количеству паст
    $stmt = $pdo->query("
        SELECT u.username, u.emoji, u.name_color, COUNT(p.id) as paste_count 
        FROM users u 
        LEFT JOIN pastes p ON u.id = p.user_id 
        WHERE u.role != 'admin' 
        GROUP BY u.id 
        ORDER BY paste_count DESC 
        LIMIT 5
    ");
    $stats['top_users'] = $stmt->fetchAll();
    
    return $stats;
}

function getPasteStats() {
    global $pdo;
    
    $stats = [];
    
    // Общее количество паст
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pastes");
    $stats['total_pastes'] = $stmt->fetch()['count'];
    
    // Пасты за последние 24 часа
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM pastes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['new_pastes_24h'] = $stmt->fetch()['count'];
    
    // Топ 5 паст по просмотрам
    $stmt = $pdo->query("
        SELECT p.title, p.views, p.rating, u.username, u.emoji, u.name_color 
        FROM pastes p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.visibility = 'public' 
        ORDER BY p.views DESC 
        LIMIT 5
    ");
    $stats['top_viewed'] = $stmt->fetchAll();
    
    // Топ 5 паст по рейтингу
    $stmt = $pdo->query("
        SELECT p.title, p.views, p.rating, u.username, u.emoji, u.name_color 
        FROM pastes p 
        LEFT JOIN users u ON p.user_id = u.id 
        WHERE p.visibility = 'public' 
        ORDER BY p.rating DESC 
        LIMIT 5
    ");
    $stats['top_rated'] = $stmt->fetchAll();
    
    // Статистика по синтаксису
    $stmt = $pdo->query("
        SELECT syntax, COUNT(*) as count 
        FROM pastes 
        GROUP BY syntax 
        ORDER BY count DESC
    ");
    $stats['syntax_stats'] = $stmt->fetchAll();
    
    return $stats;
}

/**
 * Функции для работы с настройками сайта
 */
function getSiteSettings() {
    global $pdo;
    
    $settings = [
        'site_name' => SITE_NAME,
        'site_url' => SITE_URL,
        'admin_email' => ADMIN_EMAIL,
        'max_paste_size' => 1048576, // 1MB по умолчанию
        'max_title_length' => 50,
        'default_paste_expire' => 0, // никогда не истекает
        'default_paste_visibility' => 'public',
        'enable_registration' => true,
        'enable_password_reset' => true,
        'enable_paste_rating' => true
    ];
    
    return $settings;
}

/**
 * Обновляет настройки сайта в config.php
 */
function updateSiteSettings($settings) {
    $configFile = __DIR__ . '/config.php';
    
    if (!file_exists($configFile) || !is_writable($configFile)) {
        return false;
    }
    
    $configContent = file_get_contents($configFile);
    
    // Обновляем значения констант
    if (isset($settings['site_name'])) {
        $configContent = preg_replace(
            '/define\s*\(\s*[\'"]SITE_NAME[\'"]\s*,\s*[\'"].*?[\'"]\s*\)\s*;/i',
            'define(\'SITE_NAME\', \'' . addslashes($settings['site_name']) . '\');',
            $configContent
        );
    }
    
    if (isset($settings['site_url'])) {
        $configContent = preg_replace(
            '/define\s*\(\s*[\'"]SITE_URL[\'"]\s*,\s*[\'"].*?[\'"]\s*\)\s*;/i',
            'define(\'SITE_URL\', \'' . addslashes($settings['site_url']) . '\');',
            $configContent
        );
    }
    
    if (isset($settings['admin_email'])) {
        $configContent = preg_replace(
            '/define\s*\(\s*[\'"]ADMIN_EMAIL[\'"]\s*,\s*[\'"].*?[\'"]\s*\)\s*;/i',
            'define(\'ADMIN_EMAIL\', \'' . addslashes($settings['admin_email']) . '\');',
            $configContent
        );
    }
    
    // Записываем обновленное содержимое обратно в файл
    return file_put_contents($configFile, $configContent) !== false;
}

/**
 * Функции безопасности
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function checkRateLimit($key, $limit = 5, $period = 60) {
    $current = time();
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = [];
    }
    
    // Очистка старых записей
    $_SESSION['rate_limits'][$key] = array_filter(
        $_SESSION['rate_limits'][$key],
        function($timestamp) use ($current, $period) {
            return $timestamp > ($current - $period);
        }
    );
    
    // Проверка лимита
    if (count($_SESSION['rate_limits'][$key]) >= $limit) {
        return false;
    }
    
    // Добавление новой записи
    $_SESSION['rate_limits'][$key][] = $current;
    return true;
}

/**
 * Функции для работы с уведомлениями
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

function hasFlashMessage() {
    return !empty($_SESSION['flash_messages']);
}

function getFlashMessage() {
    if (!hasFlashMessage()) {
        return null;
    }
    $message = $_SESSION['flash_messages'][0];
    array_shift($_SESSION['flash_messages']);
    if (empty($_SESSION['flash_messages'])) {
        unset($_SESSION['flash_messages']);
    }
    return $message;
}

/**
 * Вспомогательные функции
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
}

function sanitizeInput($data) {
    // Store raw values (trimmed) and escape ONLY on output.
    // This prevents double-encoding like &amp; showing for banner texts.
    return is_string($data) ? trim($data) : $data;
}

function formatTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' ' . ($minutes == 1 ? 'minute' : 'minutes') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
    } elseif ($diff < 604800) { // 7 дней
        $days = floor($diff / 86400);
        return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
    } elseif ($diff < 2592000) { // 30 дней
        $weeks = floor($diff / 604800);
        return $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks') . ' ago';
    } else {
        $months = floor($diff / 2592000);
        return $months . ' ' . ($months == 1 ? 'month' : 'months') . ' ago';
    }
}

function formatDate($date) {
    if (!$date) return 'Никогда';
    
    $timestamp = strtotime($date);
    return date('d.m.Y H:i', $timestamp);
}

/**
 * Функции для работы с рейтингами пастов
 */
function getUserPasteRating($pasteId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT rating FROM paste_ratings WHERE paste_id = ? AND user_id = ?");
    $stmt->execute([$pasteId, $userId]);
    $result = $stmt->fetch();
    return $result ? $result['rating'] : null;
}

function getPasteRatingStats($pasteId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as likes,
            SUM(CASE WHEN rating = -1 THEN 1 ELSE 0 END) as dislikes
        FROM paste_ratings 
        WHERE paste_id = ?
    ");
    $stmt->execute([$pasteId]);
    return $stmt->fetch();
}

function ratePaste($pasteId, $userId, $rating) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Проверяем существующий рейтинг
        $currentRating = getUserPasteRating($pasteId, $userId);
        
        if ($currentRating === null) {
            // Добавляем новый рейтинг
            $stmt = $pdo->prepare("
                INSERT INTO paste_ratings (paste_id, user_id, rating) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$pasteId, $userId, $rating]);
            $ratingChange = $rating;
        } elseif ($currentRating == $rating) {
            // Удаляем рейтинг, если пользователь нажал на ту же кнопку
            $stmt = $pdo->prepare("
                DELETE FROM paste_ratings 
                WHERE paste_id = ? AND user_id = ?
            ");
            $stmt->execute([$pasteId, $userId]);
            $ratingChange = -$rating;
        } else {
            // Обновляем существующий рейтинг
            $stmt = $pdo->prepare("
                UPDATE paste_ratings 
                SET rating = ? 
                WHERE paste_id = ? AND user_id = ?
            ");
            $stmt->execute([$rating, $pasteId, $userId]);
            $ratingChange = $rating * 2; // умножаем на 2, так как меняем с -1 на 1 или наоборот
        }
        
        // Обновляем общий рейтинг паста
        $stmt = $pdo->prepare("
            UPDATE pastes 
            SET rating = rating + ? 
            WHERE id = ?
        ");
        $stmt->execute([$ratingChange, $pasteId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Функции для работы с лайками
 */

/**
 * Проверяет, поставил ли пользователь лайк/дизлайк пасту
 */
function hasUserLiked($paste_id, $type) {
    // Для анонимных пользователей проверяем сессию
    if (!isLoggedIn()) {
        if (!isset($_SESSION['anonymous_likes'])) {
            return false;
        }
        
        $key = $paste_id . '_' . $type;
        return isset($_SESSION['anonymous_likes'][$key]);
    }
    
    // Для авторизованных пользователей проверяем базу данных
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM paste_likes 
        WHERE paste_id = ? AND user_id = ? AND type = ?
    ");
    $stmt->execute([$paste_id, $_SESSION['user_id'], $type]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Получает количество лайков/дизлайков для пасты
 */
function getLikeCount($paste_id, $type) {
    global $pdo;
    
    // Преобразуем тип для базы данных
    $db_type = ($type === 'up' || $type === 'like') ? 'like' : 'dislike';
    
    // Получаем количество лайков из базы данных
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM paste_likes 
        WHERE paste_id = ? AND type = ?
    ");
    $stmt->execute([$paste_id, $db_type]);
    $db_count = $stmt->fetchColumn();
    
    // Добавляем анонимные лайки из сессии
    $anon_count = 0;
    if (isset($_SESSION['anonymous_likes'])) {
        foreach ($_SESSION['anonymous_likes'] as $key => $value) {
            list($pid, $vote_type) = explode('_', $key);
            if ($pid == $paste_id && $vote_type == $db_type) {
                $anon_count++;
            }
        }
    }
    
    return $db_count + $anon_count;
}

/**
 * Определяет ранг пользователя на основе общего количества просмотров его паст
 * [vip] - 10,000 просмотров
 * [vip+] - 25,000 просмотров
 * [⭐️] - 100,000 просмотров (ранее titanium)
 * 
 * Административные роли:
 * Administrator - красный цвет имени пользователя и эмодзи короны
 * Staff - оранжевый цвет имени пользователя и эмодзи trusted, префикс [ STAFF ]
 * Developer - фиолетовый цвет имени пользователя и эмодзи trusted, префикс [ DEV ]
 */
function getUserRank($userId) {
    global $pdo;
    
    // Получаем общее количество просмотров паст пользователя
    $stmt = $pdo->prepare("SELECT SUM(views) as total_views FROM pastes WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalViews = $result['total_views'] ?? 0;
    
    // Проверяем, существует ли столбец rank в таблице users
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'rank'");
        $columnExists = $stmt->rowCount() > 0;
        
        if (!$columnExists) {
            // Если столбца нет, добавляем его
            $pdo->exec("ALTER TABLE users ADD COLUMN `rank` VARCHAR(20) DEFAULT NULL");
        }
        
        // Проверяем, существует ли столбец role в таблице users
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        $roleColumnExists = $stmt->rowCount() > 0;
        
        if (!$roleColumnExists) {
            // Если столбца нет, добавляем его
            $pdo->exec("ALTER TABLE users ADD COLUMN `role` VARCHAR(50) DEFAULT NULL");
        } else {
            // Если столбец существует, но слишком маленький, изменяем его размер
            $pdo->exec("ALTER TABLE users MODIFY COLUMN `role` VARCHAR(50)");
        }
        
        // Получаем сохраненный ранг и роль пользователя
        $stmt = $pdo->prepare("SELECT `rank`, `role` FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $savedRank = $user['rank'] ?? null;
        $userRole = $user['role'] ?? null;
    } catch (Exception $e) {
        // Если возникла ошибка, предполагаем, что столбца нет
        $savedRank = null;
        $userRole = null;
    }
    
    // CSS для фиолетово-розового градиента
    $purpleGradientCss = '';
    
    // CSS для золотого градиента
    $goldGradientCss = '';
    
    // CSS для бриллиантового градиента
    $diamondGradientCss = '';
    
    // Определяем ранг на основе просмотров
    $rank = null;
    $rankHtml = '';
    $usernameClass = '';
    $usernameStyle = '';
    $usernamePrefix = '';
    $usernameSuffix = '';
    
    // Проверяем административные роли (overrides view-based)
    if ($userRole === 'administrator' || $userRole === 'admin') {
        $rank = 'administrator';
        $usernameStyle = 'color: #FF0000; font-weight: bold;';
        $usernameSuffix = '<img src="' . SITE_URL . '/Items/crown.gif" alt="Crown" style="height: 18px; vertical-align: middle; display: inline-block; margin-bottom: 6px;">';
    } elseif ($userRole === 'staff') {
        $rank = 'staff';
        $usernameStyle = 'color: #FF8C00; font-weight: bold;';
        $usernamePrefix = '<span style="color: #FF8C00; font-weight: bold; display: inline-block; margin-right: 5px;">[ STAFF ]</span>';
        $usernameSuffix = '<img src="' . SITE_URL . '/Items/trusted.webp" alt="Trusted" style="height: 16px; vertical-align: middle; display: inline-block; margin-bottom: 5px;">';
    } elseif ($userRole === 'developer') {
        $rank = 'developer';
        $usernameStyle = $diamondGradientCss; // keep gradient via inline style
        $usernamePrefix = '<span style="' . $diamondGradientCss . '; display: inline-block; margin-right: 5px;">&lt;DEV&gt;</span>';
        $usernameSuffix = '<img src="' . SITE_URL . '/Items/trusted.webp" alt="Trusted" style="height: 16px; vertical-align: middle; display: inline-block; margin-bottom: 5px;">';
    } else {
        // compute current rank purely by views thresholds
        if ($totalViews >= 100000) {
            $rank = 'star';
            $usernameClass = 'diamond_rank_pto';
            $usernamePrefix = '[ ⭐️ ]';
        } elseif ($totalViews >= 25000) {
            $rank = 'vip+';
            $usernameClass = 'kings_rank';
            $usernamePrefix = '[ VIP+ ]';
        } elseif ($totalViews >= 10000) {
            $rank = 'vip';
            $usernameClass = 'heaven_rank';
            $usernamePrefix = '[ VIP ]';
        } else {
            // fallback to saved rank visuals if any (legacy), but keep as prefix
            if ($savedRank === 'star') {
                $rank = 'star';
                $usernameClass = 'diamond_rank_pto';
                $usernamePrefix = '[ ⭐️ ]';
            } elseif ($savedRank === 'vip+') {
                $rank = 'vip+';
                $usernameClass = 'kings_rank';
                $usernamePrefix = '[ VIP+ ]';
            } elseif ($savedRank === 'vip') {
                $rank = 'vip';
                $usernameClass = 'heaven_rank';
                $usernamePrefix = '[ VIP ]';
            }
        }
    }
    
    // Если ранг изменился, обновляем его в базе данных
    if ($rank !== null && $rank !== $savedRank) {
        try {
            // Проверяем, существует ли столбец rank
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'rank'");
            $columnExists = $stmt->rowCount() > 0;
            
            if (!$columnExists) {
                // Если столбца нет, добавляем его
                $pdo->exec("ALTER TABLE users ADD COLUMN `rank` VARCHAR(20) DEFAULT NULL");
            }
            
            // Обновляем ранг пользователя
            $stmt = $pdo->prepare("UPDATE users SET `rank` = ? WHERE id = ?");
            $stmt->execute([$rank, $userId]);
        } catch (Exception $e) {
            // Игнорируем ошибку, если не удалось обновить ранг
        }
    }
    
    return [
        'rank' => $rank,
        'html' => $rankHtml,
        'total_views' => $totalViews,
        'username_class' => $usernameClass,
        'username_style' => $usernameStyle,
        'username_prefix' => $usernamePrefix,
        // Return raw suffix (no wrapper) so pages can wrap prefix+username+suffix in a single styled span
        // This ensures tags inherit the same text effects (glow/gradient) as the username
        'username_suffix' => $usernameSuffix
    ];
}
function getNameColor($uid) {
    global $pdo;
    $uid = (int)$uid;
    $stmt = $pdo->prepare("SELECT name_color FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['name_color'] : null;
}


/**
 * Функция для отображения эмодзи пользователя
 * Преобразует имя файла эмодзи в HTML-код для отображения
 */
function displayUserEmoji($emoji) {
    if (empty($emoji)) {
        return '';
    }
    
    // Проверяем, является ли эмодзи уже HTML-кодом (для обратной совместимости)
    if (strpos($emoji, '<img') === 0) {
        return $emoji;
    }
    
    // Проверяем, существует ли файл эмодзи
    $emojiPath = __DIR__ . '/../Items/' . $emoji;
    if (!file_exists($emojiPath)) {
        // Если файл не существует, проверяем, может быть отсутствует расширение
        if (strpos($emoji, '.') === false) {
            // Пробуем добавить расширение .gif
            $emojiWithExt = $emoji . '.gif';
            if (file_exists(__DIR__ . '/../Items/' . $emojiWithExt)) {
                $emoji = $emojiWithExt;
            } else {
                // Если файл все равно не найден, возвращаем стандартную эмодзи
                return '<i class="fa-solid fa-user" style="color: #00ff9d; height: 16px; vertical-align: middle; display: inline-block;"></i>';
            }
        } else {
            // Если файл с указанным расширением не найден, возвращаем стандартную эмодзи
            return '<i class="fa-solid fa-user" style="color: #00ff9d; height: 16px; vertical-align: middle; display: inline-block;"></i>';
        }
    }
    
    // Формируем HTML-код для отображения эмодзи
    return '<img src="' . SITE_URL . '/Items/' . $emoji . '" alt="Emoji" style="height: 18px; vertical-align: middle; display: inline-block;">';
}

/**
 * Проверяет, является ли пользователь staff
 */
function isStaff() {
    // Проверка новых ролей
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && isset($user['role'])) {
            return $user['role'] === 'staff';
        }
    }
    
    return false;
}

/**
 * Функция для определения высоты блока pasteattributes
 */
function getPasteAttributesHeight($pasteUserId) {
    // Безопасное значение по умолчанию
    $height = '550px';
    
    try {
        // Если пользователь не авторизован или паста не имеет владельца
        if (!isLoggedIn() || empty($pasteUserId)) {
            return $height;
        }
        
        // Если паста принадлежит текущему пользователю
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $pasteUserId) {
            $height = '770px';
        }
        // Если пользователь - администратор, разработчик или staff
        else if (isAdmin() || isStaff()) {
            $height = '870px'; // Увеличено на 100px для администраторов
        }
    } catch (Exception $e) {
        // В случае ошибки возвращаем значение по умолчанию
        error_log('Ошибка в getPasteAttributesHeight: ' . $e->getMessage());
    }
    
    return $height;
}

/**
 * Функции для работы с закрепленными пастами
 */

// Закрепляет пасту (максимум 3 пасты могут быть закреплены)
function pinPaste($pasteId) {
    global $pdo;
    
    // Проверяем, не закреплена ли уже паста
    $stmt = $pdo->prepare("SELECT is_pinned FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    $paste = $stmt->fetch();
    
    if (!$paste) {
        return [
            'success' => false,
            'message' => 'Paste not found'
        ];
    }
    
    if ($paste['is_pinned']) {
        return [
            'success' => false,
            'message' => 'Paste is already pinned'
        ];
    }
    
    // Проверяем текущее количество закрепленных паст
    $stmt = $pdo->query("SELECT COUNT(*) FROM pastes WHERE is_pinned = 1");
    $pinnedCount = $stmt->fetchColumn();
    
    if ($pinnedCount >= 3) {
        return [
            'success' => false,
            'message' => 'Maximum number of pinned pastes reached (3)'
        ];
    }
    
    // Закрепляем пасту
    $stmt = $pdo->prepare("UPDATE pastes SET is_pinned = 1 WHERE id = ?");
    $success = $stmt->execute([$pasteId]);
    
    return [
        'success' => $success,
        'message' => $success ? 'Paste pinned successfully' : 'Error pinning paste'
    ];
}

// Открепляет пасту
function unpinPaste($pasteId) {
    global $pdo;
    
    // Проверяем, закреплена ли паста
    $stmt = $pdo->prepare("SELECT is_pinned FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    $paste = $stmt->fetch();
    
    if (!$paste) {
        return [
            'success' => false,
            'message' => 'Paste not found'
        ];
    }
    
    if (!$paste['is_pinned']) {
        return [
            'success' => false,
            'message' => 'Paste is not pinned'
        ];
    }
    
    $stmt = $pdo->prepare("UPDATE pastes SET is_pinned = 0 WHERE id = ?");
    $success = $stmt->execute([$pasteId]);
    
    return [
        'success' => $success,
        'message' => $success ? 'Paste unpinned successfully' : 'Error unpinning paste'
    ];
}

// Получает список всех закрепленных паст
function getPinnedPastes() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.username, u.emoji, u.name_color,
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
        COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
        FROM pastes p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.is_pinned = 1 AND p.visibility = 'public'
        ORDER BY p.created_at DESC
    ");
    
    $stmt->execute();
    return $stmt->fetchAll();
}

// Проверяет, закреплена ли паста
function isPastePinned($pasteId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT is_pinned FROM pastes WHERE id = ?");
    $stmt->execute([$pasteId]);
    $result = $stmt->fetch();
    
    return $result && $result['is_pinned'] == 1;
}

// Проверяет, может ли пользователь закреплять/откреплять пасты
function canPinPastes() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Проверяем роль пользователя
    $user = getUserById($_SESSION['user_id']);
    $allowedRoles = ['administrator', 'developer', 'staff'];
    
    return $user && in_array(strtolower($user['role']), $allowedRoles);
}



// Other helper functions like `getUserById` or `formatDate` can be added here...



/**
 * Convert timestamp to human-readable time ago format
 * @param string $datetime Database datetime string
 * @return string Human-readable time ago
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    $units = array(
        31536000 => 'year',
        2592000 => 'month', 
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    );
    
    foreach ($units as $unit => $text) {
        if ($time < $unit) continue;
        $numberOfUnits = floor($time / $unit);
        return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '') . ' ago';
    }
    
    return 'just now';
}

/**
 * Render unified pagination markup similar to provided design screenshot.
 * Parameters:
 *  - $current: current page (1-based)
 *  - $totalPages: total page count
 *  - $baseUrl: script name e.g. 'top.php'
 *  - $extraParams: assoc array of extra query params
 */
function renderPagination(int $current, int $totalPages, string $baseUrl, array $extraParams = []): string {
    if ($totalPages <= 1) return '';
    
    $queryStatic = [];
    foreach ($extraParams as $k=>$v) {
        if ($v === '' || $v === null) continue;
        $queryStatic[$k] = $v;
    }
    $buildUrl = function(int $page) use ($baseUrl, $queryStatic) {
        $q = array_merge($queryStatic, ['page'=>$page]);
        return $baseUrl.'?'.http_build_query($q);
    };
    
    ob_start();
    echo '<div class="pagination-container"><div class="pagination-wrapper">';
    
    // Previous button
    if ($current > 1) {
        echo '<a class="page-prev" href="'.htmlspecialchars($buildUrl($current - 1)).'">‹ Previous</a>';
    } else {
        echo '<span class="page-prev disabled">‹ Previous</span>';
    }
    
    // Show only pages around current (2 before and 2 after)
    $start = max(1, $current - 2);
    $end = min($totalPages, $current + 2);
    
    // Adjust to always show 5 pages if possible
    if ($end - $start < 4) {
        if ($start == 1) {
            $end = min($totalPages, $start + 4);
        } elseif ($end == $totalPages) {
            $start = max(1, $end - 4);
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $current ? 'active' : '';
        echo '<a class="'.$active.'" href="'.htmlspecialchars($buildUrl($i)).'">'.$i.'</a>';
    }
    
    // Next button
    if ($current < $totalPages) {
        echo '<a class="page-next" href="'.htmlspecialchars($buildUrl($current + 1)).'">Next ›</a>';
    } else {
        echo '<span class="page-next disabled">Next ›</span>';
    }
    
    echo '</div></div>';
    return ob_get_clean();
}

// ============================================
// API KEY FUNCTIONS
// ============================================

/**
 * Generate a new API key for a user
 * @param int $userId
 * @return string|false The new API key or false on failure
 */
function generateApiKey($userId) {
    global $pdo;
    
    // Generate a secure random API key (64 characters hex)
    $apiKey = bin2hex(random_bytes(32));
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET api_key = ?, api_requests_count = 0, api_last_request = NULL WHERE id = ?");
        $success = $stmt->execute([$apiKey, $userId]);
        
        if ($success) {
            return $apiKey;
        }
        return false;
    } catch (PDOException $e) {
        error_log("Error generating API key: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's current API key
 * @param int $userId
 * @return string|null The API key or null if not set
 */
function getApiKey($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT api_key FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['api_key'] : null;
}

/**
 * Revoke (delete) a user's API key
 * @param int $userId
 * @return bool Success status
 */
function revokeApiKey($userId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET api_key = NULL, api_requests_count = 0, api_last_request = NULL WHERE id = ?");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Error revoking API key: " . $e->getMessage());
        return false;
    }
}

/**
 * Get API usage stats for a user
 * @param int $userId
 * @return array Stats including today's paste count
 */
function getApiStats($userId) {
    global $pdo;
    
    // Get today's paste count
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pastes WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$userId, $today]);
    $todayCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total paste count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pastes WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get user's API request info
    $stmt = $pdo->prepare("SELECT api_requests_count, api_last_request FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'pastes_today' => (int)$todayCount,
        'pastes_total' => (int)$totalCount,
        'daily_limit' => 30,
        'rate_limit' => 5,
        'requests_this_minute' => (int)($user['api_requests_count'] ?? 0),
        'last_request' => $user['api_last_request']
    ];
}

?>
