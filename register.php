<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Security error. Please try again.';
    } else {
        $turnstileResponse = $_POST['cf-turnstile-response'] ?? '';
        $errors = [];

        if (empty($turnstileResponse)) {
            $errors[] = 'Please complete the security check.';
        } else {
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
            $result = file_get_contents($verifyURL, false, $context);
            $resultData = json_decode($result, true);

            if (!$resultData['success']) {
                $errors[] = 'Security verification failed. Please try again.';
            }
        }

        $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

       if (empty($username)) {
    $errors[] = 'Username is required';
} elseif (strlen($username) < 3 || strlen($username) > 20) {
    $errors[] = 'Username must be between 3 and 20 characters';
} elseif (strpos($username, '.') !== false || strpos($username, '/') !== false) {
    $errors[] = 'Username cannot contain special characters.';
} elseif (preg_match('/[.@\/\\\\]/', $username)) {
    $errors[] = 'Username cannot contain special characters.';
}



        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }

        if (empty($errors)) {
            try {
                $existingUser = getUserByUsername($username);
                if ($existingUser) {
                    $errors[] = 'Username already exists';
                }

                $existingEmail = getUserByEmail($email);
                if ($existingEmail) {
                    $errors[] = 'Email address is already registered';
                }

                if (empty($errors)) {
                    if (createUser($username, $password, $email)) {
                        if (loginUser($username, $password)) {
                            header('Location: index.php');
                            exit;
                        } else {
                            $errors[] = 'Registration successful, but login failed. Please log in manually.';
                        }
                    } else {
                        $errors[] = 'Error creating user. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    if (strpos($e->getMessage(), 'username') !== false) {
                        $errors[] = 'Username already exists';
                    } elseif (strpos($e->getMessage(), 'email') !== false) {
                        $errors[] = 'Email address is already registered';
                    } else {
                        $errors[] = 'Registration error. Please try again.';
                    }
                } else {
                    $errors[] = 'Registration error. Please try again.';
                }
            } catch (Exception $e) {
                $errors[] = 'Registration error. Please try again.';
            }
        }
    }
}

$banners = getRandomBanners(2);
$bannerTexts = getRandomBannerTexts(2);

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>example.com - Registration</title>
    
    <meta name="keywords" content="example.com, pastehub, pastebin, pastebin alternative, free, proxies, configs, anonfiles, leaks, leaked, bayfiles, ghostbin, cracked, accounts, files, paste">
    <meta name="author" content="example.com">
    <meta name="copyright" content="example.com">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <link href="css/responsive.css" rel="stylesheet" type="text/css">
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
            <?php if (!empty($banners)): ?>
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
            <?php endif; ?>

            <div class="w-[400px] max-w-full bg-backgroundSecondary rounded-lg p-8">
                <h2 class="text-textColor font-bold text-3xl mb-2 tracking-wide text-center" style="border-bottom: 4px solid #00ff9d;">Create Account</h2>
                <div class="border-b-4 border-[#00ff9d] mb-8"></div>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="error-message mb-4">
                        <ul class="list-disc pl-5">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    
                    <div>
                        <label for="username" class="block text-textColor mb-2">Username</label>
                        <input type="text" id="username" name="username" class="w-full bg-background border border-primary/50 rounded p-2 text-textColor focus:border-primary outline-none" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-textColor mb-2">Email</label>
                        <input type="email" id="email" name="email" class="w-full bg-background border border-primary/50 rounded p-2 text-textColor focus:border-primary outline-none" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-textColor mb-2">Password</label>
                        <input type="password" id="password" name="password" class="w-full bg-background border border-primary/50 rounded p-2 text-textColor focus:border-primary outline-none" required>
                        <p class="hint-text mt-1 text-gray-400">Must be at least 6 Characters</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-textColor mb-2">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="w-full bg-background border border-primary/50 rounded p-2 text-textColor focus:border-primary outline-none" required>
                    </div>

                    <div class="cf-turnstile" data-sitekey="0x4AAAAAABQIYSDzB006i8rQ"></div>
                    
                    <button type="submit" class="w-full bg-primary text-black font-bold py-2 px-4 rounded hover:opacity-90 transition-opacity">
                        Register
                    </button>
                    
                    <p class="text-center text-textColor mt-4">
                        Already have an account? <a href="login.php" class="text-primary hover:underline">Login</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
    
    <br></br>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
