<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user not found, redirect to home page
if (!$user) {
    header('Location: index.php');
    exit;
}

// Получаем ранг пользователя
$userRank = getUserRank($user_id);

// Get user statistics
// Paste count
$stmt = $pdo->prepare("SELECT COUNT(*) as paste_count FROM pastes WHERE user_id = ?");
$stmt->execute([$user_id]);
$paste_count = $stmt->fetch(PDO::FETCH_ASSOC)['paste_count'];

// Total views
$stmt = $pdo->prepare("SELECT SUM(views) as total_views FROM pastes WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_views = $stmt->fetch(PDO::FETCH_ASSOC)['total_views'] ?? 0;

// Get top 5 pastes by user
$stmt = $pdo->prepare("
    SELECT id, title, syntax, views, created_at 
    FROM pastes 
    WHERE user_id = ? AND visibility = 'public'
    ORDER BY views DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$top_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest 5 pastes by user
$stmt = $pdo->prepare("
    SELECT id, title, syntax, views, created_at 
    FROM pastes 
    WHERE user_id = ? AND visibility = 'public'
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_pastes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get random banners and text banners
$banners = getRandomBanners(2);
$bannerTexts = getRandomBannerTexts(2);

// Derived fields and modular view components
$member_since = date('F Y', strtotime($user['created_at'] ?? 'now'));

function render_user_profile_card(array $user, array $userRank, string $memberSince): string {
    ob_start(); ?>
    <div class="card mb-4 overflow-hidden">
        <!-- Cover Image -->
        <?php if (!empty($user['cover_url'])): ?>
        <div class="user-cover-image" style="height: 120px; background: linear-gradient(135deg, #1d1e3a 0%, #151529 100%); position: relative; overflow: hidden;">
            <img src="<?php echo htmlspecialchars($user['cover_url']); ?>" alt="Cover" 
                 style="width: 100%; height: 100%; object-fit: cover; opacity: 0.8;"
                 onerror="this.style.display='none';">
        </div>
        <?php else: ?>
        <div class="user-cover-image" style="height: 80px; background: linear-gradient(135deg, #1d1e3a 0%, #2d2e5a 50%, #1d1e3a 100%);"></div>
        <?php endif; ?>
        
        <div class="card-body" style="margin-top: -40px; position: relative; z-index: 10;">
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
                        $classxD = getNameColor((int)$user['id']);
                        $attrParts = [];
                        $classes = [];
                        if (!empty($classxD)) { $classes[] = $classxD; }
                        if (!empty($userRank['username_class'])) { $classes[] = $userRank['username_class']; }
                        if (!empty($classes)) { $attrParts[] = 'class="' . htmlspecialchars(implode(' ', $classes)) . '"'; }
                        if (!empty($userRank['username_style'])) { $attrParts[] = 'style="' . htmlspecialchars($userRank['username_style']) . '"'; }
                        $wrapperAttr = implode(' ', $attrParts);
                        echo '<span ' . $wrapperAttr . '>'
                            . (!empty($userRank['username_prefix']) ? $userRank['username_prefix'] . ' ' : '')
                            . htmlspecialchars($user['username'])
                            . (!empty($userRank['username_suffix']) ? ' ' . $userRank['username_suffix'] : '')
                            . '</span>';
                        if (!empty($userRank['html'])) { echo ' ' . $userRank['html']; }
                        ?>
                    </h2>
                    <div class="muted" style="font-size: 0.875rem;">
                        Member Since: <?php echo htmlspecialchars($memberSince); ?>
                    </div>
                </div>
            </div>
            
            <!-- Bio -->
            <?php if (!empty($user['bio'])): ?>
            <div class="user-bio" style="margin-top: 16px; padding: 12px; background: rgba(0,255,157,0.05); border-left: 3px solid #00ff9d; border-radius: 0 8px 8px 0;">
                <p style="margin: 0; color: #ccc; font-size: 0.9rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

function render_user_stats_card(int $pasteCount, int $totalViews, string $memberSince): string {
    ob_start(); ?>
    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-chart-simple"></i> Statistics</div>
        </div>
        <div class="card-body">
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-file-lines"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($pasteCount); ?></div>
                        <div class="stat-label">Pastes</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-eye"></i></div>
                    <div>
                        <div class="stat-value"><?php echo number_format($totalViews); ?></div>
                        <div class="stat-label">Total Views</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fa-solid fa-calendar"></i></div>
                    <div>
                        <div class="stat-value"><?php echo htmlspecialchars($memberSince); ?></div>
                        <div class="stat-label">Member Since</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

function render_user_contacts_card(array $user): string {
    ob_start();
    $hasContacts = !empty($user['telegram']) || !empty($user['discord']) || !empty($user['website']); ?>
    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-address-book"></i> Contacts</div>
        </div>
        <div class="card-body<?php echo $hasContacts ? '' : ' empty-state'; ?>">
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
                        <a class="list-item" href="<?php echo htmlspecialchars($tgUrl); ?>" target="_blank"><i class="fab fa-telegram"></i><span><?php echo htmlspecialchars($tgUrl); ?></span></a>
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
                <i class="fa-regular fa-address-card" style="color:#8aa1b2;"></i>
                <div>
                    <div class="text-gray-300 font-medium">No contact info</div>
                    <div class="muted">User hasn't added any contacts yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

function render_user_paste_list_card(string $title, array $pastes): string {
    ob_start(); ?>
    <div class="card mb-4">
        <div class="card-header">
            <div class="card-title"><?php echo htmlspecialchars($title); ?></div>
        </div>
        <div class="card-body">
            <?php if (empty($pastes)): ?>
                <div class="muted">No pastes to show.</div>
            <?php else: ?>
                <div class="flex flex-col space-y-3">
                    <?php foreach ($pastes as $paste): ?>
                        <a href="view.php?id=<?php echo $paste['id']; ?>" class="flex justify-between items-center p-2 hover:bg-[#191a36] rounded transition-colors border border-transparent hover:border-[#262a4b]">
                            <div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars($paste['title']); ?></div>
                                <div class="text-gray-400 text-sm"><?php echo htmlspecialchars($paste['syntax']); ?> • <?php echo date('M j, Y', strtotime($paste['created_at'])); ?></div>
                            </div>
                            <div class="text-gray-400 flex items-center">
                                <i class="fa-regular fa-eye mr-1"></i> <?php echo number_format((int)$paste['views']); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

// Include header
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($user['username']); ?> - example.com</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;700&display=swap">
    <style>
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
.glory_rank_pto {
    color: #FF006C;
    background: url(https://patched.to/images/sparkles.gif);
    text-shadow: 0px 0px 4px;
}
.legendary_rank_pto {
    color: #f39c12; 
    background: url(https://patched.to/images/sparkles.gif); 
    text-shadow: 0px 0px 4px #fabc00;
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

/* Modern Card UI (like profile) */
.card { background:#1d1e3a; border:1px solid #2a2b52; border-radius:14px; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
.card-header { padding:14px 16px; border-bottom:1px solid #25284a; display:flex; align-items:center; justify-content:space-between; }
.card-title { font-size:16px; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px; }
.card-body { padding:14px 16px; }
.muted { color:#9aa4b2; font-size:12.5px; }
.pill { background:#16202a; border:1px solid #1f2a36; color:#8fb; border-radius:9999px; padding:4px 10px; font-size:12px; }
.stat-grid { display:grid; grid-template-columns: repeat(1,minmax(0,1fr)); gap:12px; }
@media(min-width:640px){ .stat-grid{ grid-template-columns: repeat(2,minmax(0,1fr)); } }
@media(min-width:1024px){ .stat-grid{ grid-template-columns: repeat(3,minmax(0,1fr)); } }
.stat-card { background:#191a36; border:1px solid #2a2b52; border-radius:12px; padding:14px; display:flex; align-items:center; gap:10px; }
.stat-icon { width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:8px; background:#0c2c22; color:#00ff9d; }
.stat-value { color:#fff; font-size:18px; font-weight:800; line-height:1; }
.stat-label { color:#9aa4b2; font-size:12px; margin-top:2px; }
.list { display:flex; flex-direction:column; gap:10px; }
.list-item { display:flex; align-items:center; gap:10px; padding:10px 12px; background:#191a36; border:1px solid #262a4b; border-radius:10px; }
.list-item i { color:#00ff9d; }

/* Equal height rows for side-by-side cards */
.equal-grid > div { display:flex; }
.equal-grid > div > .card { flex: 1 1 auto; display:flex; flex-direction:column; }
.card-body { flex: 1 1 auto; }
.card-body.empty-state { display:flex; align-items:center; gap:10px; }

    </style>
</head>

<div class="container mx-auto flex justify-center mt-16">
    <div class="w-full max-w-[1200px] flex flex-col items-center">
        <!-- Banners -->
        <!-- Random Banners -->
<div class="banner-container">
    <?php foreach ($banners as $banner): ?>
        <div class="banner">
            <a href="redirect.php?type=banner&id=<?php echo $banner['id']; ?>&url=<?php echo urlencode($banner['url']); ?>" target="_blank">
                <?php if (isset($banner['is_external']) && $banner['is_external']): ?>
                    <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Banner">
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" alt="Banner">
                <?php endif; ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<!-- Random Text Banners -->
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
                <a href="<?php echo htmlspecialchars($bannerText['url']); ?>" target="_blank">
                    <span class="font-bold text-lg <?php echo $class; ?>">
                        <?php echo htmlspecialchars($bannerText['text']); ?>
                    </span>
                </a>
            <?php else: ?>
                <span class="font-bold text-lg <?php echo $class; ?>">
                    <?php echo htmlspecialchars($bannerText['text']); ?>
                </span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
        
        <!-- User Profile (modular, cards) -->
        <div class="w-full">
            <?php echo render_user_profile_card($user, $userRank, $member_since); ?>
            <!-- Row 1: Statistics | Contacts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 equal-grid">
                <div>
                    <?php echo render_user_stats_card((int)$paste_count, (int)$total_views, $member_since); ?>
                </div>
                <div>
                    <?php echo render_user_contacts_card($user); ?>
                </div>
            </div>
            <!-- Row 2: Top Pastes | Recent Pastes -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4 equal-grid">
                <div>
                    <?php echo render_user_paste_list_card('Top Pastes', $top_pastes); ?>
                </div>
                <div>
                    <?php echo render_user_paste_list_card('Recent Pastes', $recent_pastes); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Green separator line -->
<div class="container mx-auto flex justify-center">
    <div class="w-full max-w-[1200px] my-4" style="border-bottom: 1px solid #00ff9d;"></div>
</div>

<?php include 'includes/footer.php'; ?>
