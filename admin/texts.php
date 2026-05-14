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
        header('Location: texts.php');
        exit;
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_text':
                $text = sanitizeInput($_POST['text']);
                $url = sanitizeInput($_POST['url']);
                $style = sanitizeInput($_POST['style'] ?? '');
                
                if (!empty($text) && !empty($url)) {
                    if (addBannerText($text, $url, $style)) {
                        setFlashMessage('success', 'Banner text successfully added.');
                    } else {
                        setFlashMessage('error', 'Error adding banner text.');
                    }
                } else {
                    setFlashMessage('error', 'Please fill in all fields.');
                }
                break;
                
            case 'update_text':
                $textId = (int)$_POST['text_id'];
                $text = sanitizeInput($_POST['text']);
                $url = sanitizeInput($_POST['url']);
                $style = sanitizeInput($_POST['style'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;
                
                if (!empty($text) && !empty($url)) {
                    if (updateBannerText($textId, $text, $url, $style, $active)) {
                        setFlashMessage('success', 'Banner text successfully updated.');
                    } else {
                        setFlashMessage('error', 'Error updating banner text.');
                    }
                } else {
                    setFlashMessage('error', 'Please fill in all fields.');
                }
                break;
                
            case 'delete_text':
                $textId = (int)$_POST['text_id'];
                
                if (deleteBannerText($textId)) {
                    setFlashMessage('success', 'Banner text successfully deleted.');
                } else {
                    setFlashMessage('error', 'Error deleting banner text.');
                }
                break;
            case 'pin_text':
                $textId = !empty($_POST['text_id']) ? (int)$_POST['text_id'] : null;
                if ($textId) {
                    // Toggle pin: if already pinned, unpin; otherwise pin
                    $wasPinned = isTextPinned($textId);
                    if (setPinnedText($textId, true)) { // true = toggle mode
                        if ($wasPinned) {
                            setFlashMessage('success', 'Banner text unpinned from view page.');
                        } else {
                            setFlashMessage('success', 'Banner text pinned to view page.');
                        }
                    } else {
                        setFlashMessage('error', 'Failed to update pinned text.');
                    }
                } else {
                    if (setPinnedText(null)) {
                        setFlashMessage('success', 'All pinned banner texts removed.');
                    } else {
                        setFlashMessage('error', 'Failed to remove pinned banner texts.');
                    }
                }
                break;
        }
        
        header('Location: texts.php');
        exit;
    }
}

