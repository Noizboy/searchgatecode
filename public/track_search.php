<?php
// Track search usage
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$dataFile = __DIR__ . '/data/searches.json';
$dataDir = dirname($dataFile);

// Create data directory if it doesn't exist
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

// Read existing data
$searches = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $searches = json_decode($content, true) ?: [];
}

// Add new search entry
$searches[] = [
    'timestamp' => date('Y-m-d H:i:s'),
    'query' => trim($_POST['query'] ?? ''),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
];

// Keep only last 10000 searches to prevent file from growing too large
if (count($searches) > 10000) {
    $searches = array_slice($searches, -10000);
}

// Save to file
$result = file_put_contents($dataFile, json_encode($searches, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

if ($result !== false) {
    echo json_encode(['success' => true, 'total' => count($searches)]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save data']);
}
