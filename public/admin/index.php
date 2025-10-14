<?php
/******************** CONFIG ********************/
require_once __DIR__ . '/includes/config.php';
require_key();

/******************** GPS EXTRACTION HELPERS ********************/
function getGPS($exifCoord, $hemi) {
  $degrees = count($exifCoord) > 0 ? getGPSPart($exifCoord[0]) : 0;
  $minutes = count($exifCoord) > 1 ? getGPSPart($exifCoord[1]) : 0;
  $seconds = count($exifCoord) > 2 ? getGPSPart($exifCoord[2]) : 0;

  $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;
  return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
}

function getGPSPart($coordPart) {
  if (strpos($coordPart, '/') === false) {
    return floatval($coordPart);
  }
  $parts = explode('/', $coordPart);
  if (count($parts) <= 0) return 0;
  if (count($parts) == 1) return floatval($parts[0]);
  return floatval($parts[0]) / floatval($parts[1]);
}

function extractGPSCoordinates($imagePath) {
  if (!function_exists('exif_read_data')) {
    return null;
  }

  $exif = @exif_read_data($imagePath, 'GPS');
  if (!$exif || !isset($exif['GPSLatitude']) || !isset($exif['GPSLongitude'])) {
    return null;
  }

  $latitudeRef = $exif['GPSLatitudeRef'] ?? 'N';
  $longitudeRef = $exif['GPSLongitudeRef'] ?? 'E';

  $latitude = getGPS($exif['GPSLatitude'], $latitudeRef);
  $longitude = getGPS($exif['GPSLongitude'], $longitudeRef);

  return [
    'latitude' => round($latitude, 6),
    'longitude' => round($longitude, 6)
  ];
}

/******************** COUNT SUGGESTIONS ********************/
$suggest_count = 0;
if(file_exists(SUGGEST_JSON)){
  $suggestions = read_json(SUGGEST_JSON);
  $suggest_count = count($suggestions);
}

/******************** API ENDPOINTS ********************/
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$msg = '';

// AJAX: UPLOAD PHOTO
if (($_GET['ajax'] ?? '') === 'upload' && $_SERVER['REQUEST_METHOD']==='POST') {
  header('Content-Type: application/json');
  $MAX_BYTES = 16 * 1024 * 1024;
  $out = function($status, $extra = []) {
    http_response_code($status === 'ok' ? 200 : (http_response_code() ?: 400));
    echo json_encode(array_merge(['status'=>$status], $extra));
    exit;
  };
  if (!isset($_FILES['photo'])) $out('fail', ['error'=>'no_file']);
  $f = $_FILES['photo'];
  if ($f['error'] !== UPLOAD_ERR_OK) {
    $map = [
      UPLOAD_ERR_INI_SIZE   => 'php_err_ini_size',
      UPLOAD_ERR_FORM_SIZE  => 'php_err_form_size',
      UPLOAD_ERR_PARTIAL    => 'php_err_partial',
      UPLOAD_ERR_NO_FILE    => 'php_err_no_file',
      UPLOAD_ERR_NO_TMP_DIR => 'php_err_no_tmp_dir',
      UPLOAD_ERR_CANT_WRITE => 'php_err_cant_write',
      UPLOAD_ERR_EXTENSION  => 'php_err_extension'
    ];
    $code = $map[$f['error']] ?? ('php_err_'.$f['error']);
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) { http_response_code(413); }
    $out('fail', ['error'=>$code]);
  }
  if ($f['size'] > $MAX_BYTES) { http_response_code(413); $out('fail', ['error'=>'too_large']); }
  if (!is_dir(ASSETS_DIR) && !@mkdir(ASSETS_DIR, 0775, true)) $out('fail', ['error'=>'assets_dir_create_failed']);
  if (!is_writable(ASSETS_DIR)) $out('fail', ['error'=>'assets_not_writable']);
  $tmp  = $f['tmp_name'];
  if (!is_uploaded_file($tmp)) $out('fail', ['error'=>'invalid_tmp']);
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($tmp) ?: 'application/octet-stream';
  $allowed_native = ['image/jpeg','image/png','image/webp'];
  $needs_convert  = ['image/heic','image/heif'];

  // Use community name for filename if provided
  $community_name = $_POST['community_name'] ?? '';
  if ($community_name) {
    // Sanitize community name for filename
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($community_name));
    $base = 'gate_' . $safe_name . '_' . date('Ymd_His');
  } else {
    $base = 'gate_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
  }
  $dest = '';
  if (in_array($mime, $needs_convert, true)) {
    if (class_exists('Imagick')) {
      try {
        $img = new Imagick($tmp);
        if (method_exists($img, 'setImageOrientation')) { $img->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT); }
        $img->setImageFormat('jpeg');
        if (method_exists($img, 'setImageCompressionQuality')) { $img->setImageCompressionQuality(85); }
        $dest = ASSETS_DIR.$base.'.jpg';
        if (!$img->writeImage($dest)) $out('fail',['error'=>'heic_convert_failed']);
      } catch (Throwable $e) {
        $out('fail', ['error'=>'heic_unavailable']);
      }
    } else {
      $out('fail', ['error'=>'heic_not_supported']);
    }
    echo json_encode(['status'=>'ok','url'=>ASSETS_RELATIVE.basename($dest)]);
    exit;
  }
  if (!in_array($mime, $allowed_native, true)) {
    $out('fail', ['error'=>"type_{$mime}_not_allowed"]);
  }
  $ext = match ($mime) {
    'image/jpeg' => '.jpg',
    'image/png'  => '.png',
    'image/webp' => '.webp',
    default      => ''
  };
  if ($ext === '') $out('fail', ['error'=>'ext_resolve_failed']);
  $dest = ASSETS_DIR.$base.$ext;
  if (!@move_uploaded_file($tmp, $dest)) $out('fail', ['error'=>'move_failed']);

  // Extract GPS coordinates from image
  $coordinates = extractGPSCoordinates($dest);
  $response = ['status'=>'ok','url'=>ASSETS_RELATIVE.basename($dest)];
  if ($coordinates) {
    $response['coordinates'] = $coordinates;
  }

  echo json_encode($response);
  exit;
}

// DOWNLOAD JSON
if ($action === 'download_json') {
  if (!file_exists(GATES_JSON)) {
    http_response_code(404);
    exit('gates.json not found');
  }
  header('Content-Type: application/json');
  header('Content-Disposition: attachment; filename="gates_backup_' . date('Y-m-d_His') . '.json"');
  header('Content-Length: ' . filesize(GATES_JSON));
  readfile(GATES_JSON);
  exit;
}

// UPLOAD JSON
if ($action === 'upload_json') {
  if (!isset($_FILES['json_file'])) {
    $msg = 'No file selected.';
  } else {
    $file = $_FILES['json_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $msg = 'Upload error: ' . $file['error'];
    } else if ($file['size'] > 5 * 1024 * 1024) {
      $msg = 'File too large (max 5MB).';
    } else {
      $content = file_get_contents($file['tmp_name']);
      $json = json_decode($content, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $msg = 'Invalid JSON file: ' . json_last_error_msg();
      } else if (!is_array($json)) {
        $msg = 'JSON must be an array.';
      } else {
        $backup_dir = dirname(GATES_JSON) . '/backups';
        if (!is_dir($backup_dir)) @mkdir($backup_dir, 0775, true);
        $backup_file = $backup_dir . '/gates_backup_' . date('Y-m-d_His') . '.json';
        if (file_exists(GATES_JSON)) {
          @copy(GATES_JSON, $backup_file);
        }
        if (write_json(GATES_JSON, $json)) {
          header('Location: ?key=' . urlencode(ADMIN_KEY) . '&section=backup&flash=' . urlencode('JSON file uploaded successfully. Backup created.'));
          exit;
        } else {
          $msg = 'Failed to write JSON file.';
        }
      }
    }
  }
}

// ADD COMMUNITY
if ($action === 'add') {
  $data = read_json(GATES_JSON);
  $community = trim($_POST['community'] ?? '');
  $city = trim($_POST['city'] ?? '');
  if ($community===''){
    $msg='Missing community name.';
  } else {
    $codes = [];
    $rows = $_POST['codes'] ?? [];

    // Find existing photo from community if it exists
    $existing_photo = null;
    $idx = find_comm_index($data, $community);
    if ($idx >= 0 && isset($data[$idx]['codes'])) {
      foreach ($data[$idx]['codes'] as $existing_code) {
        $photo = trim($existing_code['photo'] ?? '');
        if ($photo !== '' &&
            $photo !== DEFAULT_THUMB_URL &&
            $photo !== 'assets/thumbnailnone.png' &&
            stripos($photo, 'thumbnailnone.png') === false) {
          $existing_photo = $photo;
          break;
        }
      }
    }

    foreach ($rows as $r){
      $code = trim($r['code'] ?? '');
      if($code==='') continue;
      $entry = ['code'=>$code];
      foreach(['notes','details','photo'] as $k){
        $v=trim($r[$k]??'');
        if($k==='photo'){
          if($v==='' || $v===DEFAULT_THUMB_URL){
            // Use existing community photo if available, otherwise default
            $v = $existing_photo ?? DEFAULT_THUMB_URL;
          }
        }
        if($v!=='') $entry[$k]=$v;
      }
      $codes[] = $entry;
    }

    if(empty($codes)){
      $msg='Add at least one code.';
    } else {
      if($idx>=0){
        // Community exists, check for duplicate codes
        $existing_codes = array_column($data[$idx]['codes'], 'code');
        $added_count = 0;
        foreach($codes as $new_code){
          if(!in_array($new_code['code'], $existing_codes)){
            $data[$idx]['codes'][] = $new_code;
            $added_count++;
          }
        }
        if($added_count > 0){
          // Update city if provided
          if ($city !== '') {
            $data[$idx]['city'] = $city;
          }
          write_json(GATES_JSON,$data);
          header('Location: ?key='.urlencode(ADMIN_KEY).'&section=home&flash='.urlencode("Added {$added_count} new code(s) to existing community."));
          exit;
        } else {
          $msg = 'All codes already exist in this community.';
        }
      } else {
        // New community
        $newCommunity = ['community'=>$community, 'codes'=>$codes];
        if ($city !== '') {
          $newCommunity['city'] = $city;
        }
        $data[] = $newCommunity;
        write_json(GATES_JSON,$data);
        header('Location: ?key='.urlencode(ADMIN_KEY).'&section=home&flash='.urlencode('Community added successfully.'));
        exit;
      }
    }
  }
}

