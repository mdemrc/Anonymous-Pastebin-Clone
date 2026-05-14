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
    if (isset($_POST['action'])) {
        $userId = (int)$_POST['user_id'];
        
        switch ($_POST['action']) {
            case 'update_user':
                $emoji = sanitizeInput($_POST['emoji']);
                $nameColor = sanitizeInput($_POST['name_color']);
                $role = sanitizeInput($_POST['role']);
                
                $stmt = $pdo->prepare("UPDATE users SET emoji = ?, name_color = ?, role = ? WHERE id = ?");
                $stmt->execute([$emoji, $nameColor, $role, $userId]);
                break;
                
            case 'delete_user':
                // First delete all user's pastes
                $stmt = $pdo->prepare("DELETE FROM pastes WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Then delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                break;
                
            case 'ban_user':
                $banReason = sanitizeInput($_POST['ban_reason']);
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?");
                $stmt->execute([$banReason, $userId]);
                break;
                
            case 'unban_user':
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?");
                $stmt->execute([$userId]);
                break;
        }
        
        header('Location: users.php');
        exit;
    }
}

// Handle search and limit parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limitInput = $_GET['limit'] ?? 20;
$limit = $limitInput === 'all' ? null : (int)$limitInput;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = $limit !== null ? ($page - 1) * $limit : 0;

// Count total users with search filter
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'admin' AND username LIKE :search");
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();
} else {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
}
$totalPages = $limit ? ceil($totalUsers / $limit) : 1;

// Fetch users with optional search and pagination
$sql = "SELECT u.*, 
           COUNT(p.id) as paste_count,
           SUM(p.views) as total_views,
           SUM(p.rating) as total_rating
    FROM users u
    LEFT JOIN pastes p ON u.id = p.user_id
    WHERE u.role != 'admin'";

