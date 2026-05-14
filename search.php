<?php
require_once 'includes/init.php';

// Get search parameters
$query = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$syntax = isset($_GET['syntax']) ? strtolower(trim(sanitizeInput($_GET['syntax']))) : '';
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'recent';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Available syntax options
$syntaxOptions = [
    '' => 'All Languages',
    'text' => 'Plain Text',
    'php' => 'PHP',
    'javascript' => 'JavaScript',
    'html' => 'HTML',
    'css' => 'CSS',
    'python' => 'Python',
    'java' => 'Java',
    'csharp' => 'C#',
    'cpp' => 'C++',
    'ruby' => 'Ruby',
    'go' => 'Go',
    'rust' => 'Rust',
    'sql' => 'SQL',
    'bash' => 'Bash',
    'json' => 'JSON',
    'xml' => 'XML',
    'yaml' => 'YAML',
    'markdown' => 'Markdown',
    'shell' => 'Shell',
    // Removed duplicate/ambiguous 'clike' to avoid mismatches
];

// Validate syntax filter against allowed keys
if (!array_key_exists($syntax, $syntaxOptions)) {
    $syntax = '';
}

// Sort options
$sortOptions = [
    'recent' => 'Most Recent',
    'oldest' => 'Oldest',
    'views' => 'Most Viewed',
    'likes' => 'Most Liked'
];

// Build search query
$searchConditions = ["p.visibility = 'public'"];
$searchParams = [];

if (!empty($query)) {
    $searchConditions[] = "(p.title LIKE ? OR p.content LIKE ?)";
    $searchParams[] = "%$query%";
    $searchParams[] = "%$query%";
}

if (!empty($syntax)) {
    $searchConditions[] = "p.syntax = ?";
    $searchParams[] = $syntax;
}

// Build ORDER BY clause
$orderBy = "p.created_at DESC";
switch ($sort) {
    case 'oldest':
        $orderBy = "p.created_at ASC";
        break;
    case 'views':
        $orderBy = "p.views DESC";
        break;
    case 'likes':
        $orderBy = "(SELECT COUNT(*) FROM paste_likes pl WHERE pl.paste_id = p.id) DESC";
        break;
}

// Get total count
$countQuery = "
    SELECT COUNT(*) as total
    FROM pastes p
    WHERE " . implode(' AND ', $searchConditions);

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($searchParams);
$totalResults = $countStmt->fetch()['total'];

// Get search results
$searchQuery = "
    SELECT p.*, u.username, u.emoji, u.name_color,
           (SELECT COUNT(*) FROM paste_likes pl WHERE pl.paste_id = p.id) as likes_count
    FROM pastes p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE " . implode(' AND ', $searchConditions) . "
    ORDER BY $orderBy
    LIMIT $limit OFFSET $offset";

$searchStmt = $pdo->prepare($searchQuery);
$searchStmt->execute($searchParams);
$results = $searchStmt->fetchAll();

// Calculate pagination
$totalPages = ceil($totalResults / $limit);

// Get random banners and banner texts
$banners = getRandomBanners(2);
$bannerTexts = getRandomBannerTexts(2);

$pageTitle = 'Search Pastes';
?>
<?php 
// AJAX isteğini erken tespit et (yalnızca sonuç kısmını döndürmek için)
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';
?>
<?php if (!$isAjax): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Search Pastes - example.com</title>
    
    <meta name="keywords" content="example.com, pastehub, pastebin, pastebin alternative, free, proxies, configs, anonfiles, leaks, leaked, bayfiles, ghostbin, cracked, accounts, files, paste">
    <meta name="author" content="example.com">
    <meta name="copyright" content="example.com">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/ico" href="assets/img/favicon.ico">
    
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <link href="css/responsive.css" rel="stylesheet" type="text/css">
    <link href="css/mobile-responsive.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css">
    <link href="css/dark.css?v=103" rel="stylesheet" type="text/css">
    <link href="css/fonts.css" rel="stylesheet" type="text/css">
    <link href="css/common.css" rel="stylesheet" type="text/css">
    
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
                        backgroundSecondary: '#1d1e3a',
                        backgroundTextarea: '#1d1e3a',
                        textColor: '#ffffff'
                    }
                }
            }
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
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
    </style>
