<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Если пользователь не авторизован, перенаправляем на страницу входа
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Получаем информацию о текущем пользователе
$user = getUserById($_SESSION['user_id']);

// Получаем пасты пользователя
$userPastes = getUserPastes($_SESSION['user_id']);

// Получаем случайные баннеры и рекламные тексты
$banners = getRandomBanners(2);
$bannerTexts = getRandomBannerTexts(2);


$itemsPerPage = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalPastes = count($userPastes);
$totalPages = ceil($totalPastes / $itemsPerPage);

// Slice the array of pastes to only include those for the current page
$startIndex = ($page - 1) * $itemsPerPage;
$paginatedPastes = array_slice($userPastes, $startIndex, $itemsPerPage);

// Quick aggregates for compact chips
$pasteCount = $totalPastes;
$totalViews = 0;
$totalLikes = 0;
foreach ($userPastes as $p) {
    $totalViews += (int)($p['views'] ?? 0);
    $totalLikes += (int)($p['likes'] ?? 0);
}
$memberSince = date('F Y', strtotime($user['created_at']));

?>
<?php
// --- View Components (modülerlik) ---
function render_profile_card(array $user, string $memberSince): string {
    ob_start();
    ?>
    <div class="card mb-4 overflow-hidden">
        <!-- Cover Image -->
        <?php if (!empty($user['cover_url'])): ?>
        <div class="user-cover-image" style="height: 120px; background: linear-gradient(135deg, #1d1e3a 0%, #151529 100%); position: relative; overflow: hidden;">
            <img src="<?php echo htmlspecialchars($user['cover_url']); ?>" alt="Cover" 
                 style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;"
                 onerror="this.style.display='none';">
            <a href="settings.php" class="absolute top-2 right-2 px-2 py-1 bg-black/50 rounded text-xs text-white hover:bg-black/70 transition">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
        <?php else: ?>
        <div class="user-cover-image" style="height: 80px; background: linear-gradient(135deg, #1d1e3a 0%, #2d2e5a 50%, #1d1e3a 100%); position: relative;">
            <a href="settings.php" class="absolute top-2 right-2 px-2 py-1 bg-black/50 rounded text-xs text-white hover:bg-black/70 transition">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </div>
        <?php endif; ?>
        
        <div class="card-body" style="margin-top: -40px; position: relative; z-index: 10;">
            <div class="flex items-end gap-4 justify-between">
                <div class="flex items-end gap-4">
                    <!-- Avatar -->
                    <div class="user-profile-avatar" style="width: 80px; height: 80px; border-radius: 50%; border: 4px solid #1d1e3a; background: #151529; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" 
                                 style="width: 100%; height: 100%; object-fit: cover;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <i class="fa-solid fa-user" style="color: #00ff9d; font-size: 28px; display: none;"></i>
                        <?php elseif (!empty($user['emoji'])): ?>
                            <span style="font-size: 36px;"><?php echo displayUserEmoji($user['emoji']); ?></span>
                        <?php else: ?>
                            <i class="fa-solid fa-user" style="color: #00ff9d; font-size: 28px;"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- User Info -->
                    <div class="profile-info" style="padding-bottom: 8px;">
                        <h2 style="margin: 0; font-size: 1.5rem;">
                            <?php
                            $userId = (int)($_SESSION['user_id'] ?? 0);
                            $userRank = getUserRank($userId);
                            $classxD = getNameColor($userId);
                            $attrParts = [];
                            $classes = [];
                            if (!empty($classxD)) { $classes[] = $classxD; }
                            if (!empty($userRank['username_class'])) { $classes[] = $userRank['username_class']; }
                            if (!empty($classes)) { $attrParts[] = 'class="' . htmlspecialchars(implode(' ', $classes)) . '"'; }
                            if (!empty($userRank['username_style'])) { $attrParts[] = 'style="' . htmlspecialchars($userRank['username_style']) . '"'; }
                            $wrapperAttr = implode(' ', $attrParts);
                            echo '<span ' . $wrapperAttr . '>'
                                . (!empty($userRank['username_prefix']) ? $userRank['username_prefix'] . ' ' : '')
                                . htmlspecialchars($_SESSION['username'])
                                . (!empty($userRank['username_suffix']) ? ' ' . $userRank['username_suffix'] : '')
                                . '</span>';
                            if (!empty($userRank['html'])) { echo ' ' . $userRank['html']; }
                            ?>
                        </h2>
                        <div class="muted" style="font-size: 0.875rem;">
                            Joined: <?php echo $memberSince; ?>
                            <?php if (!empty($user['last_login'])) { echo ' · Last: ' . formatDate($user['last_login']); } ?>
                        </div>
                    </div>
                </div>
                
                <!-- Logout Button -->
                <a href="logout.php" class="logout-btn" style="margin-bottom: 8px;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
            
            <!-- Bio -->
            <?php if (!empty($user['bio'])): ?>
            <div class="user-bio" style="margin-top: 16px; padding: 12px; background: rgba(0,255,157,0.05); border-left: 3px solid #00ff9d; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #ccc; font-size: 0.9rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_stats_card(int $likes, int $pastes, int $views, string $since): string {
    ob_start();
    ?>
    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-chart-simple"></i> Statistics</div>
        </div>
        <div class="card-body">
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-heart"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($likes); ?></div>
                        <div class="stat-label">Total Likes</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($pastes); ?></div>
                        <div class="stat-label">Pastes</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-eye"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($views); ?></div>
                        <div class="stat-label">Total Views</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-calendar"></i></div>
                    <div>
                        <div class="stat-value"><?php echo htmlspecialchars($since); ?></div>
                        <div class="stat-label">Member Since</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_contacts_card(array $user): string {
    ob_start();
    $hasContacts = !empty($user['telegram']) || !empty($user['discord']) || !empty($user['website']);
    ?>
    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-address-book"></i> Contacts</div>
        </div>
        <div class="card-body">
            <?php if ($hasContacts): ?>
                <div class="list">
                    <?php if (!empty($user['telegram'])): ?>
                        <?php
                            $tgRaw = $user['telegram'];
                            $handle = $tgRaw;
                            if (preg_match('~^(?:https?://)?(?:t\.me|telegram\.me)/([A-Za-z0-9_]{3,})$~i', $tgRaw, $m)) {
                                $handle = '@' . $m[1];
                            } elseif ($tgRaw[0] !== '@') {
                                $handle = '@' . ltrim($tgRaw, '@');
                            }
                            $handleClean = ltrim($handle, '@');
                            $tgUrl = 'https://t.me/' . rawurlencode($handleClean);
                        ?>
                        <a class="list-item" href="<?php echo htmlspecialchars($tgUrl); ?>" target="_blank">
                            <i class="fab fa-telegram"></i>
                            <span><?php echo htmlspecialchars($tgUrl); ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($user['discord'])): ?>
                        <div class="list-item"><i class="fab fa-discord"></i><span><?php echo htmlspecialchars($user['discord']); ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($user['website'])): ?>
                        <?php $website_display = preg_replace('#^https?://#', '', htmlspecialchars($user['website'])); ?>
                        <div class="list-item"><i class="fas fa-globe"></i><a href="<?php echo htmlspecialchars($user['website']); ?>" target="_blank"><?php echo $website_display; ?></a></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="muted">No contact information available.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_pastes_card(array $pastes): string {
    ob_start();
    ?>
    <div class="card mb-3">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-file-code"></i> My Pastes</div>
        </div>
        <div class="card-body">
            <?php if (empty($pastes)): ?>
                <div class="muted">You don't have any pastes yet.</div>
            <?php else: ?>
                <div class="grid-pastes">
                    <?php foreach ($pastes as $paste): ?>
                        <div class="paste-card">
                            <a href="view.php?id=<?php echo $paste['id']; ?>" class="title"><?php echo htmlspecialchars($paste['title']); ?></a>
                            <div class="meta">
                                <span><i class="fa-regular fa-clock"></i> <?php echo formatTimeAgo($paste['created_at']); ?></span>
                                <span><i class="fa-regular fa-eye"></i> <?php echo (int)$paste['views']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
// --- /View Components ---
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>example.com - Profile</title>
    
    <meta name="keywords" content="example.com, pastehub, pastebin, pastebin alternative, free, proxies, configs, anonfiles, leaks, leaked, bayfiles, ghostbin, cracked, accounts, files, paste">
    <meta name="author" content="example.com">
    <meta name="copyright" content="example.com">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <link href="css/responsive.css" rel="stylesheet" type="text/css">
    <link href="css/mobile-responsive.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css">
    <link href="css/dark.css" rel="stylesheet" type="text/css">
    <link href="css/fonts.css" rel="stylesheet" type="text/css">
    <link href="css/common.css" rel="stylesheet" type="text/css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#00ff9d',
                        primaryHover: '#32ffb6',
                        background: '#151529',
                        backgroundSecondary: '#1d1e3a',
                        textColor: '#ffffff'
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
<style>
    /* Modern Card UI */
    .card { background:#1d1e3a; border:1px solid #2a2b52; border-radius:14px; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
    .card-header { padding:14px 16px; border-bottom:1px solid #25284a; display:flex; align-items:center; justify-content:space-between; }
    .card-title { font-size:16px; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px; }
    .card-body { padding:14px 16px; }
    .muted { color:#9aa4b2; font-size:12.5px; }
    .pill { background:#16202a; border:1px solid #1f2a36; color:#8fb; border-radius:9999px; padding:4px 10px; font-size:12px; }
    /* Stat cards */
    .stat-grid { display:grid; grid-template-columns: repeat(1,minmax(0,1fr)); gap:12px; }
    @media(min-width:640px){ .stat-grid{ grid-template-columns: repeat(2,minmax(0,1fr)); } }
    @media(min-width:1024px){ .stat-grid{ grid-template-columns: repeat(4,minmax(0,1fr)); } }
    .stat-card { background:#191a36; border:1px solid #2a2b52; border-radius:12px; padding:14px; display:flex; align-items:center; gap:10px; }
    .stat-icon { width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:8px; background:#0c2c22; color:#00ff9d; }
    .stat-value { color:#fff; font-size:18px; font-weight:800; line-height:1; }
    .stat-label { color:#9aa4b2; font-size:12px; margin-top:2px; }
    /* List inside card */
    .list { display:flex; flex-direction:column; gap:10px; list-style:none !important; padding:0; margin:0; }
    .list-item { display:flex; align-items:center; gap:10px; padding:10px 12px; background:#191a36; border:1px solid #262a4b; border-radius:10px; min-width:0; list-style:none !important; text-decoration:none; color:#fff; }
    .list-item:hover { background:#1f2048; }
    .list-item i { color:#00ff9d; flex-shrink:0; width:20px; font-size:16px; text-align:center; display:inline-flex; align-items:center; justify-content:center; margin-right:5px; }
    .list-item span, .list-item a { min-width:0; overflow-wrap:anywhere; word-break:break-word; color:#fff; text-decoration:none; flex:1; }
    /* Paste cards */
    .grid-pastes { display:grid; grid-template-columns:1fr; gap:12px; }
    @media(min-width:640px){ .grid-pastes{ grid-template-columns: repeat(2,minmax(0,1fr)); } }
    .paste-card { background:#191a36; border:1px solid #2a2b52; border-radius:12px; padding:12px 14px; transition: transform .18s ease, box-shadow .18s ease; }
    .paste-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.25); }
    .paste-card .title { font-weight:800; color:#00ff9d; margin-bottom:6px; text-decoration:none; }
    .paste-card .meta { display:flex; justify-content:space-between; color:#9aa4b2; font-size:12.5px; }

    /* Navigation styles */
    .nav-link {
        color: #fff;
        text-decoration: none;
        transition: opacity 0.3s ease;
    }
    
    .nav-link:hover {
        opacity: 0.7;
        color: #fff;
        text-decoration: none;
    }
    
    .nav-link.active {
        color: #00ff9d;
        opacity: 1;
    }

    .profile-section { background-color: #1d1e3a; border-radius: 10px; padding: 14px; margin-bottom: 12px; }

    .profile-header {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
    }

    .profile-avatar { font-size: 28px; margin-right: 10px; }

    .profile-info h2 { font-size: 18px; font-weight: 700; margin-bottom: 2px; }

    .profile-info p { color: #ccc; font-size: 13px; margin: 0; }

    /* eski paste item stilleri kaldırıldı (kart grid kullanılıyor) */

    .logout-btn { background-color: #ff3333; color: white; padding: 6px 12px; border-radius: 9999px; font-weight: 700; transition: background-color 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }

    .logout-btn:hover { background-color: #cc0000; text-decoration: none; color: white; }
    
    /* Устанавливаем шрифт Source Code Pro для всего текста, кроме шапки */
    .container body, .container input, .container label, .container p, 
    .container span, .container div, .container button, .container td, 
    .container th, .container a {
        font-family: 'Source Code Pro', monospace !important;
        font-size: 15.5px !important;
    }
    
    /* Исключения для заголовков */
    .container h1, .container h2, .container h3, 
    .container h4, .container h5, .container h6 {
        font-family: 'Source Code Pro', monospace !important;
    }
    
    /* Размер шрифта для текстовых объявлений */
    .banner-text span {
        font-size: 18px !important;
    }
    
    /* Username Style by @AustinnXD on ONEMILI Reqeust */
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

.olympus {
    font-family: 'Roboto',sans-serif;
    font-weight: bold;
    --bg-pos: .2em -.07em;
    position: relative;
    letter-spacing: .02em;
    text-shadow: 0px 0px 5px #F9FF4A;
    -webkit-text-fill-color: transparent;
    -webkit-background-clip: border-box,border-box,text;
    background-image: url(https://cdn.patched.to/custom_group_olympus/bg1.webp),url(https://cdn.patched.to/custom_group_olympus/thunder.gif),linear-gradient(125deg,rgba(170,119,28,1) 0%,rgba(255,242,138,1) 26%,rgba(246,194,35,1) 37%,rgba(255,215,120,1) 49%,rgba(238,187,29,1) 61%,rgba(170,119,28,1) 100%);
    background-size: 3em,3em,15em
}

.olympus:before {
    -webkit-background-clip: border-box,text,border-box;
    background-image: url(https://cdn.patched.to/custom_group_olympus/premium_sparkle_CS.gif),linear-gradient(135deg,rgba(255,249,136,1) 0%,rgba(255,182,51,1) 53%,rgba(255,249,136,1) 100%),url(https://cdn.patched.to/custom_group_olympus/halo.gif);
    background-size: 100%,2em,100% 100%;
    display: inline-block;
    position: absolute;
    width: .85em;
    height: .8em;
    left: -.40em;
    top: -20%;
    transform: rotate(340deg);
    content: ''
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
.diamond_rank_pto {
    color: #00cec9;
    background: url(https://patched.to/images/sparkles.gif);
    text-shadow: 0px 0px 4px #00bcd4;
}

.nova_rank_pto {
    color: #fd9644;
}
.vip_rank_pto {
    color: #E44A9C;
}
.royal_rank_pto {
    color: #74b9ff;
}
.cont_rank_pto {
    color: #f1c40f;
}
.glory_rank_pto {
    text-shadow: #bf0000 1px 1px 10px;
    color: #BF0000;
    font-weight: bold;
    background: url(https://i.postimg.cc/2jQD6YNS/glitter-397.gif);
}
.legendary_rank_pto {
    color: #f39c12; 
    background: url(https://patched.to/images/sparkles.gif); 
    text-shadow: 0px 0px 4px #fabc00;
}
.CEO_rank {
    color: #ffffff;
    text-shadow: 1px 1px 10px #ffffff;
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

.redxdezn {
    color: red;
    background: url(https://i.postimg.cc/HsTDJTK2/bg1.webp);
    background-size: 3em, 5em, 15em;
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

.sda {
  text-shadow: 1px 1px 8px rgb(178 209 0);
  letter-spacing: .02em;
  font-weight: 400;
  -webkit-text-fill-color: transparent;
  -webkit-background-clip: border-box,border-box,text;
  background-image: url(https://i.imgur.com/HZnWWX5.gif),url(https://i.imgur.com/qEudtIM.gif), linear-gradient( 15deg,rgba(212,175,55,1) 20%,rgba(58,147,74,1) 30%,rgba(58,147,74,1) 40%,rgba(212,175,55,1) 50%,rgba(212,175,55,1) 60%,rgba(58,147,74,1) 70%,rgba(58,147,74,1) 80%);
background-size: 11em, 11em, auto;
background-position-y: center, center, 0%; 
  animation: hue 0.5s linear infinite;
}
  

@keyframes hue {
  0% {
    filter: hue-rotate(60deg);
  }
  100% {
    filter: hue-rotate(360deg);
  }
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

.bg-background {
    --tw-bg-opacity: 1;
    background-color: rgb(21 21 41 / var(--tw-bg-opacity, 1));
}
</style>
</head>
<body class="bg-background min-h-screen flex flex-col pt-12">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] flex flex-col items-center">
            <!-- Баннеры -->
            <?php if (!empty($banners)): ?>
            <div class="banner-container flex justify-center gap-1 my-4 flex-wrap">
                <?php foreach ($banners as $banner): ?>
                <a href="<?php echo htmlspecialchars($banner['url']); ?>" class="banner" target="_blank">
                    <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" 
                         alt="Banner" 
                         class="w-[440px] h-[111px]">
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Рекламные тексты -->
            <?php if (!empty($bannerTexts)): ?>
            <div class="banner-text-container flex flex-col items-center gap-1 mt-0 mb-0">
        <?php 
        $i = 0;
        foreach ($bannerTexts as $bannerText): 
            $class = !empty($bannerText['style']) ? 
                htmlspecialchars($bannerText['style']) : 
                ($i == 0 ? 
                    "default-gradient-1" :
                    "default-gradient-2"
                );
            
            $i++;
        ?>
            <div class="banner-text">
                <?php if (!empty($bannerText['url'])): ?>
                    <a href="<?php echo htmlspecialchars($bannerText['url']); ?>" target="_blank"><span class="font-bold text-lg <?php echo $class; ?>"><?php echo htmlspecialchars($bannerText['text']); ?></span></a>
                <?php else: ?>
                    <span class="font-bold text-lg <?php echo $class; ?>"><?php echo htmlspecialchars($bannerText['text']); ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
            <?php endif; ?>

            <div class="w-full">
                <?php echo render_profile_card($user, $memberSince); ?>
                
                                <?php echo render_stats_card($totalLikes, $pasteCount, $totalViews, $memberSince); ?>
                
                            <?php echo render_contacts_card($user); ?>
                
                <?php echo render_pastes_card($paginatedPastes); ?>

    <!-- Pagination Bar -->
    <?php 
        $paginationHtml = renderPagination($page, $totalPages, 'profile.php');
        if ($paginationHtml) {
            $paginationHtml = str_replace('pagination-container"', 'pagination-container" style="margin-top:12px;margin-bottom:24px;"', $paginationHtml);
            echo $paginationHtml; 
        }
    ?>

            </div>
        </div>
    </div>

    <!-- Green separator line -->
    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] my-4" style="border-bottom: 1px solid #00ff9d;"></div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