// UPDATE COMMUNITY
if ($action === 'update') {
  $data = read_json(GATES_JSON);
  $original = trim($_POST['original'] ?? '');
  $idx = find_comm_index($data,$original);
  if ($idx<0){
    $msg='Community not found.';
  } else {
    $new_name = trim($_POST['community'] ?? $original);
    $city = trim($_POST['city'] ?? '');
    $rows = $_POST['codes'] ?? [];
    $existing_photo = null;
    if (isset($data[$idx]['codes'])) {
      foreach ($data[$idx]['codes'] as $existing_code) {
        $photo = trim($existing_code['photo'] ?? '');
        if ($photo !== '' &&
            $photo !== DEFAULT_THUMB_URL &&
            $photo !== 'assets/thumbnailnone.png' &&
            stripos($photo, 'thumbnailnone.png') === false) {
          $existing_photo = $photo;
          break;
        }
      }
    }
    $codes=[];
    foreach($rows as $r){
      $code=trim($r['code']??''); if($code==='') continue;
      $entry=['code'=>$code];
      foreach(['notes','details','photo'] as $k){
        $v=trim($r[$k]??'');
        if($k==='photo' && $v===''){
          $v = $existing_photo ?? DEFAULT_THUMB_URL;
        }
        if($v!=='') $entry[$k]=$v;
      }
      $codes[]=$entry;
    }
    if(empty($codes)){
      $msg='Add at least one code.';
    } else {
      $updatedCommunity = ['community'=>$new_name,'codes'=>$codes];
      if ($city !== '') {
        $updatedCommunity['city'] = $city;
      }

      // Handle coordinates
      $latitude = trim($_POST['latitude'] ?? '');
      $longitude = trim($_POST['longitude'] ?? '');
      if ($latitude !== '' && $longitude !== '') {
        $updatedCommunity['coordinates'] = [
          'latitude' => floatval($latitude),
          'longitude' => floatval($longitude)
        ];
      } elseif (isset($data[$idx]['coordinates'])) {
        // Preserve existing coordinates if not updated
        $updatedCommunity['coordinates'] = $data[$idx]['coordinates'];
      }

      $data[$idx] = $updatedCommunity;
      write_json(GATES_JSON,$data);
      header('Location: ?key='.urlencode(ADMIN_KEY).'&section=home&flash='.urlencode('Community updated successfully.'));
      exit;
    }
  }
}

// DELETE CODE
if ($action === 'delete_code') {
  $data = read_json(GATES_JSON);
  $comm = trim($_POST['community']??'');
  $code = trim($_POST['code']??'');
  $idx = find_comm_index($data,$comm);
  $failed = [];
  $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

  if($idx>=0){
    $list = $data[$idx]['codes'] ?? [];
    $new=[];
    foreach($list as $c){
      $c_code = trim($c['code']??'');
      if($c_code === $code){
        $photo = trim($c['photo'] ?? '');
        if($photo && !delete_photo_by_url($photo)){
          $failed[] = basename(parse_url($photo, PHP_URL_PATH) ?: $photo);
        }
        continue;
      }
      $new[]=$c;
    }
    $data[$idx]['codes']=$new;
    if(empty($new)) {
      array_splice($data,$idx,1);
      write_json(GATES_JSON,$data);
      if ($is_ajax) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Code deleted. Community emptied and removed.']);
        exit;
      }
      header('Location: ?key='.urlencode(ADMIN_KEY).'&section=home&flash='.urlencode('Code deleted. Community emptied and removed.'));
      exit;
    }
    write_json(GATES_JSON,$data);
    $flash='Code deleted.';
    if ($failed) { $flash .= ' (Could not delete: '.implode(', ', $failed).')'; }
    if ($is_ajax) {
      http_response_code(200);
      echo json_encode(['success' => true, 'message' => $flash]);
      exit;
    }
    header('Location: ?key='.urlencode(ADMIN_KEY).'&section=home&flash='.urlencode($flash));
    exit;
  } else {
    if ($is_ajax) {
      http_response_code(404);
      echo json_encode(['success' => false, 'message' => 'Community not found.']);
      exit;
    }
    $msg='Community not found.';
  }
}

// CLEAR REPORTS
if ($action === 'clear_reports') {
  $data = read_json(GATES_JSON);
  $community_idx = intval($_POST['community_idx'] ?? -1);
  $code_idx = intval($_POST['code_idx'] ?? -1);

  if ($community_idx >= 0 && $code_idx >= 0 && isset($data[$community_idx]['codes'][$code_idx])) {
    // Set report_count to 0
    $data[$community_idx]['codes'][$code_idx]['report_count'] = 0;

    // Save the updated data
    $success = write_json(GATES_JSON, $data);

    if ($success) {
      http_response_code(200);
      echo json_encode(['success' => true, 'message' => 'Reports cleared successfully']);
    } else {
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'Failed to save data']);
    }
  } else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
  }
  exit;
}

// DELETE COMMUNITY
if ($action === 'delete_comm') {
  $data = read_json(GATES_JSON);
  $comm = trim($_POST['community']??'');
  $idx = find_comm_index($data,$comm);
  $failed = [];
  if($idx>=0){
    $codes = $data[$idx]['codes'] ?? [];
    foreach($codes as $c){
      $photo = trim($c['photo'] ?? '');
      if($photo && !delete_photo_by_url($photo)){
        $failed[] = basename(parse_url($photo, PHP_URL_PATH) ?: $photo);
      }
    }
    array_splice($data,$idx,1);
    write_json(GATES_JSON,$data);
    $flash='Community deleted.';
    if ($failed) { $flash .= ' (Could not delete: '.implode(', ', $failed).')'; }
    header('Location: ?key='.urlencode(ADMIN_KEY).'&section=home&flash='.urlencode($flash));
    exit;
  } else {
    $msg='Community not found.';
  }
}

// APPROVE CONTRIBUTION
if ($action === 'approve_contribution') {
  $suggestions = read_json(SUGGEST_JSON);
  $index = intval($_POST['index'] ?? -1);

  if ($index >= 0 && $index < count($suggestions)) {
    $suggestion = $suggestions[$index];
    $gates_data = read_json(GATES_JSON);

    // Move photo from temp to assets
    $photo = $suggestion['photo'] ?? '';
    $new_photo = '';
    if ($photo && strpos($photo, 'temp_assets/') !== false) {
      $filename = basename($photo);
      $temp_path = TEMP_ASSETS_DIR . $filename;
      $new_path = ASSETS_DIR . $filename;
      if (file_exists($temp_path)) {
        if (!is_dir(ASSETS_DIR)) @mkdir(ASSETS_DIR, 0775, true);
        if (@copy($temp_path, $new_path)) {
          @unlink($temp_path);
          $new_photo = ASSETS_RELATIVE . $filename;
        }
      }
    } else {
      $new_photo = $photo;
    }

    // Add to gates.json
    $community = trim($suggestion['community'] ?? '');
    $idx = find_comm_index($gates_data, $community);
    $new_code = [
      'code' => $suggestion['code'] ?? '',
      'notes' => $suggestion['notes'] ?? '',
      'details' => $suggestion['details'] ?? '',
      'photo' => $new_photo ?: DEFAULT_THUMB_URL
    ];

    if ($idx >= 0) {
      $gates_data[$idx]['codes'][] = $new_code;
    } else {
      $gates_data[] = [
        'community' => $community,
        'codes' => [$new_code]
      ];
    }

    write_json(GATES_JSON, $gates_data);

    // Remove from suggestions
    array_splice($suggestions, $index, 1);
    write_json(SUGGEST_JSON, $suggestions);

    header('Location: ?key='.urlencode(ADMIN_KEY).'&section=contributions&flash='.urlencode('Contribution approved successfully.'));
    exit;
  } else {
    $msg = 'Invalid contribution index.';
  }
}

// DELETE CONTRIBUTION
if ($action === 'delete_contribution') {
  $suggestions = read_json(SUGGEST_JSON);
  $index = intval($_POST['index'] ?? -1);

  if ($index >= 0 && $index < count($suggestions)) {
    $suggestion = $suggestions[$index];

    // Delete photo from temp_assets
    $photo = $suggestion['photo'] ?? '';
    if ($photo && strpos($photo, 'temp_assets/') !== false) {
      $filename = basename($photo);
      $temp_path = TEMP_ASSETS_DIR . $filename;
      if (file_exists($temp_path)) {
        @unlink($temp_path);
      }
    }

    // Remove from suggestions
    array_splice($suggestions, $index, 1);
    write_json(SUGGEST_JSON, $suggestions);

    header('Location: ?key='.urlencode(ADMIN_KEY).'&section=contributions&flash='.urlencode('Contribution deleted.'));
    exit;
  } else {
    $msg = 'Invalid contribution index.';
  }
}

// SETTINGS: ADD PIN
if ($action === 'add_pin') {
  $pins = read_json(PIN_JSON);
  $name = trim($_POST['pin_name'] ?? '');
  $pin = trim($_POST['pin_value'] ?? '');

  if ($name === '' || $pin === '') {
    $msg = 'Name and PIN are required.';
  } else {
    $pins[] = [
      'name' => $name,
      'pin' => $pin,
      'date' => date('Y-m-d H:i:s')
    ];
    write_json(PIN_JSON, $pins);
    header('Location: ?key='.urlencode(ADMIN_KEY).'&section=settings&flash='.urlencode('PIN added successfully.'));
    exit;
  }
}

// SETTINGS: UPDATE PIN
if ($action === 'update_pin') {
  $pins = read_json(PIN_JSON);
  $index = intval($_POST['index'] ?? -1);
  $name = trim($_POST['pin_name'] ?? '');
  $pin = trim($_POST['pin_value'] ?? '');

  if ($index >= 0 && $index < count($pins)) {
    if ($name === '' || $pin === '') {
      $msg = 'Name and PIN are required.';
    } else {
      $pins[$index]['name'] = $name;
      $pins[$index]['pin'] = $pin;
      write_json(PIN_JSON, $pins);
      header('Location: ?key='.urlencode(ADMIN_KEY).'&section=settings&flash='.urlencode('PIN updated successfully.'));
      exit;
    }
  } else {
    $msg = 'Invalid PIN index.';
  }
}

// SETTINGS: DELETE PIN
if ($action === 'delete_pin') {
  $pins = read_json(PIN_JSON);
  $index = intval($_POST['index'] ?? -1);

  if ($index >= 0 && $index < count($pins)) {
    array_splice($pins, $index, 1);
    write_json(PIN_JSON, $pins);
    header('Location: ?key='.urlencode(ADMIN_KEY).'&section=settings&flash='.urlencode('PIN deleted successfully.'));
    exit;
  } else {
    $msg = 'Invalid PIN index.';
  }
}

/******************** DATA + FILTER ********************/
// Set current page for sidebar active state
$current_page = 'home';

$data = read_json(GATES_JSON);
$suggestions = read_json(SUGGEST_JSON);
$pins = read_json(PIN_JSON);
$q = trim($_GET['q'] ?? '');
$section = trim($_GET['section'] ?? 'home');
$edit = trim($_GET['edit'] ?? '');
$msg = $_GET['flash'] ?? $msg;

