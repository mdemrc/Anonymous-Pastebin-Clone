<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Проверяем, авторизован ли пользователь и имеет ли он права на закрепление паст
if (!isLoggedIn() || !canPinPastes()) {
    header('Location: ../index.php');
    exit;
}

// Обработка действий (закрепление/открепление)
if (isset($_POST['action']) && isset($_POST['paste_id'])) {
    $pasteId = intval($_POST['paste_id']);
    $action = $_POST['action'];
    
    if ($action === 'pin') {
        $result = pinPaste($pasteId);
        $message = $result['message'];
        $success = $result['success'];
    } elseif ($action === 'unpin') {
        $result = unpinPaste($pasteId);
        $message = $result['message'];
        $success = $result['success'];
    }
}

// Получаем список всех закрепленных паст
$pinnedPastes = getPinnedPastes();

// Получаем список всех публичных паст для возможности закрепления
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.emoji, u.name_color
    FROM pastes p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.visibility = 'public' AND p.is_pinned = 0
    ORDER BY p.created_at DESC
    LIMIT 50
");
$stmt->execute();
$unpinnedPastes = $stmt->fetchAll();

// No banners needed for admin panel
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pinned Pastes Management - paste.to</title>
    
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
    
    <style>
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            /* Container padding adjustment */
            .container {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            /* Header adjustments */
            .flex.justify-between.items-center.mb-8 {
                flex-direction: column !important;
                gap: 1rem !important;
                text-align: center !important;
            }
            
            .flex.justify-between.items-center.mb-8 h1 {
                font-size: 1.5rem !important;
                margin-bottom: 0 !important;
            }
            
            /* Success/Error message mobile */
            .bg-green-900\/20,
            .bg-red-900\/20 {
                padding: 1rem !important;
                margin-bottom: 1.5rem !important;
            }
            
            .bg-green-900\/20 .flex,
            .bg-red-900\/20 .flex {
                flex-direction: column !important;
                text-align: center !important;
                gap: 1rem !important;
            }
            
            /* Pinned counter mobile */
            .max-w-md.mx-auto.mb-8 {
                margin-left: 0 !important;
                margin-right: 0 !important;
                max-width: 100% !important;
            }
            
            .max-w-md.mx-auto.mb-8 .bg-gradient-to-r {
                padding: 1.5rem !important;
            }
            
            .max-w-md.mx-auto.mb-8 .text-4xl {
                font-size: 2rem !important;
            }
            
            /* Featured pastes mobile */
            .bg-gradient-to-br.from-backgroundTextarea {
                padding: 1.5rem !important;
            }
            
            .bg-gradient-to-br.from-backgroundTextarea .text-center.mb-8 h2 {
                font-size: 1.75rem !important;
                flex-direction: column !important;
                gap: 1rem !important;
            }
            
            /* Featured paste cards mobile */
            .bg-gradient-to-r.from-background {
                margin-bottom: 1rem !important;
            }
            
            .bg-gradient-to-r.from-background .p-6 {
                padding: 1rem !important;
            }
            
            .bg-gradient-to-r.from-background .flex.items-start.justify-between {
                flex-direction: column !important;
                gap: 1rem !important;
            }
            
            .bg-gradient-to-r.from-background .flex.items-center.gap-4 {
                flex-direction: column !important;
                text-align: center !important;
            }
            
            .bg-gradient-to-r.from-background .flex.items-center.gap-6 {
                flex-direction: column !important;
                gap: 0.5rem !important;
                text-align: center !important;
            }
            
            .bg-gradient-to-r.from-background .text-xl {
                font-size: 1.125rem !important;
                text-align: center !important;
            }
            
            /* Action buttons mobile */
            .flex.gap-3.flex-shrink-0 {
                width: 100% !important;
                justify-content: center !important;
                flex-wrap: wrap !important;
            }
            
            .flex.gap-3.flex-shrink-0 a,
            .flex.gap-3.flex-shrink-0 button {
                flex: 1 !important;
                min-width: 120px !important;
                justify-content: center !important;
                padding: 0.75rem 1rem !important;
                font-size: 0.875rem !important;
            }
            
            /* Empty state mobile */
            .text-center.py-16 {
                padding-top: 3rem !important;
                padding-bottom: 3rem !important;
            }
            
            .text-center.py-16 .w-24.h-24 {
                width: 4rem !important;
                height: 4rem !important;
            }
            
            .text-center.py-16 .text-xl {
                font-size: 1.125rem !important;
            }
            
            /* Search section mobile */
            .bg-backgroundTextarea.rounded-lg.p-6.shadow-lg {
                padding: 1rem !important;
            }
            
            .flex.items-center.gap-4 {
                flex-direction: column !important;
                gap: 1rem !important;
            }
            
            .flex.items-center.gap-4 .flex-1 {
                width: 100% !important;
            }
            
            .flex.items-center.gap-4 button {
                width: 100% !important;
                justify-content: center !important;
            }
            
            /* Paste grid mobile */
            .grid.grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-3.xl\\:grid-cols-4 {
                grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
                gap: 1rem !important;
            }
            
            .paste-item .p-4 {
                padding: 1rem !important;
            }
            
            .paste-item .text-sm {
                font-size: 0.75rem !important;
            }
            
            .paste-item .flex.gap-2 {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }
            
            .paste-item .flex.gap-2 a,
            .paste-item .flex.gap-2 button {
                width: 100% !important;
                justify-content: center !important;
                padding: 0.5rem !important;
                font-size: 0.75rem !important;
            }
            
            /* Progress bar mobile */
            .bg-background.rounded-full.h-3 {
                height: 0.5rem !important;
            }
        }
        
        @media (max-width: 480px) {
            /* Extra small mobile adjustments */
            .container {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            .bg-gradient-to-br.from-backgroundTextarea {
                padding: 1rem !important;
                margin-left: 0.5rem !important;
                margin-right: 0.5rem !important;
            }
            
            .max-w-md.mx-auto.mb-8 .bg-gradient-to-r {
                margin-left: 0.5rem !important;
                margin-right: 0.5rem !important;
                padding: 1rem !important;
            }
            
            .flex.justify-between.items-center.mb-8 h1 {
                font-size: 1.25rem !important;
            }
            
            .bg-gradient-to-r.from-background .p-6 {
                padding: 0.75rem !important;
            }
            
            .text-3xl {
                font-size: 1.5rem !important;
            }
            
            .text-2xl {
                font-size: 1.25rem !important;
            }
        }
    </style>

</head>
<body class="bg-background min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-textColor">Pinned Pastes Management</h1>
            <a href="index.php" class="text-primary hover:text-primaryHover">← Return to Admin Panel</a>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="<?php echo $success ? 'bg-green-900/20 border-green-500/30' : 'bg-red-900/20 border-red-500/30'; ?> border rounded-lg p-6 mb-8 shadow-lg">
                <div class="flex items-center gap-4">
                    <div class="<?php echo $success ? 'bg-green-500' : 'bg-red-500'; ?> rounded-full p-3">
                        <i class="fas <?php echo $success ? 'fa-check' : 'fa-times'; ?> text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="<?php echo $success ? 'text-green-400' : 'text-red-400'; ?> font-bold text-lg mb-1">
                            <?php echo $success ? 'Success!' : 'Error!'; ?>
                        </h3>
                        <p class="<?php echo $success ? 'text-green-300' : 'text-red-300'; ?> text-sm">
                            <?php echo $message; ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Pinned Pastes Count -->
        <div class="max-w-md mx-auto mb-8">
            <div class="bg-gradient-to-r from-primary/10 to-primaryHover/10 border border-primary/30 rounded-xl p-6 text-center shadow-lg">
                <div class="flex items-center justify-center gap-3 mb-3">
                    <i class="fas fa-thumbtack text-primary text-2xl"></i>
                    <h3 class="text-primary font-bold text-xl">Pinned Pastes</h3>
                </div>
                <div class="text-4xl font-bold text-textColor mb-2">
                    <?php echo count($pinnedPastes); ?><span class="text-2xl text-textColorHover">/3</span>
                </div>
                <div class="text-sm text-textColorHover">
                    <?php 
                    $remaining = 3 - count($pinnedPastes);
                    echo $remaining > 0 ? "$remaining more can be pinned" : "Maximum pins reached";
                    ?>
                </div>
                <div class="mt-4">
                    <div class="bg-background rounded-full h-3 overflow-hidden">
                        <div class="bg-gradient-to-r from-primary to-primaryHover h-full transition-all duration-500" 
                             style="width: <?php echo (count($pinnedPastes) / 3) * 100; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Currently Pinned Section - Enhanced Design -->
        <div class="mb-8">
            <div class="bg-gradient-to-br from-backgroundTextarea to-background2 rounded-xl p-8 shadow-2xl border border-gray-600/50">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-textColor mb-2 flex items-center justify-center gap-3">
                        <div class="bg-primary/20 rounded-full p-3">
                            <i class="fas fa-star text-primary text-2xl"></i>
                        </div>
                        Featured Pastes
                    </h2>
                    <p class="text-textColorHover">These pastes are prominently displayed on your website</p>
                </div>
                
                <?php if (empty($pinnedPastes)): ?>
                    <div class="text-center py-16">
                        <div class="bg-gray-700/30 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-thumbtack text-gray-500 text-4xl"></i>
                        </div>
                        <h3 class="text-textColor text-xl font-bold mb-3">No Featured Pastes</h3>
                        <p class="text-textColorHover text-lg mb-6">Start featuring great content by pinning pastes below</p>
                        <div class="bg-primary/10 border border-primary/30 rounded-lg p-4 max-w-md mx-auto">
                            <p class="text-primary text-sm">💡 Featured pastes appear first on your homepage and get more visibility!</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid gap-6">
                        <?php foreach ($pinnedPastes as $index => $paste): ?>
                            <div class="bg-gradient-to-r from-background to-backgroundTextarea rounded-xl border border-primary/20 hover:border-primary/40 transition-all duration-300 hover:shadow-xl group">
                                <div class="p-6">
                                    <div class="flex items-start justify-between gap-6">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-4 mb-4">
                                                <div class="bg-primary/20 rounded-full w-12 h-12 flex items-center justify-center flex-shrink-0">
                                                    <span class="text-primary font-bold text-lg">#<?php echo $index + 1; ?></span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <h4 class="text-textColor font-bold text-xl mb-1 group-hover:text-primary transition-colors" title="<?php echo htmlspecialchars($paste['title']); ?>">
                                                        <?php echo htmlspecialchars($paste['title']); ?>
                                                    </h4>
                                                    <div class="flex items-center gap-6 text-sm text-textColorHover">
                                                        <?php if ($paste['user_id']): ?>
                                                            <span class="flex items-center gap-2">
                                                                <i class="fas fa-user"></i>
                                                                <span class="font-medium"><?php echo htmlspecialchars($paste['username']); ?></span>
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="flex items-center gap-2">
                                                            <i class="fas fa-eye text-blue-400"></i>
                                                            <span><?php echo number_format($paste['views'] ?? 0); ?> views</span>
                                                        </span>
                                                        <span class="flex items-center gap-2">
                                                            <i class="fas fa-calendar text-green-400"></i>
                                                            <span><?php echo date('M j, Y', strtotime($paste['created_at'])); ?></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex gap-3 flex-shrink-0">
                                            <a href="../view.php?id=<?php echo $paste['id']; ?>" 
                                               class="bg-primary hover:bg-primaryHover text-black px-6 py-3 rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg hover:shadow-xl">
                                                <i class="fas fa-external-link-alt"></i>
                                                <span>View</span>
                                            </a>
                                            <form method="post" class="inline">
                                                <input type="hidden" name="paste_id" value="<?php echo $paste['id']; ?>">
                                                <input type="hidden" name="action" value="unpin">
                                                <button type="submit" 
                                                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg hover:shadow-xl"
                                                        onclick="return confirm('Are you sure you want to unpin this paste?')">
                                                    <i class="fas fa-thumbtack"></i>
                                                    <span>Unpin</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Available Pastes Section - Full Width -->
        <div class="bg-backgroundTextarea rounded-lg p-6 shadow-lg border border-gray-700">
            <!-- Search Bar -->
            <div class="mb-6">
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <input type="text" 
                               id="pasteSearch"
                               placeholder="Search all pastes by title..." 
                               class="w-full bg-background text-textColor px-4 py-3 rounded-lg border border-gray-600 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-all"
                               onkeyup="searchPastes()"
                               onchange="searchPastes()">
                    </div>
                    <button onclick="clearSearch()" 
                            class="bg-gray-600 hover:bg-gray-500 text-white px-4 py-3 rounded-lg font-bold transition-all">
                        <i class="fas fa-times mr-2"></i>Clear
                    </button>
                </div>
                <div id="searchLoading" class="hidden text-center py-4">
                    <i class="fas fa-spinner fa-spin text-primary text-xl"></i>
                    <p class="text-textColorHover mt-2">Searching...</p>
                </div>
            </div>
            
            <?php if (empty($unpinnedPastes)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-list text-gray-500 text-5xl mb-4"></i>
                    <p class="text-textColorHover text-lg">No available pastes</p>
                </div>
            <?php else: ?>
                <div id="pastesContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 max-h-96 overflow-y-auto">
                    <?php foreach ($unpinnedPastes as $paste): ?>
                        <div class="paste-item bg-background rounded-lg p-4 border border-gray-600 hover:border-primary/40 transition-all duration-300" 
                             data-title="<?php echo strtolower(htmlspecialchars($paste['title'])); ?>">
                            <div class="mb-3">
                                <h4 class="text-textColor font-medium text-sm mb-2 truncate" title="<?php echo htmlspecialchars($paste['title']); ?>">
                                    <?php echo htmlspecialchars($paste['title']); ?>
                                </h4>
                                <?php if ($paste['user_id']): ?>
                                    <p class="text-textColorHover text-xs flex items-center">
                                        <i class="fas fa-user mr-1"></i>
                                        <?php echo htmlspecialchars($paste['username']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <a href="../view.php?id=<?php echo $paste['id']; ?>" 
                                   class="flex-1 bg-primary hover:bg-primaryHover text-black px-3 py-2 rounded text-xs font-bold transition-all text-center">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                                <?php if (count($pinnedPastes) < 3): ?>
                                    <form method="post" class="flex-1">
                                        <input type="hidden" name="paste_id" value="<?php echo $paste['id']; ?>">
                                        <input type="hidden" name="action" value="pin">
                                        <button type="submit" 
                                                class="w-full bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-xs font-bold transition-all">
                                            <i class="fas fa-thumbtack mr-1"></i>Pin
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button disabled 
                                            class="flex-1 bg-gray-600 text-gray-400 px-3 py-2 rounded text-xs font-bold cursor-not-allowed"
                                            title="Maximum 3 pastes can be pinned">
                                        <i class="fas fa-thumbtack mr-1"></i>Pin
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div id="noResults" class="text-center py-8 hidden">
                    <i class="fas fa-search text-gray-500 text-3xl mb-3"></i>
                    <p class="text-textColorHover">No pastes found matching your search</p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        let searchTimeout;
        let isSearching = false;
        
        function searchPastes() {
            const searchInput = document.getElementById('pasteSearch');
            const searchTerm = searchInput.value.trim();
            
            // Clear previous timeout
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Debounce search
            searchTimeout = setTimeout(() => {
                performSearch(searchTerm);
            }, 300);
        }
        
        function performSearch(searchTerm) {
            if (isSearching) return;
            
            const container = document.getElementById('pastesContainer');
            const loading = document.getElementById('searchLoading');
            const noResults = document.getElementById('noResults');
            
            if (searchTerm === '') {
                // Show original results
                location.reload();
                return;
            }
            
            isSearching = true;
            loading.classList.remove('hidden');
            container.style.opacity = '0.5';
            
            // Fetch search results
            fetch('search_pastes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'search=' + encodeURIComponent(searchTerm)
            })
            .then(response => response.json())
            .then(data => {
                loading.classList.add('hidden');
                container.style.opacity = '1';
                isSearching = false;
                
                if (data.success) {
                    displaySearchResults(data.pastes);
                } else {
                    console.error('Search failed:', data.error);
                }
            })
            .catch(error => {
                loading.classList.add('hidden');
                container.style.opacity = '1';
                isSearching = false;
                console.error('Search error:', error);
            });
        }
        
        function displaySearchResults(pastes) {
            const container = document.getElementById('pastesContainer');
            const noResults = document.getElementById('noResults');
            
            if (pastes.length === 0) {
                container.innerHTML = '';
                noResults.classList.remove('hidden');
                return;
            }
            
            noResults.classList.add('hidden');
            
            let html = '';
            pastes.forEach(paste => {
                const isPinDisabled = <?php echo count($pinnedPastes); ?> >= 3;
                html += `
                    <div class="paste-item bg-background rounded-lg p-4 border border-gray-600 hover:border-primary/40 transition-all duration-300">
                        <div class="mb-3">
                            <h4 class="text-textColor font-medium text-sm mb-2 truncate" title="${paste.title}">
                                ${paste.title}
                            </h4>
                            ${paste.username ? `
                                <p class="text-textColorHover text-xs flex items-center">
                                    <i class="fas fa-user mr-1"></i>
                                    ${paste.username}
                                </p>
                            ` : ''}
                        </div>
                        <div class="flex gap-2">
                            <a href="../view.php?id=${paste.id}" 
                               class="flex-1 bg-primary hover:bg-primaryHover text-black px-3 py-2 rounded text-xs font-bold transition-all text-center">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                            ${!isPinDisabled ? `
                                <form method="post" class="flex-1">
                                    <input type="hidden" name="paste_id" value="${paste.id}">
                                    <input type="hidden" name="action" value="pin">
                                    <button type="submit" 
                                            class="w-full bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded text-xs font-bold transition-all">
                                        <i class="fas fa-thumbtack mr-1"></i>Pin
                                    </button>
                                </form>
                            ` : `
                                <button disabled 
                                        class="flex-1 bg-gray-600 text-gray-400 px-3 py-2 rounded text-xs font-bold cursor-not-allowed"
                                        title="Maximum 3 pastes can be pinned">
                                    <i class="fas fa-thumbtack mr-1"></i>Pin
                                </button>
                            `}
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function clearSearch() {
            document.getElementById('pasteSearch').value = '';
            location.reload();
        }
        </script>
    </div>
</body>
</html>
