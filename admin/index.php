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

// ========== PLATFORM STATISTICS ==========
// Get time range from query parameter (default: 30 days)
$range = isset($_GET['range']) ? (int)$_GET['range'] : 30;
$validRanges = [7, 14, 30, 90];
if (!in_array($range, $validRanges)) {
    $range = 30;
}

// Calculate date range
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime("-{$range} days"));

// Get total statistics
$stmt = $pdo->query("SELECT COALESCE(SUM(views), 0) as total FROM pastes");
$totalViews = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM paste_likes WHERE type = 'like'");
$totalLikes = $stmt->fetch()['total'];

// Get today's statistics
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$todayUsers = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pastes WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$todayPastes = $stmt->fetch()['total'];

// Get today's views (from pastes created today)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(views), 0) as total FROM pastes WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$todayViews = $stmt->fetch()['total'];

// Get today's likes
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM paste_likes WHERE type = 'like' AND DATE(created_at) = ?");
$stmt->execute([$today]);
$todayLikes = $stmt->fetch()['total'];

// Get daily data for charts
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM users 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$startDate, $endDate]);
$usersByDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM pastes 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$startDate, $endDate]);
$pastesByDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM paste_likes 
    WHERE type = 'like' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$startDate, $endDate]);
$likesByDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get views by day - calculate from pastes created each day and their current views
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        SUM(views) as count
    FROM pastes 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$startDate, $endDate]);
$viewsByDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Build chart data arrays
$labels = [];
$usersData = [];
$pastesData = [];
$likesData = [];
$viewsData = [];

$currentDate = strtotime($startDate);
$endTimestamp = strtotime($endDate);

while ($currentDate <= $endTimestamp) {
    $dateStr = date('Y-m-d', $currentDate);
    $displayDate = date('M j', $currentDate);
    
    $labels[] = $displayDate;
    $usersData[] = isset($usersByDay[$dateStr]) ? (int)$usersByDay[$dateStr] : 0;
    $pastesData[] = isset($pastesByDay[$dateStr]) ? (int)$pastesByDay[$dateStr] : 0;
    $likesData[] = isset($likesByDay[$dateStr]) ? (int)$likesByDay[$dateStr] : 0;
    $viewsData[] = isset($viewsByDay[$dateStr]) ? (int)$viewsByDay[$dateStr] : 0;
    
    $currentDate = strtotime('+1 day', $currentDate);
}