// Calculate statistics
$total_communities = count($data);
$total_contributions = count($suggestions);
$total_users = count($pins);
$total_reported = 0;
foreach ($data as $community) {
  if (isset($community['codes'])) {
    foreach ($community['codes'] as $code) {
      if (isset($code['report_count']) && $code['report_count'] > 0) {
        $total_reported++;
      }
    }
  }
}

// Calculate search statistics
$searches_file = __DIR__ . '/../data/searches.json';
$total_searches = 0;
if (file_exists($searches_file)) {
  $searches_data = json_decode(file_get_contents($searches_file), true) ?: [];
  $total_searches = count($searches_data);
}

function match_row($row,$q){
  if($q==='') return true;
  $q = norm($q);
  if(strpos(norm($row['community']??''), $q)!==false) return true;
  foreach(($row['codes']??[]) as $c){
    if(strpos(norm($c['code']??''),$q)!==false) return true;
    if(strpos(norm($c['notes']??''),$q)!==false) return true;
  }
  return false;
}
$filtered = array_values(array_filter($data, fn($r)=>match_row($r,$q)));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard · Gate Code</title>
<style>
  :root{
    --bg:#0b0d10; --panel:#151a20; --panel-2:#0f1318;
    --text:#e8eef4; --muted:#93a0ad; --brand:#3bdd82; --brand-2:#1bbf67;
    --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
    --gradient-1:#1a2330; --gradient-2:#11202a;
    --border:#2a3340; --border-2:#1e2a34; --line:#22303b;
    --input-bg-1:#0f141a; --input-bg-2:#0c1116;
    --scrollbar-track:#0f141a; --scrollbar-thumb:#2a3340; --scrollbar-thumb-hover:#364456;
    --modal-bg-1:#1a1f26; --modal-bg-2:#12161c; --modal-border:#233041;
    --btn-secondary-bg:#22272f; --btn-secondary-text:#d0d7de; --btn-secondary-border:#2e3947;
    --btn-secondary-hover:#2a3240;
    --footer-bg:rgba(15,19,24,0.5);
    --sidebar-width:260px;
  }

  [data-theme="light"]{
    --bg:#f5f7fa; --panel:#ffffff; --panel-2:#f8f9fa;
    --text:#1a1f26; --muted:#5a6c7d; --brand:#3bdd82; --brand-2:#1bbf67;
    --danger:#ff5c5c; --danger-2:#e53935; --radius:14px;
    --gradient-1:#e0f5ee; --gradient-2:#d4ede2;
    --border:#d1dce5; --border-2:#e1e8ed; --line:#d1dce5;
    --input-bg-1:#ffffff; --input-bg-2:#f9fafb;
    --scrollbar-track:#e8eef4; --scrollbar-thumb:#c1ccd7; --scrollbar-thumb-hover:#a8b5c2;
    --modal-bg-1:#ffffff; --modal-bg-2:#f8f9fa; --modal-border:#d1dce5;
    --btn-secondary-bg:#f0f3f6; --btn-secondary-text:#2c3845; --btn-secondary-border:#d1dce5;
    --btn-secondary-hover:#e4e9ed;
    --footer-bg:rgba(255,255,255,0.5);
  }

  * { box-sizing: border-box; }

  html, body {
    height: 100%;
    margin: 0;
    font-family: system-ui, Segoe UI, Roboto, Arial;
    color: var(--text);
    background: var(--bg);
    transition: background 0.3s ease, color 0.3s ease;
  }

  body::before {
    content: "";
    position: fixed;
    inset: 0;
    z-index: -1;
    background:
      radial-gradient(1000px 500px at 80% -10%, var(--gradient-1) 0%, transparent 60%),
      radial-gradient(900px 400px at -10% 90%, var(--gradient-2) 0%, transparent 55%),
      var(--bg);
    background-repeat: no-repeat;
    transition: background 0.3s ease;
  }

  /* SIDEBAR */
  .sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: var(--sidebar-width);
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border-right: 1px solid var(--line);
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transition: transform 0.3s ease;
  }

  .sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid var(--line);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-shrink: 0;
  }

  .sidebar-logo {
    font-size: 1.4rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-decoration: none;
  }

  .theme-toggle-sidebar {
    background: var(--input-bg-1);
    border: 1px solid var(--border);
    border-radius: 8px;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
  }

  .theme-toggle-sidebar:hover {
    transform: scale(1.1);
    background: var(--panel-2);
  }

  .theme-toggle-sidebar svg {
    width: 18px;
    height: 18px;
    fill: var(--brand);
    transition: transform 0.3s ease;
  }

  .theme-toggle-sidebar:hover svg {
    transform: rotate(20deg);
  }

  .sidebar-nav {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
  }

  .nav-item {
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--muted);
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    border-left: 3px solid transparent;
    position: relative;
  }

  .nav-item:hover {
    background: var(--panel-2);
    color: var(--text);
  }

  .nav-item.active {
    background: var(--panel-2);
    color: var(--brand);
    border-left-color: var(--brand);
    font-weight: 600;
  }

  .nav-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
  }

  .nav-badge {
    margin-left: auto;
    background: var(--danger);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
  }

  /* MAIN CONTENT */
  .main-content {
    margin-left: var(--sidebar-width);
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .content-header {
    padding: 24px 32px;
    border-bottom: 1px solid var(--line);
    background: var(--panel);
    position: sticky;
    top: 0;
    z-index: 100;
  }

  .content-header h1 {
    margin: 0 0 8px 0;
    font-size: 1.8rem;
    color: var(--text);
  }

  .content-header .subtitle {
    color: var(--muted);
    font-size: 0.9rem;
  }

  /* SECTIONS */
  .section {
    display: none;
  }

  .section.active {
    display: block;
  }

  /* CARDS */
  .card {
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 24px;
    margin-bottom: 24px;
  }

  .card-title {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0 0 16px 0;
    color: var(--text);
  }

  /* FORMS */
  .field {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    outline: none;
    transition: border-color .15s ease, box-shadow .15s ease;
    font-size: 15px;
  }

  .field::placeholder {
    color: var(--muted);
  }

  .field:focus {
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(59, 221, 130, .15);
  }

  textarea.field {
    resize: vertical;
    min-height: 90px;
  }

  .form-group {
    margin-bottom: 20px;
  }

  .form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--text);
    font-weight: 600;
    font-size: 0.95rem;
  }

  /* BUTTONS */
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    gap: 8px;
  }

  .btn:hover {
    background: var(--panel-2);
    transform: translateY(-1px);
  }

  .btn-primary {
    background: linear-gradient(135deg, #2FD874, #12B767);
    border: 0;
    color: #fff;
    box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
  }

  .btn-primary:hover {
    background: linear-gradient(135deg, #12B767, #0e9a52);
    box-shadow: 0 6px 18px rgba(59, 221, 130, .55);
  }

  .btn-danger {
    background: linear-gradient(135deg, #FF5A5F, #E23D3D);
    border: 0;
    color: #fff;
    box-shadow: 0 4px 14px rgba(255, 92, 92, .4);
  }

  .btn-danger:hover {
    background: linear-gradient(135deg, #E23D3D, #c73030);
    box-shadow: 0 6px 18px rgba(255, 92, 92, .55);
  }


  .btn-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }

  /* COMMUNITIES LIST */
  .communities-grid {
    display: grid;
    gap: 16px;
  }

  .community-item {
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 16px;
  }

  .community-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }

  .community-name {
    font-weight: 700;
    font-size: 1.2rem;
    color: var(--text);
  }

  .codes-list {
    display: grid;
    gap: 10px;
  }

  .code-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    background: var(--input-bg-1);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 12px;
  }

  .code-thumb {
    width: 80px;
    height: 64px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid var(--line);
    background: #000;
    cursor: zoom-in;
    flex-shrink: 0;
  }

  .code-info {
    flex: 1;
  }

  .code-value {
    font-family: ui-monospace, Menlo, Consolas, monospace;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
  }

  .code-note {
    color: var(--muted);
    font-size: 0.85rem;
  }

  .code-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
  }

  /* FLASH MESSAGE */
  .flash {
    background: var(--panel-2);
    border: 1px solid var(--brand);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .flash-icon {
    width: 20px;
    height: 20px;
    fill: var(--brand);
  }

  /* SUGGESTIONS */
  .suggestion-item {
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
  }

  .suggestion-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
  }

  .suggestion-photo {
    width: 100%;
    max-width: 300px;
    height: auto;
    border-radius: 8px;
    margin-bottom: 12px;
    border: 1px solid var(--line);
  }

  /* PINS LIST */
  .pins-grid {
    display: grid;
    gap: 12px;
  }

  .pin-item {
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .pin-info h3 {
    margin: 0 0 4px 0;
    color: var(--text);
    font-size: 1.1rem;
  }

  .pin-info p {
    margin: 0;
    color: var(--muted);
    font-size: 0.85rem;
  }

  .pin-value {
    font-family: ui-monospace, Menlo, Consolas, monospace;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--brand);
    margin: 4px 0;
  }

  /* MODAL */
  .modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
  }

  .modal.open {
    display: flex;
  }

  .modal img {
    max-width: min(90vw, 1000px);
    max-height: 80vh;
    object-fit: contain;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: #000;
    box-shadow: 0 10px 40px rgba(0, 0, 0, .6);
  }

  .modal-close {
    position: absolute;
    top: 14px;
    right: 18px;
    font-size: 28px;
    line-height: 1;
    color: var(--text);
    cursor: pointer;
    user-select: none;
    background: transparent;
    border: none;
    padding: 6px 10px;
  }

  /* CODE EDITOR */
  .codes-editor {
    display: grid;
    gap: 16px;
  }

  .code-edit-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 16px;
  }

  .code-edit-row .full-width {
    grid-column: 1 / -1;
  }

  .preview-box {
    margin-top: 10px;
    display: none;
  }

  .preview-box.show {
    display: block;
  }

  .preview-thumb {
    width: 100%;
    max-width: 200px;
    aspect-ratio: 16 / 9;
    object-fit: cover;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #000;
  }

  /* SCROLLBAR */
  ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  ::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }

  ::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }

  /* RESPONSIVE */
  .mobile-menu-toggle {
    display: none;
    background: transparent;
    border: none;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    color: var(--text);
  }

  /* Removed - consolidated below */

  .empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
  }

  .empty-state-icon {
    font-size: 3rem;
    margin-bottom: 16px;
    opacity: 0.5;
  }

  /* PAGE HEADER */
  .page-header {
    flex-shrink: 0;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 20px 24px;
  }

  .page-header-left {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 16px;
  }

  .page-header-content {
    flex: 1;
  }

  .page-header-right {
    flex-shrink: 0;
  }

  .page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text);
    margin: 0 0 8px 0;
  }

  .page-subtitle {
    font-size: 1rem;
    color: var(--muted);
    margin: 0;
  }

  /* Removed - consolidated below */

  /* HOME SECTION LAYOUT */
  .home-container {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
    min-height: 0;
    gap: 20px;
  }

  /* SEARCH SECTION - STATIC */
  .search-section {
    flex-shrink: 0;
  }

  .search-bar-container {
    display: flex;
    gap: 12px;
  }

  .search-bar-container .field {
    flex: 1;
  }

  /* COMMUNITIES CARD - SCROLLABLE BOX */
  .home-card {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
    min-height: 0;
    margin-bottom: 0;
  }

  .communities-scroll-wrapper {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 8px;
    min-height: 0;
  }

  .communities-scroll-wrapper::-webkit-scrollbar {
    width: 8px;
  }

  .communities-scroll-wrapper::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }

  .communities-scroll-wrapper::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }

  .communities-scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }

  .content-body {
    flex: 1;
    padding: 32px 32px 100px 32px;
    overflow-y: auto;
    overflow-x: hidden;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }

  /* EDIT MODAL */
  .edit-modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, .7);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
    overflow-y: auto;
  }

  .edit-modal.open {
    display: flex;
  }

  .edit-modal-content {
    width: min(90vw, 800px);
    max-height: 90vh;
    background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
    border: 1px solid var(--modal-border);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .edit-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--line);
    flex-shrink: 0;
  }

  .edit-modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--text);
  }

  .edit-modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
  }

  .edit-modal-footer {
    padding: 20px 24px;
    border-top: 1px solid var(--line);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    flex-shrink: 0;
  }

  .modal-codes-scroll-wrapper {
    max-height: 400px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 8px;
    margin-bottom: 12px;
    scroll-snap-type: y mandatory;
  }

  .edit-code-item {
    background: var(--panel-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
    scroll-snap-align: start;
    scroll-snap-stop: always;
  }

  .edit-code-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
  }

  .edit-code-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .edit-code-full {
    grid-column: 1 / -1;
  }

  /* RESPONSIVE DESIGN */
  @media (max-width: 768px) {
    .sidebar {
      transform: translateX(-100%);
    }

    .sidebar.open {
      transform: translateX(0);
    }

    .main-content {
      margin-left: 0;
    }

    .mobile-menu-toggle {
      display: flex;
    }

    .content-header {
      padding: 20px 20px 20px 70px;
    }

    .content-body {
      padding: 20px 20px 100px 20px;
    }

    .page-header {
      flex-direction: column;
      align-items: stretch;
      padding: 16px;
      gap: 16px;
    }

    .page-header-left {
      gap: 12px;
    }

    .page-header-content {
      flex: 1;
      min-width: 0;
    }

    .page-title {
      font-size: 1.5rem;
      margin: 0 0 4px 0;
    }

    .page-subtitle {
      font-size: 0.875rem;
    }

    .page-header-right {
      width: 100%;
    }

    .page-header-right .btn {
      width: 100%;
    }

    .home-container {
      gap: 16px;
    }

    .search-bar-container {
      flex-direction: column;
      gap: 8px;
    }

    .search-bar-container .btn {
      width: 100%;
    }

    .code-edit-row {
      grid-template-columns: 1fr;
    }

    .edit-code-grid {
      grid-template-columns: 1fr;
    }

    .form-row {
      grid-template-columns: 1fr !important;
    }

    .edit-modal-footer {
      flex-direction: column;
      gap: 10px;
    }

    .edit-modal-footer > * {
      width: 100%;
      margin: 0 !important;
    }

    .edit-modal-footer > div {
      margin-left: 0 !important;
      flex-direction: column-reverse;
      width: 100%;
    }

    .edit-modal-footer .btn {
      width: 100%;
    }


    #home-section {
      height: calc(100vh - 200px);
    }
  }

  /* FILE UPLOAD LABEL */
  .file-upload-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--input-bg-1), var(--input-bg-2));
    color: var(--text);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .file-upload-label:hover {
    background: var(--panel-2);
  }

  input[type="file"]:not(.field) {
    display: none;
  }

  .edit-file-input {
    display: block !important;
    width: 100%;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--input-bg-1);
    color: var(--text);
    font-size: 14px;
    cursor: pointer;
  }

  .edit-file-input::file-selector-button {
    padding: 6px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: var(--panel);
    color: var(--text);
    cursor: pointer;
    margin-right: 10px;
    transition: background 0.2s ease;
  }

  .edit-file-input::file-selector-button:hover {
    background: var(--panel-2);
  }

  .upload-status {
    margin-top: 8px;
    font-size: 0.85rem;
  }

  /* STATISTICS GRID */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
  }

  .stat-card {
    background: linear-gradient(180deg, var(--panel), var(--panel-2));
    border: 1px solid var(--line);
    border-radius: var(--radius);
    padding: 32px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
  }

  .stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--brand), var(--brand-2));
    opacity: 0;
    transition: opacity 0.2s ease;
  }

  .stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(59, 221, 130, .15);
  }

  .stat-card:hover::before {
    opacity: 1;
  }

  .stat-card.danger::before {
    background: linear-gradient(90deg, var(--danger), var(--danger-2));
  }

  .stat-card.danger:hover {
    box-shadow: 0 8px 24px rgba(255, 92, 92, .15);
  }

  .stat-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    background: linear-gradient(135deg, rgba(59, 221, 130, 0.15), rgba(27, 191, 103, 0.1));
  }

  .stat-icon svg {
    width: 40px;
    height: 40px;
    fill: var(--brand);
  }

  .stat-icon.danger {
    background: linear-gradient(135deg, rgba(255, 92, 92, 0.15), rgba(229, 57, 53, 0.1));
  }

  .stat-icon.danger svg {
    fill: var(--danger);
  }

  .stat-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--brand);
    margin: 12px 0;
    line-height: 1;
  }

  .stat-number.danger {
    color: var(--danger);
  }

  .stat-label {
    font-size: 1.1rem;
    color: var(--muted);
    font-weight: 600;
    margin-bottom: 0;
  }

  .stat-button {
    margin-top: 12px;
    padding: 8px 20px;
    background: linear-gradient(135deg, var(--brand), var(--brand-2));
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: inline-block;
  }

  .stat-button:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(59, 221, 130, .3);
  }

  .stat-button.danger {
    background: linear-gradient(135deg, var(--danger), var(--danger-2));
  }

  .stat-button.danger:hover {
    box-shadow: 0 4px 12px rgba(255, 92, 92, .3);
  }

  @media (max-width: 768px) {
    .stats-grid {
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
    }
  }

  @media (max-width: 480px) {
    .stats-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>
<body>

<?php
// Calculate suggestion count for badge
$suggest_count = count($suggestions);
require_once __DIR__ . '/includes/sidebar.php';
?>

<!-- MAIN CONTENT -->
<main class="main-content">
  <div class="content-body">
    <?php if($msg): ?>
      <div class="flash" style="display: none;" id="flashMessage" data-message="<?= htmlspecialchars($msg) ?>"></div>
    <?php endif; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-header-left">
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <line x1="3" y1="18" x2="21" y2="18"/>
          </svg>
        </button>
        <div class="page-header-content">
          <h1 class="page-title">Welcome to Admin Dashboard</h1>
          <p class="page-subtitle">Manage your communities, contributions, and system settings</p>
        </div>
      </div>
    </div>

    <!-- STATISTICS GRID -->
    <div class="stats-grid">
      <!-- Total Communities -->
      <div class="stat-card">
        <div class="stat-icon">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
          </svg>
        </div>
        <div class="stat-number"><?= $total_communities ?></div>
        <div class="stat-label">Total Communities</div>
        <a href="communities.php?key=<?= urlencode(ADMIN_KEY) ?>" class="stat-button">View Communities</a>
      </div>

      <!-- Pending Contributions -->
      <div class="stat-card">
        <div class="stat-icon">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="stat-number"><?= $total_contributions ?></div>
        <div class="stat-label">Pending Contributions</div>
        <a href="contributions.php?key=<?= urlencode(ADMIN_KEY) ?>" class="stat-button">View Contributions </a>
      </div>

      <!-- Registered Users -->
      <div class="stat-card">
        <div class="stat-icon">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
          </svg>
        </div>
        <div class="stat-number"><?= $total_users ?></div>
        <div class="stat-label">Registered Users</div>
        <a href="users.php?key=<?= urlencode(ADMIN_KEY) ?>" class="stat-button">View Users</a>
      </div>

      <!-- Reported Codes -->
      <div class="stat-card danger">
        <div class="stat-icon danger">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="stat-number danger"><?= $total_reported ?></div>
        <div class="stat-label">Reported Codes</div>
        <a href="communities.php?key=<?= urlencode(ADMIN_KEY) ?>&filter=reported" class="stat-button danger" style="display: inline-block; text-decoration: none; text-align: center;">View Codes</a>
      </div>

      <!-- Total Searches -->
      <div class="stat-card">
        <div class="stat-icon">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
          </svg>
        </div>
        <div class="stat-number"><?= number_format($total_searches) ?></div>
        <div class="stat-label">Total Searches</div>
      </div>
    </div>

    <!-- CONTRIBUTIONS SECTION -->
    <section id="contributions-section" class="section" style="display: none;">
      <div class="card">
        <h2 class="card-title">User Contributions</h2>

        <?php if(empty($suggestions)): ?>
          <div class="empty-state">
            <div class="empty-state-icon">✨</div>
            <p>No contributions yet.</p>
          </div>
        <?php else: foreach($suggestions as $idx => $suggestion): ?>
          <div class="suggestion-item">
            <div class="suggestion-header">
              <div>
                <h3 style="margin: 0 0 8px 0; color: var(--text);"><?= htmlspecialchars($suggestion['community'] ?? '') ?></h3>
                <p style="margin: 0; color: var(--muted); font-size: 0.9rem;">Code: <strong><?= htmlspecialchars($suggestion['code'] ?? '') ?></strong></p>
                <?php if(!empty($suggestion['notes'])): ?>
                  <p style="margin: 4px 0 0 0; color: var(--muted); font-size: 0.85rem;"><?= htmlspecialchars($suggestion['notes']) ?></p>
                <?php endif; ?>
              </div>
              <div class="btn-group">
                <form method="post" style="display: inline;">
                  <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
                  <input type="hidden" name="action" value="approve_contribution">
                  <input type="hidden" name="index" value="<?= $idx ?>">
                  <button type="submit" class="btn btn-primary">Approve</button>
                </form>
                <form method="post" style="display: inline;">
                  <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
                  <input type="hidden" name="action" value="delete_contribution">
                  <input type="hidden" name="index" value="<?= $idx ?>">
                  <button type="submit" class="btn btn-danger">Delete</button>
                </form>
              </div>
            </div>

            <?php if(!empty($suggestion['photo'])):
              $photo_url = $suggestion['photo'];
              if(strpos($photo_url, 'temp_assets/') !== false) {
                $photo_url = TEMP_ASSETS_URL . basename($photo_url);
              } else {
                $photo_url = web_photo($photo_url);
              }
            ?>
              <img class="suggestion-photo js-open-modal" src="<?= htmlspecialchars($photo_url) ?>" alt="Photo" data-full="<?= htmlspecialchars($photo_url) ?>">
            <?php endif; ?>

            <?php if(!empty($suggestion['details'])): ?>
              <p style="margin: 12px 0 0 0; color: var(--text);"><?= htmlspecialchars($suggestion['details']) ?></p>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </section>

    <!-- BACKUP SECTION -->
    <section id="backup-section" class="section" style="display: none;">
      <div class="card">
        <h2 class="card-title">Download Backup</h2>
        <p style="color: var(--muted); margin-bottom: 16px;">Download a backup copy of your gates.json file.</p>
        <a href="?key=<?= urlencode(ADMIN_KEY) ?>&action=download_json" class="btn btn-primary" download>📥 Download gates.json</a>
      </div>

      <div class="card">
        <h2 class="card-title">Upload JSON</h2>
        <p style="color: var(--muted); margin-bottom: 16px;">Upload a gates.json file to replace the current data. A backup will be created automatically.</p>

        <form method="post" enctype="multipart/form-data" id="uploadJsonForm">
          <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
          <input type="hidden" name="action" value="upload_json">
          <input type="file" name="json_file" id="jsonFileInput" accept=".json,application/json" required>
          <label for="jsonFileInput" class="file-upload-label">📤 Choose JSON File</label>
        </form>
      </div>
    </section>

    <!-- SETTINGS SECTION -->
    <section id="settings-section" class="section" style="display: none;">
      <div class="card">
        <h2 class="card-title">PIN Management</h2>

        <div class="pins-grid">
          <?php if(empty($pins)): ?>
            <div class="empty-state">
              <div class="empty-state-icon">🔐</div>
              <p>No PINs configured.</p>
            </div>
          <?php else: foreach($pins as $idx => $pin): ?>
            <div class="pin-item">
              <div class="pin-info">
                <h3><?= htmlspecialchars($pin['name'] ?? '') ?></h3>
                <div class="pin-value"><?= htmlspecialchars($pin['pin'] ?? '') ?></div>
                <p>Created: <?= htmlspecialchars($pin['date'] ?? '') ?></p>
              </div>
              <div class="btn-group">
                <button class="btn btn-edit-pin" data-index="<?= $idx ?>" data-name="<?= htmlspecialchars($pin['name'] ?? '') ?>" data-pin="<?= htmlspecialchars($pin['pin'] ?? '') ?>">Edit</button>
                <button type="button" class="btn btn-danger btn-delete-pin" data-index="<?= $idx ?>">Delete</button>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <button class="btn btn-primary" id="addPinBtn" style="margin-top: 20px;">+ Add New PIN</button>
      </div>

      <!-- ADD/EDIT PIN FORM (Hidden by default) -->
      <div class="card" id="pinFormCard" style="display: none;">
        <h2 class="card-title" id="pinFormTitle">Add New PIN</h2>

        <form method="post" id="pinForm">
          <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
          <input type="hidden" name="action" value="add_pin" id="pinFormAction">
          <input type="hidden" name="index" value="" id="pinFormIndex">

          <div class="form-group">
            <label class="form-label">Name</label>
            <input type="text" class="field" name="pin_name" id="pinName" placeholder="e.g., John Doe" required>
          </div>

          <div class="form-group">
            <label class="form-label">PIN</label>
            <input type="text" class="field" name="pin_value" id="pinValue" placeholder="e.g., 1234" required>
          </div>

          <div class="btn-group">
            <button type="submit" class="btn btn-primary">Save PIN</button>
            <button type="button" class="btn" id="cancelPinBtn">Cancel</button>
          </div>
        </form>
      </div>
    </section>

  </div>
</main>

<!-- IMAGE MODAL -->
<div id="imgModal" class="modal" aria-hidden="true">
  <button class="modal-close" type="button" aria-label="Close">&times;</button>
  <img id="imgModalPic" src="" alt="photo">
</div>

<!-- EDIT COMMUNITY MODAL -->
<div id="editModal" class="edit-modal">
  <div class="edit-modal-content">
    <div class="edit-modal-header">
      <h2 id="editModalTitle">Edit Community</h2>
      <div style="display: flex; gap: 8px; align-items: center;">
        <button class="btn btn-danger" id="deleteEditCommunity" type="button" title="Delete Community">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"/>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            <line x1="10" y1="11" x2="10" y2="17"/>
            <line x1="14" y1="11" x2="14" y2="17"/>
          </svg>
        </button>
        <button class="btn" id="closeEditModal" type="button">✕</button>
      </div>
    </div>
    <div class="edit-modal-body" id="editModalBody">
      <form id="editCommunityForm" method="post">
        <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="original" id="editOriginalName" value="">

        <!-- STATIC HEADER -->
        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Community Name</label>
            <input type="text" class="field" name="community" id="editCommunityName" required>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">City Name</label>
            <input type="text" class="field" name="city" id="editCityName" placeholder="e.g., Orlando">
          </div>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label">GPS Coordinates (optional)</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <input type="text" class="field" name="latitude" id="editLatitude" placeholder="Latitude (e.g., 28.5383)">
            <input type="text" class="field" name="longitude" id="editLongitude" placeholder="Longitude (e.g., -81.3792)">
          </div>
          <input type="hidden" name="coordinates" id="editCoordinates" value="">
        </div>

        <!-- SCROLLABLE CODES AREA -->
        <div class="form-group">
          <label class="form-label">Codes</label>
        </div>
        <div class="modal-codes-scroll-wrapper">
          <div id="editCodesContainer"></div>
        </div>
      </form>
    </div>
    <div class="edit-modal-footer">
      <button type="button" class="btn" id="addEditCodeBtn">+ Add Code</button>
      <button class="btn btn-primary" id="saveEditModal" style="margin-left: auto;">Save Changes</button>
    </div>
  </div>
</div>

<!-- ADD NEW COMMUNITY MODAL -->
<div id="addNewModal" class="edit-modal">
  <div class="edit-modal-content">
    <div class="edit-modal-header">
      <h2>Add New Community</h2>
      <button class="btn" id="closeAddNewModal" type="button">✕</button>
    </div>
    <div class="edit-modal-body">
      <form id="addNewForm" method="post">
        <input type="hidden" name="key" value="<?= htmlspecialchars(ADMIN_KEY) ?>">
        <input type="hidden" name="action" value="add">

        <!-- STATIC HEADER -->
        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Community Name</label>
            <input type="text" class="field" name="community" id="addNewCommunityName" placeholder="e.g., Water Oaks" required>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">City Name</label>
            <input type="text" class="field" name="city" id="addNewCityName" placeholder="e.g., Orlando">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Codes</label>
        </div>

        <!-- SCROLLABLE CODES AREA -->
        <div class="modal-codes-scroll-wrapper">
          <div id="addNewCodesContainer"></div>
        </div>
      </form>
    </div>
    <div class="edit-modal-footer">
      <button type="button" class="btn" id="addNewCodeBtn">+ Add Code</button>
      <button class="btn btn-primary" id="saveAddNewModal" style="margin-left: auto;">Add Community</button>
    </div>
  </div>
</div>


<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// INDEX.PHP SPECIFIC SCRIPTS

// IMAGE MODAL
const modal = document.getElementById('imgModal');
const modalImg = document.getElementById('imgModalPic');
const modalClose = modal.querySelector('.modal-close');

function openModal(src) {
  modalImg.src = src;
  modal.classList.add('open');
  modal.setAttribute('aria-hidden', 'false');
}

function closeModal() {
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden', 'true');
  modalImg.src = '';
}

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('js-open-modal')) {
    const full = e.target.getAttribute('data-full') || e.target.src;
    openModal(full);
  }
  if (e.target === modal || e.target === modalClose) {
    closeModal();
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
});

