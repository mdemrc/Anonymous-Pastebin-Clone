<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Проверяем, авторизован ли пользователь
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Проверяем, передан ли ID пасты
$paste_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$paste_id) {
    header('Location: index.php');
    exit;
}

// Получаем информацию о пасте
$stmt = $pdo->prepare("SELECT * FROM pastes WHERE id = ?");
$stmt->execute([$paste_id]);
$paste = $stmt->fetch(PDO::FETCH_ASSOC);

// Если паста не найдена, перенаправляем на главную страницу
if (!$paste) {
    header('Location: index.php');
    exit;
}

// Проверяем права на редактирование пасты
// Пользователь может редактировать пасту, если:
// 1. Он является владельцем пасты
// 2. Он имеет права на модерацию паст (Administrator, Staff, Developer)
if ($_SESSION['user_id'] != $paste['user_id'] && !canModeratePastes()) {
    header('Location: view.php?id=' . $paste_id);
    exit;
}

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $syntax = $_POST['syntax'] ?? 'text';
    $visibility = $_POST['visibility'] ?? 'public';
    $expires = $_POST['expires'] ?? 'never';
    
    // Валидация данных
    if (empty($title)) {
        $title = 'Untitled';
    }
    
    if (empty($content)) {
        $errors[] = 'Content cannot be empty';
    }
    
    // Если нет ошибок, обновляем пасту
    if (empty($errors)) {
        // Определяем дату истечения срока действия
        $expires_at = null;
        if ($expires !== 'never') {
            switch ($expires) {
                case '10min':
                    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    break;
                case '1hour':
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    break;
                case '1day':
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));
                    break;
                case '1week':
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 week'));
                    break;
                case '1month':
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 month'));
                    break;
                case '1year':
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
                    break;
            }
        }
        
        // Обновляем пасту в базе данных
        $stmt = $pdo->prepare("
            UPDATE pastes 
            SET title = ?, content = ?, syntax = ?, visibility = ?, expires_at = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $content, $syntax, $visibility, $expires_at, $paste_id]);
        
        // Записываем в лог информацию о редактировании
        $user_id = $_SESSION['user_id'];
        $username = $_SESSION['username'];
        $log_message = "Paste ID: $paste_id edited by User ID: $user_id ($username)";
        
        if (canModeratePastes() && $_SESSION['user_id'] != $paste['user_id']) {
            $log_message .= " [MODERATION ACTION]";
        }
        
        // Создаем директорию для логов, если она не существует
        $log_dir = __DIR__ . '/logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Запись в лог
        $log_file = fopen($log_dir . '/paste_edits.log', 'a');
        fwrite($log_file, date('Y-m-d H:i:s') . " - " . $log_message . "\n");
        fclose($log_file);
        
        // Перенаправляем на страницу просмотра пасты
        $_SESSION['flash_message'] = "Paste successfully updated.";
        header('Location: view.php?id=' . $paste_id);
        exit;
    }
}

// Получаем список доступных языков для подсветки синтаксиса
$syntaxOptions = [
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
    'markdown' => 'Markdown'
];

// Получаем список опций для срока действия пасты
$expiresOptions = [
    'never' => 'Never',
    '10min' => '10 Minutes',
    '1hour' => '1 Hour',
    '1day' => '1 Day',
    '1week' => '1 Week',
    '1month' => '1 Month',
    '1year' => '1 Year'
];

// Определяем текущий срок действия пасты
$currentExpires = 'never';
if ($paste['expires_at']) {
    $now = new DateTime();
    $expiresAt = new DateTime($paste['expires_at']);
    $diff = $now->diff($expiresAt);
    
    if ($diff->y > 0) {
        $currentExpires = '1year';
    } elseif ($diff->m > 0) {
        $currentExpires = '1month';
    } elseif ($diff->d > 0) {
        if ($diff->d >= 7) {
            $currentExpires = '1week';
        } else {
            $currentExpires = '1day';
        }
    } elseif ($diff->h > 0) {
        $currentExpires = '1hour';
    } else {
        $currentExpires = '10min';
    }
}