// Get banner texts list
$stmt = $pdo->query("SELECT * FROM banner_texts ORDER BY created_at DESC");
$texts = $stmt->fetchAll();
// Current pinned text ids (array)
$__pinnedIds = getPinnedIds();
$__pinnedTextIds = $__pinnedIds['text_ids'] ?? [];

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner Text Management - <?php echo SITE_NAME; ?></title>
    
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
            <h1 class="text-2xl font-bold text-textColor">Banner Text Management</h1>
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
        
        <!-- Add Banner Text Form -->
        <div class="bg-backgroundTextarea rounded-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-textColor mb-4">Add New Banner Text</h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="add_text">
                
                <div>
                    <label class="block text-textColor mb-2">Banner Text</label>
                    <textarea name="text" required rows="3" 
                              class="bg-background2 text-textColor p-2 rounded w-full"></textarea>
                </div>
                
                <div>
                    <label class="block text-textColor mb-2">Target URL</label>
                    <input type="url" name="url" required 
                           class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
                
                <div>
                    <label class="block text-textColor mb-2">Custom Style (optional)</label>
                    <input type="text" name="style" placeholder="class name" 
                           class="bg-background2 text-textColor p-2 rounded w-full">
                    <p class="text-textColorHover text-sm mt-1">Example: glory_rank_pto</p>
                </div>
                
                <div>
                    <button type="submit" 
                            class="px-4 py-2 bg-primary hover:bg-primaryHover text-background rounded">
                        Add Banner Text
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Banner Texts List -->
        <div class="bg-backgroundTextarea rounded-lg overflow-hidden">
            <h2 class="text-xl font-bold text-textColor p-6 border-b border-background2">Existing Banner Texts</h2>
            
            <?php if (empty($texts)): ?>
                <p class="p-6 text-textColor">No banner texts found.</p>
            <?php else: ?>
                <div class="divide-y divide-background2">
                    <?php foreach ($texts as $text): ?>
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <?php
                                        // Decode once in case legacy rows are HTML-encoded; we'll escape on output
                                        $displayText = isset($text['text']) ? html_entity_decode($text['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                                        $displayUrl = isset($text['url']) ? html_entity_decode($text['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                                    ?>
                                    <p class="text-textColor text-lg mb-2"><?php echo nl2br(htmlspecialchars($displayText)); ?></p>
                                    <p class="text-textColorHover">
                                        <strong>URL:</strong> 
                                        <a href="<?php echo htmlspecialchars($displayUrl); ?>" 
                                           target="_blank" 
                                           class="text-primary hover:text-primaryHover break-all">
                                            <?php echo htmlspecialchars($displayUrl); ?>
                                        </a>
                                    </p>
                                    <?php if (!empty($text['clicks']) || $text['clicks'] === 0): ?>
                                        <p class="text-textColorHover mt-2">
                                            <strong>Total Clicks:</strong> 
                                            <?php $text['clicks']; ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($text['views']) || $text['views'] === 0): ?>
                                        <p class="text-textColorHover mt-2">
                                            <strong>Total Views:</strong> 
                                            <?php echo $text['views']; ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="text-textColorHover mt-2">
                                        <strong>Added:</strong> 
                                        <?php echo date('d.m.Y H:i', strtotime($text['created_at'])); ?>
                                    </p>
                                    <?php if (!empty($text['style']) || $text['style'] === 0): ?>
                                        <p class="text-textColorHover mt-2">
                                            <strong>Style:</strong> 
                                            <?php echo htmlspecialchars($text['style'] ?? ''); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2 py-1 rounded text-xs <?php echo $text['active'] ? 'bg-green-800' : 'bg-red-800'; ?>">
                                    <?php echo $text['active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button onclick="openEditModal(
                                    <?php echo $text['id']; ?>,
                                    '<?php echo addslashes(htmlspecialchars($text['text'])); ?>',
                                    '<?php echo htmlspecialchars($text['url']); ?>',
                                    '<?php echo htmlspecialchars($text['style'] ?? 'NOT ADDED'); ?>',
                                    <?php echo $text['active']; ?>
                                )" class="px-3 py-1 bg-primary hover:bg-primaryHover text-background rounded">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </button>
                                
                                <button onclick="deleteText(<?php echo $text['id']; ?>)" 
                                        class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white rounded">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>

                                <?php $isPinned = in_array((int)$text['id'], $__pinnedTextIds); ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="pin_text">
                                    <input type="hidden" name="text_id" value="<?php echo (int)$text['id']; ?>">
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
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-backgroundTextarea rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold text-textColor mb-4">Edit Banner Text</h3>
            
            <form id="editForm" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="update_text">
                <input type="hidden" name="text_id" id="editTextId" value="">
                
                <div>
                    <label class="block text-textColor mb-2">Banner Text</label>
                    <textarea name="text" id="editText" required rows="3" 
                              class="bg-background2 text-textColor p-2 rounded w-full"></textarea>
                </div>
                
                <div>
                    <label class="block text-textColor mb-2">Target URL</label>
                    <input type="url" name="url" id="editUrl" required 
                           class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
                
                <div>
                    <label class="block text-textColor mb-2">Custom Style</label>
                    <input type="text" name="style" id="editStyle" 
                           placeholder="class name"
                           class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="active" id="editActive" 
                           class="mr-2">
                    <label class="text-textColor">Active</label>
                </div>
                
                <div class="flex justify-end space-x-4 pt-4 border-t border-background2">
                    <button type="button" onclick="closeEditModal()" 
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-primary hover:bg-primaryHover text-background rounded">
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
            <p class="text-textColor mb-6">Are you sure you want to delete this banner text? This action cannot be undone.</p>
            
            <form id="deleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="delete_text">
                <input type="hidden" name="text_id" id="deleteTextId" value="">
                
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
    
    <script>
        // Edit banner text
        function openEditModal(textId, text, url, style, active) {
            document.getElementById('editTextId').value = textId;
            document.getElementById('editText').value = text;
            document.getElementById('editUrl').value = url;
            document.getElementById('editStyle').value = style || '';
            document.getElementById('editActive').checked = active === 1;
            
            document.getElementById('editModal').classList.remove('hidden');
            document.getElementById('editModal').classList.add('flex');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }
        
        // Delete banner text confirmation
        function deleteText(textId) {
            document.getElementById('deleteTextId').value = textId;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
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