// SEARCH
const searchInput = document.getElementById('searchInput');
const searchBtn = document.getElementById('searchBtn');

searchBtn.addEventListener('click', () => {
  const query = searchInput.value.trim();
  window.location.href = `?key=${ADMIN_KEY}&section=home&q=${encodeURIComponent(query)}`;
});

searchInput.addEventListener('keypress', (e) => {
  if (e.key === 'Enter') {
    e.preventDefault();
    searchBtn.click();
  }
});

// ADD CODE ROWS (for Add New section)
let codeIndex = 0;

function createCodeRow(index) {
  const div = document.createElement('div');
  div.className = 'code-edit-row';
  div.innerHTML = `
    <div class="form-group">
      <label class="form-label">Code</label>
      <input type="text" class="field" name="codes[${index}][code]" placeholder="e.g., #54839*" required maxlength="8" pattern="[A-Za-z0-9#*]{1,8}">
    </div>
    <div class="form-group">
      <label class="form-label">Notes</label>
      <input type="text" class="field" name="codes[${index}][notes]" placeholder="e.g., Main entrance">
    </div>
    <div class="form-group full-width">
      <label class="form-label">Details</label>
      <textarea class="field" name="codes[${index}][details]" placeholder="Additional details"></textarea>
    </div>
    <div class="form-group full-width">
      <label class="form-label">Photo (JPG/PNG/WebP/HEIC)</label>
      <input type="file" class="edit-file-input" accept="image/*" id="file-${index}">
      <div class="preview-box" id="preview-${index}">
        <img class="preview-thumb" src="" alt="preview">
      </div>
      <input type="hidden" name="codes[${index}][photo]" id="photo-${index}" value="">
      <div class="upload-status" id="status-${index}" style="margin-top: 8px; font-size: 0.85rem; color: var(--muted);"></div>
    </div>
    <div class="full-width">
      <button type="button" class="btn btn-danger btn-remove-code" title="Remove Code">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"/>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
          <line x1="10" y1="11" x2="10" y2="17"/>
          <line x1="14" y1="11" x2="14" y2="17"/>
        </svg>
      </button>
    </div>
  `;
  return div;
}

