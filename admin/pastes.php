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
        header('Location: pastes.php');
        exit;
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_paste':
                $pasteId = (int)$_POST['paste_id'];
                
                // Delete the paste
                $stmt = $pdo->prepare("DELETE FROM pastes WHERE id = ?");
                if ($stmt->execute([$pasteId])) {
                    setFlashMessage('success', 'Paste successfully deleted.');
                } else {
                    setFlashMessage('error', 'Error deleting paste.');
                }
                break;
                
            case 'update_visibility':
                $pasteId = (int)$_POST['paste_id'];
                $visibility = sanitizeInput($_POST['visibility']);
                
                // Update paste visibility
                $stmt = $pdo->prepare("UPDATE pastes SET visibility = ? WHERE id = ?");
                if ($stmt->execute([$visibility, $pasteId])) {
                    setFlashMessage('success', 'Paste visibility successfully updated.');
                } else {
                    setFlashMessage('error', 'Error updating paste visibility.');
                }
                break;
                
            case 'toggle_pin':
                $pasteId = (int)$_POST['paste_id'];
                $isPinned = (bool)$_POST['is_pinned'];
                
                if ($isPinned) {
                    $result = unpinPaste($pasteId);
                } else {
                    $result = pinPaste($pasteId);
                }
                
                if ($result['success']) {
                    setFlashMessage('success', $result['message']);
                } else {
                    setFlashMessage('error', $result['message']);
                }
                break;
        }
        
        header('Location: pastes.php');
        exit;
    }
}

// Get pastes list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search filter
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition = "WHERE p.title LIKE ? OR p.content LIKE ?";
    $searchParams = ["%$search%", "%$search%"];
}

// Get total pastes count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM pastes p 
    $searchCondition
");
$countStmt->execute($searchParams);
$totalPastes = $countStmt->fetch()['total'];
$totalPages = ceil($totalPastes / $perPage);

// Get pastes with user info
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.emoji, u.name_color,
    COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'like'), 0) as likes,
    COALESCE((SELECT COUNT(*) FROM paste_likes WHERE paste_id = p.id AND type = 'dislike'), 0) as dislikes
    FROM pastes p
    LEFT JOIN users u ON p.user_id = u.id
    $searchCondition
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");

