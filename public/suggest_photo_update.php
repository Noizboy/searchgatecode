<?php
session_start();

header('Content-Type: application/json');

// Set Florida timezone
date_default_timezone_set('America/New_York');

// Check if user is authenticated
if (!isset($_SESSION['user_authenticated']) || $_SESSION['user_authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['community']) || !isset($input['code']) || !isset($input['photo'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$community = trim($input['community']);
$code = trim($input['code']);
$photo = trim($input['photo']);
$coordinates = $input['coordinates'] ?? null;
$timestamp = date('Y-m-d H:i:s'); // Use server time

// Load existing suggestions
$suggestFile = __DIR__ . '/data/suggest.json';
$suggestions = [];

if (file_exists($suggestFile)) {
    $content = file_get_contents($suggestFile);
    $suggestions = json_decode($content, true);
    if (!is_array($suggestions)) {
        $suggestions = [];
    }
}

// Create new suggestion entry (matching submit.php format)
$newSuggestion = [
    'community' => $community,
    'city' => '', // Empty for photo updates
    'codes' => [
        [
            'code' => $code,
            'notes' => '',
            'photo' => $photo,
            'coordinates' => $coordinates
        ]
    ],
    'type' => 'photo_update',
    'submitted_date' => $timestamp,
    'submitted_by' => $_SESSION['user_name'] ?? 'User'
];

// Check if suggestion already exists for this community+code+type
$exists = false;
foreach ($suggestions as $idx => $suggestion) {
    if (isset($suggestion['community']) &&
        isset($suggestion['type']) &&
        $suggestion['community'] === $community &&
        $suggestion['type'] === 'photo_update' &&
        isset($suggestion['codes'][0]['code']) &&
        $suggestion['codes'][0]['code'] === $code) {
        // Update existing suggestion
        $suggestions[$idx] = $newSuggestion;
        $exists = true;
        break;
    }
}

if (!$exists) {
    // Add new suggestion
    $suggestions[] = $newSuggestion;
}

// Save suggestions
if (file_put_contents($suggestFile, json_encode($suggestions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
    echo json_encode([
        'success' => true,
        'message' => 'Photo suggestion saved successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save suggestion'
    ]);
}
