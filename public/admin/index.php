<?php
/******************** CONFIG ********************/
// rutas de disco (sin cambio)
const ADMIN_KEY   = '43982';
const GATES_JSON  = __DIR__ . '/../data/gates.json';
const ASSETS_DIR  = __DIR__ . '/../assets/';
const DEFAULT_THUMB_FILE = 'thumbnailnone.png';

// URL base absoluta para /gatecodes/assets/ (normaliza ../)
$APP_URL = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); // ej: "/gatecodes"
define('ASSETS_URL', $APP_URL . '/assets/');                     // ej: "/gatecodes/assets/" (solo para visualización)
define('ASSETS_RELATIVE', 'assets/');                             // ruta relativa para guardar en JSON
define('DEFAULT_THUMB_URL', ASSETS_RELATIVE . DEFAULT_THUMB_FILE); // ej: "assets/thumbnailnone.png"


/* Minimal auth */
function require_key(){
  $k = $_GET['key'] ?? $_POST['key'] ?? '';
  if ($k !== ADMIN_KEY) { http_response_code(403); exit('Forbidden'); }
}
require_key();

/******************** HELPERS ********************/
function read_json($path){
  if(!file_exists($path)) return [];
  $h=fopen($path,'r'); if(!$h) return [];
  flock($h,LOCK_SH); $s=stream_get_contents($h); flock($h,LOCK_UN); fclose($h);
  $j=json_decode($s,true); return is_array($j)?$j:[];
}
function write_json($path,$data){
  $dir = dirname($path);
  if(!is_dir($dir)) @mkdir($dir, 0775, true);
  $tmp=$path.'.tmp'; $h=fopen($tmp,'w'); if(!$h) return false;
  flock($h,LOCK_EX); fwrite($h,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
  fflush($h); flock($h,LOCK_UN); fclose($h);
  return @rename($tmp,$path);
}
/* Normaliza la URL web de la foto para el panel (acepta http(s), /absoluto, ../assets, assets/, nombre suelto) */
function web_photo($p){
  $p = trim((string)$p);
  if ($p === '') return DEFAULT_THUMB_URL;

  // http(s) o absoluto desde raíz → úsalo tal cual
  if (preg_match('#^(https?:)?//#', $p)) return $p;
  if ($p[0] === '/') return $p;

  // quita prefijos relativos y enruta a ASSETS_URL
  $p = ltrim($p, './'); // quita "./" o "../" iniciales
  if (stripos($p, 'assets/') === 0) $p = substr($p, 7);         // deja solo el nombre relativo dentro de assets/
  if (stripos($p, '../assets/') === 0) $p = substr($p, 10);

  return ASSETS_URL . ltrim($p, '/');
}

/* Normalización */
function norm($s){
  $s = mb_strtolower((string)$s, 'UTF-8');
  $map = [
    'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
    'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
    'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
    'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
    'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
    'ñ'=>'n','ç'=>'c'
  ];
  $s = strtr($s, $map);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}
function find_comm_index(&$data,$community){
  $q=norm($community);
  foreach($data as $i=>$c){ if (norm($c['community'] ?? '') === $q) return $i; }
  return -1;
}

/* Paths seguros para borrar fotos */
function path_join($base, $rel){
  $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
  $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
  return $base . ltrim($rel, DIRECTORY_SEPARATOR);
}
function photo_url_to_path($url){
  $url = trim((string)$url);
  if ($url === '' || $url === DEFAULT_THUMB_URL) return '';
  $pathPart = parse_url($url, PHP_URL_PATH);
  if ($pathPart) {
    if (strpos($pathPart, ASSETS_URL) === 0) {
      $rel = substr($pathPart, strlen(ASSETS_URL));
      $candidate = path_join(ASSETS_DIR, $rel);
    } else {
      $candidate = $pathPart;
      if ($candidate !== '' && $candidate[0] !== DIRECTORY_SEPARATOR) {
        $candidate = path_join(ASSETS_DIR, $candidate);
      }
    }
  } else {
    $candidate = path_join(ASSETS_DIR, $url);
  }
  $assetDirReal = realpath(ASSETS_DIR);
  $fileReal = @realpath($candidate);
  if ($fileReal === false) {
    $dirReal = realpath(dirname($candidate));
    if ($dirReal !== false) $fileReal = $dirReal . DIRECTORY_SEPARATOR . basename($candidate);
  }
  if ($fileReal === false || $assetDirReal === false) return '';
  if (strpos($fileReal, $assetDirReal . DIRECTORY_SEPARATOR) !== 0 && $fileReal !== $assetDirReal) return '';
  if (basename($fileReal) === DEFAULT_THUMB_FILE) return '';
  return $fileReal;
}
function delete_photo_by_url($url){
  $p = photo_url_to_path($url);
  if ($p === '') return true;
  if (!file_exists($p)) return true;
  return @unlink($p);
}

/******************** DOWNLOAD JSON ********************/
if (isset($_GET['download_json'])) {
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

/******************** AJAX: UPLOAD (single-row) ********************/
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

  $base = 'gate_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
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

  echo json_encode(['status'=>'ok','url'=>ASSETS_RELATIVE.basename($dest)]);
  exit;
}

/******************** ACTIONS (POST) ********************/
$action = $_POST['action'] ?? '';
$msg = '';

if ($action === 'upload_json') {
  if (!isset($_FILES['json_file'])) {
    $msg = 'No file selected.';
  } else {
    $file = $_FILES['json_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
      $msg = 'Upload error: ' . $file['error'];
    } else if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
      $msg = 'File too large (max 5MB).';
    } else {
      // Validate JSON
      $content = file_get_contents($file['tmp_name']);
      $json = json_decode($content, true);
      
      if (json_last_error() !== JSON_ERROR_NONE) {
        $msg = 'Invalid JSON file: ' . json_last_error_msg();
      } else if (!is_array($json)) {
        $msg = 'JSON must be an array.';
      } else {
        // Create backup before overwriting
        $backup_dir = dirname(GATES_JSON) . '/backups';
        if (!is_dir($backup_dir)) @mkdir($backup_dir, 0775, true);
        
        $backup_file = $backup_dir . '/gates_backup_' . date('Y-m-d_His') . '.json';
        if (file_exists(GATES_JSON)) {
          @copy(GATES_JSON, $backup_file);
        }
        
        // Write new file
        if (write_json(GATES_JSON, $json)) {
          header('Location: ?key=' . urlencode(ADMIN_KEY) . '&flash=' . urlencode('JSON file uploaded successfully. Backup created.'));
          exit;
        } else {
          $msg = 'Failed to write JSON file.';
        }
      }
    }
  }
}