</head>
<body class="bg-background min-h-screen flex flex-col pt-12">
    <?php include 'includes/header.php'; ?>
        
    <!-- Random Banners -->
    <div class="banner-container flex justify-center gap-1 my-4 flex-wrap mt-16">
        <?php foreach ($banners as $banner): ?>
            <div class="banner">
                <a href="<?php echo htmlspecialchars($banner['url']); ?>" target="_blank">
                    <?php if (isset($banner['is_external']) && $banner['is_external']): ?>
                        <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" 
                             alt="Banner" 
                             class="w-[440px] h-[111px]">
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($banner['image_path']); ?>" 
                             alt="Banner" 
                             class="w-[440px] h-[111px]">
                    <?php endif; ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Random Text Banners -->
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
                        <a href="<?php echo htmlspecialchars($bannerText['url']); ?>" target="_blank">
                            <span class="font-bold text-lg <?php echo $class; ?>">
                                <?php echo htmlspecialchars($bannerText['text']); ?>
                            </span>
                        </a>
                    <?php else: ?>
                        <span class="font-bold text-lg <?php echo $class; ?>">
                            <?php echo htmlspecialchars($bannerText['text']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] flex flex-col items-center">
            <h1 class="text-textColor font-extrabold mb-2 pagetitle" style="font-size: 24px; font-weight: 1000; font-weight: bold;">Search Pastes</h1>
            <div class="border-[#00ff9d] min-w-[400px] mb-4" style="border-bottom: 1px solid #00ff9d; width: 400px;"></div>

        <!-- Search Form -->
        <div class="bg-backgroundSecondary rounded-lg p-6 mb-8 shadow-lg">
            <form id="searchForm" method="GET" class="space-y-4" onsubmit="return false;">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search Query -->
                    <div class="md:col-span-1">
                        <label for="q" class="block text-sm font-medium text-textColor mb-2">
                            <i class="fas fa-search mr-2"></i>Search Term
                        </label>
                        <input type="text" 
                               id="q" 
                               name="q" 
                               value="<?php echo htmlspecialchars($query); ?>"
                               placeholder="Search in titles and content..." 
                               class="w-full px-4 py-3 bg-background border border-background2 rounded-lg text-textColor placeholder-textColorHover focus:outline-none focus:border-primary transition duration-300">
                    </div>

                    <!-- Syntax Filter -->
                    <div>
                        <label for="syntax" class="block text-sm font-medium text-textColor mb-2">
                            <i class="fas fa-code mr-2"></i>Programming Language
                        </label>
                        <select id="syntax" 
                                name="syntax" 
                                class="w-full px-4 py-3 bg-background border border-background2 rounded-lg text-textColor focus:outline-none focus:border-primary transition duration-300">
                            <?php foreach ($syntaxOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $syntax === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div>
                        <label for="sort" class="block text-sm font-medium text-textColor mb-2">
                            <i class="fas fa-sort mr-2"></i>Sort By
                        </label>
                        <select id="sort" 
                                name="sort" 
                                class="w-full px-4 py-3 bg-background border border-background2 rounded-lg text-textColor focus:outline-none focus:border-primary transition duration-300">
                            <?php foreach ($sortOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $sort === $value ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Clear link (button removed; live search enabled) -->
                <div class="flex justify-center pt-4">
                    <?php if (!empty($query) || !empty($syntax) || !empty($sort)): ?>
                        <a href="search.php" 
                           class="ml-4 bg-background2 hover:bg-background text-textColor px-6 py-3 rounded-lg transition duration-300">
                            <i class="fas fa-times mr-2"></i>
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

    <?php endif; // !$isAjax - tam sayfa üst kısım ?>

    <?php if (!$isAjax): ?>
    <div id="results-container">
    <?php endif; ?>
    <?php ob_start(); ?>
            <!-- Search Results Info -->
            <?php if (!empty($query) || !empty($syntax) || $page > 1): ?>
                <div class="mb-6 p-4 bg-background rounded-lg border border-background2">
                    <div class="flex items-center justify-between">
                        <div class="text-textColor">
                            <i class="fas fa-info-circle mr-2 text-primary"></i>
                            <strong><?php echo number_format($totalResults); ?></strong> results found
                            <?php if (!empty($query)): ?>
                                for "<strong><?php echo htmlspecialchars($query); ?></strong>"
                            <?php endif; ?>
                            <?php if (!empty($syntax)): ?>
                                in <strong><?php echo htmlspecialchars($syntaxOptions[$syntax]); ?></strong>
                            <?php endif; ?>
                        </div>
                        <div class="text-textColorHover text-sm">
                            Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search Results -->
            <?php if (!empty($results)): ?>
                <div class="space-y-4">
                    <?php foreach ($results as $paste): ?>
                        <div class="bg-backgroundSecondary rounded-lg p-6 shadow-lg hover:shadow-xl transition duration-300 transform hover:scale-[1.02]">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <!-- Paste Title -->
                                    <h3 class="text-xl font-bold mb-2">
                                        <a href="view.php?id=<?php echo $paste['id']; ?>" 
                                           class="text-primary hover:text-primaryHover transition duration-300">
                                            <?php echo htmlspecialchars($paste['title'] ?: 'Untitled Paste'); ?>
                                        </a>
                                    </h3>

                                    <!-- Paste Info -->
                                    <div class="flex items-center space-x-4 text-sm text-textColorHover mb-3">
                                        <!-- Author -->
                                        <div class="flex items-center">
                                            <i class="fas fa-user mr-1"></i>
                                            <?php if ($paste['username']): ?>
                                                <span style="color: <?php echo $paste['name_color'] ?: '#ffffff'; ?>">
                                                    <?php echo $paste['emoji'] ? $paste['emoji'] . ' ' : ''; ?>
                                                    <?php echo htmlspecialchars($paste['username']); ?>
                                                </span>
                                            <?php else: ?>
                                                Anonymous
                                            <?php endif; ?>
                                        </div>

                                        <!-- Syntax -->
                                        <div class="flex items-center">
                                            <i class="fas fa-code mr-1"></i>
                                            <span class="bg-primary text-black px-2 py-1 rounded text-xs font-semibold">
                                                <?php echo htmlspecialchars($syntaxOptions[$paste['syntax']] ?? $paste['syntax']); ?>
                                            </span>
                                        </div>

                                        <!-- Views -->
                                        <div class="flex items-center">
                                            <i class="fas fa-eye mr-1"></i>
                                            <?php echo number_format($paste['views']); ?> views
                                        </div>

                                        <!-- Likes -->
                                        <div class="flex items-center">
                                            <i class="fas fa-heart mr-1"></i>
                                            <?php echo number_format($paste['likes_count']); ?> likes
                                        </div>

                                        <!-- Date -->
                                        <div class="flex items-center">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php echo timeAgo($paste['created_at']); ?>
                                        </div>
                                    </div>

                                    <!-- Content Preview -->
                                    <div class="bg-background rounded-lg p-4 border border-background2">
                                        <pre class="text-textColor text-sm overflow-hidden whitespace-pre-wrap break-words max-w-full"><?php 
                                            $preview = substr($paste['content'], 0, 200);
                                            if (strlen($paste['content']) > 200) {
                                                $preview .= '...';
                                            }
                                            echo htmlspecialchars($preview);
                                        ?></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php 
                if ($totalPages > 1) {
                    $paginationHtml = renderPagination($page, $totalPages, 'search.php', [
                        'q' => $query,
                        'syntax' => $syntax,
                        'sort' => $sort
                    ]);
                    $paginationHtml = str_replace('pagination-container"', 'pagination-container" style="margin-top:16px;margin-bottom:32px;"', $paginationHtml);
                    echo $paginationHtml;
                }
                ?>
            <?php elseif (!empty($query) || !empty($syntax)): ?>
                <!-- No Results -->
                <div class="text-center py-12">
                    <div class="text-6xl text-textColorHover mb-4">
                        <i class="fas fa-search-minus"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-textColor mb-4">No Results Found</h3>
                    <p class="text-textColorHover mb-6">
                        No pastes match your search criteria. Try different keywords or filters.
                    </p>
                    <a href="search.php" 
                       class="bg-primary hover:bg-primaryHover text-black font-bold px-6 py-3 rounded-lg transition duration-300">
                        <i class="fas fa-search mr-2"></i>
                        Start New Search
                    </a>
                </div>
 
            <?php else: ?>
                <!-- Initial State -->
                <div class="text-center py-12">
                    <div class="text-6xl text-primary mb-4">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-textColor mb-4">Search Pastes</h3>
                    <p class="text-textColorHover mb-6">
                        Use the search form above to find pastes by title, content, or programming language.
                    </p>
                    <div class="flex justify-center space-x-4">
                        <a href="recent.php" 
                           class="bg-background2 hover:bg-background text-textColor px-6 py-3 rounded-lg transition duration-300">
                            <i class="fas fa-clock mr-2"></i>
                            Recent Pastes
                        </a>
                        <a href="top.php" 
                           class="bg-background2 hover:bg-background text-textColor px-6 py-3 rounded-lg transition duration-300">
                            <i class="fas fa-fire mr-2"></i>
                            Top Pastes
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php 
            $partial = ob_get_clean();
            if ($isAjax) {
                // AJAX çağrısında SADECE sonuç içeriğini döndür
                echo $partial; exit;
            }
            echo $partial;
        ?>
    <?php if (!$isAjax): ?>
        </div>
        
        <!-- Close page containers before footer -->
        </div>
    </div>

    <!-- Green separator line -->
    <div class="container mx-auto flex justify-center">
        <div class="w-full max-w-[1200px] my-4" style="border-bottom: 1px solid #00ff9d;"></div>
    </div>

    <div class="container mx-auto flex justify-center">
    <div class="text-center">
    <span class="font-bold text-white text-xl">© 2025 example.com</span><br>
    <span class="text-sm opacity-70">Version 1.0.0</span><br>
    <a class="inline-block px-1.5" href="https://t.me/eznflex" target="_blank">
        <i class="fa-brands fa-telegram" style="color: #00ff9d; font-size: 24px;"></i>
    </a>
    <a class="inline-block px-1.5" href="https://discord.gg/qewJmrqyUK" target="_blank">
        <i class="fa-brands fa-discord" style="color: #00ff9d; font-size: 24px;"></i>
    </a>
    </div>
    </div>

    <script src="js/main.js"></script>
    <script>
    (function(){
        const form = document.getElementById('searchForm');
        const input = document.getElementById('q');
        const syntaxSel = document.getElementById('syntax');
        const sortSel = document.getElementById('sort');
        const results = document.getElementById('results-container');

        if (!form || !input || !results) return;
        form.addEventListener('submit', e => e.preventDefault());

        const debounce = (fn, ms=300) => {
            let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
        };

        function buildParams(resetPage=false){
            const params = new URLSearchParams(window.location.search);
            const q = input.value.trim();
            const syntax = syntaxSel ? syntaxSel.value : '';
            const sort = sortSel ? sortSel.value : 'recent';
            if (q) params.set('q', q); else params.delete('q');
            if (syntax) params.set('syntax', syntax); else params.delete('syntax');
            if (sort) params.set('sort', sort); else params.delete('sort');
            if (resetPage) params.set('page', '1');
            return params;
        }

        async function doSearch(resetPage=false){
            const params = buildParams(resetPage);
            params.set('ajax','1');
            const url = 'search.php?' + params.toString();
            try {
                const resp = await fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest'}});
                const html = await resp.text();
                results.innerHTML = html;
                // Update URL without ajax param
                params.delete('ajax');
                history.replaceState(null, '', 'search.php?' + params.toString());
            } catch(e) {
                console.error('Search update failed', e);
            }
        }

        const onInput = debounce(() => doSearch(true), 300);
        input.addEventListener('input', onInput);
        if (syntaxSel) syntaxSel.addEventListener('change', () => doSearch(true));
        if (sortSel) sortSel.addEventListener('change', () => doSearch(true));

        // Intercept pagination clicks for AJAX
        document.addEventListener('click', (e) => {
            const a = e.target.closest('.pagination-wrapper a');
            if (!a) return;
            e.preventDefault();
            const url = new URL(a.href, window.location.origin);
            const params = new URLSearchParams(url.search);
            params.set('ajax','1');
            fetch('search.php?' + params.toString(), {headers:{'X-Requested-With':'XMLHttpRequest'}})
                .then(r=>r.text())
                .then(html => {
                    results.innerHTML = html;
                    params.delete('ajax');
                    history.replaceState(null, '', 'search.php?' + params.toString());
                })
                .catch(err => console.error(err));
        });
    })();
    </script>
</body>
</html>
<?php endif; // !$isAjax - tam sayfa alt kısım ?>