document.getElementById('addCodeBtn').addEventListener('click', () => {
  const row = createCodeRow(codeIndex);
  document.getElementById('codesEditor').appendChild(row);
  wireCodeRow(row, codeIndex);
  codeIndex++;
});

// Add initial row
document.getElementById('addCodeBtn').click();

// Submit Add Community Form
document.getElementById('submitAddForm').addEventListener('click', async () => {
  const form = document.getElementById('addForm');

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  // Check if at least one code has a value
  const codeInputs = form.querySelectorAll('input[name*="[code]"]');
  let hasCode = false;
  for (const input of codeInputs) {
    if (input.value.trim()) {
      hasCode = true;
      break;
    }
  }

  if (!hasCode) {
    showAlert({
      type: 'error',
      title: 'Error',
      message: 'Please add at least one code.',
      buttons: [
        {
          text: 'OK',
          className: 'btn-alert-primary'
        }
      ]
    });
    return;
  }

  form.submit();
});

function wireCodeRow(row, index) {
  const fileInput = row.querySelector(`#file-${index}`);
  const preview = row.querySelector(`#preview-${index}`);
  const previewImg = preview.querySelector('img');
  const photoInput = row.querySelector(`#photo-${index}`);
  const status = row.querySelector(`#status-${index}`);
  const removeBtn = row.querySelector('.btn-remove-code');

  fileInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    status.textContent = 'Uploading...';
    status.style.color = 'var(--muted)';

    const fd = new FormData();
    fd.append('key', ADMIN_KEY);
    fd.append('photo', file);

    // Get community name for filename
    const communityInput = document.querySelector('input[name="community"]');
    if (communityInput && communityInput.value.trim()) {
      fd.append('community_name', communityInput.value.trim());
    }

    try {
      const response = await fetch(`?ajax=upload&key=${ADMIN_KEY}`, {
        method: 'POST',
        body: fd
      });

      const result = await response.json();

      if (result.status === 'ok' && result.url) {
        photoInput.value = result.url;
        // Show preview with full URL
        const fullUrl = result.url.startsWith('http') ? result.url : `<?= ASSETS_URL ?>${result.url.replace('assets/', '')}`;
        previewImg.src = fullUrl;
        preview.classList.add('show');
        status.textContent = 'Uploaded!';
        status.style.color = 'var(--brand)';
      } else {
        status.textContent = `Error: ${result.error || 'Upload failed'}`;
        status.style.color = 'var(--danger)';
      }
    } catch (error) {
      status.textContent = 'Network error';
      status.style.color = 'var(--danger)';
    }
  });

  removeBtn.addEventListener('click', () => {
    row.remove();
  });
}