if ($action === 'add') {
  $data = read_json(GATES_JSON);
  $community = trim($_POST['community'] ?? '');
  if ($community===''){ $msg='Missing community name.'; }
  else {
    $codes = [];
    $rows = $_POST['codes'] ?? [];
    foreach ($rows as $r){
      $code = trim($r['code'] ?? '');
      if($code==='') continue;
      $entry = ['code'=>$code];
      foreach(['notes','details','photo'] as $k){
        $v=trim($r[$k]??'');
        if($k==='photo' && $v===''){ $v = DEFAULT_THUMB_URL; }
        if($v!=='') $entry[$k]=$v;
      }
      $codes[] = $entry;
    }
    if(empty($codes)){ $msg='Add at least one code.'; }
    else{
      $i = find_comm_index($data,$community);
      if($i>=0){
        header('Location: ?key='.urlencode(ADMIN_KEY).'&edit='.urlencode($community).'&flash='.urlencode('Community already exists, opening it.'));
        exit;
      } else {
        $data[] = ['community'=>$community, 'codes'=>$codes];
        write_json(GATES_JSON,$data);
        header('Location: ?key='.urlencode(ADMIN_KEY).'&edit='.urlencode($community).'&flash='.urlencode('Saved.'));
        exit;
      }
    }
  }
}

if ($action === 'update') {
  $data = read_json(GATES_JSON);
  $original = trim($_POST['original'] ?? '');
  $idx = find_comm_index($data,$original);
  if ($idx<0){ $msg='Community not found.'; }
  else {
    $new_name = trim($_POST['community'] ?? $original);
    $rows = $_POST['codes'] ?? [];

    // Find an existing photo from this community's codes
    $existing_photo = null;
    if (isset($data[$idx]['codes'])) {
      foreach ($data[$idx]['codes'] as $existing_code) {
        $photo = trim($existing_code['photo'] ?? '');
        // Skip default thumbnails (both relative and absolute paths)
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
          // Use existing photo from community if available, otherwise use default
          $v = $existing_photo ?? DEFAULT_THUMB_URL;
        }
        if($v!=='') $entry[$k]=$v;
      }
      $codes[]=$entry;
    }
    if(empty($codes)){ $msg='Add at least one code.'; }
    else{
      $data[$idx] = ['community'=>$new_name,'codes'=>$codes];
      write_json(GATES_JSON,$data);
      header('Location: ?key='.urlencode(ADMIN_KEY).'&edit='.urlencode($new_name).'&flash='.urlencode('Updated.'));
      exit;
    }
  }
}

/* eliminar código individual + foto, permaneciendo en edición */
if ($action === 'delete_code') {
  $data = read_json(GATES_JSON);
  $comm = trim($_POST['community']??'');
  $code = trim($_POST['code']??'');
  $idx = find_comm_index($data,$comm);
  $failed = [];

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
      header('Location: ?key='.urlencode(ADMIN_KEY).'&flash='.urlencode('Code deleted. Community emptied and removed.'));
      exit;
    }
    write_json(GATES_JSON,$data);
    $flash='Code deleted.';
    if ($failed) { $flash .= ' (No se pudo borrar: '.implode(', ', $failed).')'; }
    header('Location: ?key='.urlencode(ADMIN_KEY).'&edit='.urlencode($comm).'&flash='.urlencode($flash));
    exit;
  } else {
    $msg='Community not found.';
  }
}

/* eliminar comunidad completa + fotos */
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
    if ($failed) { $flash .= ' (No se pudo borrar: '.implode(', ', $failed).')'; }
    header('Location: ?key='.urlencode(ADMIN_KEY).'&flash='.urlencode($flash));
    exit;
  } else {
    $msg='Community not found.';
  }
}

