<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check authorization and admin rights
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

// Action processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Security error. Please try again.');
        header('Location: banners.php');
        exit;
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'pin_banner':
                $bannerId = !empty($_POST['banner_id']) ? (int)$_POST['banner_id'] : null;
                if ($bannerId) {
                    // Toggle pin: if already pinned, unpin; otherwise pin
                    $wasPinned = isBannerPinned($bannerId);
                    if (setPinnedBanner($bannerId, true)) { // true = toggle mode
                        if ($wasPinned) {
                            setFlashMessage('success', 'Banner unpinned from view page.');
                        } else {
                            setFlashMessage('success', 'Banner pinned to view page.');
                        }
                    } else {
                        setFlashMessage('error', 'Failed to update pinned banner.');
                    }
                } else {
                    // Unpin all
                    if (setPinnedBanner(null)) {
                        setFlashMessage('success', 'All pinned banners removed.');
                    } else {
                        setFlashMessage('error', 'Failed to remove pinned banners.');
                    }
                }
                break;
            case 'add_banner':
                $price = (float)$_POST['price'] ?? 0.00;
                $buyerUsername = sanitizeInput($_POST['buyer_username']);
                $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
                $expirationDate = null;
                $extraInfo = sanitizeInput($_POST['extra_info'] ?? '');
                $url = sanitizeInput($_POST['url']);
            
                if ($expiresAt) {
                    $expirationDate = date('Y-m-d H:i:s', strtotime("+{$expiresAt}"));
                    $expiresChoice = $expiresAt;
                } else {
                    $expiresChoice = null;
                }
                
                if (isset($_POST['is_external']) && $_POST['is_external'] == 1 && !empty($_POST['external_url'])) {
                    $externalUrl = sanitizeInput($_POST['external_url']);
                
                    if (filter_var($externalUrl, FILTER_VALIDATE_URL)) {
                        $isImgur = (strpos($externalUrl, 'https://i.imgur.com') === 0);
                        
                        if ($isImgur) {
                            if (addBanner($externalUrl, $url, 1, $buyerUsername, $expirationDate, $extraInfo, $expiresChoice, $price)) {
                                setFlashMessage('success', 'Imgur banner successfully added.');
                            } else {
                                setFlashMessage('error', 'Error adding banner to database.');
                            }
                        } else {
                            $imageSize = @getimagesize($externalUrl);
                            if ($imageSize) {
                                if (addBanner($externalUrl, $url, 1, $buyerUsername, $expirationDate, $extraInfo)) {
                                    setFlashMessage('success', 'Banner successfully added from external URL.');
                                } else {
                                    setFlashMessage('error', 'Error adding banner to database.');
                                }
                            } else {
                                setFlashMessage('error', 'Could not verify external image. Please ensure it is a valid image URL.');
                            }
                        }
                    } else {
                        setFlashMessage('error', 'Please enter a valid URL for the external image.');
                    }
                }
                // Check if file is uploaded
                else if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errors = validateBannerImage($_FILES['banner_image']);
                    
                    if (empty($errors)) {
                        $fileName = time() . '_' . basename($_FILES['banner_image']['name']);
                        $uploadPath = '../uploads/banners/' . $fileName;
                        
                        if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadPath)) {
                            $relativePath = 'uploads/banners/' . $fileName;
                            
                            if (addBanner($relativePath, $url, 0, $buyerUsername, $expirationDate, $extraInfo)) {
                                setFlashMessage('success', 'Banner successfully added.');
                            } else {
                                setFlashMessage('error', 'Error adding banner to database.');
                            }
                        } else {
                            setFlashMessage('error', 'Error uploading file.');
                        }
                    } else {
                        setFlashMessage('error', implode('<br>', $errors));
                    }
                } else {
                    setFlashMessage('error', 'Please select an image to upload or provide an external image URL.');
                }
                break;
                
            case 'update_banner':
                $bannerId = (int)$_POST['banner_id'];
                $url = sanitizeInput($_POST['url']);
                $active = isset($_POST['active']) ? 1 : 0;
                $buyerUsername = sanitizeInput($_POST['buyer_username']);
                $extraInfo = sanitizeInput($_POST['extra_info'] ?? '');
                $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
                $expirationDate = null;
                $expiresChoice = null;
                $price = (float)$_POST['price'] ?? 0.00;
                
                if ($expiresAt) {
                    $expirationDate = date('Y-m-d H:i:s', strtotime("+{$expiresAt}"));
                    $expiresChoice = $expiresAt;
                }
                
                if (updateBanner(
                    $bannerId, 
                    $url, 
                    $active,
                    null,
                    null,
                    $buyerUsername,
                    $expirationDate,
                    $extraInfo,
                    $expiresChoice,
                    $price
                )) {
                    setFlashMessage('success', 'Banner successfully updated.');
                } else {
                    setFlashMessage('error', 'Error updating banner.');
                }
                break;
                
            case 'delete_banner':
                $bannerId = (int)$_POST['banner_id'];
                
                if (deleteBanner($bannerId)) {
                    setFlashMessage('success', 'Banner successfully deleted.');
                } else {
                    setFlashMessage('error', 'Error deleting banner.');
                }
                break;
        }
        
        header('Location: banners.php');
        exit;
    }
}