// Convert to JSON for JavaScript
$labelsJson = json_encode($labels);
$usersJson = json_encode($usersData);
$pastesJson = json_encode($pastesData);
$likesJson = json_encode($likesData);
$viewsJson = json_encode($viewsData);
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
                        primary: '#00ff9d',
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .stat-card {
            background: linear-gradient(135deg, #1d1e3a 0%, #191935 100%);
            border: 1px solid rgba(0, 255, 157, 0.3);
            border-radius: 12px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            border-color: rgba(0, 255, 157, 0.6);
            box-shadow: 0 0 20px rgba(0, 255, 157, 0.2);
        }
        
        .stat-card .icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 40px;
            color: rgba(0, 255, 157, 0.5);
        }
        
        .stat-card .label {
            color: #00ff9d;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            color: #fff;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-card .change {
            color: #00ff9d;
            font-size: 13px;
        }
        
        .chart-container {
            background: linear-gradient(135deg, #1d1e3a 0%, #191935 100%);
            border: 1px solid rgba(0, 255, 157, 0.3);
            border-radius: 12px;
            padding: 24px;
        }
        
        .chart-title {
            color: #fff;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .section-header {
            background: linear-gradient(135deg, #1d1e3a 0%, #191935 100%);
            border: 1px solid rgba(0, 255, 157, 0.3);
            border-radius: 12px;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            color: #fff;
            font-size: 18px;
            font-weight: 600;
        }
        
        .time-range-select {
            background: #151529;
            border: 1px solid rgba(0, 255, 157, 0.4);
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            outline: none;
        }
        
        .time-range-select:focus {
            border-color: #00ff9d;
        }
        
        .today-card {
            background: linear-gradient(135deg, #1d1e3a 0%, #191935 100%);
            border: 1px solid rgba(0, 255, 157, 0.2);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s ease;
        }
        
        .today-card:hover {
            border-color: rgba(0, 255, 157, 0.5);
            transform: translateY(-2px);
        }
        
        .today-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .today-info {
            display: flex;
            flex-direction: column;
        }
        
        .today-label {
            color: #888;
            font-size: 13px;
            margin-bottom: 2px;
        }
        
        .today-value {
            color: #fff;
            font-size: 24px;
            font-weight: 700;
        }
    </style>
</head>
<body class="bg-background min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-textColor">Admin Panel</h1>
            <div class="alert bg-dark text-light alert-dismissible mt-4">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true"></button>
                <h5><i class="fa fa-user-secret" aria-hidden="true"></i> <strong>Hello Admin, Remember - Being Broke is a Choice.</strong></h5>
            </div>
            <a href="../" class="text-primary hover:text-primaryHover">← Return to Site</a>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-backgroundTextarea rounded-lg p-4">
                <h3 class="text-primary font-bold mb-2">Total Pastes</h3>
                <p class="text-2xl text-textColor"><?php echo $totalPastes; ?></p>
            </div>
            <div class="bg-backgroundTextarea rounded-lg p-4">
                <h3 class="text-primary font-bold mb-2">Users</h3>
                <p class="text-2xl text-textColor"><?php echo $totalUsers; ?></p>
            </div>
            <div class="bg-backgroundTextarea rounded-lg p-4">
                <h3 class="text-primary font-bold mb-2">Active Banners</h3>
                <p class="text-2xl text-textColor"><?php echo $activeBanners; ?></p>
            </div>
            <div class="bg-backgroundTextarea rounded-lg p-4">
                <h3 class="text-primary font-bold mb-2">Active Banner Texts</h3>
                <p class="text-2xl text-textColor"><?php echo $activeBannerTexts; ?></p>
            </div>
        </div>

        <!-- Menu -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <a href="users.php" class="bg-backgroundTextarea hover:bg-background2 rounded-lg p-6 transition duration-300">
                <i class="fas fa-users text-primary text-2xl mb-4"></i>
                <h3 class="text-xl font-bold text-textColor mb-2">User Management</h3>
                <p class="text-textColorHover">Manage users, configure emojis and name colors</p>
            </a>
            
            <a href="banners.php" class="bg-backgroundTextarea hover:bg-background2 rounded-lg p-6 transition duration-300">
                <i class="fas fa-image text-primary text-2xl mb-4"></i>
                <h3 class="text-xl font-bold text-textColor mb-2">Banner Management</h3>
                <p class="text-textColorHover">Add and edit banners</p>
            </a>
            
            <a href="texts.php" class="bg-backgroundTextarea hover:bg-background2 rounded-lg p-6 transition duration-300">
                <i class="fas fa-font text-primary text-2xl mb-4"></i>
                <h3 class="text-xl font-bold text-textColor mb-2">Banner Texts</h3>
                <p class="text-textColorHover">Manage promotional texts</p>
            </a>
            
            <a href="pastes.php" class="bg-backgroundTextarea hover:bg-background2 rounded-lg p-6 transition duration-300">
                <i class="fas fa-paste text-primary text-2xl mb-4"></i>
                <h3 class="text-xl font-bold text-textColor mb-2">Paste Management</h3>
                <p class="text-textColorHover">View and moderate pastes</p>
            </a>
            
            <a href="statics.php" class="bg-backgroundTextarea hover:bg-background2 rounded-lg p-6 transition duration-300">
                <i class="fas fa-chart-line text-primary text-2xl mb-4"></i>
                <h3 class="text-xl font-bold text-textColor mb-2">Banner Stats</h3>
                <p class="text-textColorHover">Detailed banner statics</p>
            </a>
            
            <a href="pinned_pastes.php" class="bg-backgroundTextarea hover:bg-background2 rounded-lg p-6 transition duration-300">
                <i class="fas fa-thumbtack text-primary text-2xl mb-4"></i>
                <h3 class="text-xl font-bold text-textColor mb-2">Pinned Pastes</h3>
                <p class="text-textColorHover">Manage pinned pastes</p>
            </a>
            
            <a href="settings.php" class="bg-backgroundTextarea hover:bg-background2 rounded-lg p-6 transition duration-300">
                <i class="fas fa-cog text-primary text-2xl mb-4"></i>
                <h3 class="text-xl font-bold text-textColor mb-2">Site Settings</h3>
                <p class="text-textColorHover">General site settings</p>
            </a>
        </div>
        
        <!-- ========== PLATFORM STATISTICS SECTION ========== -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-primary mb-4">
                <i class="fas fa-chart-area mr-2"></i>Platform Statistics
            </h2>
        </div>
        
        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="label">Total Users</div>
                <div class="value"><?php echo number_format($totalUsers); ?></div>
                <div class="change">+<?php echo $todayUsers; ?> today</div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="label">Total Pastes</div>
                <div class="value"><?php echo number_format($totalPastes); ?></div>
                <div class="change">+<?php echo $todayPastes; ?> today</div>
                <div class="icon">
                    <i class="fas fa-file-alt"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="label">Total Views</div>
                <div class="value"><?php echo number_format($totalViews); ?></div>
                <div class="icon">
                    <i class="fas fa-eye"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="label">Total Likes</div>
                <div class="value"><?php echo number_format($totalLikes); ?></div>
                <div class="icon">
                    <i class="fas fa-heart"></i>
                </div>
            </div>
        </div>
        
        <!-- Activity Over Time Section Header -->
        <div class="section-header mb-4">
            <span class="section-title">Activity Over Time</span>
            <div class="flex items-center gap-4">
                <span class="text-gray-400 text-sm">Time Range:</span>
                <select class="time-range-select" onchange="window.location.href='?range='+this.value">
                    <option value="7" <?php echo $range == 7 ? 'selected' : ''; ?>>1 Week</option>
                    <option value="14" <?php echo $range == 14 ? 'selected' : ''; ?>>2 Weeks</option>
                    <option value="30" <?php echo $range == 30 ? 'selected' : ''; ?>>1 Month</option>
                    <option value="90" <?php echo $range == 90 ? 'selected' : ''; ?>>3 Months</option>
                </select>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Activity Trend (Line Chart) -->
            <div class="chart-container">
                <h3 class="chart-title">Activity Trend</h3>
                <canvas id="activityTrendChart"></canvas>
                <div class="flex justify-center gap-6 mt-4 text-sm text-gray-400">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" style="background: #00ff9d;"></span> Likes</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" style="background: #10b981;"></span> New Pastes</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" style="background: #34d399;"></span> New Users</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full" style="background: #6ee7b7;"></span> Views</span>
                </div>
            </div>
            
            <!-- Daily Activity (Bar Chart) -->
            <div class="chart-container">
                <h3 class="chart-title">Daily Activity</h3>
                <canvas id="dailyActivityChart"></canvas>
                <div class="flex justify-center gap-6 mt-4 text-sm text-gray-400">
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm" style="background: #00ff9d;"></span> Likes</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm" style="background: #10b981;"></span> New Pastes</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm" style="background: #34d399;"></span> New Users</span>
                    <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-sm" style="background: #6ee7b7;"></span> Views</span>
                </div>
            </div>
        </div>
        
        <!-- Today's Summary -->
        <div class="mt-6">
            <div class="section-header mb-4">
                <h3 class="section-title"><i class="fas fa-calendar-day mr-2"></i>Today's Summary</h3>
                <span class="text-gray-400 text-sm"><?php echo date('F j, Y'); ?></span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="today-card">
                    <div class="today-icon" style="background: rgba(0, 255, 157, 0.15); color: #00ff9d;">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="today-info">
                        <span class="today-label">New Users</span>
                        <span class="today-value"><?php echo $todayUsers; ?></span>
                    </div>
                </div>
                <div class="today-card">
                    <div class="today-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fas fa-file-plus"></i>
                    </div>
                    <div class="today-info">
                        <span class="today-label">New Pastes</span>
                        <span class="today-value"><?php echo $todayPastes; ?></span>
                    </div>
                </div>
                <div class="today-card">
                    <div class="today-icon" style="background: rgba(52, 211, 153, 0.15); color: #34d399;">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="today-info">
                        <span class="today-label">Today Views</span>
                        <span class="today-value"><?php echo number_format($todayViews); ?></span>
                    </div>
                </div>
                <div class="today-card">
                    <div class="today-icon" style="background: rgba(110, 231, 183, 0.15); color: #6ee7b7;">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="today-info">
                        <span class="today-label">New Likes</span>
                        <span class="today-value"><?php echo $todayLikes; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const labels = <?php echo $labelsJson; ?>;
        const likesData = <?php echo $likesJson; ?>;
        const pastesData = <?php echo $pastesJson; ?>;
        const usersData = <?php echo $usersJson; ?>;
        const viewsData = <?php echo $viewsJson; ?>;
        
        // Activity Trend - Line Chart (Green Theme)
        const trendCtx = document.getElementById('activityTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Likes',
                        data: likesData,
                        borderColor: '#00ff9d',
                        backgroundColor: 'rgba(0, 255, 157, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#00ff9d',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'New Pastes',
                        data: pastesData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#10b981',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'New Users',
                        data: usersData,
                        borderColor: '#34d399',
                        backgroundColor: 'rgba(52, 211, 153, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#34d399',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        label: 'Views',
                        data: viewsData,
                        borderColor: '#6ee7b7',
                        backgroundColor: 'rgba(110, 231, 183, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#6ee7b7',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(29, 30, 58, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(0, 255, 157, 0.5)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#666',
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#666'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Daily Activity - Bar Chart (Green Theme)
        const barCtx = document.getElementById('dailyActivityChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Likes',
                        data: likesData,
                        backgroundColor: '#00ff9d',
                        borderRadius: 4
                    },
                    {
                        label: 'New Pastes',
                        data: pastesData,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    },
                    {
                        label: 'New Users',
                        data: usersData,
                        backgroundColor: '#34d399',
                        borderRadius: 4
                    },
                    {
                        label: 'Views',
                        data: viewsData,
                        backgroundColor: '#6ee7b7',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(29, 30, 58, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(0, 255, 157, 0.5)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666',
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#666'
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