/******************** DATA + FILTER ********************/
$data = read_json(GATES_JSON);
$q = trim($_GET['q'] ?? '');
$edit = trim($_GET['edit'] ?? '');
$msg = $_GET['flash'] ?? $msg;

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
<title>Admin · Gate Code</title>
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

    /* Paleta botones */
    --btn-green-1:#2FD874;
    --btn-green-2:#12B767;
    --btn-red-1:#FF5A5F;
    --btn-red-2:#E23D3D;
    --btn-gray-1:#2B3440;
    --btn-gray-2:#1F2630;
    --btn-gray-border:#394556;
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

    /* Paleta botones light mode */
    --btn-green-1:#2FD874;
    --btn-green-2:#12B767;
    --btn-red-1:#FF5A5F;
    --btn-red-2:#E23D3D;
    --btn-gray-1:#e8eef4;
    --btn-gray-2:#d8dfe6;
    --btn-gray-border:#c1ccd7;
  }

  html,body{
    height:100%;
    margin:0;
    font-family:system-ui,Segoe UI,Roboto,Arial;
    color:var(--text);
    background:transparent;
    transition: background 0.3s ease, color 0.3s ease;
  }
  body::before{
    content:"";
    position:fixed;
    inset:0;
    z-index:-1;
    background:
      radial-gradient(1000px 500px at 80% -10%, var(--gradient-1) 0%, transparent 60%),
      radial-gradient(900px 400px at -10% 90%, var(--gradient-2) 0%, transparent 55%),
      var(--bg);
    background-repeat:no-repeat;
    transition: background 0.3s ease;
  }

  header, footer{padding:16px; text-align:center}
  
  footer {
    margin-top: 40px;
    padding: 20px 16px;
    background: var(--footer-bg);
    border-top: 1px solid var(--line);
    backdrop-filter: blur(10px);
  }

  footer .footer-content {
    max-width: 1100px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 14px;
    color: var(--muted);
  }

  footer .footer-heart {
    color: #ff5c5c;
    animation: heartbeat 1.5s ease-in-out infinite;
    display: inline-block;
  }

  footer .footer-by {
    font-weight: 600;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }

  footer a {
    color: var(--brand);
    text-decoration: none;
    font-weight: 600;
    transition: color .15s ease;
  }

  footer a:hover {
    color: var(--brand-2);
    text-decoration: underline;
  }
  
  @keyframes heartbeat {
    0%, 100% { transform: scale(1); }
    25% { transform: scale(1.1); }
    50% { transform: scale(1); }
  }
  
  .sub{color:var(--muted);margin-top:6px}

  /* Title style del front page */
  .title {
    font-size: 3rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: 2px; margin: 0;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
    position: relative; display: inline-block; text-shadow: 0 2px 6px rgba(0,0,0,.3);
    cursor: pointer;
    text-decoration: none;
  }
  .title:hover {
    opacity: 0.9;
  }
  .title::after {
    content:""; position: absolute; left: 0; bottom: -6px; width: 100%; height: 3px;
    background: linear-gradient(90deg, #3bdd82, #1bbf67);
    border-radius: 2px; transform: scaleX(0); transform-origin: left;
    transition: transform 0.6s ease-in-out;
  }
  .title:hover::after {
    transform: scaleX(1);
  }

  .json-actions {
    max-width:1100px;
    margin:20px auto 20px;
    padding:0 12px;
    display:flex;
    gap:12px;
    justify-content:center;
    flex-wrap:wrap;
  }

  .btn,
  .btn:link,
  .btn:visited{
    display:inline-flex; align-items:center; justify-content:center;
    height:46px; padding:0 18px; min-width:120px;
    border-radius:12px; border:1px solid var(--btn-gray-border);
    background:linear-gradient(180deg,var(--btn-gray-1),var(--btn-gray-2));
    color:var(--text); font-size:16px; font-weight:700; letter-spacing:.2px;
    text-decoration:none; cursor:pointer; text-align:center; line-height:1; box-sizing:border-box;
    transition:background .15s ease, color .15s ease;
  }
  .btn:hover,.btn:focus-visible{ background:linear-gradient(180deg,#313C4B,#222A36); outline:none; }
  [data-theme="light"] .btn:hover,[data-theme="light"] .btn:focus-visible{ background:linear-gradient(180deg,#d1dce5,#c1ccd7); }
  .btn.primary{ border:0; background:linear-gradient(135deg,var(--btn-green-1),var(--btn-green-2)); color:#fff; }
  .btn.primary:hover, .btn.primary:focus-visible{ background:linear-gradient(135deg,#12B767,#0e9a52); }
  .btn.danger{ border:0; background:linear-gradient(135deg,var(--btn-red-1),var(--btn-red-2)); color:#fff; }
  .btn.neutral{ background:linear-gradient(180deg,var(--btn-gray-1),var(--btn-gray-2)); color:var(--text); border:1px solid var(--btn-gray-border); }

  .upload-json-form {
    display:flex;
    gap:8px;
    align-items:center;
  }
  
  .upload-json-form input[type="file"] {
    display:none;
  }

  .wrap{
    max-width:1100px; margin:0 auto 28px; padding:0 12px;
    display:grid; grid-template-columns: 7fr 3fr; gap:16px; align-items:start;
  }

  .wrap.edit-mode {
    grid-template-columns: 1fr;
  }

  @media (max-width:900px){
    .wrap{ grid-template-columns:1fr; gap: 24px; }
    .title { font-size: 2.2rem; }
    .card:first-child { order: 2; }
    .card:last-child { order: 1; }
  }

  .card{
    background:linear-gradient(180deg,var(--panel),var(--panel-2));
    border:1px solid var(--line);
    border-radius:var(--radius);
    padding:14px;
    display: flex;
    flex-direction: column;
    /*height: 100%;*/
    height: 100%;
    max-height: 700px;
  }

  .wrap.edit-mode .card {
    max-height: 800px;
  }

  .wrap.edit-mode .card.add-community-card {
    display: none;
  }
  
  .card-header {
    flex-shrink: 0;
  }
  
  .card h2{margin:0 0 12px 0}
  .muted{color:var(--muted)}

  /* Search form inside Communities */
  .search-inline {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
  }
  .search-inline .field {
    flex: 1;
  }
  .search-inline .btn {
    min-width: 100px;
  }
  
  /* Scroll container for communities list */
  .communities-scroll{
    flex: 1;
    overflow-y:auto;
    overflow-x:hidden;
    padding-right:8px;
    min-height: 0;
  }
  
  /* Custom Scrollbar for communities */
  .communities-scroll::-webkit-scrollbar {
    width: 8px;
  }
  .communities-scroll::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }
  .communities-scroll::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }
  .communities-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }
  
  .grid{display:grid;gap:12px}
  .comm{background:var(--panel-2);border:1px solid var(--line);border-radius:12px;padding:12px}
  .comm-head{display:flex;justify-content:space-between;align-items:center;gap:12px}
  .comm-name{
    font-weight:800;
    font-size: 20px;
    line-height: 1.2;
    color:var(--text);
  }
  .comm-actions{display:flex;gap:8px;flex-wrap:wrap}
  .codes{display:grid;gap:8px;margin-top:10px}
  .code-row{display:flex;gap:12px;align-items:flex-start;background:var(--input-bg-1);border:1px solid var(--line);border-radius:12px;padding:10px}
  .c-left{flex:1;display:flex;flex-direction:column;align-items:flex-start;text-align:left}
  .code{font-family:ui-monospace,Menlo,Consolas,monospace; display:flex; align-items:center; gap:8px; font-size:17px; color:var(--text);}
  .note{color:var(--muted);font-size:13px}

  .report-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:20px;
    height:20px;
    min-width:20px;
    min-height:20px;
    max-width:20px;
    max-height:20px;
    background:#ff3b3b;
    color:#fff;
    font-size:11px;
    font-weight:600;
    border-radius:50%;
    font-family:system-ui,Segoe UI,Roboto,Arial;
    flex-shrink:0;
    line-height:20px;
    box-sizing:border-box;
    text-align:center;
  }
  .thumb{ width:80px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:#000; cursor: zoom-in; }

  .form{display:grid;gap:16px; overflow:visible; min-height: 0;}
  
  /* Contenedor con scroll para codes-editor */
  .codes-scroll-container {
    max-height: 350px;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 8px;
    margin-bottom: 12px;
  }
  
  /* Custom Scrollbar for codes editor */
  .codes-scroll-container::-webkit-scrollbar {
    width: 8px;
  }
  .codes-scroll-container::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }
  .codes-scroll-container::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }
  .codes-scroll-container::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }

  /* Custom Scrollbar for Add Community form - mantener solo para otros elementos */
  .form::-webkit-scrollbar {
    width: 8px;
  }
  .form::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
    border-radius: 10px;
  }
  .form::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 10px;
  }
  .form::-webkit-scrollbar-thumb:hover {
    background: var(--scrollbar-thumb-hover);
  }
  
  .field{
    width:100%; box-sizing:border-box; padding:12px 14px; border-radius:12px; border:1px solid var(--border);
    background:linear-gradient(180deg,var(--input-bg-1),var(--input-bg-2)); color:var(--text);
    outline:none; transition:border-color .15s ease, box-shadow .15s ease;
  }
  .field::placeholder{ color:var(--muted) }
  .field:focus{ border-color:var(--brand); box-shadow:0 0 0 3px rgba(59,221,130,.15) }
  textarea.field{ resize:vertical; min-height:90px }

  .lbl > span{ display:block; margin-bottom:6px; color:var(--text); font-weight:600 }
  .lbl { margin-bottom: 0; }
  .codes-editor{display:grid;gap:10px}

  .code-edit-new{
    display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) auto;
    gap:10px; align-items:start; background:var(--panel-2); border:1px solid var(--line);
    border-radius:12px; padding:12px; box-sizing:border-box;
  }
  .code-edit-new .row-wide { grid-column: 1 / -1; }
  .code-edit-new .col-img{display:flex;align-items:center;gap:10px;justify-content:flex-end}

  .preview-box{ margin-top:10px; display:none; }
  .preview-box.show{ display:block; }
  .preview-box .mini-thumb{
    width:100%; aspect-ratio: 16 / 9; display:block; object-fit:cover;
    border:1px solid var(--line); border-radius:12px; background:#000;
  }

  .modal{ position:fixed; inset:0; background:rgba(0,0,0,.7); display:none; align-items:center; justify-content:center; z-index:9999; padding:20px; }
  .modal.open{ display:flex; }
  .modal img{ max-width:min(90vw,1000px); max-height:80vh; object-fit:contain; border-radius:12px; border:1px solid var(--border); background:#000; box-shadow:0 10px 40px rgba(0,0,0,.6); }
  .modal .close{ position:absolute; top:14px; right:18px; font-size:28px; line-height:1; color:var(--text); cursor:pointer; user-select:none; background:transparent; border:none; padding:6px 10px; }

  .mini{font-size:13px;color:var(--text);font-weight:600;margin-bottom:6px;display:block}
  .hr{height:1px;background:var(--line);margin:10px 0}
  .flash{margin:10px auto;max-width:1100px;background:var(--panel-2);border:1px solid var(--line);padding:10px;border-radius:10px;text-align:center;color:var(--text)}

  .actions-stack{
    width:100%; margin:0 auto; display:flex; flex-direction:column; gap:10px;
  }
  .actions-stack .btn{ width:100%; }

  /* Action buttons - Frontend style */
  .btn-primary, .btn-danger {
    width: 100%;
    padding: 14px 20px;
    border-radius: 12px;
    border: 0;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(59,221,130,.4);
    transition: transform .1s ease, box-shadow .2s ease;
    color: #07140c;
  }
  .btn-primary {
    background: linear-gradient(135deg, #2FD874, #12B767);
    color: #fff;
  }
  .btn-primary:hover {
    background: linear-gradient(135deg, #12B767, #0e9a52);
    box-shadow: 0 6px 18px rgba(59,221,130,.55);
  }
  .btn-danger {
    background: linear-gradient(135deg, #FF5A5F, #E23D3D);
    color: #fff;
    box-shadow: 0 4px 14px rgba(255,92,92,.4);
  }
  .btn-danger:hover {
    background: linear-gradient(135deg, #E23D3D, #c73030);
    box-shadow: 0 6px 18px rgba(255,92,92,.55);
  }
  .btn-primary:active, .btn-danger:active {
    transform: translateY(1px);
  }
  /* Oculto por defecto */
  .br-mobile { display: none; }

  /* Solo en responsive (ajusta el breakpoint) */
  @media (max-width: 768px) {
    .br-mobile { display: inline; }
  }

  /* Theme Toggle Button */
  .theme-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 50%;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,.2);
    z-index: 100;
  }
  .theme-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(59,221,130,.3);
  }
  .theme-toggle svg {
    width: 24px;
    height: 24px;
    fill: var(--brand);
    transition: transform 0.3s ease;
  }
  .theme-toggle:hover svg {
    transform: rotate(20deg);
  }

  @media (max-width: 768px) {
    .theme-toggle {
      width: 44px;
      height: 44px;
      top: 15px;
      right: 15px;
    }
    .theme-toggle svg {
      width: 20px;
      height: 20px;
    }
  }
</style>
</head>
<body>
  <!-- Theme Toggle Button -->
  <button id="themeToggle" class="theme-toggle" aria-label="Toggle theme">
    <svg id="moonIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
    </svg>
    <svg id="sunIcon" style="display:none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="5"/>
      <path d="M12 1L13 5L11 5Z"/>
      <path d="M12 23L13 19L11 19Z"/>
      <path d="M23 12L19 13L19 11Z"/>
      <path d="M1 12L5 13L5 11Z"/>
      <path d="M19.07 4.93L16 7.5L15 6.5Z"/>
      <path d="M4.93 19.07L8 16.5L9 17.5Z"/>
      <path d="M19.07 19.07L16.5 16L17.5 15Z"/>
      <path d="M4.93 4.93L7.5 8L6.5 9Z"/>
    </svg>
  </button>

<header>
  <a href="?key=<?=urlencode(ADMIN_KEY)?>" class="title">Gate Codes</a>
  <div class="sub">Admin Dashboard · Edit <code>gates.json</code></div>
  <?php if($msg): ?><div class="flash"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
</header>

<!-- JSON Upload/Download Actions -->
<div class="json-actions">
  <a class="btn" href="?key=<?=urlencode(ADMIN_KEY)?>&download_json=1" download>
    📥 Download JSON
  </a>
  
  <form class="upload-json-form" method="post" enctype="multipart/form-data" id="uploadJsonForm">
    <input type="hidden" name="key" value="<?=htmlspecialchars(ADMIN_KEY)?>">
    <input type="hidden" name="action" value="upload_json">
    <input type="file" name="json_file" id="jsonFileInput" accept=".json,application/json" required>
    <label for="jsonFileInput" class="btn" style="cursor:pointer;margin:0;">
      📤 Upload JSON
    </label>
  </form>
</div>

<div class="wrap<?= $edit ? ' edit-mode' : '' ?>">
  <!-- LIST / EDIT  (Communities) -->
  <section class="card">
    <div class="card-header">
      <h2><?= $edit ? 'Edit community' : 'Communities' ?></h2>
      
      <?php if(!$edit): ?>
        <!-- Search form inline -->
        <form class="search-inline" method="get">
          <input type="hidden" name="key" value="<?=htmlspecialchars(ADMIN_KEY)?>">
          <input class="field" type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search by community or code">
          <button class="btn" type="submit">Search</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if($edit):
      $i = find_comm_index($data,$edit);
      if($i<0): ?>
        <p class="muted">Community not found.</p>
      <?php else:
        $c = $data[$i]; ?>
        <form class="form" method="post" id="editForm">
          <input type="hidden" name="key" value="<?=htmlspecialchars(ADMIN_KEY)?>">
          <input type="hidden" name="original" value="<?=htmlspecialchars($c['community'])?>">
          <input type="hidden" name="action" id="editAction" value="update">

          <label class="lbl"><span>Community</span>
            <input class="field" name="community" value="<?=htmlspecialchars($c['community'])?>" required>
          </label>

          <label class="lbl"><span>Codes (add/remove as needed)</span>
            <div class="codes-scroll-container">
              <div id="codesEditor" class="codes-editor">
            <?php foreach(($c['codes']??[]) as $idx=>$row):
              $photo = web_photo($row['photo'] ?? '');
            ?>
              <div class="code-edit-new">
                <input class="field" name="codes[<?= $idx ?>][code]" placeholder="e.g., #54839*"
                       value="<?=htmlspecialchars($row['code']??'')?>"
                       required maxlength="8" pattern="[A-Za-z0-9#*]{1,8}"
                       title="Up to 8 characters: letters, numbers, # or *">

                <input class="field" name="codes[<?= $idx ?>][notes]"
                       placeholder="Entrance type"
                       value="<?=htmlspecialchars($row['notes']??'')?>">

                <div class="col-img">
                  <!-- Botón JS para borrar este code -->
                  <button class="btn danger js-del-code" type="button"
                          data-code="<?=htmlspecialchars($row['code']??'')?>"
                          data-community="<?=htmlspecialchars($c['community'])?>">
                    Delete Code
                  </button>
                </div>

                <textarea class="field row-wide" name="codes[<?= $idx ?>][details]" placeholder="Details"><?=htmlspecialchars($row['details']??'')?></textarea>

                <div class="row-wide">
                  <label class="lbl"><span>Location photo (JPG/PNG/WebP/HEIC)</span>
                    <input class="field file-row" type="file" accept="image/*">
                  </label>
                  <div class="preview-box show">
                    <img class="mini-thumb" src="<?= htmlspecialchars($photo) ?>" alt="preview">
                    <input type="hidden" name="codes[<?= $idx ?>][photo]" value="<?=htmlspecialchars($photo)?>">
                  </div>
                  <div class="mini up-note"></div>
                </div>
              </div>
            <?php endforeach; ?>
              </div>
            </div>

            <div><button class="btn" type="button" onclick="addRowEdit()">+ Add code</button></div>
          </label>
          <div class="hr"></div>

          <div class="actions-stack">
            <button class="btn-primary" type="submit" id="btnSave">Save Changes</button>
            <button class="btn-danger" type="submit" id="btnDelete">Delete Community</button>
          </div>
        </form>
      <?php endif; ?>

    <?php else: ?>
      <div class="communities-scroll">
        <div class="grid">
          <?php if(empty($filtered)): ?>
            <p class="muted">No communities found.</p>
          <?php else: foreach($filtered as $row): ?>
            <div class="comm">
              <div class="comm-head">
                <div class="comm-name"><?= htmlspecialchars($row['community']) ?></div>
                <div class="comm-actions">
                  <a class="btn" href="?key=<?=urlencode(ADMIN_KEY)?>&edit=<?=urlencode($row['community'])?>">Modify</a>
                </div>
              </div>
              <div class="codes">
                <?php foreach(($row['codes']??[]) as $code):
                  $p = web_photo($code['photo'] ?? '');
                  ?>
                  <div class="code-row">
                    <img class="thumb js-open-modal" src="<?= htmlspecialchars($p) ?>" alt="" data-full="<?= htmlspecialchars($p) ?>">
                    <div class="c-left">
                      <div class="code">
                        <span><?= htmlspecialchars($code['code'] ?? '') ?></span>
                        <?php if(isset($code['report_count']) && $code['report_count'] > 0): ?>
                          <span class="report-badge" title="<?= $code['report_count'] ?> report<?= $code['report_count'] > 1 ? 's' : '' ?>"><?= $code['report_count'] ?></span>
                        <?php endif; ?>
                      </div>
                      <?php if(!empty($code['notes'])): ?><div class="note"><?= htmlspecialchars($code['notes']) ?></div><?php endif; ?>
                      <?php if(!empty($code['details'])): ?><div class="note"><?= htmlspecialchars($code['details']) ?></div><?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>

  <!-- ADD NEW -->
  <section class="card add-community-card">
    <div class="card-header">
      <h2>Add community</h2>
    </div>

    <form class="form" method="post" id="addForm">
      <input type="hidden" name="key" value="<?=htmlspecialchars(ADMIN_KEY)?>">
      <input type="hidden" name="action" value="add">
      <label class="lbl"><span>Community Name</span>
        <input class="field" name="community" placeholder="e.g., Water Oaks" required>
      </label>

      <label class="lbl"><span>Add Codes (add as many as you need)</span>
        <div class="codes-scroll-container">
          <div id="codesNew" class="codes-editor"></div>
        </div>
        <div><button class="btn" type="button" id="btnAddRow">+ Add code</button></div>
      </label>

      <button class="btn primary" id="btnAddCommunity" type="button">Add New Entry</button>
    </form>
  </section>
  <br class="br-mobile" />
</div>

<!-- ===== Image Modal ===== -->
<div id="imgModal" class="modal" aria-hidden="true">
  <button class="close" type="button" aria-label="Close">&times;</button>
  <img id="imgModalPic" src="" alt="photo">
</div>

<footer>
  <div class="footer-content">
    <span>© <?=date('Y')?> Built by <a href="mailto:blancuniverse@gmail.com" class="footer-by">Alejandro</a></span>
  </div>
</footer>
<script>
const DEFAULT_THUMB_URL = "<?=htmlspecialchars(DEFAULT_THUMB_URL)?>";

/* Handle JSON file upload */
document.getElementById('jsonFileInput')?.addEventListener('change', function(e) {
  if (this.files.length > 0) {
    const fileName = this.files[0].name;
    if (!fileName.endsWith('.json')) {
      alert('Please select a valid JSON file.');
      this.value = '';
      return;
    }
    
    if (confirm(`Upload "${fileName}" and replace current gates.json?\n\nA backup will be created automatically.`)) {
      document.getElementById('uploadJsonForm').submit();
    } else {
      this.value = '';
    }
  }
});

/* Utilidad para crear HTML */
function el(html){ const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstElementChild; }

/* Plantilla de fila NUEVA */
function rowTemplate(prefix, idx){
  return `
  <div class="code-edit-new" data-row="${idx}">
    <input class="field" name="${prefix}[${idx}][code]" placeholder="e.g., #54839*"
           required maxlength="8" pattern="[A-Za-z0-9#*]{1,8}"
           title="Up to 8 characters: letters, numbers, # or *">

    <input class="field" name="${prefix}[${idx}][notes]"
           placeholder="Entrance type">

    <textarea class="field row-wide" name="${prefix}[${idx}][details]" placeholder="Details"></textarea>

    <div class="row-wide">
      <label class="lbl"><span>Location photo (JPG/PNG/WebP/HEIC)</span>
        <input class="field file-row" type="file" accept="image/*">
      </label>
      <div class="preview-box">
        <img class="mini-thumb" src="" alt="preview">
        <input type="hidden" name="${prefix}[${idx}][photo]" value="">
      </div>
      <div class="mini up-note"></div>
    </div>

    <div class="row-wide">
      <button class="btn danger btn-remove-code" type="button" style="width:100%;">Remove</button>
    </div>
  </div>`;
}

/* Actualizar visibilidad de botones Remove */
function updateRemoveButtons(container) {
  if (!container) return;
  const removeButtons = container.querySelectorAll('.btn-remove-code');
  
  // Si solo hay 1 fila con botón remove (nueva), ocultar el botón Remove
  if (removeButtons.length === 1) {
    removeButtons.forEach(btn => btn.style.display = 'none');
  } else if (removeButtons.length > 1) {
    // Si hay más de 1 fila nueva, mostrar todos los botones Remove
    removeButtons.forEach(btn => btn.style.display = 'block');
  }
}

/* Guardar archivo seleccionado por fila para subirlo justo antes del submit */
const rowFiles = new WeakMap();

function wireRow(row){
  const file = row.querySelector('.file-row');
  const note = row.querySelector('.up-note');
  const pbox = row.querySelector('.preview-box');
  const img  = row.querySelector('.preview-box .mini-thumb');
  if(!file) return;

  file.addEventListener('change', (e)=>{
    const f = e.target.files[0];
    if(!f){
      rowFiles.delete(row);
      if (note) note.textContent = '';
      if (pbox) pbox.classList.remove('show');
      if (img)  img.src = '';
      return;
    }
    rowFiles.set(row, f);
    if (pbox) pbox.classList.add('show');
    if (img)  img.src = URL.createObjectURL(f);
    if (note) note.textContent = 'Ready to upload on submit.';
  });
}

function attachPhotoToRow(row, url){
  const hidden = row.querySelector('.preview-box input[type="hidden"]');
  const img    = row.querySelector('.preview-box .mini-thumb');
  const pbox   = row.querySelector('.preview-box');
  if (hidden) hidden.value = url || '';
  if (img && url) img.src = url;
  if (pbox) pbox.classList.add('show');
}

  /* Subir en lote lo pendiente dentro de un contenedor (Add o Edit) */
async function uploadQueuedRows(container){
  if (!container) return;
  const rows = container.querySelectorAll('.code-edit-new');
  const uploads = [];
  rows.forEach((row)=>{
    const f = rowFiles.get(row);
    if (!f) return;
    const note = row.querySelector('.up-note');
    if (note) note.textContent = 'Uploading photo...';

    const fd = new FormData();
    fd.append('key','<?=htmlspecialchars(ADMIN_KEY)?>');
    fd.append('photo', f);

    const p = fetch('?ajax=upload&key=<?=htmlspecialchars(ADMIN_KEY)?>', { method:'POST', body: fd })
      .then(async r=>{
        const text = await r.text();
        let data;
        try { data = JSON.parse(text); } catch { data = {status:'fail', error: 'bad_json', raw:text}; }

        if (!r.ok) {
          if (note) note.textContent = `HTTP ${r.status}${data.error ? ` · ${data.error}` : ''}`;
          return;
        }

        if (data.status==='ok' && data.url) {
          attachPhotoToRow(row, data.url);
          rowFiles.delete(row);
          if (note) note.textContent = 'Photo uploaded.';
        } else {
          const msg = data.error || 'Upload failed';
          if (note) note.textContent = msg;
        }
      })
      .catch(()=> { if (note) note.textContent='Network error.'; });

    uploads.push(p);
  });
  if (uploads.length) await Promise.all(uploads);

  // Asegurar que todas las filas tengan una foto (por defecto o subida)
  rows.forEach((row)=>{
    const hidden = row.querySelector('.preview-box input[type="hidden"]');
    const img = row.querySelector('.preview-box .mini-thumb');
    const pbox = row.querySelector('.preview-box');
    
    // Si no tiene foto asignada, usar la imagen por defecto
    if (hidden && !hidden.value) {
      hidden.value = DEFAULT_THUMB_URL;
      if (img) img.src = DEFAULT_THUMB_URL;
      if (pbox) pbox.classList.add('show');
    }
  });
}

/* Crear filas en "Add" */
let idxNew  = 0;
const codesNewBox = document.getElementById('codesNew');
function addRowNew(){
  const row = el(rowTemplate('codes', idxNew++));
  codesNewBox.appendChild(row);
  wireRow(row);
  
  // Agregar event listener al botón Remove
  const removeBtn = row.querySelector('.btn-remove-code');
  if (removeBtn) {
    removeBtn.addEventListener('click', function() {
      this.closest('.code-edit-new').remove();
      updateRemoveButtons(codesNewBox);
    });
  }
  
  updateRemoveButtons(codesNewBox);
}
if (codesNewBox){ addRowNew(); }
const addRowBtn = document.getElementById('btnAddRow');
if (addRowBtn) addRowBtn.addEventListener('click', ()=> addRowNew());

/* Edit: añadir nuevas filas (client-side) */
function addRowEdit(){
  const box = document.getElementById('codesEditor');
  if (!box) return;
  const next = box.querySelectorAll('.code-edit-new').length;
  const row = el(rowTemplate('codes', next));
  box.appendChild(row);
  wireRow(row);
  
  // Agregar event listener al botón Remove
  const removeBtn = row.querySelector('.btn-remove-code');
  if (removeBtn) {
    removeBtn.addEventListener('click', function() {
      this.closest('.code-edit-new').remove();
      updateRemoveButtons(box);
    });
  }
  
  updateRemoveButtons(box);
}
/* Wire inicial para filas existentes */
document.querySelectorAll('#codesEditor .code-edit-new').forEach(wireRow);

/* Actualizar botones Remove en la carga inicial (Edit mode) */
const codesEditorBox = document.getElementById('codesEditor');
if (codesEditorBox) {
  updateRemoveButtons(codesEditorBox);
}

/* Submit "Add New Entry" - FIXED */
const addBtn = document.getElementById('btnAddCommunity');
if (addBtn){
  addBtn.addEventListener('click', async ()=>{
    const codeInputs = document.querySelectorAll('#codesNew input[name*="[code]"]');
    
    // Verificar que haya al menos una fila
    if (codeInputs.length === 0) {
      alert('Add at least one code.');
      return;
    }
    
    // Validar que al menos UN código esté completo
    let hasValidCode = false;
    for (const inp of codeInputs) {
      const v = inp.value.trim();
      if (v.length > 0) {
        if (v.length > 8) {
          alert('Code must be 8 characters or less.');
          inp.focus();
          return;
        }
        hasValidCode = true;
      }
    }
    
    if (!hasValidCode) {
      alert('Add at least one code.');
      codeInputs[0]?.focus();
      return;
    }
    
    await uploadQueuedRows(document.getElementById('codesNew'));
    HTMLFormElement.prototype.submit.call(document.getElementById('addForm'));
  });
}

/* Delete Community / Save changes control */
const editForm = document.getElementById('editForm');
if (editForm){
  const editAction = document.getElementById('editAction');
  const btnSave = document.getElementById('btnSave');
  const btnDelete = document.getElementById('btnDelete');

  if (btnSave){
    btnSave.addEventListener('click', ()=>{ editAction.value = 'update'; });
  }
  if (btnDelete){
    btnDelete.addEventListener('click', (ev)=>{
      editAction.value = 'delete_comm';
      if (!confirm('Are you sure you want to delete this community?')){
        ev.preventDefault();
        editAction.value = 'update';
      }
    });
  }

  let editSubmitting = false;
  editForm.addEventListener('submit', async (e)=>{
    if (editSubmitting) return;
    e.preventDefault();
    if (editAction.value !== 'delete_comm'){
      await uploadQueuedRows(document.getElementById('codesEditor'));
    }
    editSubmitting = true;
    HTMLFormElement.prototype.submit.call(editForm);
  });
}

/* Botón "Delete code": crea y envía un form propio */
document.addEventListener('click', (e)=>{
  const t = e.target;
  if (t && t.classList && t.classList.contains('js-del-code')){
    const code = t.getAttribute('data-code') || '';
    const comm = t.getAttribute('data-community') || (document.querySelector('#editForm input[name="community"]')?.value || '');
    if (!code || !comm) return;
    if (!confirm('Delete this code?')) return;

    const f = document.createElement('form');
    f.method = 'post';
    f.innerHTML = `
      <input type="hidden" name="key" value="<?=htmlspecialchars(ADMIN_KEY)?>">
      <input type="hidden" name="action" value="delete_code">
      <input type="hidden" name="community" value="${comm.replace(/"/g,'&quot;')}">
      <input type="hidden" name="code" value="${code.replace(/"/g,'&quot;')}">
    `;
    document.body.appendChild(f);
    f.submit();
  }
});

/* Modal de imagen */
const modal = document.getElementById('imgModal');
const modalImg = document.getElementById('imgModalPic');
const modalClose = modal ? modal.querySelector('.close') : null;

function openModal(src){
  if (!modal) return;
  modalImg.src = src || '<?=htmlspecialchars(DEFAULT_THUMB_URL)?>';
  modal.classList.add('open');
  modal.setAttribute('aria-hidden','false');
}
function closeModal(){
  if (!modal) return;
  modal.classList.remove('open');
  modal.setAttribute('aria-hidden','true');
  modalImg.src = '';
}
document.addEventListener('click', (e)=>{
  const t = e.target;
  if (t.classList && t.classList.contains('js-open-modal')) {
    const full = t.getAttribute('data-full') || t.src;
    openModal(full);
  }
  if (t === modal || t === modalClose) {
    closeModal();
  }
});
document.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape' && modal && modal.classList.contains('open')) closeModal();
});

// Theme Toggle Functionality
const themeToggle = document.getElementById('themeToggle');
const moonIcon = document.getElementById('moonIcon');
const sunIcon = document.getElementById('sunIcon');
const htmlElement = document.documentElement;

// Load theme from localStorage or default to dark
const savedTheme = localStorage.getItem('theme') || 'dark';
if (savedTheme === 'light') {
  htmlElement.setAttribute('data-theme', 'light');
  moonIcon.style.display = 'block';
  sunIcon.style.display = 'none';
} else {
  moonIcon.style.display = 'none';
  sunIcon.style.display = 'block';
}

// Toggle theme
themeToggle.addEventListener('click', () => {
  const currentTheme = htmlElement.getAttribute('data-theme');
  const newTheme = currentTheme === 'light' ? 'dark' : 'light';

  htmlElement.setAttribute('data-theme', newTheme);
  localStorage.setItem('theme', newTheme);

  if (newTheme === 'light') {
    moonIcon.style.display = 'block';
    sunIcon.style.display = 'none';
  } else {
    moonIcon.style.display = 'none';
    sunIcon.style.display = 'block';
  }
});
</script>
</body>
</html>