// Get banners list
$stmt = $pdo->query("SELECT * FROM banners ORDER BY created_at DESC");
$banners = $stmt->fetchAll();
// Current pinned banner ids (array)
$__pinnedIds = getPinnedIds();
$__pinnedBannerIds = $__pinnedIds['banner_ids'] ?? [];

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner Management - <?php echo SITE_NAME; ?></title>
    
    <link href="../css/style.css" rel="stylesheet" type="text/css">
    <link href="../css/responsive.css" rel="stylesheet" type="text/css">
    <link href="../css/dark.css" rel="stylesheet" type="text/css">
    <link href="../css/fonts.css" rel="stylesheet" type="text/css">
    <style>
        .modal-enter {
    opacity: 0;
}
.modal-enter-active {
    opacity: 1;
    transition: opacity 200ms;
}
.modal-exit {
    opacity: 1;
}
.modal-exit-active {
    opacity: 0;
    transition: opacity 200ms;
}

/* Modal content scroll */
.modal-content {
    max-height: 80vh;
    overflow-y: auto;
}

/* Chart containers */
.chart-container {
    position: relative;
    height: 200px;
    width: 100%;
}
    </style>
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
            <h1 class="text-2xl font-bold text-textColor">Banner Management</h1>
            <div class="flex space-x-4">
                <a href="../update_banners_table.php" class="text-primary hover:text-primaryHover">Update Banner Table</a>
                <a href="./" class="text-primary hover:text-primaryHover">← Back to Admin Panel</a>
            </div>
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
        
        <!-- Add Banner Form -->
        <div class="bg-backgroundTextarea rounded-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-textColor mb-4">Add New Banner</h2>
        
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="add_banner">
        
                <!-- Image Upload Tabs -->
                <div class="mb-4">
                    <div class="flex space-x-4 mb-4">
                        <button type="button" id="uploadTabBtn" class="px-4 py-2 bg-primary text-background rounded" onclick="showUploadTab()">
                            Upload Image
                        </button>
                        <button type="button" id="externalTabBtn" class="px-4 py-2 bg-background2 text-textColor rounded" onclick="showExternalTab()">
                            External URL
                        </button>
                    </div>
        
                    <div id="uploadTab">
                        <label class="block text-textColor mb-2">Banner Image (440x111 pixels)</label>
                        <input type="file" name="banner_image" accept="image/jpeg,image/png,image/gif" class="bg-background2 text-textColor p-2 rounded w-full">
                        <p class="text-textColorHover text-sm mt-1">Allowed formats: JPG, PNG (до 2MB), GIF (до 10MB). Размер: 440x111 пикселей.</p>
                    </div>
        
                    <div id="externalTab" class="hidden">
                        <label class="block text-textColor mb-2">External Image URL</label>
                        <input type="url" name="external_url" placeholder="https://i.imgur.com/example.jpg" class="bg-background2 text-textColor p-2 rounded w-full">
                        <p class="text-textColorHover text-sm mt-1">Supports JPG, PNG (up to 2MB), GIF (up to 10MB). Any image dimensions. Recommended: i.imgur.com.</p>
                        <input type="hidden" name="is_external" id="isExternal" value="0">
                    </div>
                </div>
        
                <!-- Target URL -->
                <div>
                    <label class="block text-textColor mb-2">Target URL</label>
                    <input type="url" name="url" required class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
        
                <div>
                    <label class="block text-textColor mb-2">Buyer Username<span class="text-red-500">*</span></label>
                    <input type="text" name="buyer_username" required placeholder="@username of buyer Telegram/Discord" class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
        
                <div>
                    <label class="block text-textColor mb-2">Expires At (optional)</label>
                    <select name="expires_at" class="bg-background2 text-textColor p-2 rounded w-full">
                        <option value="">No expiration</option>
                        <option value="1 week">1 week</option>
                        <option value="2 weeks">2 weeks</option>
                        <option value="1 month">1 month</option>
                        <option value="2 months">2 months</option>
                        <option value="3 months">3 months</option>
                        <option value="4 months">4 months</option>
                        <option value="6 months">6 months</option>
                        <option value="1 year">1 year</option>
                    </select>
                    <p class="text-textColorHover text-sm mt-1">Select expiration period or leave as "No expiration".</p>
                </div>
                
                <div>
                    <label class="block text-textColor mb-2">Price (USD)</label>
                    <input type="number" name="price" step="0.01" min="0" 
                           class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
        
                <div>
                    <label class="block text-textColor mb-2">Extra Info (optional)</label>
                    <textarea name="extra_info" rows="3" placeholder="Any additional notes..." class="bg-background2 text-textColor p-2 rounded w-full"></textarea>
                </div>
        
                <div>
                    <button type="submit" class="px-4 py-2 bg-primary hover:bg-primaryHover text-background rounded">
                        Add Banner
                    </button>
                </div>
            </form>
        </div>

        
        <!-- Banners List -->
        <div class="bg-backgroundTextarea rounded-lg overflow-hidden">
        <h2 class="text-xl font-bold text-textColor p-6 border-b border-background2">Existing Banners</h2>
        
        <?php if (empty($banners)): ?>
        <p class="p-6 text-textColor">No banners found.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 p-2">
                    <?php foreach ($banners as $banner): ?>
                        <?php
                        $price = isset($banner['price']) ? $banner['price'] : 0;
                        $isExpired = !empty($banner['expires_at']) && strtotime($banner['expires_at']) < time();
                        $expiresChoice = isset($banner['expires_choice']) ? $banner['expires_choice'] : null;
                        $expiresAtFormatted = !empty($banner['expires_at']) ? 
                            date('d.m.Y H:i', strtotime($banner['expires_at'])) : 'Never';
                        ?>
                        
                        <div class="bg-background2 rounded-lg overflow-hidden relative">
                            <div class="p-4 flex justify-between items-center border-b border-backgroundTextarea">
                                <h3 class="text-textColor font-bold">Banner #<?php echo $banner['id']; ?></h3>
                                <span class="px-2 py-1 rounded text-xs <?php echo $banner['active'] ? 'bg-green-800' : 'bg-red-800'; ?>">
                                    <?php
                                        echo $banner['active'] ? 'Active' : 'Inactive';
                                        if ($isExpired) {
                                            echo ' — EXPIRED';
                                        }
                                    ?>
                                </span>
                            </div>
                            
                            <div class="p-4">
                                <?php if ($banner['is_external']): ?>
                                    <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" 
                                         alt="Banner #<?php echo $banner['id']; ?>" 
                                         class="w-full h-auto mb-4 border border-backgroundTextarea">
                                <?php else: ?>
                                    <img src="../<?php echo htmlspecialchars($banner['image_path']); ?>" 
                                         alt="Banner #<?php echo $banner['id']; ?>" 
                                         class="w-full h-auto mb-4 border border-backgroundTextarea">
                                <?php endif; ?>
                                
                                <!-- Banner Metadata -->
                                <div class="space-y-3 mb-4">
                                    <p class="text-textColor">
                                        <strong>URL:</strong> 
                                        <a href="<?php echo htmlspecialchars($banner['url']); ?>" 
                                           target="_blank" 
                                           class="text-primary hover:text-primaryHover break-all">
                                            <?php echo htmlspecialchars($banner['url']); ?>
                                        </a>
                                    </p>
                                    
                                    <p class="text-textColor">
                                        <strong>Buyer:</strong> 
                                        <span class="text-primary"><?php echo htmlspecialchars($banner['buyer_username'] ?? 'N/A'); ?></span>
                                    </p>
                                    
                                    <p class="text-textColor">
                                        <strong>Added:</strong> 
                                        <?php echo date('d.m.Y H:i', strtotime($banner['created_at'])); ?>
                                    </p>
                                    
                                    <p class="text-textColor <?php echo $isExpired ? 'text-red-400' : ''; ?>">
                                        <strong>Expires:</strong> 
                                        <?php echo $expiresAtFormatted; ?>
                                        <?php if ($isExpired): ?>
                                            <span class="text-red-400 ml-1">(Expired)</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php if (!empty($banner['expires_at'])): ?>
                                        <?php $isExpired = strtotime($banner['expires_at']) < time(); ?>
                                        <p class="text-textColor <?php echo $isExpired ? 'text-red-400' : ''; ?>">
                                            <strong>Time:</strong> 
                                            <?php echo $expiresChoice; ?>
                                            <?php if ($isExpired): ?>
                                                <span class="text-red-400 ml-1">(Expired)</span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-textColorHover">
                                                (until <?php echo date('d.m.Y H:i', strtotime($banner['expires_at'])); ?>)
                                            </small>
                                        </p>
                                    <?php endif; ?>

                                    
                                    <?php if (!empty($price)): ?>
                                        <p class="text-textColor">
                                            <strong>Price:</strong> 
                                            <?php echo $price; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($banner['views']) || $banner['views'] === 0): ?>
                                        <p class="text-textColor">
                                            <strong>Total View:</strong> 
                                            <?php echo $banner['views']; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($banner['clicks']) || $banner['clicks'] === 0): ?>
                                        <p class="text-textColor">
                                            <strong>Total Clicks:</strong> 
                                            <?php echo $banner['clicks']; ?>
                                        </p>
                                    <?php endif; ?>

                                    
                                    <?php if (!empty($banner['extra_info'])): ?>
                                        <p class="text-textColor">
                                            <strong>Notes:</strong> 
                                            <?php echo htmlspecialchars($banner['extra_info']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex space-x-2">
                                    <button onclick="openEditModal(
                                        <?php echo $banner['id']; ?>, 
                                        '<?php echo htmlspecialchars($banner['url'], ENT_QUOTES); ?>', 
                                        <?php echo $banner['active']; ?>,
                                        '<?php echo htmlspecialchars($banner['buyer_username'] ?? '', ENT_QUOTES); ?>',
                                        '<?php echo $banner['expires_at'] ?? ''; ?>',
                                        '<?php echo htmlspecialchars($banner['extra_info'] ?? '', ENT_QUOTES); ?>',
                                        <?php echo $banner['price'] ?? 0; ?>
                                    )" class="px-3 py-1 bg-primary hover:bg-primaryHover text-background rounded">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    
                                    <button onclick="deleteBanner(<?php echo $banner['id']; ?>)" 
                                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                    
                                    <button onclick="showBannerStats(<?php echo $banner['id']; ?>)" 
                                            class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded">
                                        <i class="fas fa-chart-bar mr-1"></i> Stats
                                    </button>

                                    <?php $isPinned = in_array((int)$banner['id'], $__pinnedBannerIds); ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="pin_banner">
                                        <input type="hidden" name="banner_id" value="<?php echo (int)$banner['id']; ?>">
                                        <?php if ($isPinned): ?>
                                            <button type="submit" class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded">
                                                <i class="fas fa-thumbtack mr-1"></i> Pinned ✓
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded">
                                                <i class="fas fa-thumbtack mr-1"></i> Pin
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!--Stats Modal-->
<div id="statsModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Modal backdrop -->
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
            <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
        </div>
        
        <!-- Modal container -->
        <div class="inline-block align-bottom bg-backgroundTextarea mt-50 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-bold text-textColor" id="statsModalTitle">
                            Banner Statistics
                        </h3>
                        
                        <div class="mt-4 grid grid-cols-1 gap-4">
                            <!-- Key Metrics -->
                            <div class="bg-background2 rounded-lg p-4">
                                <h4 class="font-bold text-primary mb-2">Performance Summary</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-textColor">Total Views:</span>
                                        <span class="font-bold" id="totalViews">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-textColor">Total Clicks:</span>
                                        <span class="font-bold" id="totalClicks">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-textColor">CTR:</span>
                                        <span class="font-bold" id="ctr">0%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-textColor">Impressions/Day:</span>
                                        <span class="font-bold" id="impressionsPerDay">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-textColor">Days Active:</span>
                                        <span class="font-bold" id="daysActive">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="closeStatsModal()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary text-base font-medium text-white hover:bg-primaryHover sm:ml-3 sm:w-auto sm:text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-backgroundTextarea rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold text-textColor mb-4">Edit Banner</h3>
    
            <form id="editForm" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="update_banner">
                <input type="hidden" name="banner_id" id="editBannerId" value="">
    
                <div>
                    <label class="block text-textColor mb-2">Target URL</label>
                    <input type="url" name="url" id="editUrl" required class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
    
                <div>
                    <label class="block text-textColor mb-2">Buyer Username<span class="text-red-500">*</span></label>
                    <input type="text" name="buyer_username" placeholder="@username of buyer Telegram/Discord" id="editBuyerUsername" required class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
    
                <div>
                    <label class="block text-textColor mb-2">Expires At (optional)</label>
                    <select name="expires_at" class="bg-background2 text-textColor p-2 rounded w-full">
                        <option value="">No expiration</option>
                        <option value="1 week">1 week</option>
                        <option value="2 weeks">2 weeks</option>
                        <option value="1 month">1 month</option>
                        <option value="2 months">2 months</option>
                        <option value="3 months">3 months</option>
                        <option value="4 months">4 months</option>
                        <option value="6 months">6 months</option>
                        <option value="1 year">1 year</option>
                    </select>
                    <p class="text-textColorHover text-sm mt-1">Select expiration period or leave as "No expiration".</p>
                </div>
                
                <div>
                    <label class="block text-textColor mb-2">Price (USD)</label>
                    <input type="number" name="price" id="editPrice" step="0.01" min="0"
                           class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
    
                <div>
                    <label class="block text-textColor mb-2">Extra Info (optional)</label>
                    <textarea name="extra_info" id="editExtraInfo" rows="3" class="bg-background2 text-textColor p-2 rounded w-full"></textarea>
                </div>
    
                <div class="flex items-center">
                    <input type="checkbox" name="active" id="editActive" class="mr-2">
                    <label class="text-textColor">Active</label>
                </div>
    
                <div class="flex justify-end space-x-4 pt-4 border-t border-background2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary hover:bg-primaryHover text-background rounded">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-backgroundTextarea rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold text-textColor mb-4">Confirm Deletion</h3>
            <p class="text-textColor mb-6">Are you sure you want to delete this banner? This action cannot be undone.</p>
            
            <form id="deleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="delete_banner">
                <input type="hidden" name="banner_id" id="deleteBannerId" value="">
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="closeDeleteModal()" 
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment"></script>
    <script>
    function showBannerStats(bannerId) {
    // Show loading state
    document.getElementById('statsModalTitle').textContent = `Loading Banner #${bannerId} Stats...`;
    
    // Fetch banner stats via AJAX
    fetch(`https://example.com/getStats.php?id=${bannerId}`)
    .then(response => response.json())
    .then(data => {
        // Update modal title if bannerId exists
        if (bannerId) {
            document.getElementById('statsModalTitle').textContent = `Banner #${bannerId} Statistics`;
        }
        
        // Update key metrics if they exist
        if (typeof data.total_views !== 'undefined') {
            document.getElementById('totalViews').textContent = data.total_views;
        }
        if (typeof data.total_clicks !== 'undefined') {
            document.getElementById('totalClicks').textContent = data.total_clicks;
        }
        if (typeof data.ctr !== 'undefined') {
            document.getElementById('ctr').textContent = data.ctr + '%';
        }
        if (typeof data.impressions_per_day !== 'undefined') {
            document.getElementById('impressionsPerDay').textContent = data.impressions_per_day;
        }
        if (typeof data.days_active !== 'undefined') {
            document.getElementById('daysActive').textContent = data.days_active;
        }
        

        if (data.performance_data && data.performance_data.views && data.performance_data.clicks) {
            renderPerformanceChart(data.performance_data);
        }
        
        if (data.click_patterns) {
            renderClickPatternChart(data.click_patterns);
        }
        
        // Show modal
        document.getElementById('statsModal').classList.remove('hidden');
    })
    .catch(error => {
        console.error('Error loading banner stats:', error);
        alert('Failed to load banner statistics');
    });

}