// Получаем случайные баннеры и рекламные тексты
$banners = getRandomBanners(2);
$bannerTexts = getRandomBannerTexts(2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>example.com - Edit Paste</title>
    
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
                        background2: '#191935',
                        backgroundTextarea: '#1d1e3a',
                        textColor: '#ffffff',
                        textColorHover: '#d0d0d0'
                    }
                }
            }
        }
    </script>
    
    <!-- CodeMirror -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/material-palenight.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/ruby/ruby.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/go/go.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/rust/rust.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/shell/shell.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/markdown/markdown.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/yaml/yaml.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .CodeMirror {
            height: 500px;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .edit-form {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            background-color: var(--background-textarea);
            border: 1px solid #2a2a4a;
            color: var(--text-color);
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            background-color: var(--background-textarea);
            border: 1px solid #2a2a4a;
            color: var(--text-color);
            transition: border-color 0.3s;
        }
        
        .form-select:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: #000;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        
        .btn-secondary {
            background-color: #2a2a4a;
            color: var(--text-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-secondary:hover {
            background-color: #3a3a5a;
        }
        
        .error-message {
            color: #ff5555;
            margin-bottom: 1rem;
        }
        
        .moderation-notice {
            background-color: #FF8C00;
            color: #000;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-background min-h-screen flex flex-col pt-12">
    <?php include 'includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-textColor text-3xl font-bold mb-6">Edit Paste</h1>
        
        <?php if (canModeratePastes() && $_SESSION['user_id'] != $paste['user_id']): ?>
        <div class="moderation-notice">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            You are editing this paste as a moderator. This action will be logged.
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="error-message">
            <ul>
                <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <form method="post" class="edit-form">
            <div class="form-group">
                <label for="title" class="form-label">Title</label>
                <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($paste['title']); ?>">
            </div>
            
            <div class="form-group">
                <label for="content" class="form-label">Content</label>
                <textarea id="content" name="content" class="form-control"><?php echo htmlspecialchars($paste['content']); ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="form-group">
                    <label for="syntax" class="form-label">Syntax Highlighting</label>
                    <select id="syntax" name="syntax" class="form-select">
                        <?php foreach ($syntaxOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $paste['syntax'] === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="visibility" class="form-label">Visibility</label>
                    <select id="visibility" name="visibility" class="form-select">
                        <option value="public" <?php echo $paste['visibility'] === 'public' ? 'selected' : ''; ?>>Public</option>
                        <option value="unlisted" <?php echo $paste['visibility'] === 'unlisted' ? 'selected' : ''; ?>>Unlisted</option>
                        <option value="private" <?php echo $paste['visibility'] === 'private' ? 'selected' : ''; ?>>Private</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="expires" class="form-label">Expires</label>
                    <select id="expires" name="expires" class="form-select">
                        <?php foreach ($expiresOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $currentExpires === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex gap-4 mt-6">
                <button type="submit" class="btn-primary">Save Changes</button>
                <a href="view.php?id=<?php echo $paste_id; ?>" class="btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var editor = CodeMirror.fromTextArea(document.getElementById('content'), {
                mode: '<?php echo $paste['syntax']; ?>',
                theme: 'material-palenight',
                lineNumbers: true,
                indentUnit: 4,
                lineWrapping: true,
                autofocus: true
            });
            
            // Обновляем режим подсветки синтаксиса при изменении выбора
            document.getElementById('syntax').addEventListener('change', function() {
                var syntax = this.value;
                editor.setOption('mode', syntax);
            });
        });
        
        // Функция для определения режима CodeMirror на основе выбранного синтаксиса
        function detectLanguage(syntax) {
            switch(syntax) {
                case 'php': return 'application/x-httpd-php';
                case 'javascript': return 'text/javascript';
                case 'html': return 'text/html';
                case 'css': return 'text/css';
                case 'python': return 'text/x-python';
                case 'java': return 'text/x-java';
                case 'csharp': return 'text/x-csharp';
                case 'cpp': return 'text/x-c++src';
                case 'ruby': return 'text/x-ruby';
                case 'go': return 'text/x-go';
                case 'rust': return 'text/x-rustsrc';
                case 'sql': return 'text/x-sql';
                case 'bash': return 'text/x-sh';
                case 'json': return 'application/json';
                case 'xml': return 'application/xml';
                case 'yaml': return 'text/x-yaml';
                case 'markdown': return 'text/x-markdown';
                default: return 'text/plain';
            }
        }
    </script>
</body>
</html>
