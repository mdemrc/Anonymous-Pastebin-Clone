<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// If the user is already logged in, redirect to the main page
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Processing the login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cloudflare Turnstile Validation
    $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
    if (empty($turnstileResponse)) {
        $error = 'Please complete the security check.';
    } else {
        // Verify Turnstile with Cloudflare
        $verifyURL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => '0x4AAAAAABQIYal5FFWny978crIbX4aYBFA',
            'response' => $turnstileResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($verifyURL, false, $context);
        $resultData = json_decode($result, true);

        if (!$result || !isset($resultData['success']) || !$resultData['success']) {
            $error = 'Security verification failed. Please try again.';
        }
    }

    if (!isset($error)) {
        $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields';
        } else {
            try {
                if (loginUser($username, $password)) {
                    // Save credentials if "Remember Me" is checked
                    // Session Lifetime
                    $lifetime = 60 * 60 * 24 * 7; // 7 days
                    
                    if ($rememberMe) {
                        // Old UX cookies (optional autofill) - 10 years
                        $foreverLifetime = 60 * 60 * 24 * 365 * 10; // 10 years
                        setcookie('saved_username', $username, time() + $foreverLifetime, "/"); 
                        setcookie('saved_password', base64_encode($password), time() + $foreverLifetime, "/");

                        // Persistent login cookie - 10 years (essentially forever until logout)
                        createPersistentLogin($_SESSION['user_id'], 3650);
                    } else {
                        setcookie('saved_username', '', time() - 3600, "/"); // Clear cookies
                        setcookie('saved_password', '', time() - 3600, "/");
                        // Ensure persistent login is cleared if previously set
                        clearPersistentLogin();
                    }

                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid username or password';
                }
            } catch (Exception $e) {
                $error = 'Login error: ' . $e->getMessage();
            }
        }
    }
}

// Getting random banners and ad texts
$banners = getRandomBanners(2);
$bannerTexts = getRandomBannerTexts(2);

// Generating a CSRF token
$csrf_token = generateCSRFToken();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>example.com - Login</title>
    
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
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
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

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        label {
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .hint-text {
            font-size: 12px;
            color: #666;
            letter-spacing: .2px;
        }
        
        .error-message {
            color: #ff3333;
            background-color: rgba(255, 51, 51, 0.1);
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-background min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto flex justify-center pt-[50px]">
        <div class="w-full max-w-[1200px] flex flex-col items-center">
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
    <?php $i = 0; foreach ($bannerTexts as $bannerText): ?>
        <div class="banner-text">
            <a href="redirect.php?type=text&id=<?php echo $bannerText['id']; ?>&url=<?php echo urlencode($bannerText['url']); ?>" target="_blank">
                <?php $gradientStyle = ($i == 0) 
                    ? "background-image: linear-gradient(to right, #ff0000, #ff3366, #ff66cc, #ff99ff, #ff66cc, #ff3366, #ff0000);" 
                    : "background-image: linear-gradient(to right, #00ffff, #00bfff, #0080ff, #8a2be2, #ff00ff);";
                $gradientStyle .= " background-size: 200% auto; animation: shine 2s linear infinite; -webkit-background-clip: text; background-clip: text; color: transparent; font-weight: bold;";
                ?>
                <span class="font-bold text-lg" style="<?php echo $gradientStyle; ?>">
                    <?php echo $bannerText['text']; ?>
                </span>
            </a>
        </div>
    <?php $i++; endforeach; ?>
</div>

            <div class="w-[410px] max-w-full bg-backgroundSecondary rounded-lg p-8">
                <h2 class="text-textColor font-bold text-3xl mb-2 tracking-wide text-center" style="border-bottom: 3px solid #00ff9d;">Login to Account</h2> 
                <div class="border-b-4 border-[#00ff9d] mb-8"></div>
                
                
                <?php if (isset($error)): ?>
                    <div class="error-message mb-4">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div>
                        <label for="username" class="block text-textColor mb-2">Username</label>
                        <input type="text" id="username" name="username" class="w-full bg-background border border-primary/50 rounded p-2 text-textColor focus:border-primary outline-none" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : (isset($_COOKIE['saved_username']) ? htmlspecialchars($_COOKIE['saved_username']) : ''); ?>" required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-textColor mb-2">Password</label>
                        <input type="password" id="password" name="password" class="w-full bg-background border border-primary/50 rounded p-2 text-textColor focus:border-primary outline-none"
                            value="<?php echo isset($_COOKIE['saved_password']) ? base64_decode($_COOKIE['saved_password']) : ''; ?>" required>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="remember_me" name="remember_me" class="mr-2" <?php echo isset($_COOKIE['saved_username']) ? 'checked' : ''; ?>>
                        <label for="remember_me" class="text-textColor text-sm">Remember Me</label>
                    </div>

                    <!-- Cloudflare Turnstile -->
                    <div class="cf-turnstile" data-sitekey="0x4AAAAAABQIYSDzB006i8rQ"></div>
                    
                    <button type="submit" class="w-full bg-primary text-black font-bold py-2 px-4 rounded hover:opacity-90 transition-opacity">
                        Login
                    </button>
                    
                    <p class="text-center text-textColor mt-4">
                        Don't have an account? <a href="register.php" class="text-primary hover:underline">Register</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
    
    <br></br>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