function closeStatsModal() {
    document.getElementById('statsModal').classList.add('hidden');
}

// Chart rendering functions
let performanceChart, clickPatternChart;

function renderPerformanceChart(data) {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    if (performanceChart) {
        performanceChart.destroy();
    }
    
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            datasets: [{
                label: 'Views',
                data: data.views,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.3,
                fill: true
            }, {
                label: 'Clicks',
                data: data.clicks,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'day'
                    }
                },
                y: {
                    beginAtZero: true
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

function renderClickPatternChart(data) {
    const ctx = document.getElementById('clickPatternChart').getContext('2d');
    
    if (clickPatternChart) {
        clickPatternChart.destroy();
    }
    
    clickPatternChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Direct Clicks', 'Referral Clicks', 'Repeat Clicks'],
            datasets: [{
                data: [data.direct, data.referral, data.repeat],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
        // Edit banner
        function openEditModal(bannerId, url, active, buyerUsername, currentExpiresAt, extraInfo, price) {
            document.getElementById('editBannerId').value = bannerId;
            document.getElementById('editUrl').value = url;
            document.getElementById('editActive').checked = active === 1;
            document.getElementById('editBuyerUsername').value = buyerUsername || '';
            document.getElementById('editExtraInfo').value = extraInfo || '';
            document.getElementById('editPrice').value = price || '0.00';
            
            const expiresAtSelect = document.querySelector('#editModal select[name="expires_at"]');
            expiresAtSelect.value = '';
            
            if (currentExpiresAt) {
                const now = new Date();
                const expiresDate = new Date(currentExpiresAt);
                const timeDiff = expiresDate - now;
                
                const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
                
                if (daysDiff >= 365) {
                    expiresAtSelect.value = '1 year';
                } else if (daysDiff >= 180) {
                    expiresAtSelect.value = '6 months';
                } else if (daysDiff >= 120) {
                    expiresAtSelect.value = '4 months';
                } else if (daysDiff >= 90) {
                    expiresAtSelect.value = '3 months';
                } else if (daysDiff >= 60) {
                    expiresAtSelect.value = '2 months';
                } else if (daysDiff >= 30) {
                    expiresAtSelect.value = '1 month';
                } else if (daysDiff >= 14) {
                    expiresAtSelect.value = '2 weeks';
                } else if (daysDiff >= 7) {
                    expiresAtSelect.value = '1 week';
                }
                
            }
            
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }
        
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }
        
        // Delete banner confirmation
        function deleteBanner(bannerId) {
            document.getElementById('deleteBannerId').value = bannerId;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }
        
        // Tab switching for upload/external URL
        function showUploadTab() {
            document.getElementById('uploadTab').classList.remove('hidden');
            document.getElementById('externalTab').classList.add('hidden');
            document.getElementById('uploadTabBtn').classList.add('bg-primary');
            document.getElementById('uploadTabBtn').classList.remove('bg-background2');
            document.getElementById('uploadTabBtn').classList.add('text-background');
            document.getElementById('uploadTabBtn').classList.remove('text-textColor');
            document.getElementById('externalTabBtn').classList.remove('bg-primary');
            document.getElementById('externalTabBtn').classList.add('bg-background2');
            document.getElementById('externalTabBtn').classList.remove('text-background');
            document.getElementById('externalTabBtn').classList.add('text-textColor');
            document.getElementById('isExternal').value = '0';
        }
        
        function showExternalTab() {
            document.getElementById('uploadTab').classList.add('hidden');
            document.getElementById('externalTab').classList.remove('hidden');
            document.getElementById('uploadTabBtn').classList.remove('bg-primary');
            document.getElementById('uploadTabBtn').classList.add('bg-background2');
            document.getElementById('uploadTabBtn').classList.remove('text-background');
            document.getElementById('uploadTabBtn').classList.add('text-textColor');
            document.getElementById('externalTabBtn').classList.add('bg-primary');
            document.getElementById('externalTabBtn').classList.remove('bg-background2');
            document.getElementById('externalTabBtn').classList.add('text-background');
            document.getElementById('externalTabBtn').classList.remove('text-textColor');
            document.getElementById('isExternal').value = '1';
        }
        
        // Close modals when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