$params = [];
if (!empty($search)) {
    $sql .= " AND u.username LIKE :search";
    $params[':search'] = "%$search%";
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

if ($limit !== null) {
    $sql .= " LIMIT :limit OFFSET :offset";
}

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
if ($limit !== null) {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}
$stmt->execute();
$users = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo SITE_NAME; ?></title>
    
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emoji-mart@latest/css/emoji-mart.css">
    <script src="https://cdn.jsdelivr.net/npm/emoji-mart@latest/dist/emoji-mart.js"></script>
</head>
<body class="bg-background min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-textColor">User Management</h1>
            <a href="./" class="text-primary hover:text-primaryHover">← Back to Admin Panel</a>
        </div>

        <!-- Search and Pagination Controls -->
        <form method="GET" class="flex justify-between items-center mb-6 bg-backgroundTextarea p-4 rounded-lg" id="searchForm">
            <!-- Left side: Entries dropdown -->
            <div class="flex items-center space-x-2">
                <label class="text-textColor">Show</label>
                <select name="limit" onchange="document.getElementById('searchForm').submit();" class="bg-background2 text-textColor p-2 rounded">
                    <?php foreach ([10, 20, 25, 50, 100, 500, 'all'] as $val): ?>
                        <option value="<?php echo $val; ?>" <?php echo ($limitInput == $val) ? 'selected' : ''; ?>>
                            <?php echo $val === 'all' ? 'All' : $val; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-textColor">entries</span>
            </div>

            <!-- Right side: Search input -->
            <div class="flex items-center space-x-2">
                <label class="text-textColor">Search:</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       class="bg-background2 text-textColor p-2 rounded w-48" placeholder="Search username...">
                <button type="submit" class="bg-primary hover:bg-primaryHover text-background px-3 py-2 rounded">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="users.php?limit=<?php echo urlencode($limitInput); ?>" class="text-sm text-red-400 hover:underline">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Results Info -->
        <div class="mb-4 text-textColor">
            Showing <?php echo count($users); ?> of <?php echo $totalUsers; ?> users
            <?php if (!empty($search)): ?>
                for search "<?php echo htmlspecialchars($search); ?>"
            <?php endif; ?>
        </div>

        <!-- Users Table -->
        <div class="bg-backgroundTextarea rounded-lg overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="text-left">
                        <th class="p-4 text-primary">User</th>
                        <th class="p-4 text-primary">Email</th>
                        <th class="p-4 text-primary">Pastes</th>
                        <th class="p-4 text-primary">Views</th>
                        <th class="p-4 text-primary">Rating</th>
                        <th class="p-4 text-primary">Status</th>
                        <th class="p-4 text-primary">Registration Date</th>
                        <th class="p-4 text-primary">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="p-8 text-center text-textColor">
                            <?php if (!empty($search)): ?>
                                No users found matching "<?php echo htmlspecialchars($search); ?>"
                            <?php else: ?>
                                No users found
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr class="border-t border-background2 <?php echo $user['is_banned'] ? 'bg-red-900 bg-opacity-20' : ''; ?>">
                        <td class="p-4">
                            <div class="flex items-center">
                                <span style="color: <?php echo htmlspecialchars($user['name_color']); ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </span>
                                <?php if ($user['emoji']): ?>
                                    <span class="ml-1"><?php echo displayUserEmoji($user['emoji']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-4 text-textColor"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="p-4 text-textColor"><?php echo $user['paste_count']; ?></td>
                        <td class="p-4 text-textColor"><?php echo $user['total_views']; ?></td>
                        <td class="p-4 text-textColor"><?php echo $user['total_rating']; ?></td>
                        <td class="p-4">
                            <?php if ($user['is_banned']): ?>
                                <span class="text-red-500 font-bold">Banned</span>
                                <?php if ($user['ban_reason']): ?>
                                    <span class="text-red-400 text-xs block"><?php echo htmlspecialchars($user['ban_reason']); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-green-500">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-textColor"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                        <td class="p-4">
                            <button onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo $user['emoji']; ?>', '<?php echo $user['name_color']; ?>', '<?php echo $user['role']; ?>')" 
                                    class="text-primary hover:text-primaryHover mr-2">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <?php if ($user['is_banned']): ?>
                                <button onclick="unbanUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                        class="text-green-500 hover:text-green-400 mr-2" title="Unban User">
                                    <i class="fas fa-user-check"></i>
                                </button>
                            <?php else: ?>
                                <button onclick="openBanModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                        class="text-yellow-500 hover:text-yellow-400 mr-2" title="Ban User">
                                    <i class="fas fa-user-slash"></i>
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                    class="text-red-500 hover:text-red-400" title="Delete User">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php 
        if ($limit !== null && $totalPages > 1) {
            $paginationHtml = renderPagination($page, $totalPages, 'users.php', [
                'search' => $search,
                'limit' => $limitInput
            ]);
            $paginationHtml = str_replace('pagination-container"', 'pagination-container" style="margin-top:16px;margin-bottom:32px;"', $paginationHtml);
            echo $paginationHtml;
        }
        ?>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
    <div class="bg-backgroundTextarea rounded-lg p-6 max-w-md w-full">
        <h2 class="text-xl font-bold text-textColor mb-4">Edit User</h2>
        
        <form id="editForm" method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="editUserId">
            
            <div>
                <label class="block text-textColor mb-2">Emoji</label>
                <div class="flex flex-col">
                    <div class="flex mb-2">
                        <input type="text" name="emoji" id="emojiInput" 
                               class="bg-background2 text-textColor p-2 rounded w-full" 
                               placeholder="Emoji path (e.g., crown.gif)">
                    </div>
                    <div class="grid grid-cols-5 gap-2 bg-background2 p-3 rounded max-h-60 overflow-y-auto">
                        <?php
                        $itemsDir = '../Items/';
                        $files = scandir($itemsDir);
                        foreach ($files as $file) {
                            if ($file != '.' && $file != '..' && !is_dir($itemsDir . $file)) {
                                $ext = pathinfo($file, PATHINFO_EXTENSION);
                                if (in_array(strtolower($ext), ['png', 'gif', 'jpg', 'jpeg', 'webp'])) {
                                    echo '<div class="emoji-item cursor-pointer p-2 hover:bg-background rounded flex justify-center items-center" onclick="selectEmoji(\'' . $file . '\')">';
                                    echo '<img src="' . $itemsDir . $file . '" alt="' . $file . '" class="h-8 w-8 object-contain">';
                                    echo '</div>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <div class="mt-2">
                        <p class="text-sm text-textColor">Выбранная эмодзи: <span id="selectedEmojiName" class="font-semibold"></span></p>
                        <div id="selectedEmojiPreview" class="mt-1 flex items-center">
                            <span class="text-sm text-textColor mr-2">Предпросмотр:</span>
                            <img id="emojiPreviewImg" src="" alt="" class="h-6 w-6 object-contain hidden">
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-textColor mb-2">Name Color</label>
                <input type="text" name="name_color" id="colorInput" 
                       class="bg-background2 rounded w-full h-10 p-2" 
                       placeholder="#ffffff or any valid color code">
                <div class="mt-2 flex items-center">
                    <span class="text-sm text-textColor mr-2">Предпросмотр:</span>
                    <span id="colorPreview" class="font-semibold">Username</span>
                </div>
            </div>
            
            <div>
                <label class="block text-textColor mb-2">Role</label>
                <select name="role" id="roleInput" class="bg-background2 text-textColor p-2 rounded w-full">
                    <option value="user">User</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Administrator</option>
                    <option value="developer">Developer</option>
                </select>
            </div>
            
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeEditModal()" 
                        class="px-4 py-2 text-textColor hover:text-textColorHover">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 bg-primary hover:bg-primaryHover text-background rounded">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <!-- Ban Modal -->
    <div id="banModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-backgroundTextarea rounded-lg p-6 max-w-md w-full">
            <h2 class="text-xl font-bold text-textColor mb-4">Ban User</h2>
            
            <form id="banForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="ban_user">
                <input type="hidden" name="user_id" id="banUserId">
                
                <p class="text-textColor">You are about to ban user <span id="banUsername" class="font-bold"></span>.</p>
                
                <div>
                    <label class="block text-textColor mb-2">Ban Reason (optional)</label>
                    <textarea name="ban_reason" 
                              class="bg-background2 text-textColor p-2 rounded w-full h-24" 
                              placeholder="Enter reason for banning this user..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="closeBanModal()" 
                            class="px-4 py-2 text-textColor hover:text-textColorHover">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded">
                        Ban User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(userId, emoji, color, role) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('emojiInput').value = emoji;
            document.getElementById('colorInput').value = color;
            document.getElementById('roleInput').value = role;
            document.getElementById('editModal').classList.remove('hidden');
            
            // Инициализация предпросмотра эмодзи
            if (emoji) {
                document.getElementById('selectedEmojiName').textContent = emoji;
                document.getElementById('emojiPreviewImg').src = '../Items/' + emoji;
                document.getElementById('emojiPreviewImg').classList.remove('hidden');
            }
            
            // Инициализация предпросмотра цвета
            const colorPreview = document.getElementById('colorPreview');
            colorPreview.style.color = color;
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        function openBanModal(userId, username) {
            document.getElementById('banUserId').value = userId;
            document.getElementById('banUsername').textContent = username;
            document.getElementById('banModal').classList.remove('hidden');
        }
        
        function closeBanModal() {
            document.getElementById('banModal').classList.add('hidden');
        }
        
        function unbanUser(userId, username) {
            if (confirm(`Are you sure you want to unban user ${username}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'unban_user';
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                
                form.appendChild(actionInput);
                form.appendChild(userIdInput);
                document.body.appendChild(form);
                
                form.submit();
            }
        }
        
        function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user ${username}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_user';
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                
                form.appendChild(actionInput);
                form.appendChild(userIdInput);
                document.body.appendChild(form);
                
                form.submit();
            }
        }
        
        function selectEmoji(file) {
            // Проверяем расширение файла и добавляем его, если отсутствует
            if (file.indexOf('.') === -1) {
                // Если расширение отсутствует, добавляем .gif по умолчанию
                file = file + '.gif';
            }
            
            document.getElementById('emojiInput').value = file;
            document.getElementById('selectedEmojiName').textContent = file;
            document.getElementById('emojiPreviewImg').src = '../Items/' + file;
            document.getElementById('emojiPreviewImg').classList.remove('hidden');
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const colorInput = document.getElementById('colorInput');
            const colorPreview = document.getElementById('colorPreview');
            
            // Инициализация предпросмотра при открытии модального окна
            colorInput.addEventListener('input', function() {
                colorPreview.style.color = this.value;
            });
        });
    </script>
</body>
</html>