$params = array_merge($searchParams, [$perPage, $offset]);
$stmt->execute($params);
$pastes = $stmt->fetchAll();

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paste Management - <?php echo SITE_NAME; ?></title>
    
    <link href="../css/style.css" rel="stylesheet" type="text/css">
    <link href="../css/responsive.css" rel="stylesheet" type="text/css">
    <link href="../css/dark.css" rel="stylesheet" type="text/css">
    <link href="../css/fonts.css" rel="stylesheet" type="text/css">
    <link href="../css/common.css" rel="stylesheet" type="text/css">
    
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
            <h1 class="text-2xl font-bold text-textColor">Paste Management</h1>
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
        
        <!-- Search Form -->
        <div class="bg-backgroundTextarea rounded-lg p-6 mb-8">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by title or content" 
                           class="bg-background2 text-textColor p-2 rounded w-full">
                </div>
                <div>
                    <button type="submit" 
                            class="px-4 py-2 bg-primary hover:bg-primaryHover text-background rounded w-full md:w-auto">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                </div>
                <?php if (!empty($search)): ?>
                <div>
                    <a href="pastes.php" 
                       class="px-4 py-2 bg-background2 hover:bg-gray-700 text-textColor rounded inline-block text-center w-full md:w-auto">
                        <i class="fas fa-times mr-1"></i> Clear
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Pastes List -->
        <div class="bg-backgroundTextarea rounded-lg overflow-hidden mb-8">
            <h2 class="text-xl font-bold text-textColor p-6 border-b border-background2">
                Pastes List
                <?php if (!empty($search)): ?>
                <span class="text-sm font-normal ml-2">
                    (Search results for: "<?php echo htmlspecialchars($search); ?>")
                </span>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($pastes)): ?>
                <p class="p-6 text-textColor">No pastes found.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-background2 text-textColor">
                                <th class="py-3 px-4 text-left">ID</th>
                                <th class="py-3 px-4 text-left">Title</th>
                                <th class="py-3 px-4 text-left">User</th>
                                <th class="py-3 px-4 text-left">Created</th>
                                <th class="py-3 px-4 text-left">Views</th>
                                <th class="py-3 px-4 text-left">Likes</th>
                                <th class="py-3 px-4 text-left">Visibility</th>
                                <th class="py-3 px-4 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-background2">
                            <?php foreach ($pastes as $paste): ?>
                                <tr class="hover:bg-background2">
                                    <td class="py-3 px-4 text-textColor"><?php echo $paste['id']; ?></td>
                                    <td class="py-3 px-4 text-textColor">
                                        <a href="../view.php?id=<?php echo $paste['id']; ?>" 
                                           target="_blank" 
                                           class="text-primary hover:text-primaryHover">
                                            <?php echo htmlspecialchars(substr($paste['title'], 0, 30)); ?>
                                            <?php echo (strlen($paste['title']) > 30) ? '...' : ''; ?>
                                        </a>
                                    </td>
                                    <td class="py-3 px-4 text-textColor">
                                        <?php if ($paste['user_id']): ?>
                                            <span style="color: <?php echo htmlspecialchars($paste['name_color']); ?>">
                                                <?php echo $paste['emoji']; ?> <?php echo htmlspecialchars($paste['username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-textColorHover">Anonymous</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-textColor">
                                        <?php echo date('d.m.Y H:i', strtotime($paste['created_at'])); ?>
                                    </td>
                                    <td class="py-3 px-4 text-textColor">
                                        <?php echo $paste['views']; ?>
                                    </td>
                                    <td class="py-3 px-4 text-textColor">
                                        <span class="text-green-500 mr-2">
                                            <i class="fas fa-thumbs-up"></i> <?php echo $paste['likes']; ?>
                                        </span>
                                        <span class="text-red-500">
                                            <i class="fas fa-thumbs-down"></i> <?php echo $paste['dislikes']; ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="update_visibility">
                                            <input type="hidden" name="paste_id" value="<?php echo $paste['id']; ?>">
                                            <select name="visibility" 
                                                    onchange="this.form.submit()" 
                                                    class="bg-background2 text-textColor p-1 rounded border border-background">
                                                <option value="public" <?php echo $paste['visibility'] === 'public' ? 'selected' : ''; ?>>
                                                    Public
                                                </option>
                                                <option value="unlisted" <?php echo $paste['visibility'] === 'unlisted' ? 'selected' : ''; ?>>
                                                    Unlisted
                                                </option>
                                                <option value="private" <?php echo $paste['visibility'] === 'private' ? 'selected' : ''; ?>>
                                                    Private
                                                </option>
                                                <option value="password" <?php echo $paste['visibility'] === 'password' ? 'selected' : ''; ?>>
                                                    Password
                                                </option>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="py-3 px-4 text-textColor">
                                        <div class="flex space-x-2">
                                            <a href="../view.php?id=<?php echo $paste['id']; ?>" 
                                               target="_blank"
                                               class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            
                                            <button onclick="deletePaste(<?php echo $paste['id']; ?>)" 
                                                    class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded text-xs">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            
                                            <button onclick="togglePin(<?php echo $paste['id']; ?>, <?php echo $paste['is_pinned'] ? 'true' : 'false'; ?>)" 
                                                    class="px-2 py-1 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-xs">
                                                <i class="fas fa-thumbtack"></i> <?php echo $paste['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php 
        if ($totalPages > 1) {
            $paginationHtml = renderPagination($page, $totalPages, 'pastes.php', ['search' => $search]);
            $paginationHtml = str_replace('pagination-container"', 'pagination-container" style="margin-top:16px;margin-bottom:32px;"', $paginationHtml);
            echo $paginationHtml;
        }
        ?>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-backgroundTextarea rounded-lg p-6 max-w-md w-full">
            <h3 class="text-xl font-bold text-textColor mb-4">Confirm Deletion</h3>
            <p class="text-textColor mb-6">Are you sure you want to delete this paste? This action cannot be undone.</p>
            
            <form id="deleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="delete_paste">
                <input type="hidden" name="paste_id" id="deletePasteId" value="">
                
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
        // Delete paste confirmation
        function deletePaste(pasteId) {
            document.getElementById('deletePasteId').value = pasteId;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteModal').classList.add('flex');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('deleteModal').classList.remove('flex');
        }
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        
        function togglePin(pasteId, isPinned) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const csrfTokenInput = document.createElement('input');
            csrfTokenInput.type = 'hidden';
            csrfTokenInput.name = 'csrf_token';
            csrfTokenInput.value = '<?php echo $csrfToken; ?>';
            form.appendChild(csrfTokenInput);
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'toggle_pin';
            form.appendChild(actionInput);
            
            const pasteIdInput = document.createElement('input');
            pasteIdInput.type = 'hidden';
            pasteIdInput.name = 'paste_id';
            pasteIdInput.value = pasteId;
            form.appendChild(pasteIdInput);
            
            const isPinnedInput = document.createElement('input');
            isPinnedInput.type = 'hidden';
            isPinnedInput.name = 'is_pinned';
            isPinnedInput.value = isPinned;
            form.appendChild(isPinnedInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