// DELETE COMMUNITY
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-delete-comm')) {
    const community = e.target.getAttribute('data-community');

    showAlert({
      type: 'warning',
      title: 'Delete Community',
      message: `Are you sure you want to delete "${community}"? This action cannot be undone.`,
      buttons: [
        {
          text: 'No',
          className: 'btn-alert-secondary'
        },
        {
          text: 'Yes',
          className: 'btn-alert-danger',
          onClick: () => {
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `
              <input type="hidden" name="key" value="${ADMIN_KEY}">
              <input type="hidden" name="action" value="delete_comm">
              <input type="hidden" name="community" value="${community}">
            `;
            document.body.appendChild(form);
            form.submit();
          }
        }
      ]
    });
  }
});

// EDIT COMMUNITY MODAL
const editModal = document.getElementById('editModal');
const editModalTitle = document.getElementById('editModalTitle');
const editCommunityForm = document.getElementById('editCommunityForm');
const editOriginalName = document.getElementById('editOriginalName');
const editCommunityName = document.getElementById('editCommunityName');
const editCityName = document.getElementById('editCityName');
const editLatitude = document.getElementById('editLatitude');
const editLongitude = document.getElementById('editLongitude');
const editCoordinates = document.getElementById('editCoordinates');
const editCodesContainer = document.getElementById('editCodesContainer');
const closeEditModal = document.getElementById('closeEditModal');
const saveEditModal = document.getElementById('saveEditModal');
const addEditCodeBtn = document.getElementById('addEditCodeBtn');

let editCodeIndex = 0;
const editCodeFiles = new Map();

// Load gates.json data
const gatesData = <?= json_encode($data) ?>;

function openEditModal(communityName) {
  const community = gatesData.find(c => c.community === communityName);
  if (!community) {
    showAlert({
      type: 'error',
      title: 'Error',
      message: 'Community not found',
      buttons: [
        {
          text: 'OK',
          className: 'btn-alert-primary'
        }
      ]
    });
    return;
  }

  editModalTitle.textContent = `Edit: ${communityName}`;
  editOriginalName.value = communityName;
  editCommunityName.value = communityName;
  editCityName.value = community.city || '';

  // Load coordinates if available
  const latInput = document.getElementById('editLatitude');
  const lonInput = document.getElementById('editLongitude');

  if (community.coordinates) {
    const lat = community.coordinates.latitude || '';
    const lon = community.coordinates.longitude || '';
    if (latInput) latInput.value = lat;
    if (lonInput) lonInput.value = lon;
  } else {
    if (latInput) latInput.value = '';
    if (lonInput) lonInput.value = '';
  }

  editCodesContainer.innerHTML = '';
  editCodeIndex = 0;
  editCodeFiles.clear();

  // Load existing codes
  if (community.codes && community.codes.length > 0) {
    community.codes.forEach((code, idx) => {
      addEditCodeRow(code);
    });
  } else {
    addEditCodeRow();
  }

  editModal.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeEditModalFunc() {
  editModal.classList.remove('open');
  document.body.style.overflow = '';
  editCodesContainer.innerHTML = '';
  editCodeFiles.clear();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function addEditCodeRow(codeData = null) {
  const index = editCodeIndex++;
  const div = document.createElement('div');
  div.className = 'edit-code-item';
  div.setAttribute('data-edit-index', index);

  const photoUrl = codeData?.photo ? `<?= ASSETS_URL ?>${codeData.photo.replace('assets/', '')}` : '';
  const code = codeData?.code || '';
  const notes = codeData?.notes || '';
  const details = codeData?.details || '';
  const photo = codeData?.photo || '';

  div.innerHTML = `
    <div class="edit-code-header">
      <strong style="color: var(--text);">Code #${index + 1}</strong>
      <button type="button" class="btn btn-danger btn-remove-edit-code" data-index="${index}" title="Remove Code">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"/>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
          <line x1="10" y1="11" x2="10" y2="17"/>
          <line x1="14" y1="11" x2="14" y2="17"/>
        </svg>
      </button>
    </div>
    <div class="edit-code-grid">
      <div class="form-group">
        <label class="form-label">Code</label>
        <input type="text" class="field" name="codes[${index}][code]" value="${escapeHtml(code)}" placeholder="e.g., #54839*" required maxlength="8" pattern="[A-Za-z0-9#*]{1,8}">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <input type="text" class="field" name="codes[${index}][notes]" value="${escapeHtml(notes)}" placeholder="e.g., Main entrance">
      </div>
      <div class="form-group edit-code-full">
        <label class="form-label">Details</label>
        <textarea class="field" name="codes[${index}][details]" placeholder="Additional details">${escapeHtml(details)}</textarea>
      </div>
      <div class="form-group edit-code-full">
        <label class="form-label">Photo</label>
        <input type="file" class="edit-file-input" accept="image/*" id="edit-file-${index}" data-index="${index}">
        <div class="preview-box ${photoUrl ? 'show' : ''}" id="edit-preview-${index}">
          <img class="preview-thumb" src="${escapeHtml(photoUrl)}" alt="preview">
        </div>
        <input type="hidden" name="codes[${index}][photo]" id="edit-photo-${index}" value="${escapeHtml(photo)}">
        <div class="upload-status" id="edit-status-${index}" style="margin-top: 8px; font-size: 0.85rem;"></div>
      </div>
    </div>
  `;

  editCodesContainer.appendChild(div);
  wireEditCodeRow(div, index);
}

function wireEditCodeRow(row, index) {
  const fileInput = row.querySelector(`#edit-file-${index}`);
  const preview = row.querySelector(`#edit-preview-${index}`);
  const previewImg = preview.querySelector('img');
  const photoInput = row.querySelector(`#edit-photo-${index}`);
  const status = row.querySelector(`#edit-status-${index}`);
  const removeBtn = row.querySelector('.btn-remove-edit-code');

  fileInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    status.textContent = 'Uploading...';
    status.style.color = 'var(--muted)';

    const fd = new FormData();
    fd.append('key', ADMIN_KEY);
    fd.append('photo', file);
    fd.append('community_name', editCommunityName.value);

    try {
      const response = await fetch(`?ajax=upload&key=${ADMIN_KEY}`, {
        method: 'POST',
        body: fd
      });

      const result = await response.json();

      if (result.status === 'ok' && result.url) {
        photoInput.value = result.url;
        previewImg.src = result.url;
        preview.classList.add('show');

        // Update coordinates if image has GPS and community doesn't have coordinates yet
        const latInput = document.getElementById('editLatitude');
        const lonInput = document.getElementById('editLongitude');
        if (result.coordinates && latInput && lonInput && (!latInput.value || !lonInput.value)) {
          latInput.value = result.coordinates.latitude;
          lonInput.value = result.coordinates.longitude;
          status.textContent = 'Uploaded with GPS!';
        } else {
          status.textContent = 'Uploaded!';
        }
        status.style.color = 'var(--brand)';
      } else {
        status.textContent = `Error: ${result.error || 'Upload failed'}`;
        status.style.color = 'var(--danger)';
      }
    } catch (error) {
      status.textContent = 'Network error';
      status.style.color = 'var(--danger)';
    }
  });

  // Remove button event - handled globally at the end of script
}

addEditCodeBtn.addEventListener('click', () => {
  addEditCodeRow();
});

closeEditModal.addEventListener('click', closeEditModalFunc);

saveEditModal.addEventListener('click', () => {
  if (editCommunityForm.checkValidity()) {
    editCommunityForm.submit();
  } else {
    editCommunityForm.reportValidity();
  }
});

// Close modal on background click
editModal.addEventListener('click', (e) => {
  if (e.target === editModal) {
    closeEditModalFunc();
  }
});

// Close modal on ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && editModal.classList.contains('open')) {
    closeEditModalFunc();
  }
});

// Open edit modal when clicking Edit button
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-edit-comm')) {
    const community = e.target.getAttribute('data-community');
    openEditModal(community);
  }
});

// PIN MANAGEMENT
const addPinBtn = document.getElementById('addPinBtn');
const pinFormCard = document.getElementById('pinFormCard');
const pinForm = document.getElementById('pinForm');
const pinFormTitle = document.getElementById('pinFormTitle');
const pinFormAction = document.getElementById('pinFormAction');
const pinFormIndex = document.getElementById('pinFormIndex');
const pinName = document.getElementById('pinName');
const pinValue = document.getElementById('pinValue');
const cancelPinBtn = document.getElementById('cancelPinBtn');

