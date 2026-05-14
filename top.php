<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Получаем случайные баннеры
$banners = getRandomBanners();

// Получаем случайные текстовые баннеры
$bannerTexts = getRandomBannerTexts();

// Получаем номер страницы
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Убедимся, что страница не меньше 1
$perPage = 18;
$offset = ($page - 1) * $perPage;

// Получаем топ пасты с лайками и дислайками
// Получаем топ пасты с лайками и дислайками
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.emoji, u.name_color,
           COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
           COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
    FROM pastes p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.visibility = 'public'
    ORDER BY 
        p.is_pinned DESC,
        p.views DESC,
        (SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like') DESC,
        p.created_at DESC
    LIMIT ? OFFSET ?
");


$stmt->execute([$perPage, $offset]);
$pastes = $stmt->fetchAll();

// Получаем общее количество публичных паст
$stmt = $pdo->query("SELECT COUNT(*) FROM pastes WHERE visibility = 'public'");
$totalPastes = $stmt->fetchColumn();

// Вычисляем общее количество страниц
$totalPages = ceil($totalPastes / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Top Pastes - example.com</title>
    
    <meta name="keywords" content="example.com, pastehub, pastebin, pastebin alternative, free, proxies, configs, anonfiles, leaks, leaked, bayfiles, ghostbin, cracked, accounts, files, paste">
    <meta name="author" content="example.com">
    <meta name="copyright" content="example.com">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <link href="css/responsive.css" rel="stylesheet" type="text/css">
    <link href="css/mobile-responsive.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css">
    <link href="css/dark.css?v=103" rel="stylesheet" type="text/css">
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
    
    <style>
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

.features_tborder td.trow1:nth-child(2):before,

.supreme_rank {
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
.scooby {
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
a[href*="user.php"] span span {
    margin-bottom: 5px;
} /* @zfzp1 best dev */
img[src$="bitcoin.gif"] {
        margin-top: 5px !important;
    }
img[src$="money.webp"] {
        margin-top: 5px !important;
    }
    </style>
</head>
<body class="bg-background min-h-screen flex flex-col pt-12">
    <?php include 'includes/header.php'; ?>

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

    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] flex flex-col items-center">
            <h1 class="text-textColor font-extrabold mb-2" style="font-size: 24px; font-weight: 1000; font-weight: bold;">Top Pastes</h1>
            <div class="border-[#00ff9d] min-w-[400px] mb-4" style="border-bottom: 1px solid #00ff9d; width: 400px;"></div>
            
            <!-- Search Box -->
            <div class="w-full max-w-[600px] mb-6">
                <input type="text" 
                       id="searchInput"
                       placeholder="Search pastes by title..." 
                       class="w-full bg-backgroundTextarea text-textColor px-4 py-2 rounded-lg border border-gray-600 focus:border-primary focus:outline-none">
            </div>
            
            <div class="w-[1000px] max-w-full bg-[#1d1e3a] rounded-lg overflow-hidden pb-0 mb-6 shadow-sm">
                <table style="border-collapse: separate; border-spacing: 0; width: 100%; font-size: 15.5px; font-family: 'Source Code Pro', monospace;">
                    <thead>
                        <tr style="border-bottom: 1px solid white;">
                            <th class="py-0.5 px-1 text-center text-textColor" style="border-bottom: 1px solid white;">Paste</th>
                            <th class="py-0.5 px-1 text-center text-textColor" style="border-bottom: 1px solid white;">Views</th>
                            <th class="py-0.5 px-1 text-center text-textColor" style="border-bottom: 1px solid white;">Rating</th>
                            <th class="py-0.5 px-1 text-center text-textColor" style="border-bottom: 1px solid white;">Creator</th>
                            <th class="py-0.5 px-1 text-center text-textColor" style="border-bottom: 1px solid white;">Creation Time</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php 
    $pinnedPastes = [];
    $normalPastes = [];

    foreach ($pastes as $paste) {
        if (!empty($paste['is_pinned'])) {
            $pinnedPastes[] = $paste;
        } else {
            $normalPastes[] = $paste;
        }
    }

    $lastPinnedIndex = count($pinnedPastes) - 1;
    ?>

    <!-- Render pinned pastes -->
    <?php foreach ($pinnedPastes as $index => $paste): ?>
        <tr class="hover:bg-[#1a1d30]" style="border-bottom: 1px solid white;">
            <td class="py-0.5 px-1 text-center">
                <a href="view.php?id=<?php echo $paste['id']; ?>" 
                   class="text-white hover:text-white font-bold text-center" 
                   style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; color: white !important; text-decoration: underline !important; text-decoration-color: white !important;">
                    <i class="fas fa-thumbtack" style="color: #00ff9d; margin-right: 5px;"></i>
                    <?php echo htmlspecialchars($paste['title']); ?>
                </a>
            </td>
            <td class="py-0.5 px-1 text-center text-white"><?php echo number_format($paste['views']); ?></td>
            <td class="py-0.5 px-1 text-center text-white">
                <?php 
                $rating = ($paste['likes'] ?? 0) - ($paste['dislikes'] ?? 0);
                $color = $rating > 0 ? 'text-green-500' : ($rating < 0 ? 'text-red-500' : 'text-gray-500');
                echo "<span class='$color'>" . number_format($rating) . "</span>";
                ?>
            </td>
            <td class="py-0.5 px-1 text-center text-white">
    <?php if ($paste['user_id']): ?>
        <?php $pasteUser = getUserById($paste['user_id']); ?>
        <?php if ($pasteUser): ?>
            <span style="color: white;">
                by 
                <?php $userRank = getUserRank($pasteUser['id']); ?>
                <?php $classxD = getNameColor($pasteUser['id']); ?>
                <?php if ($userRank['rank']): ?>
                    <strong>
                        <a href="user.php?id=<?php echo $pasteUser['id']; ?>" class="hover:underline font-bold" style="font-weight: bold !important;">
                            <?php
                                // Merge custom name color with rank class and add style if provided
                                $attrParts = [];
                                $classes = [];
                                if (!empty($classxD)) { $classes[] = $classxD; }
                                if (!empty($userRank['username_class'])) { $classes[] = $userRank['username_class']; }
                                if (!empty($classes)) { $attrParts[] = 'class="' . htmlspecialchars(implode(' ', $classes)) . '"'; }
                                if (!empty($userRank['username_style'])) { $attrParts[] = 'style="' . htmlspecialchars($userRank['username_style']) . '"'; }
                                $wrapperAttr = implode(' ', $attrParts);
                            ?>
                            <span <?php echo $wrapperAttr; ?>>
                                <?php echo !empty($userRank['username_prefix']) ? $userRank['username_prefix'] . ' ' : ''; ?>
                                <?php echo htmlspecialchars($pasteUser['username']); ?>
                                <?php echo !empty($userRank['username_suffix']) ? ' ' . $userRank['username_suffix'] : ''; ?>
                            </span>
                            <?php if (!empty($userRank['html'])) { echo ' ' . $userRank['html']; } ?>
                        </a>
                    </strong>
                <?php else: ?>
                    <strong><a href="user.php?id=<?php echo $pasteUser['id']; ?>" class="hover:underline">
                    <?php if (!empty($paste['name_color'])): ?>
                        <strong><span style="color: <?php echo htmlspecialchars($paste['name_color']); ?>;"><?php echo htmlspecialchars($pasteUser['username']); ?></span></strong>
                    <?php else: ?>
                        <?php echo htmlspecialchars($pasteUser['username']); ?>
                    <?php endif; ?>
                    </a></strong>
                <?php endif; ?>
                <?php if (!empty($pasteUser['emoji'])): ?>
                    <span style="display: inline-flex; align-items: center; vertical-align: middle; margin-bottom: 0.4rem; justify-content: center;">
                        <?php echo displayUserEmoji($pasteUser['emoji']); ?>
                    </span>
                <?php endif; ?>
            </span>
        <?php else: ?>
            <span style="color: white;">Unknown user</span>
        <?php endif; ?>
    <?php else: ?>
        <span class="text-accent">by Anonymous</span>
    <?php endif; ?>
</td>
            <td class="py-0.5 px-1 text-center text-white">
                <?php echo formatTimeAgo($paste['created_at']); ?>
            </td>
        </tr>
        
        <!-- Add separator after the LAST pinned element -->
        <?php if ($index === $lastPinnedIndex): ?>
        <tr>
            <td colspan="5" style="border-top: 1px solid white; padding: 0;"></td>
        </tr>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Render normal pastes -->
    <?php foreach ($normalPastes as $paste): ?>
        <tr class="hover:bg-[#1a1d30]" style="border-bottom: 1px solid white;">
            <td class="py-0.5 px-1 text-center">
                <a href="view.php?id=<?php echo $paste['id']; ?>" 
                   class="text-white hover:text-white font-bold text-center" 
                   style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; color: white !important; text-decoration: underline !important; text-decoration-color: white !important;">
                    <?php echo htmlspecialchars($paste['title']); ?>
                </a>
            </td>
            <td class="py-0.5 px-1 text-center text-white"><?php echo number_format($paste['views']); ?></td>
            <td class="py-0.5 px-1 text-center text-white">
                <?php 
                $rating = ($paste['likes'] ?? 0) - ($paste['dislikes'] ?? 0);
                $color = $rating > 0 ? 'text-green-500' : ($rating < 0 ? 'text-red-500' : 'text-gray-500');
                echo "<span class='$color'>" . number_format($rating) . "</span>";
                ?>
            </td>
            <td class="py-0.5 px-1 text-center text-white">
    <?php if ($paste['user_id']): ?>
        <?php $pasteUser = getUserById($paste['user_id']); ?>
        <?php if ($pasteUser): ?>
            <span style="color: white;">
                by 
                <?php $userRank = getUserRank($pasteUser['id']); ?>
                <strong>
    <a href="user.php?id=<?php echo htmlspecialchars($pasteUser['id']); ?>" class="hover:underline" style="font-weight: bold !important;">
        <?php
            // Build attributes combining classes and potential style
            $attrParts = [];
            $classes = [];
            $classxD = getNameColor($pasteUser['id']);
            if (!empty($classxD)) { $classes[] = $classxD; }
            if (!empty($userRank['username_class'])) { $classes[] = $userRank['username_class']; }
            if (!empty($classes)) { $attrParts[] = 'class="' . htmlspecialchars(implode(' ', $classes)) . '"'; }
            if (!empty($userRank['username_style'])) { $attrParts[] = 'style="' . htmlspecialchars($userRank['username_style']) . '"'; }
            $wrapperAttr = implode(' ', $attrParts);
        ?>
        <span <?php echo $wrapperAttr; ?>>
            <?php echo !empty($userRank['username_prefix']) ? $userRank['username_prefix'] . ' ' : ''; ?>
            <?php echo htmlspecialchars($pasteUser['username']); ?>
            <?php echo !empty($userRank['username_suffix']) ? ' ' . $userRank['username_suffix'] : ''; ?>
        </span>
        <?php if (!empty($userRank['html'])) { echo ' ' . $userRank['html']; } ?>
    </a>
    <?php if (!empty($pasteUser['emoji'])): ?>
        <span style="display: inline-flex; align-items: center; vertical-align: middle; margin-bottom: 0.4rem; justify-content: center;">
            <?php echo displayUserEmoji($pasteUser['emoji']); ?>
        </span>
    <?php endif; ?>
</strong>


            </span>
        <?php else: ?>
            <strong><span style="color: white;">Unknown user</span></strong>
        <?php endif; ?>
    <?php else: ?>
        <strong><span class="text-accent">by Anonymous</span></strong>
    <?php endif; ?>
</td>
            <td class="py-0.5 px-1 text-center text-white"><?php echo formatTimeAgo($paste['created_at']); ?></td>
        </tr>
    <?php endforeach; ?>
</tbody>

                                    </table>
                    
                    <!-- Mobile Card Layout (Hidden on Desktop) -->
                    <div class="mobile-only paste-card-container">
                        <?php foreach ($pastes as $paste): ?>
                            <div class="paste-card">
                                <div class="paste-card-title">
                                    <a href="view.php?id=<?php echo $paste['id']; ?>" class="text-primary hover:underline">
                                        <?php echo htmlspecialchars($paste['title']); ?>
                                    </a>
                                    <span class="text-sm text-yellow-400 ml-2">👍 <?php echo $paste['likes']; ?></span>
                                </div>
                                
                                <div class="paste-card-meta">
                                    <span>
                                        <?php if ($paste['user_id']): ?>
                                            <?php $pasteUser = getUserById($paste['user_id']); ?>
                                            <?php if ($pasteUser): ?>
                                                by <strong><?php echo htmlspecialchars($pasteUser['username']); ?></strong>
                                            <?php else: ?>
                                                by Unknown user
                                            <?php endif; ?>
                                        <?php else: ?>
                                            by Anonymous
                                        <?php endif; ?>
                                    </span>
                                    <span><?php echo formatTimeAgo($paste['created_at']); ?></span>
                                </div>
                                
                                <div class="paste-card-preview">
                                    <?php echo htmlspecialchars(substr($paste['content'], 0, 100)) . (strlen($paste['content']) > 100 ? '...' : ''); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
            </div>
        </div><!-- end of dark panel: ends directly under table/cards -->

    <!-- Pagination OUTSIDE panel -->
    <?php echo renderPagination($page, $totalPages, 'top.php'); ?>

    <!-- Green separator line -->
    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] my-4" style="border-bottom: 1px solid #00ff9d;"></div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="js/main.js"></script>
    <script>
        // Dynamic search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const tableRows = document.querySelectorAll('tbody tr:not([data-separator])');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase().trim();
                    
                    tableRows.forEach(row => {
                        const titleElement = row.querySelector('td:first-child a');
                        if (titleElement) {
                            const title = titleElement.textContent.toLowerCase();
                            if (title.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        }
                    });
                    
                    // Update pagination visibility based on search
                    const paginationContainer = document.querySelector('.flex.justify-center.overflow-x-auto');
                    if (searchTerm.length > 0) {
                        if (paginationContainer) {
                            paginationContainer.style.display = 'none';
                        }
                    } else {
                        if (paginationContainer) {
                            paginationContainer.style.display = 'flex';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
