<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get user information
$user = getUserById($_SESSION['user_id']);

// Current tab
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'api' ? 'api' : 'settings';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security error. Please try again.';
    } else {
        // Handle API key actions
        if (isset($_POST['api_action'])) {
            $action = $_POST['api_action'];
            
            if ($action === 'generate') {
                $newKey = generateApiKey($_SESSION['user_id']);
                if ($newKey) {
                    $api_success = 'API key generated successfully!';
                    $user = getUserById($_SESSION['user_id']); // Refresh user data
                } else {
                    $api_error = 'Failed to generate API key. Please try again.';
                }
            } elseif ($action === 'regenerate') {
                $newKey = generateApiKey($_SESSION['user_id']);
                if ($newKey) {
                    $api_success = 'API key regenerated successfully! Your old key is now invalid.';
                    $user = getUserById($_SESSION['user_id']);
                } else {
                    $api_error = 'Failed to regenerate API key. Please try again.';
                }
            } elseif ($action === 'revoke') {
                if (revokeApiKey($_SESSION['user_id'])) {
                    $api_success = 'API key revoked successfully.';
                    $user = getUserById($_SESSION['user_id']);
                } else {
                    $api_error = 'Failed to revoke API key. Please try again.';
                }
            }
            
            $activeTab = 'api';
        } else {
            // Handle regular settings
            $default_visibility = $_POST['default_visibility'] ?? 'public';
            $telegram = trim($_POST['telegram'] ?? '');
            $discord = trim($_POST['discord'] ?? '');
            $website = trim($_POST['website'] ?? '');
            $avatar_url = trim($_POST['avatar_url'] ?? '');
            $cover_url = trim($_POST['cover_url'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            
            // Validate visibility value
            if (!in_array($default_visibility, ['public', 'private'])) {
                $errors[] = 'Invalid visibility value';
            }
            
            // Validate avatar URL if provided
            if (!empty($avatar_url)) {
                if (!filter_var($avatar_url, FILTER_VALIDATE_URL)) {
                    $errors[] = 'Please enter a valid avatar image URL';
                }
            }
            
            // Validate cover URL if provided
            if (!empty($cover_url)) {
                if (!filter_var($cover_url, FILTER_VALIDATE_URL)) {
                    $errors[] = 'Please enter a valid cover image URL';
                }
            }
            
            // Validate bio length
            if (strlen($bio) > 300) {
                $errors[] = 'Bio must be 300 characters or less';
            }
            
            // Normalize and validate Telegram: store only @username
            if (!empty($telegram)) {
                // If full URL like https://t.me/username or t.me/username, extract username
                if (preg_match('~^(?:https?://)?(?:t\.me|telegram\.me)/([A-Za-z0-9_]{3,})$~i', $telegram, $m)) {
                    $telegram = '@' . $m[1];
                }
                // If they entered without @, add it
                if ($telegram[0] !== '@') {
                    $telegram = '@' . ltrim($telegram, '@');
                }
                // Validate final handle: @ + letters, digits, underscore, 3-32 chars (Telegram typical)
                if (!preg_match('/^@[A-Za-z0-9_]{3,32}$/', $telegram)) {
                    $errors[] = 'Please enter a valid Telegram handle like @username (3-32 letters, digits, or underscores).';
                }
            }

            // Validate website URL if provided
            if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
                $errors[] = 'Please enter a valid website URL';
            }
            
            // Update settings
            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE users SET default_visibility = ?, telegram = ?, discord = ?, website = ?, avatar_url = ?, cover_url = ?, bio = ? WHERE id = ?");
                if ($stmt->execute([$default_visibility, $telegram, $discord, $website, $avatar_url ?: null, $cover_url ?: null, $bio ?: null, $_SESSION['user_id']])) {
                    $success = 'Settings updated successfully';
                    // Refresh user data
                    $user = getUserById($_SESSION['user_id']);
                } else {
                    $errors[] = 'Error updating settings';
                }
            }
        }
    }
}

// Get API stats
$apiStats = getApiStats($_SESSION['user_id']);

