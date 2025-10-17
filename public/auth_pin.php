<?php
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['pin']) || empty(trim($input['pin']))) {
  echo json_encode(['success' => false, 'message' => 'PIN is required']);
  exit;
}

$pin = trim($input['pin']);

// Load pins from data/pin.json
$pinsFile = __DIR__ . '/data/pin.json';

if (!file_exists($pinsFile)) {
  echo json_encode(['success' => false, 'message' => 'Authentication system not available']);
  exit;
}

$pinsData = json_decode(file_get_contents($pinsFile), true);

if (!is_array($pinsData)) {
  echo json_encode(['success' => false, 'message' => 'Authentication system error']);
  exit;
}

// Find user with matching PIN
$foundUser = null;
foreach ($pinsData as $user) {
  if (isset($user['pin']) && $user['pin'] === $pin) {
    $foundUser = $user;
    break;
  }
}

if ($foundUser) {
  // Authentication successful
  $_SESSION['user_authenticated'] = true;
  $_SESSION['user_name'] = $foundUser['name'] ?? 'User';
  $_SESSION['user_pin'] = $foundUser['pin'];
  $_SESSION['user_id'] = $foundUser['id'] ?? null;

  echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
      'name' => $_SESSION['user_name'],
      'pin' => $_SESSION['user_pin']
    ]
  ]);
} else {
  // Authentication failed
  echo json_encode([
    'success' => false,
    'message' => 'Invalid PIN. Please check your PIN and try again.'
  ]);
}
