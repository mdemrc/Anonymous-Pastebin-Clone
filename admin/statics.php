<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Проверка авторизации и прав администратора
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$stats = getAdPerformanceStats();
$topAds = getTopPerformingAds();

// Prepare data for charts
$chartLabels = [];
$chartData = [];
foreach ($stats['views_by_day'] as $day) {
    $chartLabels[] = $day['day'];
    $chartData[] = $day['paste_views'];
}

// Получение статистики
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pastes");
$totalPastes = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$totalUsers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM banners WHERE active = 1");
$activeBanners = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM banner_texts WHERE active = 1");
$activeBannerTexts = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    
    <link href="../css/style.css" rel="stylesheet" type="text/css">
    <link href="../css/responsive.css" rel="stylesheet" type="text/css">
    <link href="../css/dark.css" rel="stylesheet" type="text/css">
    <link href="../css/fonts.css" rel="stylesheet" type="text/css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#00f784',
                        primaryHover: '#32ffb6',
                        background: '#151529',
                        background2: '#191935',
                        backgroundTextarea: '#1d1e3a',
                        textColor: '#ffffff',
                        textColorHover: '#d0d0d0',
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-background min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-textColor">Banner Statics</h1> <div class="alert bg-dark text-light alert-dismissible mt-4">
						  <button type="button" class="close" data-dismiss="alert" aria-hidden="true"></button>
						</div>
            <a href="/admin" class="text-primary hover:text-primaryHover">← Return to Admin Panel</a>
        </div>
        
        

        <!-- Statistics -->
        
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <!-- Existing stats -->
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-2">Total Pastes</h3>
        <p class="text-2xl text-textColor"><?php echo $totalPastes; ?></p>
    </div>
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-2">Users</h3>
        <p class="text-2xl text-textColor"><?php echo $totalUsers; ?></p>
    </div>
    
    <!-- New ad performance stats -->
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-2">Total Ad Revenue</h3>
        <p class="text-2xl text-textColor">$<?php echo number_format($stats['banners']['total_earned'], 2); ?></p>
    </div>
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-2">Overall CTR</h3>
        <p class="text-2xl text-textColor"><?php echo max($stats['banners']['ctr'], $stats['text_banners']['ctr']); ?>%</p>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Views Trend Chart -->
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-4">Views Trend (Last 30 Days)</h3>
        <canvas id="viewsChart" height="300"></canvas>
    </div>
    
    <!-- CTR Comparison Chart -->
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-4">Ad Performance</h3>
        <canvas id="ctrChart" height="300"></canvas>
    </div>
</div>

<!-- Detailed Ad Stats -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
    <!-- Banner Ads Stats -->
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-4">Banner Ads Performance</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <h4 class="text-secondary font-semibold">Total Banners</h4>
                <p class="text-xl"><?php echo $stats['banners']['total_banners']; ?></p>
            </div>
            <div>
                <h4 class="text-secondary font-semibold">Total Views</h4>
                <p class="text-xl"><?php echo number_format($stats['banners']['total_views']); ?></p>
            </div>
            <div>
                <h4 class="text-secondary font-semibold">Total Clicks</h4>
                <p class="text-xl"><?php echo number_format($stats['banners']['total_clicks']); ?></p>
            </div>
            <div>
                <h4 class="text-secondary font-semibold">CTR</h4>
                <p class="text-xl"><?php echo $stats['banners']['ctr']; ?>%</p>
            </div>
        </div>
        
        <h4 class="text-secondary font-semibold mt-4 mb-2">Top Performing Banners</h4>
        <div class="space-y-2">
            <?php foreach ($topAds['top_banners'] as $banner): ?>
                <div class="flex justify-between items-center p-2 bg-background rounded">
                    <span class="truncate"><?php echo basename($banner['image_path']); ?></span>
                    <span class="text-primary font-bold"><?php echo $banner['ctr']; ?>% CTR</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Text Ads Stats -->
    <div class="bg-backgroundTextarea rounded-lg p-4">
        <h3 class="text-primary font-bold mb-4">Text Ads Performance</h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <h4 class="text-secondary font-semibold">Total Text Ads</h4>
                <p class="text-xl"><?php echo $stats['text_banners']['total_banners']; ?></p>
            </div>
            <div>
                <h4 class="text-secondary font-semibold">Total Views</h4>
                <p class="text-xl"><?php echo number_format($stats['text_banners']['total_views']); ?></p>
            </div>
            <div>
                <h4 class="text-secondary font-semibold">Total Clicks</h4>
                <p class="text-xl"><?php echo number_format($stats['text_banners']['total_clicks']); ?></p>
            </div>
            <div>
                <h4 class="text-secondary font-semibold">CTR</h4>
                <p class="text-xl"><?php echo $stats['text_banners']['ctr']; ?>%</p>
            </div>
        </div>
        
        <h4 class="text-secondary font-semibold mt-4 mb-2">Top Performing Text Ads</h4>
        <div class="space-y-2">
            <?php foreach ($topAds['top_text_banners'] as $ad): ?>
                <div class="flex justify-between items-center p-2 bg-background rounded">
                    <span class="truncate"><?php echo substr($ad['content'], 0, 30); ?>...</span>
                    <span class="text-primary font-bold"><?php echo $ad['ctr']; ?>% CTR</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Views Trend Chart
    const viewsCtx = document.getElementById('viewsChart').getContext('2d');
    const viewsChart = new Chart(viewsCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Daily Views',
                data: <?php echo json_encode($chartData); ?>,
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    
    // CTR Comparison Chart
    const ctrCtx = document.getElementById('ctrChart').getContext('2d');
    const ctrChart = new Chart(ctrCtx, {
        type: 'bar',
        data: {
            labels: ['Banner Ads', 'Text Ads'],
            datasets: [{
                label: 'Click-Through Rate (%)',
                data: [
                    <?php echo $stats['banners']['ctr']; ?>,
                    <?php echo $stats['text_banners']['ctr']; ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 99, 132, 0.5)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
</script>
</body>
</html>
