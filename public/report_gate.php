<?php
header('Content-Type: application/json');

// Read the JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['community']) || !isset($data['code'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$community = $data['community'];
$code = $data['code'];

// Load gates.json
$jsonFile = 'data/gates.json';
if (!file_exists($jsonFile)) {
    echo json_encode(['success' => false, 'message' => 'Data file not found']);
    exit;
}

$jsonContent = file_get_contents($jsonFile);
$gates = json_decode($jsonContent, true);

if (!$gates) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Find the community and code
$found = false;
foreach ($gates as &$gate) {
    if ($gate['community'] === $community) {
        foreach ($gate['codes'] as &$codeObj) {
            if ($codeObj['code'] === $code) {
                // Increment report_count
                if (!isset($codeObj['report_count'])) {
                    $codeObj['report_count'] = 0;
                }
                $codeObj['report_count']++;

                // Update last_report_at timestamp
                $codeObj['last_report_at'] = date('c'); // ISO 8601 format

                $found = true;
                break 2;
            }
        }
    }
}

if (!$found) {
    echo json_encode(['success' => false, 'message' => 'Community or code not found']);
    exit;
}

// Save the updated JSON
$newJsonContent = json_encode($gates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($jsonFile, $newJsonContent) === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to save data']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Report submitted successfully',
    'community' => $community,
    'code' => $code
]);
