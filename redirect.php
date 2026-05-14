<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'banner';

if (!in_array($type, ['banner', 'text'])) {
    header('Location: index.php');
    exit;
}

if ($id <= 0) {
    header("Location: /");
    exit;
}

// Fetch the correct record based on type
if ($type === 'banner') {
    $stmt = $pdo->prepare("SELECT url, active FROM banners WHERE id = ? LIMIT 1");
} else {
    $stmt = $pdo->prepare("SELECT url, active FROM banner_texts WHERE id = ? LIMIT 1");
}
$stmt->execute([$id]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record || !$record['active']) {
    header("Location: /");
    exit;
}

// ✅ Whitelist allowed domains
function getAllowedDomainsFromBothTables(PDO $pdo): array {
    $domains = [];

    foreach (['banners', 'banner_texts'] as $table) {
        $stmt = $pdo->query("SELECT url FROM $table WHERE active = 1");
        $urls = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($urls as $url) {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? null;
            if ($host && !in_array($host, $domains)) {
                $domains[] = $host;
            }
        }
    }
    return $domains;
}

$allowed_domains = getAllowedDomainsFromBothTables($pdo);
$parsed = parse_url($record['url']);
$host = $parsed['host'] ?? '';

function isAllowedHost($host, $whitelist) {
    foreach ($whitelist as $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            return true;
        }
    }
    return false;
}

if (!isAllowedHost($host, $allowed_domains)) {
    header("Location: /");
    exit;
}

// ✅ Track click
incrementBannerClicks($id, $type);

// ✅ Redirect
header("Location: " . $record['url']);
exit;
