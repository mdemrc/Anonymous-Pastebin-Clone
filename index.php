<?php
// Запуск сессии перед любым выводом
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>example.com</title>
    
    <meta name="keywords" content="example.com, pastehub, pastebin, pastebin alternative, free, proxies, configs, anonfiles, leaks, leaked, bayfiles, ghostbin, cracked, accounts, files, paste">
    <meta name="author" content="example.com">
    <meta name="copyright" content="example.com">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    <meta property="og:description" content="example.com is the best free pasting website, and pastebin alternative in 2025. you can use it to store text online for easy sharing.">
<meta name="twitter:description" content="example.com is the best free pasting website, and pastebin alternative in 2025. you can use it to store text online for easy sharing.">
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <link href="css/responsive.css" rel="stylesheet" type="text/css">
    <link href="css/mobile-responsive.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css">
    <link href="css/dark.css?v=100" rel="stylesheet" type="text/css">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    
    <style>
        /* Стили для кнопки Paste */
        .paste-button {
            background-color: #00ff9d !important;
            color: #fff !important;
            padding: 12px 30px !important;
            border-radius: 10px !important;
            font-family: 'Source Code Pro', monospace !important;
            font-size: 20px !important;
            font-weight: bold !important;
            border: none !important;
            cursor: pointer !important;
            width: 100% !important; /* На всю длину */
            text-align: center !important; /* Выравнивание текста по центру */
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
        }
        
        .paste-button:hover {
            background-color: #32ffb6 !important;
        }
    </style>
    
    <style>
        body {
            background-color: #151529;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 40px;
            font-size: 14px;
        }

        .main-container {
            max-width: 100%;
            margin: 0 auto;  /* Смещаем контейнер влево, используя процентный отступ слева */
            padding: 15px;
            display: flex;
            gap: 15px;
        }

        .sidebar {
            width: 300px;
            flex-shrink: 0;
            padding-left: 10px;
        }

        .editor-container {
            flex-grow: 1;
            display: flex;
            justify-content: center;
        }

        /* Стили для редактора */
        .CodeMirror {
            height: 524px !important;
            width: 964px !important;
            background: #1d1e3a !important;
            color: #fff !important;
            border-radius: 8px !important;
            font-family: 'Source Code Pro', monospace !important;
            font-size: 16px !important;
        }

        /* Убираем фон у номеров строк */
        .CodeMirror-gutters {
            display: block !important; /* Make gutters visible again */
            width: auto !important; /* Allow it to adjust */
}

        .CodeMirror-linenumber {
            padding: 0 3px 0 5px !important;
            min-width: 20px !important;
            text-align: right !important;
            color: #999 !important;
            white-space: nowrap !important;
            
        }

        .CodeMirror-cursor {
            border-left: 1px solid #00ff9d !important;
        }

        .CodeMirror-selected {
            background: #2d2e4a !important;
        }

        .CodeMirror-line {
            padding: 0 8px 0 8px !important;
        }

        /* Остальные стили */
        .select-custom {
            width: 100%;
            min-width: 250px;
            padding: 10px 15px;
            background: #1d1e3a;
            color: #fff;
            border: none;
            border-radius: 10px;
            margin-bottom: 15px;
            cursor: pointer;
            font-size: 15px;
            height: 45px;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .select-custom:hover {
            background: #252644;
        }

        .select-custom:focus {
            outline: none;
            border: none;
            box-shadow: none;
            background: #1d1e3a;
            color: #fff;
        }

        input.select-custom {
            height: 45px;
            font-size: 15.5px;
            font-family: 'Source Code Pro', monospace !important;
        }

        input.select-custom::placeholder {
            color: rgba(255, 255, 255, 0.3) !important;
            font-family: 'Source Code Pro', monospace !important;
        }

        input[name="title"] {
            background: #1d1e3a;
            color: #fff !important;
            font-size: 15.5px;
            font-family: 'Source Code Pro', monospace !important;
        }

        input[name="title"]::placeholder {
            color: rgba(255, 255, 255, 0.3) !important;
            font-family: 'Source Code Pro', monospace !important;
        }

        select.select-custom {
            height: 45px;
            font-size: 15.5px;
            font-family: 'Source Code Pro', monospace !important;
            background: #1d1e3a;
            color: #fff;
            border-radius: 10px;
            border: none;
            outline: none;
        }
        
        select.select-custom option {
            background: #1d1e3a;
            color: #fff;
            font-family: 'Source Code Pro', monospace !important;
            font-size: 15.5px;
        }

        .title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin-top: 25px;
            margin-bottom: 25px;
            color: #fff;
            font-family: 'Source Code Pro', monospace !important;
            display: inline-block;
            border-bottom: 1px solid #00ff9d;
            border-bottom-width: 1px;
            width: 400px;
            padding-bottom: 5px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .title-container {
            text-align: center;
            width: 100%;
        }

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
  
 .supreme_rank {
    color: #54FF9F;
    text-shadow: 1px 1px 10px #39c70d;
    background-image: url(https://web.archive.org/web/20220205011522im_/https://static.cracked.to/images/bg1.gif)
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

</style>

</head>
<body class="bg-background min-h-screen flex flex-col">
    <?php
    require_once 'includes/config.php';
    require_once 'includes/functions.php';

    // Включаем вывод ошибок для отладки
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    // Генерируем CSRF токен для формы
    $csrf_token = generateCSRFToken();
    echo "<!-- CSRF Token: " . $csrf_token . " -->"; // Для отладки

    // Получаем случайные баннеры
    $banners = getRandomBanners();
    
    // Получаем случайные текстовые баннеры
    $bannerTexts = getRandomBannerTexts();
    ?>
    
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

    <div class="title-container" style="margin-top: -20px;">
        <h1 class="title">Create Paste</h1>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <form action="create.php" method="POST" id="pasteForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="content" id="pasteContent">
                
                <input type="text" 
                       name="title" 
                       placeholder="Untitled Paste" 
                       class="select-custom"
                       style="color: #fff !important; background: #1d1e3a !important; outline: none !important; box-shadow: none !important; border: none !important; border-radius: 10px !important;">

                <select name="syntax" class="select-custom">
                    <option value="text">No Syntax</option>
                    <option value="html">HTML</option>
                    <option value="javascript">JavaScript</option>
                    <option value="php">PHP</option>
                    <option value="python">Python</option>
                    <option value="sql">SQL</option>
                    <option value="xml">XML</option>
                    <option value="css">CSS</option>
                    <option value="shell">Shell</option>
                    <option value="clike">C++</option>
                </select>

                <select name="expiration" class="select-custom">
                    <option value="never">Never Expires</option>
                    <option value="30min">30 Minutes</option>
                    <option value="1hour">1 Hour</option>
                    <option value="12hours">12 Hours</option>
                    <option value="1day">1 Day</option>
                    <option value="3days">3 Days</option>
                    <option value="1month">1 Month</option>
                </select>

                <select class="select-custom" name="visibility" id="visibilitySelect" style="width: 100%; min-width: 250px; padding: 10px 15px; background: #1d1e3a; border-radius: 10px; font-family: 'Source Code Pro', monospace; font-size: 15.5px;">
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
                
                <!-- Password field (initially hidden) -->
                <div id="passwordFieldContainer" style="display: none; margin-top: 10px;">
                    <input type="password" 
                           name="password" 
                           placeholder="Password" 
                           class="select-custom"
                           style="width: 100%; color: #fff !important; background: #1d1e3a !important; outline: none !important; box-shadow: none !important; border: none !important; border-radius: 10px !important;">
                </div>

                <button type="submit" class="paste-button">Paste</button>
            </form>
        </div>

        <div class="editor-container">
            <textarea id="editor" name="content"></textarea>
        </div>
    </div>

    <!-- Green separator line -->
    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] my-4" style="border-bottom: 1px solid #00ff9d;"></div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/shell/shell.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>

    <script>
        var editor = CodeMirror.fromTextArea(document.getElementById("editor"), {
            lineNumbers: true,
            mode: "text",
            theme: "default",
            lineWrapping: false, // Disable wrapping for better performance
            viewportMargin: 50, // Only render 50 lines above/below viewport (NOT Infinity!)
            tabSize: 4,
            indentUnit: 4,
            indentWithTabs: true,
            gutters: ["CodeMirror-linenumbers"],
            // Performance optimizations for large files
            maxHighlightLength: 10000, // Limit syntax highlighting
            workDelay: 200 // Delay before processing
        });

        // Set size after initialization
        editor.setSize(964, 524);

        document.querySelector('select[name="syntax"]').addEventListener('change', function(e) {
            editor.setOption("mode", e.target.value);
        });

        document.querySelector('#pasteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            console.log("Form submission intercepted");
            
            // Log all form data
            const formData = new FormData(this);
            for (const [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            // Continue with submission
            var content = editor.getValue();
            document.getElementById("pasteContent").value = content;
            this.submit();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const visibilitySelect = document.getElementById('visibilitySelect');
            const passwordFieldContainer = document.getElementById('passwordFieldContainer');
            
            visibilitySelect.addEventListener('change', function() {
                if (this.value === 'private') {
                    passwordFieldContainer.style.display = 'block';
                } else {
                    passwordFieldContainer.style.display = 'none';
                }
            });
            
            visibilitySelect.dispatchEvent(new Event('change'));
        });
    </script>
    <script>
        // JavaScript для управления hover-эффектом кнопки Paste
        document.addEventListener('DOMContentLoaded', function() {
            const pasteButton = document.querySelector('.paste-button');
            
            if (pasteButton) {
                // Сохраняем оригинальный цвет фона (зеленый)
                const originalBg = '#00ff9d';
                // Цвет фона при наведении (светлее зеленого)
                const hoverBg = '#32ffb6';
                
                pasteButton.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = hoverBg;
                });
                
                pasteButton.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = originalBg;
                });

                // Устанавливаем начальный стиль
                pasteButton.style.backgroundColor = originalBg;
                pasteButton.style.color = '#fff';
                pasteButton.style.borderRadius = '10px';
                pasteButton.style.fontWeight = '700';
                pasteButton.style.fontSize = '16px';
                pasteButton.style.textTransform = 'uppercase';
                pasteButton.style.letterSpacing = '1px';
            }
        });
    </script>
</body>
</html>