addPinBtn.addEventListener('click', () => {
  pinFormTitle.textContent = 'Add New PIN';
  pinFormAction.value = 'add_pin';
  pinFormIndex.value = '';
  pinName.value = '';
  pinValue.value = '';
  pinFormCard.style.display = 'block';
  pinFormCard.scrollIntoView({ behavior: 'smooth' });
});

cancelPinBtn.addEventListener('click', () => {
  pinFormCard.style.display = 'none';
});

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('btn-edit-pin')) {
    const index = e.target.getAttribute('data-index');
    const name = e.target.getAttribute('data-name');
    const pin = e.target.getAttribute('data-pin');

    pinFormTitle.textContent = 'Edit PIN';
    pinFormAction.value = 'update_pin';
    pinFormIndex.value = index;
    pinName.value = name;
    pinValue.value = pin;
    pinFormCard.style.display = 'block';
    pinFormCard.scrollIntoView({ behavior: 'smooth' });
  }

  if (e.target.classList.contains('btn-delete-pin')) {
    const index = e.target.getAttribute('data-index');

    showAlert({
      type: 'warning',
      title: 'Delete PIN',
      message: 'Are you sure you want to delete this PIN? This action cannot be undone.',
      buttons: [
        {
          text: 'No',
          className: 'btn-alert-secondary'
        },
        {
          text: 'Yes',
          className: 'btn-alert-danger',
          onClick: () => {
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `
              <input type="hidden" name="key" value="${ADMIN_KEY}">
              <input type="hidden" name="action" value="delete_pin">
              <input type="hidden" name="index" value="${index}">
            `;
            document.body.appendChild(form);
            form.submit();
          }
        }
      ]
    });
  }
});

// JSON FILE UPLOAD
const jsonFileInput = document.getElementById('jsonFileInput');
if (jsonFileInput) {
  jsonFileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
      const fileName = this.files[0].name;
      if (!fileName.endsWith('.json')) {
        showAlert({
          type: 'error',
          title: 'Invalid File',
          message: 'Please select a valid JSON file.'
        });
        this.value = '';
        return;
      }
      showAlert({
        type: 'warning',
        title: 'Upload JSON File',
        message: `Upload "${fileName}" and replace current gates.json?\n\nA backup will be created automatically.`,
        buttons: [
          {
            text: 'No',
            className: 'btn-alert-secondary',
            onClick: () => {
              jsonFileInput.value = '';
            }
          },
          {
            text: 'Yes',
            className: 'btn-alert-primary',
            onClick: () => {
              document.getElementById('uploadJsonForm').submit();
            }
          }
        ]
      });
    }
  });
}

// ADD NEW MODAL
const addNewModal = document.getElementById('addNewModal');
const openAddNewModalBtn = document.getElementById('openAddNewModal');
const closeAddNewModalBtn = document.getElementById('closeAddNewModal');
const saveAddNewModalBtn = document.getElementById('saveAddNewModal');
const addNewForm = document.getElementById('addNewForm');
const addNewCodesContainer = document.getElementById('addNewCodesContainer');
const addNewCodeBtn = document.getElementById('addNewCodeBtn');
const addNewCommunityName = document.getElementById('addNewCommunityName');

let addNewCodeIndex = 0;
const addNewCodeFiles = new Map();

function openAddNewModal() {
  addNewModal.classList.add('open');
  document.body.style.overflow = 'hidden';
  addNewCommunityName.value = '';
  addNewCodesContainer.innerHTML = '';
  addNewCodeIndex = 0;
  addNewCodeFiles.clear();
  // Add initial code row
  addAddNewCodeRow();
}

function closeAddNewModal() {
  addNewModal.classList.remove('open');
  document.body.style.overflow = '';
}

function createAddNewCodeRow(index) {
  const div = document.createElement('div');
  div.className = 'edit-code-item';
  div.style.marginBottom = '16px';
  div.innerHTML = `
    <div class="edit-code-header">
      <strong style="color: var(--text);">Code #${index + 1}</strong>
      <button type="button" class="btn btn-danger btn-remove-add-code" title="Remove Code">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"/>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
          <line x1="10" y1="11" x2="10" y2="17"/>
          <line x1="14" y1="11" x2="14" y2="17"/>
        </svg>
      </button>
    </div>
    <div class="edit-code-grid">
      <!-- Code and Notes side by side -->
      <div class="form-group">
        <label class="form-label">Code</label>
        <input type="text" class="field" name="codes[${index}][code]" placeholder="e.g., #54839*" required maxlength="8" pattern="[A-Za-z0-9#*]{1,8}">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <input type="text" class="field" name="codes[${index}][notes]" placeholder="e.g., Main entrance">
      </div>

      <!-- Details full width below -->
      <div class="form-group edit-code-full">
        <label class="form-label">Details</label>
        <textarea class="field" name="codes[${index}][details]" placeholder="Additional details" rows="3"></textarea>
      </div>

      <!-- Upload Photo full width below -->
      <div class="form-group edit-code-full">
        <label class="form-label">Upload Photo</label>
        <input type="file" class="field" accept="image/*" id="addNewFile-${index}">
        <div class="preview-container" id="addNewPreview-${index}" style="display: none; margin-top: 8px;">
          <img src="" style="max-width: 200px; border-radius: 8px;">
        </div>
        <input type="hidden" name="codes[${index}][photo]" id="addNewPhoto-${index}">
        <div class="upload-status" id="addNewStatus-${index}" style="margin-top: 8px; font-size: 0.85rem; color: var(--muted);"></div>
      </div>
    </div>
  `;
  return div;
}

function addAddNewCodeRow(codeData = null) {
  const row = createAddNewCodeRow(addNewCodeIndex);
  addNewCodesContainer.appendChild(row);

  const fileInput = row.querySelector(`#addNewFile-${addNewCodeIndex}`);
  const preview = row.querySelector(`#addNewPreview-${addNewCodeIndex}`);
  const photoInput = row.querySelector(`#addNewPhoto-${addNewCodeIndex}`);
  const status = row.querySelector(`#addNewStatus-${addNewCodeIndex}`);
  const removeBtn = row.querySelector('.btn-remove-add-code');

  fileInput.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    status.textContent = 'Uploading...';
    status.style.color = 'var(--muted)';

    const fd = new FormData();
    fd.append('key', ADMIN_KEY);
    fd.append('photo', file);

    if (addNewCommunityName.value.trim()) {
      fd.append('community_name', addNewCommunityName.value.trim());
    }

    try {
      const response = await fetch(`?ajax=upload&key=${ADMIN_KEY}`, {
        method: 'POST',
        body: fd
      });

      const result = await response.json();

      if (result.status === 'ok' && result.url) {
        photoInput.value = result.url;
        const img = preview.querySelector('img');
        const fullUrl = result.url.startsWith('http') ? result.url : `<?= ASSETS_URL ?>${result.url.replace('assets/', '')}`;
        img.src = fullUrl;
        preview.style.display = 'block';
        status.textContent = 'Uploaded!';
        status.style.color = 'var(--brand)';
      } else {
        status.textContent = `Error: ${result.error || 'Upload failed'}`;
        status.style.color = 'var(--danger)';
      }
    } catch (error) {
      status.textContent = 'Network error';
      status.style.color = 'var(--danger)';
    }
  });

  removeBtn.addEventListener('click', () => {
    row.remove();
  });

  addNewCodeIndex++;
}

openAddNewModalBtn.addEventListener('click', openAddNewModal);
closeAddNewModalBtn.addEventListener('click', closeAddNewModal);

addNewCodeBtn.addEventListener('click', () => {
  addAddNewCodeRow();
});

saveAddNewModalBtn.addEventListener('click', () => {
  if (!addNewForm.checkValidity()) {
    addNewForm.reportValidity();
    return;
  }

  // Get community name
  const communityName = addNewCommunityName.value.trim();

  // Check if at least one code has a value
  const codeInputs = addNewForm.querySelectorAll('input[name*="[code]"]');
  let hasCode = false;
  const newCodes = [];

  for (const input of codeInputs) {
    const codeValue = input.value.trim();
    if (codeValue) {
      hasCode = true;
      newCodes.push(codeValue.toLowerCase());
    }
  }

  if (!hasCode) {
    showAlert({
      type: 'error',
      title: 'Error',
      message: 'Please add at least one code.',
      buttons: [
        {
          text: 'OK',
          className: 'btn-alert-primary'
        }
      ]
    });
    return;
  }

  // Check if community exists and if any code already exists
  const existingCommunity = gatesData.find(c =>
    c.community.toLowerCase() === communityName.toLowerCase()
  );

  if (existingCommunity && existingCommunity.codes) {
    const existingCodes = existingCommunity.codes.map(c =>
      (c.code || '').toLowerCase()
    );

    const duplicates = newCodes.filter(code => existingCodes.includes(code));

    if (duplicates.length > 0) {
      const duplicateList = duplicates.map(c => c.toUpperCase()).join(', ');
      showAlert({
        type: 'error',
        title: 'Duplicate Code Error',
        message: `The following code(s) already exist in "${communityName}": ${duplicateList}`,
        buttons: [
          {
            text: 'OK',
            className: 'btn-alert-primary'
          }
        ]
      });
      return;
    }
  }

  addNewForm.submit();
});

// Close modal on ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && addNewModal.classList.contains('open')) {
    closeAddNewModal();
  }
});

// Close modal on overlay click
addNewModal.addEventListener('click', (e) => {
  if (e.target === addNewModal) {
    closeAddNewModal();
  }
});