// Get random banners and banner texts
$banners = getRandomBanners(2);
$bannerTexts = getRandomBannerTexts(2);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>example.com - Settings</title>
    
    <meta name="keywords" content="example.com, pastehub, pastebin, pastebin alternative, free, proxies, configs, anonfiles, leaks, leaked, bayfiles, ghostbin, cracked, accounts, files, paste">
    <meta name="author" content="example.com">
    <meta name="copyright" content="example.com">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    
    <link href="css/style.css" rel="stylesheet" type="text/css">
    
    <!-- Mobile Settings Responsive Styles -->
    <style>
        /* Settings Mobile Fix - Inline */
        @media screen and (max-width: 479px) {
            .w-\[400px\], .w-\[500px\] {
                width: calc(100vw - 30px) !important;
                max-width: calc(100vw - 30px) !important;
                margin-left: 15px !important;
                margin-right: 15px !important;
                box-sizing: border-box !important;
            }
            
            .border-\[#00ff9d\].min-w-\[400px\] {
                width: calc(100vw - 30px) !important;
                margin-left: 15px !important;
                margin-right: 15px !important;
                box-sizing: border-box !important;
            }
            
            .w-\[400px\] input, .w-\[500px\] input,
            .w-\[400px\] select, .w-\[500px\] select {
                width: 100% !important;
                box-sizing: border-box !important;
            }
            
            .w-\[400px\] .bg-primary, .w-\[500px\] .bg-primary {
                width: 100% !important;
            }
            
            .tab-btn {
                padding: 0.5rem 1rem !important;
                font-size: 0.875rem !important;
            }
            
            pre {
                font-size: 10px !important;
            }
        }
        
        @media screen and (min-width: 480px) and (max-width: 767px) {
            .w-\[400px\], .w-\[500px\] {
                width: 90vw !important;
                max-width: 500px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                box-sizing: border-box !important;
            }
            
            .border-\[#00ff9d\].min-w-\[400px\] {
                width: 90vw !important;
                max-width: 500px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                box-sizing: border-box !important;
            }
        }
        
        @media screen and (max-width: 319px) {
            .w-\[400px\], .w-\[500px\] {
                width: 98vw !important;
                margin-left: 1vw !important;
                margin-right: 1vw !important;
                padding: 1rem !important;
                box-sizing: border-box !important;
            }
            
            .border-\[#00ff9d\].min-w-\[400px\] {
                width: 98vw !important;
                margin-left: 1vw !important;
                margin-right: 1vw !important;
                box-sizing: border-box !important;
            }
        }
    </style>
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
        /* Emoji styles */
        .emoji-picker {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .emoji-option {
            font-size: 24px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #151529;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .emoji-option:hover {
            transform: scale(1.1);
        }
        
        .emoji-option.selected {
            background-color: rgba(0, 255, 157, 0.2);
            border: 1px solid rgba(0, 255, 157, 0.5);
        }
        
        /* Radio button styles */
        .radio-option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            cursor: pointer;
        }
        
        .radio-option input[type="radio"] {
            margin-right: 10px;
            accent-color: #00ff9d;
        }
        
        .success-message {
            color: #00ff9d;
            background-color: rgba(0, 255, 157, 0.1);
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
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
        .error-message {
            color: #ff3333;
            background-color: rgba(255, 51, 51, 0.1);
            padding: 10px;
            border-radius: 5px;
            font-size: 14px;
        }
        
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
    </style>
</head>
<body class="bg-background min-h-screen flex flex-col pt-12">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] flex flex-col items-center">
            <!-- Banners -->
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

            <!-- Banner Texts -->
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
            
            <h2 class="text-textColor font-bold mb-1 tracking-wide text-center" style="font-size: 24px; font-weight: 1000;">Account Settings</h2>
            <div class="border-[#00ff9d] min-w-[400px] mb-1 mx-auto" style="border-bottom: 1px solid #00ff9d; width: 500px;"></div>
            
            <!-- Tab Navigation -->
            <div class="flex justify-center gap-0 mt-6 mb-0">
                <a href="?tab=settings" class="tab-btn px-6 py-3 font-medium transition-all <?php echo $activeTab === 'settings' ? 'bg-primary text-black' : 'bg-backgroundSecondary text-textColor hover:bg-opacity-80'; ?>" style="border-radius: 8px 0 0 0;">
                    <i class="fas fa-cog mr-2"></i>Settings
                </a>
                <a href="?tab=api" class="tab-btn px-6 py-3 font-medium transition-all <?php echo $activeTab === 'api' ? 'bg-primary text-black' : 'bg-backgroundSecondary text-textColor hover:bg-opacity-80'; ?>" style="border-radius: 0 8px 0 0;">
                    <i class="fas fa-key mr-2"></i>API Key
                </a>
            </div>
            
            <div class="w-[500px] max-w-full bg-backgroundSecondary rounded-lg rounded-t-none p-8">
                
                <?php if ($activeTab === 'settings'): ?>
                <!-- Settings Tab Content -->
                <?php if (!empty($errors)): ?>
                    <div class="error-message mb-4">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($success)): ?>
                    <div class="success-message mb-4">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <!-- Profile Picture Section -->
                    <div class="border border-gray-600 rounded-lg p-4 mb-4">
                        <label class="block text-textColor mb-3 font-medium">
                            <i class="fas fa-user-circle mr-2 text-primary"></i>Profile Picture
                        </label>
                        <div class="flex items-center gap-4">
                            <div class="profile-preview-container">
                                <div id="avatarPreview" class="w-20 h-20 rounded-full bg-background border-2 border-primary flex items-center justify-center overflow-hidden">
                                    <?php if (!empty($user['avatar_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <i class="fa-solid fa-user text-primary text-2xl" style="display: none;"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-user text-primary text-2xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex-1">
                                <input type="text" name="avatar_url" id="avatarUrlInput" placeholder="https://example.com/avatar.png" 
                                       value="<?php echo htmlspecialchars($user['avatar_url'] ?? ''); ?>" 
                                       class="w-full bg-background rounded-lg p-3 text-textColor border border-gray-600 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                                <p class="text-gray-400 text-xs mt-1">Enter an image URL. Preview will update automatically.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cover Image Section -->
                    <div class="border border-gray-600 rounded-lg p-4 mb-4">
                        <label class="block text-textColor mb-3 font-medium">
                            <i class="fas fa-image mr-2 text-primary"></i>Cover Image
                        </label>
                        <div id="coverPreview" class="w-full h-24 rounded-lg bg-background border border-gray-600 mb-3 overflow-hidden flex items-center justify-center">
                            <?php if (!empty($user['cover_url'])): ?>
                                <img src="<?php echo htmlspecialchars($user['cover_url']); ?>" alt="Cover" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="text-gray-500 text-sm" style="display: none;"><i class="fas fa-panorama mr-2"></i>Cover Preview</div>
                            <?php else: ?>
                                <div class="text-gray-500 text-sm"><i class="fas fa-panorama mr-2"></i>Cover Preview</div>
                            <?php endif; ?>
                        </div>
                        <input type="text" name="cover_url" id="coverUrlInput" placeholder="https://example.com/cover.png" 
                               value="<?php echo htmlspecialchars($user['cover_url'] ?? ''); ?>" 
                               class="w-full bg-background rounded-lg p-3 text-textColor border border-gray-600 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                        <p class="text-gray-400 text-xs mt-1">Recommended size: 1200x300 pixels</p>
                    </div>

                    <!-- Bio Section -->
                    <div>
                        <label class="block text-textColor mb-3 font-medium">
                            <i class="fas fa-quote-left mr-2 text-primary"></i>Bio
                        </label>
                        <textarea name="bio" id="bioInput" rows="3" maxlength="300" placeholder="Telegram Business, Discord, short description..."
                                  class="w-full bg-background rounded-lg p-3 text-textColor border border-gray-600 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary resize-none"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        <p class="text-gray-400 text-xs mt-1">
                            <span id="bioCharCount"><?php echo strlen($user['bio'] ?? ''); ?></span>/300 characters
                            <span class="ml-2">• Example: Telegram Business, contact info, short intro</span>
                        </p>
                    </div>

                    <hr class="border-gray-600 my-4">

                    <div>
                        <label class="block text-textColor mb-3 font-medium">Default Paste Visibility</label>
                        <div class="space-y-2">
                            <label class="radio-option">
                                <input type="radio" name="default_visibility" value="public" 
                                       <?php echo (!isset($user['default_visibility']) || $user['default_visibility'] === 'public') ? 'checked' : ''; ?>>
                                <span class="text-textColor">Public</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="default_visibility" value="private" 
                                       <?php echo (isset($user['default_visibility']) && $user['default_visibility'] === 'private') ? 'checked' : ''; ?>>
                                <span class="text-textColor">Private</span>
                            </label>
                        </div>
                        <p class="text-gray-400 text-sm mt-2">This setting determines whether your pastes will be public or private by default.</p>
                    </div>

                    <hr class="border-gray-600 my-4">
                    <h3 class="text-textColor font-medium mb-2"><i class="fas fa-address-book mr-2 text-primary"></i>Social Links</h3>

                    <div>
               <label class="block text-textColor mb-3 font-medium">Telegram</label>
               <input type="text" name="telegram" placeholder="@username" value="<?php echo htmlspecialchars($user['telegram'] ?? ''); ?>" 
                   class="w-full bg-background rounded-lg p-3 text-textColor border border-gray-600 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
               <p class="text-gray-400 text-sm mt-2">Enter only your Telegram handle in the form <strong>@username</strong>.</p>
                    </div>

                    <div>
                        <label class="block text-textColor mb-3 font-medium">Discord</label>
                        <input type="text" name="discord" value="<?php echo htmlspecialchars($user['discord'] ?? ''); ?>" 
                               class="w-full bg-background rounded-lg p-3 text-textColor border border-gray-600 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label class="block text-textColor mb-3 font-medium">Website</label>
                        <input type="text" name="website" value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>" 
                               class="w-full bg-background rounded-lg p-3 text-textColor border border-gray-600 focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary">
                    </div>

                    <button type="submit" class="w-full bg-primary text-black font-bold py-3 px-4 rounded-lg hover:opacity-90 transition-opacity">
                        Save Changes
                    </button>
                </form>
                
                <?php else: ?>
                <!-- API Key Tab Content -->
                <?php if (isset($api_error)): ?>
                    <div class="error-message mb-4">
                        <p><?php echo htmlspecialchars($api_error); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($api_success)): ?>
                    <div class="success-message mb-4">
                        <?php echo htmlspecialchars($api_success); ?>
                    </div>
                <?php endif; ?>
                
                <div class="space-y-6">
                    <!-- API Key Display -->
                    <div>
                        <label class="block text-textColor mb-3 font-medium">
                            <i class="fas fa-key mr-2 text-primary"></i>Your API Key
                        </label>
                        <?php if (!empty($user['api_key'])): ?>
                            <div class="relative">
                                <input type="text" id="apiKeyField" value="<?php echo htmlspecialchars($user['api_key']); ?>" 
                                       readonly
                                       class="w-full bg-background rounded-lg p-3 pr-24 text-textColor border border-gray-600 font-mono text-sm">
                                <button type="button" onclick="copyApiKey()" 
                                        class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1.5 bg-primary text-black text-sm font-medium rounded hover:opacity-90 transition-opacity">
                                    <i class="fas fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                            <p class="text-yellow-500 text-sm mt-2">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Keep this key secret! Anyone with this key can create pastes on your behalf.
                            </p>
                        <?php else: ?>
                            <div class="bg-background rounded-lg p-4 border border-gray-600 text-center">
                                <p class="text-gray-400 mb-3">You don't have an API key yet.</p>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="api_action" value="generate">
                                    <button type="submit" class="px-4 py-2 bg-primary text-black font-medium rounded-lg hover:opacity-90 transition-opacity">
                                        <i class="fas fa-plus mr-2"></i>Generate API Key
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($user['api_key'])): ?>
                    <!-- API Key Actions -->
                    <div class="flex gap-3">
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="api_action" value="regenerate">
                            <button type="submit" onclick="return confirm('Are you sure? Your old API key will stop working immediately.')" 
                                    class="w-full px-4 py-2 bg-yellow-600 text-white font-medium rounded-lg hover:bg-yellow-700 transition-colors">
                                <i class="fas fa-sync-alt mr-2"></i>Regenerate
                            </button>
                        </form>
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="api_action" value="revoke">
                            <button type="submit" onclick="return confirm('Are you sure you want to revoke your API key? This cannot be undone.')" 
                                    class="w-full px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors">
                                <i class="fas fa-trash mr-2"></i>Revoke
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Usage Stats -->
                    <div class="bg-background rounded-lg p-4 border border-gray-600">
                        <h3 class="text-textColor font-medium mb-3">
                            <i class="fas fa-chart-bar mr-2 text-primary"></i>Usage Statistics
                        </h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-400">Pastes Today:</span>
                                <span class="text-textColor ml-2 font-medium"><?php echo $apiStats['pastes_today']; ?> / <?php echo $apiStats['daily_limit']; ?></span>
                            </div>
                            <div>
                                <span class="text-gray-400">Total Pastes:</span>
                                <span class="text-textColor ml-2 font-medium"><?php echo number_format($apiStats['pastes_total']); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-400">Rate Limit:</span>
                                <span class="text-textColor ml-2 font-medium"><?php echo $apiStats['rate_limit']; ?> req/min</span>
                            </div>
                            <div>
                                <span class="text-gray-400">Daily Limit:</span>
                                <span class="text-textColor ml-2 font-medium"><?php echo $apiStats['daily_limit']; ?> pastes</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- API Documentation -->
                    <div class="bg-background rounded-lg p-4 border border-gray-600">
                        <h3 class="text-textColor font-medium mb-3">
                            <i class="fas fa-book mr-2 text-primary"></i>API Documentation
                        </h3>
                        
                        <div class="space-y-4 text-sm">
                            <div>
                                <p class="text-gray-400 mb-2">Endpoint:</p>
                                <code class="block bg-backgroundSecondary px-3 py-2 rounded text-primary font-mono text-xs">
                                    GET https://example.com/api/paste.php
                                </code>
                            </div>
                            
                            <div>
                                <p class="text-gray-400 mb-2">Query Parameters:</p>
                                <ul class="text-gray-300 space-y-1 ml-4 list-disc">
                                    <li><span class="text-primary">api_key</span> (required) - Your API key</li>
                                    <li><span class="text-primary">content</span> (required) - Paste content (URL encoded)</li>
                                    <li><span class="text-primary">title</span> - Paste title (default: "Untitled Paste")</li>
                                    <li><span class="text-primary">syntax</span> - Syntax highlighting (text, php, javascript, python, etc.)</li>
                                    <li><span class="text-primary">visibility</span> - public, private, or unlisted</li>
                                    <li><span class="text-primary">password</span> - Required if visibility is "private"</li>
                                    <li><span class="text-primary">expiration</span> - never, 30min, 1hour, 12hours, 1day, 3days, 1month</li>
                                </ul>
                            </div>
                            
                            <div>
                                <p class="text-gray-400 mb-2">Example URL:</p>
                                <pre class="bg-backgroundSecondary px-3 py-2 rounded text-gray-300 font-mono text-xs overflow-x-auto whitespace-pre-wrap break-all">https://example.com/api/paste.php?api_key=YOUR_KEY&amp;title=My+Paste&amp;content=Hello+World&amp;syntax=text&amp;visibility=public</pre>
                            </div>
                            
                            <div>
                                <p class="text-gray-400 mb-2">Example (cURL):</p>
                                <pre class="bg-backgroundSecondary px-3 py-2 rounded text-gray-300 font-mono text-xs overflow-x-auto">curl "https://example.com/api/paste.php?api_key=<?php echo !empty($user['api_key']) ? substr($user['api_key'], 0, 16) . '...' : 'your_api_key'; ?>&amp;title=Test&amp;content=Hello+World&amp;syntax=text"</pre>
                            </div>
                            
                            <div>
                                <p class="text-gray-400 mb-2">Example (Python):</p>
                                <pre class="bg-backgroundSecondary px-3 py-2 rounded text-gray-300 font-mono text-xs overflow-x-auto">import requests
import urllib.parse

api_key = "<?php echo !empty($user['api_key']) ? substr($user['api_key'], 0, 16) . '...' : 'your_api_key'; ?>"
content = urllib.parse.quote("Hello World!")

response = requests.get(
    f"https://example.com/api/paste.php",
    params={
        "api_key": api_key,
        "title": "My Paste",
        "content": content,
        "syntax": "python"
    }
)
print(response.json())</pre>
                            </div>
                            
                            <div>
                                <p class="text-gray-400 mb-2">Alternative: Base64 JSON Method</p>
                                <pre class="bg-backgroundSecondary px-3 py-2 rounded text-gray-300 font-mono text-xs overflow-x-auto">import requests
import base64
import json

api_key = "your_api_key"
data = base64.b64encode(json.dumps({
    "title": "My Paste",
    "content": "Hello World!",
    "syntax": "text"
}).encode()).decode()

response = requests.get(
    f"https://example.com/api/paste.php?api_key={api_key}&amp;data={data}"
)
print(response.json())</pre>
                            </div>
                            
                            <div>
                                <p class="text-gray-400 mb-2">Success Response:</p>
                                <pre class="bg-backgroundSecondary px-3 py-2 rounded text-green-400 font-mono text-xs overflow-x-auto">{
  "success": true,
  "paste_id": 12345,
  "url": "https://example.com/view.php?id=12345",
  "title": "My Paste",
  "syntax": "text",
  "visibility": "public",
  "expiration": "never"
}</pre>
                            </div>
                            
                            <div>
                                <p class="text-gray-400 mb-2">Error Response:</p>
                                <pre class="bg-backgroundSecondary px-3 py-2 rounded text-red-400 font-mono text-xs overflow-x-auto">{
  "success": false,
  "error": "Error message here"
}</pre>
                            </div>
                            
                            <div class="bg-yellow-900/20 border border-yellow-600/30 rounded p-3 mt-4">
                                <p class="text-yellow-400 text-xs">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <strong>Note:</strong> Content must be URL encoded. Maximum content size is 64KB for GET requests.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <br></br>

    <!-- Green separator line -->
    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] my-4" style="border-bottom: 1px solid #00ff9d;"></div>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
        function selectEmoji(element, emoji) {
            // Remove selected class from all elements
            document.querySelectorAll('.emoji-option').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Add selected class to chosen element
            element.classList.add('selected');
            
            // Update hidden field value
            document.getElementById('emoji').value = emoji;
        }
        
        function copyApiKey() {
            const apiKeyField = document.getElementById('apiKeyField');
            apiKeyField.select();
            apiKeyField.setSelectionRange(0, 99999);
            
            navigator.clipboard.writeText(apiKeyField.value).then(() => {
                // Show success feedback
                const btn = event.target.closest('button');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check mr-1"></i>Copied!';
                btn.classList.remove('bg-primary');
                btn.classList.add('bg-green-500');
                
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('bg-green-500');
                    btn.classList.add('bg-primary');
                }, 2000);
            }).catch(() => {
                // Fallback for older browsers
                document.execCommand('copy');
                alert('API key copied to clipboard!');
            });
        }
        
        // Avatar Preview
        const avatarInput = document.getElementById('avatarUrlInput');
        const avatarPreview = document.getElementById('avatarPreview');
        
        if (avatarInput) {
            avatarInput.addEventListener('input', function() {
                const url = this.value.trim();
                if (url) {
                    avatarPreview.innerHTML = `<img src="${url}" alt="Avatar" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<i class=\\'fa-solid fa-exclamation-triangle text-red-500 text-xl\\'></i>';">`;
                } else {
                    avatarPreview.innerHTML = '<i class="fa-solid fa-user text-primary text-2xl"></i>';
                }
            });
        }
        
        // Cover Preview
        const coverInput = document.getElementById('coverUrlInput');
        const coverPreview = document.getElementById('coverPreview');
        
        if (coverInput) {
            coverInput.addEventListener('input', function() {
                const url = this.value.trim();
                if (url) {
                    coverPreview.innerHTML = `<img src="${url}" alt="Cover" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<div class=\\'text-red-500 text-sm\\'><i class=\\'fas fa-exclamation-triangle mr-2\\'></i>Invalid image URL</div>';">`;
                } else {
                    coverPreview.innerHTML = '<div class="text-gray-500 text-sm"><i class="fas fa-panorama mr-2"></i>Cover Preview</div>';
                }
            });
        }
        
        // Bio Character Count
        const bioInput = document.getElementById('bioInput');
        const bioCharCount = document.getElementById('bioCharCount');
        
        if (bioInput && bioCharCount) {
            bioInput.addEventListener('input', function() {
                bioCharCount.textContent = this.value.length;
                if (this.value.length > 250) {
                    bioCharCount.classList.add('text-yellow-500');
                    bioCharCount.classList.remove('text-red-500');
                }
                if (this.value.length >= 300) {
                    bioCharCount.classList.add('text-red-500');
                    bioCharCount.classList.remove('text-yellow-500');
                }
                if (this.value.length <= 250) {
                    bioCharCount.classList.remove('text-yellow-500', 'text-red-500');
                }
            });
        }
    </script>
</body>
</html>
