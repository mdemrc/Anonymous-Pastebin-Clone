<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check authorization and admin rights
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Get current site settings
$settings = getSiteSettings();

// Action processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Security error. Please try again.');
        header('Location: settings.php');
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        // Collect settings from form
        $newSettings = [
            'site_name' => sanitizeInput($_POST['site_name']),
            'site_url' => sanitizeInput($_POST['site_url']),
            'admin_email' => sanitizeInput($_POST['admin_email'])
        ];
        
        // Validate settings
        $errors = [];
        
        if (empty($newSettings['site_name'])) {
            $errors[] = 'Site name cannot be empty.';
        }
        
        if (empty($newSettings['site_url'])) {
            $errors[] = 'Site URL cannot be empty.';
        } elseif (!filter_var($newSettings['site_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Site URL must be a valid URL.';
        }
        
        if (empty($newSettings['admin_email'])) {
            $errors[] = 'Admin email cannot be empty.';
        } elseif (!filter_var($newSettings['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Admin email must be a valid email address.';
        }
        
        if (empty($errors)) {
            // Update settings
            if (updateSiteSettings($newSettings)) {
                setFlashMessage('success', 'Site settings successfully updated. You may need to refresh the page to see the changes.');
            } else {
                setFlashMessage('error', 'Error updating site settings. Make sure the config file is writable.');
            }
        } else {
            // Set error messages
            foreach ($errors as $error) {
                setFlashMessage('error', $error);
            }
        }
        
        header('Location: settings.php');
        exit;
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - <?php echo SITE_NAME; ?></title>
    
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
            <h1 class="text-2xl font-bold text-textColor">Site Settings</h1>
            <a href="./" class="text-primary hover:text-primaryHover">← Back to Admin Panel</a>
        </div>
        
        <!-- Flash Messages -->
        <?php $flashMessages = getFlashMessages(); ?>
        <?php if (!empty($flashMessages)): ?>
            <?php foreach ($flashMessages as $message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $message['type'] === 'success' ? 'bg-green-800' : 'bg-red-800'; ?>">
                    <p class="text-white"><?php echo $message['message']; ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Settings Form -->
        <div class="bg-backgroundTextarea rounded-lg p-6">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Settings -->
                    <div class="space-y-6">
                        <h2 class="text-xl font-bold text-textColor mb-4">Basic Settings</h2>
                        
                        <div>
                            <label class="block text-textColor mb-2">Site Name</label>
                            <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>" 
                                   class="bg-background2 text-textColor p-2 rounded w-full">
                            <p class="text-textColorHover text-sm mt-1">The name of your site displayed in the header and title.</p>
                        </div>
                        
                        <div>
                            <label class="block text-textColor mb-2">Site URL</label>
                            <input type="url" name="site_url" value="<?php echo htmlspecialchars($settings['site_url']); ?>" 
                                   class="bg-background2 text-textColor p-2 rounded w-full">
                            <p class="text-textColorHover text-sm mt-1">The base URL of your site (e.g., https://example.com).</p>
                        </div>
                        
                        <div>
                            <label class="block text-textColor mb-2">Admin Email</label>
                            <input type="email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>" 
                                   class="bg-background2 text-textColor p-2 rounded w-full">
                            <p class="text-textColorHover text-sm mt-1">The email address used for admin notifications.</p>
                        </div>
                    </div>
                    
                    <!-- Feature Settings -->
                    <div class="space-y-6">
                        <h2 class="text-xl font-bold text-textColor mb-4">Feature Settings</h2>
                        
                        <div class="p-4 bg-background2 rounded">
                            <p class="text-textColor mb-4">
                                <i class="fas fa-info-circle text-primary mr-2"></i>
                                The following settings require database modifications and are currently view-only.
                            </p>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-textColor mb-2">Max Paste Size</label>
                                    <input type="text" value="<?php echo htmlspecialchars(number_format($settings['max_paste_size'] / 1024, 0) . ' KB'); ?>" 
                                           class="bg-background text-textColor p-2 rounded w-full" disabled>
                                </div>
                                
                                <div>
                                    <label class="block text-textColor mb-2">Max Title Length</label>
                                    <input type="text" value="<?php echo htmlspecialchars($settings['max_title_length'] . ' characters'); ?>" 
                                           class="bg-background text-textColor p-2 rounded w-full" disabled>
                                </div>
                                
                                <div>
                                    <label class="block text-textColor mb-2">Default Paste Visibility</label>
                                    <input type="text" value="<?php echo htmlspecialchars(ucfirst($settings['default_paste_visibility'])); ?>" 
                                           class="bg-background text-textColor p-2 rounded w-full" disabled>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" <?php echo $settings['enable_registration'] ? 'checked' : ''; ?> 
                                           class="mr-2" disabled>
                                    <label class="text-textColor">Enable User Registration</label>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" <?php echo $settings['enable_password_reset'] ? 'checked' : ''; ?> 
                                           class="mr-2" disabled>
                                    <label class="text-textColor">Enable Password Reset</label>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" <?php echo $settings['enable_paste_rating'] ? 'checked' : ''; ?> 
                                           class="mr-2" disabled>
                                    <label class="text-textColor">Enable Paste Rating</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-background2">
                    <button type="submit" 
                            class="px-4 py-2 bg-primary hover:bg-primaryHover text-background rounded">
                        <i class="fas fa-save mr-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Advanced Settings Info -->
        <div class="mt-8 bg-backgroundTextarea rounded-lg p-6">
            <h2 class="text-xl font-bold text-textColor mb-4">Advanced Settings</h2>
            
            <div class="p-4 bg-background2 rounded mb-4">
                <p class="text-textColor">
                    <i class="fas fa-info-circle text-primary mr-2"></i>
                    Some settings can only be modified by directly editing the configuration files or database structure.
                </p>
            </div>
            
            <div class="space-y-4">
                <div>
                    <h3 class="text-lg font-bold text-textColor mb-2">Configuration Files</h3>
                    <p class="text-textColor">
                        The main configuration file is located at <code class="bg-background2 px-2 py-1 rounded">/includes/config.php</code>
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-textColor mb-2">Database Structure</h3>
                    <p class="text-textColor">
                        The database structure can be modified using the script at <code class="bg-background2 px-2 py-1 rounded">/create_tables.php</code>
                    </p>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-textColor mb-2">Server Requirements</h3>
                    <ul class="list-disc list-inside text-textColor space-y-1">
                        <li>PHP 8.1 or higher</li>
                        <li>MySQL 5.7 or higher</li>
                        <li>PDO PHP Extension</li>
                        <li>GD PHP Extension (for image processing)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
