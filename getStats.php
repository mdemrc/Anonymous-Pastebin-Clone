<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$bannerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($bannerId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid banner ID']);
    exit;
}

try {
    // Get the banner details
    $stmt = $pdo->prepare("
        SELECT *, 
               DATEDIFF(COALESCE(expires_at, NOW()), created_at) AS days_active
        FROM banners 
        WHERE id = ?
    ");
    $stmt->execute([$bannerId]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$banner) {
        http_response_code(404);
        echo json_encode(['error' => 'Banner not found']);
        exit;
    }

    $totalViews = (int) $banner['views'];
    $totalClicks = (int) $banner['clicks'];
    $daysActive = max((int) $banner['days_active'], 1);

    $ctr = $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0;
    $impressionsPerDay = round($totalViews / $daysActive, 2);

    // Real-time views by day (from viewers table)
    $stmt = $pdo->prepare("
        SELECT DATE(timestamp) AS date, COUNT(*) AS views
        FROM viewers
        WHERE paste_id = ?
        GROUP BY DATE(timestamp)
        ORDER BY date ASC
        LIMIT 30
    ");
    $stmt->execute([$bannerId]);
    $viewsByDayRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $performanceData = [];
    foreach ($viewsByDayRaw as $entry) {
        $performanceData[] = [
            'x' => $entry['date'],
            'y' => (int) $entry['views']
        ];
    }

    // Final clean response — NO FAKE DATA
    echo json_encode([
        'banner_id' => $bannerId,
        'total_views' => $totalViews,
        'total_clicks' => $totalClicks,
        'ctr' => $ctr,
        'impressions_per_day' => $impressionsPerDay,
        'days_active' => $daysActive,
        'views_by_day' => $viewsByDayRaw,
        'performance_chart_data' => $performanceData
    ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