// Delete Community button - NATIVE POPUP
document.addEventListener('click', function(e) {
  if (e.target.closest('#deleteEditCommunity')) {
    e.preventDefault();
    e.stopPropagation();

    const editOriginalName = document.getElementById('editOriginalName');
    const community = editOriginalName ? editOriginalName.value : '';

    // Create custom confirmation modal
    const confirmModal = document.createElement('div');
    confirmModal.id = 'deleteConfirmModal';
    confirmModal.style.cssText = `
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 20000;
      padding: 20px;
    `;

    confirmModal.innerHTML = `
      <div style="
        width: min(90vw, 400px);
        background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
        border: 1px solid var(--modal-border);
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
      ">
        <div style="
          width: 64px;
          height: 64px;
          margin: 0 auto 16px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(245, 124, 0, 0.15));
          display: flex;
          align-items: center;
          justify-content: center;
        ">
          <svg style="width: 32px; height: 32px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ff9800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </div>
        <h3 style="
          font-size: 1.25rem;
          font-weight: 700;
          margin-bottom: 8px;
          color: var(--text);
        ">Delete Community</h3>
        <p style="
          font-size: 0.95rem;
          color: var(--muted);
          margin-bottom: 24px;
          line-height: 1.5;
        ">Are you sure you want to delete "${community}"? This action cannot be undone.</p>
        <div style="
          display: flex;
          gap: 12px;
          justify-content: center;
        ">
          <button id="confirmCancel" style="
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 0;
            background: var(--btn-secondary-bg);
            color: var(--btn-secondary-text);
            border: 1px solid var(--btn-secondary-border);
          ">Cancel</button>
          <button id="confirmYes" style="
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 0;
            background: linear-gradient(135deg, var(--danger), var(--danger-2));
            color: #fff;
            box-shadow: 0 4px 14px rgba(255, 92, 92, .4);
          ">Yes, Delete</button>
        </div>
      </div>
    `;

    document.body.appendChild(confirmModal);
    document.body.style.overflow = 'hidden';

    // Get button references
    const confirmCancelBtn = document.getElementById('confirmCancel');
    const confirmYesBtn = document.getElementById('confirmYes');

    // Add hover effects to Cancel button
    confirmCancelBtn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
      this.style.background = 'var(--btn-secondary-hover)';
    });
    confirmCancelBtn.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
      this.style.background = 'var(--btn-secondary-bg)';
    });
    confirmCancelBtn.addEventListener('mousedown', function() {
      this.style.transform = 'translateY(0) scale(0.95)';
    });
    confirmCancelBtn.addEventListener('mouseup', function() {
      this.style.transform = 'translateY(-2px) scale(1)';
    });

    // Add hover effects to Yes button
    confirmYesBtn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
      this.style.boxShadow = '0 6px 18px rgba(255, 92, 92, .6)';
    });
    confirmYesBtn.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
      this.style.boxShadow = '0 4px 14px rgba(255, 92, 92, .4)';
    });
    confirmYesBtn.addEventListener('mousedown', function() {
      this.style.transform = 'translateY(0) scale(0.95)';
    });
    confirmYesBtn.addEventListener('mouseup', function() {
      this.style.transform = 'translateY(-2px) scale(1)';
    });

    // Cancel button click
    confirmCancelBtn.addEventListener('click', function() {
      document.body.removeChild(confirmModal);
      document.body.style.overflow = '';
    });

    // Yes button click
    confirmYesBtn.addEventListener('click', function() {
      const form = document.createElement('form');
      form.method = 'post';
      form.innerHTML = `
        <input type="hidden" name="key" value="${ADMIN_KEY}">
        <input type="hidden" name="action" value="delete_comm">
        <input type="hidden" name="community" value="${community}">
      `;
      document.body.appendChild(form);
      form.submit();
    });

    // Close on background click
    confirmModal.addEventListener('click', function(e) {
      if (e.target === confirmModal) {
        document.body.removeChild(confirmModal);
        document.body.style.overflow = '';
      }
    });
  }
});

// Delete Code button - NATIVE POPUP
document.addEventListener('click', function(e) {
  if (e.target.closest('.btn-remove-edit-code')) {
    e.preventDefault();
    e.stopPropagation();

    const btn = e.target.closest('.btn-remove-edit-code');
    const editCodeItem = btn.closest('.edit-code-item');
    const codeInput = editCodeItem.querySelector('input[name*="[code]"]');
    const codeValue = codeInput ? codeInput.value : 'this code';

    const editOriginalName = document.getElementById('editOriginalName');
    const community = editOriginalName ? editOriginalName.value : '';

    const editCodesContainer = document.getElementById('editCodesContainer');

    // Check if it's the last code
    if (editCodesContainer && editCodesContainer.children.length <= 1) {
      // Create "Cannot Remove" modal
      const cannotRemoveModal = document.createElement('div');
      cannotRemoveModal.style.cssText = `
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 20000;
        padding: 20px;
      `;

      cannotRemoveModal.innerHTML = `
        <div style="
          width: min(90vw, 400px);
          background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
          border: 1px solid var(--modal-border);
          border-radius: 12px;
          padding: 24px;
          text-align: center;
          box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
        ">
          <div style="
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(245, 124, 0, 0.15));
            display: flex;
            align-items: center;
            justify-content: center;
          ">
            <svg style="width: 32px; height: 32px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ff9800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
              <line x1="12" y1="9" x2="12" y2="13"></line>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
          </div>
          <h3 style="
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
          ">Cannot Remove</h3>
          <p style="
            font-size: 0.95rem;
            color: var(--muted);
            margin-bottom: 24px;
            line-height: 1.5;
          ">Cannot remove the last code. A community must have at least one code.</p>
          <div style="
            display: flex;
            gap: 12px;
            justify-content: center;
          ">
            <button id="cannotRemoveOk" style="
              padding: 12px 24px;
              border-radius: 10px;
              font-weight: 600;
              font-size: 15px;
              cursor: pointer;
              transition: all 0.2s ease;
              border: 0;
              background: linear-gradient(135deg, var(--brand), var(--brand-2));
              color: #07140c;
              box-shadow: 0 4px 14px rgba(59, 221, 130, .4);
            ">OK</button>
          </div>
        </div>
      `;

      document.body.appendChild(cannotRemoveModal);
      document.body.style.overflow = 'hidden';

      const cannotRemoveOkBtn = document.getElementById('cannotRemoveOk');

      // Add hover effects to OK button
      cannotRemoveOkBtn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 6px 18px rgba(59, 221, 130, .6)';
      });
      cannotRemoveOkBtn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 14px rgba(59, 221, 130, .4)';
      });
      cannotRemoveOkBtn.addEventListener('mousedown', function() {
        this.style.transform = 'translateY(0) scale(0.95)';
      });
      cannotRemoveOkBtn.addEventListener('mouseup', function() {
        this.style.transform = 'translateY(-2px) scale(1)';
      });

      cannotRemoveOkBtn.addEventListener('click', function() {
        document.body.removeChild(cannotRemoveModal);
        document.body.style.overflow = '';
      });

      cannotRemoveModal.addEventListener('click', function(e) {
        if (e.target === cannotRemoveModal) {
          document.body.removeChild(cannotRemoveModal);
          document.body.style.overflow = '';
        }
      });

      return;
    }

    // Create "Delete Code" confirmation modal
    const deleteCodeModal = document.createElement('div');
    deleteCodeModal.style.cssText = `
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 20000;
      padding: 20px;
    `;

    deleteCodeModal.innerHTML = `
      <div style="
        width: min(90vw, 400px);
        background: linear-gradient(180deg, var(--modal-bg-1), var(--modal-bg-2));
        border: 1px solid var(--modal-border);
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .5);
      ">
        <div style="
          width: 64px;
          height: 64px;
          margin: 0 auto 16px;
          border-radius: 50%;
          background: linear-gradient(135deg, rgba(255, 152, 0, 0.2), rgba(245, 124, 0, 0.15));
          display: flex;
          align-items: center;
          justify-content: center;
        ">
          <svg style="width: 32px; height: 32px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#ff9800" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </div>
        <h3 style="
          font-size: 1.25rem;
          font-weight: 700;
          margin-bottom: 8px;
          color: var(--text);
        ">Delete Code</h3>
        <p style="
          font-size: 0.95rem;
          color: var(--muted);
          margin-bottom: 24px;
          line-height: 1.5;
        ">Are you sure you want to delete the code "${codeValue}" from ${community}? This action cannot be undone.</p>
        <div style="
          display: flex;
          gap: 12px;
          justify-content: center;
        ">
          <button id="deleteCodeCancel" style="
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 0;
            background: var(--btn-secondary-bg);
            color: var(--btn-secondary-text);
            border: 1px solid var(--btn-secondary-border);
          ">Cancel</button>
          <button id="deleteCodeYes" style="
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 0;
            background: linear-gradient(135deg, var(--danger), var(--danger-2));
            color: #fff;
            box-shadow: 0 4px 14px rgba(255, 92, 92, .4);
          ">Yes, Delete</button>
        </div>
      </div>
    `;

    document.body.appendChild(deleteCodeModal);
    document.body.style.overflow = 'hidden';

    // Get button references
    const cancelBtn = document.getElementById('deleteCodeCancel');
    const yesBtn = document.getElementById('deleteCodeYes');

    // Add hover effects to Cancel button
    cancelBtn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
      this.style.background = 'var(--btn-secondary-hover)';
    });
    cancelBtn.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
      this.style.background = 'var(--btn-secondary-bg)';
    });
    cancelBtn.addEventListener('mousedown', function() {
      this.style.transform = 'translateY(0) scale(0.95)';
    });
    cancelBtn.addEventListener('mouseup', function() {
      this.style.transform = 'translateY(-2px) scale(1)';
    });

    // Add hover effects to Yes button
    yesBtn.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
      this.style.boxShadow = '0 6px 18px rgba(255, 92, 92, .6)';
    });
    yesBtn.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
      this.style.boxShadow = '0 4px 14px rgba(255, 92, 92, .4)';
    });
    yesBtn.addEventListener('mousedown', function() {
      this.style.transform = 'translateY(0) scale(0.95)';
    });
    yesBtn.addEventListener('mouseup', function() {
      this.style.transform = 'translateY(-2px) scale(1)';
    });

    // Cancel button click
    cancelBtn.addEventListener('click', function() {
      document.body.removeChild(deleteCodeModal);
      document.body.style.overflow = '';
    });

    // Yes button
    document.getElementById('deleteCodeYes').addEventListener('click', async function() {
      // Close the confirmation modal first
      document.body.removeChild(deleteCodeModal);
      document.body.style.overflow = '';

      // Send delete request via fetch
      const formData = new FormData();
      formData.append('key', ADMIN_KEY);
      formData.append('action', 'delete_code');
      formData.append('community', community);
      formData.append('code', codeValue);

      try {
        const response = await fetch('', {
          method: 'POST',
          body: formData
        });

        if (response.ok) {
          // Remove the code item from the DOM
          editCodeItem.remove();

          // Show success alert modal
          showAlert({
            type: 'success',
            title: 'Code Deleted',
            message: `The code "${codeValue}" has been successfully deleted from ${community}.`,
            buttons: [{
              text: 'OK',
              className: 'btn-alert-primary'
            }]
          });
        } else {
          showAlert({
            type: 'error',
            title: 'Error',
            message: 'Failed to delete code. Please try again.',
            buttons: [{
              text: 'OK',
              className: 'btn-alert-secondary'
            }]
          });
        }
      } catch (error) {
        console.error('Error:', error);
        showAlert({
          type: 'error',
          title: 'Network Error',
          message: 'Unable to connect to the server. Please check your connection and try again.',
          buttons: [{
            text: 'OK',
            className: 'btn-alert-secondary'
          }]
        });
      }
    });

    // Close on background click
    deleteCodeModal.addEventListener('click', function(e) {
      if (e.target === deleteCodeModal) {
        document.body.removeChild(deleteCodeModal);
        document.body.style.overflow = '';
      }
    });
  }
});

</script>
