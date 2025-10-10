<?php
header('Content-Type: application/json');

// Set Florida timezone
date_default_timezone_set('America/New_York');

// Create temp_assets folder if it doesn't exist
$uploadDir = __DIR__ . '/temp_assets/';
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0775, true);
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['success' => false, 'message' => 'No file uploaded']);
  exit;
}

$file = $_FILES['photo'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowedTypes)) {
  echo json_encode(['success' => false, 'message' => 'Invalid file type. Only images allowed.']);
  exit;
}

// Generate unique filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$uniqueName = 'temp_gate_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
$destination = $uploadDir . $uniqueName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
  $response = [
    'success' => true,
    'filename' => $uniqueName,
    'path' => 'temp_assets/' . $uniqueName
  ];

  // Extract GPS coordinates from EXIF data
  $coordinates = extractGPSCoordinates($destination);
  if ($coordinates) {
    $response['coordinates'] = $coordinates;
  }

  echo json_encode($response);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to save file']);
}

// Function to extract GPS coordinates from image
function extractGPSCoordinates($imagePath) {
  if (!function_exists('exif_read_data')) {
    return null;
  }

  $exif = @exif_read_data($imagePath, 'GPS');

  if (!$exif || !isset($exif['GPSLatitude']) || !isset($exif['GPSLongitude'])) {
    return null;
  }

  $lat = getGPS($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
  $lon = getGPS($exif['GPSLongitude'], $exif['GPSLongitudeRef']);

  if ($lat === null || $lon === null) {
    return null;
  }

  return [
    'latitude' => $lat,
    'longitude' => $lon
  ];
}

// Convert GPS coordinates to decimal format
function getGPS($exifCoord, $hemi) {
  if (!is_array($exifCoord) || count($exifCoord) < 3) {
    return null;
  }

  $degrees = count($exifCoord) > 0 ? gps2Num($exifCoord[0]) : 0;
  $minutes = count($exifCoord) > 1 ? gps2Num($exifCoord[1]) : 0;
  $seconds = count($exifCoord) > 2 ? gps2Num($exifCoord[2]) : 0;

  $flip = ($hemi == 'W' || $hemi == 'S') ? -1 : 1;

  return $flip * ($degrees + ($minutes / 60) + ($seconds / 3600));
}

// Convert EXIF GPS format to decimal number
function gps2Num($coordPart) {
  $parts = explode('/', $coordPart);
  if (count($parts) <= 0) return 0;
  if (count($parts) == 1) return $parts[0];
  return floatval($parts[0]) / floatval($parts[1]);
}
