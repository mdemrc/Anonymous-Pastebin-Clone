<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? htmlspecialchars($title) . ' - ' : ''; ?>example.com</title>
    
    <!-- Tailwind CSS -->
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

    <script>
    // Auto-fit banner text lines: keep single-line containers on one row by shrinking text if needed
    document.addEventListener('DOMContentLoaded', function() {
        function fitSingleLineBannerTexts() {
            const containers = document.querySelectorAll('.banner-text-container.single-line');
            containers.forEach(container => {
                // Reset any inline font-size overrides first
                const textNodes = container.querySelectorAll('.banner-text span, .banner-text a, .banner-text a span');
                let baseFont = 0;
                textNodes.forEach(node => {
                    // remove any previous override
                    node.style.removeProperty('font-size');
                    const fs = parseFloat(getComputedStyle(node).fontSize);
                    if (fs > baseFont) baseFont = fs;
                });

                // Measure total line width including gaps
                const children = Array.from(container.querySelectorAll('.banner-text'));
                if (children.length === 0) return;

                // Ensure no wrap and hidden overflow
                container.style.whiteSpace = 'nowrap';
                container.style.overflow = 'hidden';

                const gap = parseFloat(getComputedStyle(container).gap || '0') || 0;
                let total = 0;
                children.forEach(ch => {
                    const rect = ch.getBoundingClientRect();
                    // Include margins
                    const cs = getComputedStyle(ch);
                    const ml = parseFloat(cs.marginLeft) || 0;
                    const mr = parseFloat(cs.marginRight) || 0;
                    total += rect.width + ml + mr;
                });
                total += gap * Math.max(0, children.length - 1);

                const available = container.clientWidth || container.getBoundingClientRect().width;
                if (!available || total <= available) {
                    return; // fits already
                }

                // Compute scale and apply by reducing font-size on text nodes
                let scale = available / total;
                scale = Math.min(1, Math.max(0.6, scale)); // don't go below 60%
                const targetFont = Math.max(10, Math.floor(baseFont * scale));
                textNodes.forEach(node => {
                    node.style.setProperty('font-size', targetFont + 'px', 'important');
                });
            });
        }

        // Initial and after slight delay (for fonts/layout)
        fitSingleLineBannerTexts();
        setTimeout(fitSingleLineBannerTexts, 50);
        setTimeout(fitSingleLineBannerTexts, 150);
        window.addEventListener('resize', () => {
            clearTimeout(window.__fitSingleLineTimer);
            window.__fitSingleLineTimer = setTimeout(fitSingleLineBannerTexts, 100);
        });
    });
    </script>
    <!-- Основные стили -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/dark.css" rel="stylesheet">
    <link href="css/common.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="css/mobile-responsive.css" rel="stylesheet">
    <link href="css/responsive.css" rel="stylesheet">
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    <link href="css/fonts.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;700&display=swap" rel="stylesheet">

    <!-- CodeMirror CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/material-palenight.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/scroll/simplescrollbars.min.css">
    
    <!-- CodeMirror основной скрипт -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    
    <!-- CodeMirror режимы -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/shell/shell.min.js"></script>
    
    <!-- CodeMirror аддоны -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/selection/active-line.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/matchbrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/closebrackets.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/scroll/simplescrollbars.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/searchcursor.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/search.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/search/jump-to-line.min.js"></script>

    <style>
        /* Основные стили */
        body {
            font-family: 'JetBrains Mono', monospace;
            color: #ffffff;
        }
        
        /* Стили для навигации */
        .nav-link {
            color: #ffffff;
            text-decoration: none;
            font-family: 'Source Code Pro', monospace;
            font-size: 20.30px;
            font-weight: 1000;
            transition: color 0.3s;
        }
        
        .nav-link:hover {
            color: #00ff9d;
        }
        
        .nav-link.active {
            color: #00ff9d;
            font-weight: bold;
        }
        
        nav {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        
        /* Золотой градиент для текста */
        @keyframes rainbow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .rainbow-text {
            background-image: linear-gradient(to right, #ffd700, #ffb700, #ff8c00, #ffb700, #ffd700);
            background-size: 200% auto;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: bold;
            animation: rainbow 4s linear infinite;
        }

        /* VIP glows (for username and prefix) */
        .vip-glow {
            color: #00ff9d;
            font-weight: 800;
            text-shadow:
                0 0 4px rgba(0, 255, 157, 0.85),
                0 0 10px rgba(0, 255, 157, 0.6),
                0 0 18px rgba(0, 255, 157, 0.35);
        }
        .vipplus-glow {
            color: #9dffeb;
            font-weight: 900;
            text-shadow:
                0 0 5px rgba(0, 255, 200, 0.95),
                0 0 12px rgba(0, 255, 200, 0.7),
                0 0 22px rgba(0, 255, 200, 0.45),
                0 0 34px rgba(0, 255, 200, 0.25);
        }
        /* optional small gap when prefix precedes username inside the same span */
        .prefix-gap::after { content: ' '; }
    </style>
    
    <script>
    // NUCLEAR FORCE - Make EVERY banner/ad text BOLD on EVERY page
    document.addEventListener('DOMContentLoaded', function() {
        function forceAllBannerTextsBold() {
            // Target EVERY possible banner/ad text selector
            const selectors = [
                '.banner-text-container *',
                '.banner-text *',
                '.banner-text-container span',
                '.banner-text-container a',
                '.banner-text span',
                '.banner-text a',
                '[class*="banner"] *',
                '[class*="ad-text"] *',
                '[class*="adtext"] *',
                '[id*="banner"] *',
                '[class*="gradient"] span',
                '[class*="gradient"] a',
                'div[class*="banner-text"] *'
            ];
            
            selectors.forEach(function(selector) {
                try {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(function(element) {
                        if (element && element.style !== undefined) {
                            element.style.setProperty('font-weight', '900', 'important');
                            element.style.setProperty('font-family', 'Source Code Pro, monospace', 'important');
                        }
                    });
                } catch(e) {
                    console.log('Banner force selector failed:', selector);
                }
            });
            
            // Also force any text content that looks like ads
            const allElements = document.querySelectorAll('*');
            allElements.forEach(function(element) {
                if (element.className && (
                    element.className.includes('banner') || 
                    element.className.includes('ad-text') ||
                    element.className.includes('gradient') ||
                    element.className.includes('adtext')
                )) {
                    element.style.setProperty('font-weight', '900', 'important');
                    element.style.setProperty('font-family', 'Source Code Pro, monospace', 'important');
                }
            });
        }
        
        // Run multiple times to catch everything
        forceAllBannerTextsBold();
        setTimeout(forceAllBannerTextsBold, 50);
        setTimeout(forceAllBannerTextsBold, 100);
        setTimeout(forceAllBannerTextsBold, 200);
        setTimeout(forceAllBannerTextsBold, 500);
        setTimeout(forceAllBannerTextsBold, 1000);
        
        // Also run when page content changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    setTimeout(forceAllBannerTextsBold, 10);
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    
    <script>
    // Mobile menu toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.querySelector('.mobile-menu-button');
        const mobileMenu = document.querySelector('.mobile-menu');
        const mobileMenuClose = document.querySelector('.mobile-menu-close');
        
        if (mobileMenuButton && mobileMenu) {
            // Open menu
            mobileMenuButton.addEventListener('click', function() {
                mobileMenuButton.classList.add('active');
                mobileMenu.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
            
            // Close menu
            function closeMenu() {
                mobileMenuButton.classList.remove('active');
                mobileMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            if (mobileMenuClose) {
                mobileMenuClose.addEventListener('click', closeMenu);
            }
            
            // Close menu when clicking outside
            mobileMenu.addEventListener('click', function(e) {
                if (e.target === mobileMenu) {
                    closeMenu();
                }
            });
            
            // Close menu on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mobileMenu.classList.contains('active')) {
                    closeMenu();
                }
            });
        }
    });
    </script>
</head>
<body class="bg-background min-h-screen flex flex-col">
    <nav class="bg-[#1d1e3a] w-full h-[50px] flex justify-center items-center">
        <div class="flex items-center justify-center space-x-6 max-w-[1480px] w-full px-4">
            <a href="index.php" class="nav-link text-xl <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                Create Paste
            </a>
            <a href="top.php" class="nav-link text-xl <?php echo basename($_SERVER['PHP_SELF']) === 'top.php' ? 'active' : ''; ?>">
                Top Pastes
            </a>
            <a href="recent.php" class="nav-link text-xl <?php echo basename($_SERVER['PHP_SELF']) === 'recent.php' ? 'active' : ''; ?>">
                Recent Pastes
            </a>
            <a href="search.php" class="nav-link text-xl <?php echo basename($_SERVER['PHP_SELF']) === 'search.php' ? 'active' : ''; ?>">
                Search
            </a>
            <a href="settings.php" class="nav-link text-xl <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                Settings
            </a>
            <a href="<?php echo isLoggedIn() ? 'profile.php' : 'login.php'; ?>" class="nav-link text-xl <?php echo (basename($_SERVER['PHP_SELF']) === 'profile.php' || basename($_SERVER['PHP_SELF']) === 'login.php') ? 'active' : ''; ?>">
                Account
            </a>
        </div>
        
        <!-- Mobile hamburger button -->
        <button class="mobile-menu-button" aria-label="Open menu" type="button">
            <div class="hamburger-icon" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </nav>

    <!-- Mobile menu overlay -->
    <div class="mobile-menu">
        <div class="mobile-menu-header">
            <div class="mobile-menu-title">Navigation</div>
            <div class="mobile-menu-subtitle">Select an option</div>
        </div>
        
        <button class="mobile-menu-close" aria-label="Close menu" type="button">×</button>
        
        <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
            Create Paste
        </a>
        <a href="top.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'top.php' ? 'active' : ''; ?>">
            Top Pastes
        </a>
        <a href="recent.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'recent.php' ? 'active' : ''; ?>">
            Recent Pastes
        </a>
        <a href="search.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'search.php' ? 'active' : ''; ?>">
            Search
        </a>
        <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
            Settings
        </a>
        <a href="<?php echo isLoggedIn() ? 'profile.php' : 'login.php'; ?>" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) === 'profile.php' || basename($_SERVER['PHP_SELF']) === 'login.php') ? 'active' : ''; ?>">
            Account
        </a>
        
        <div class="mobile-menu-footer">
            example.com © 2024
        </div>
    </div>