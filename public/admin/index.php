<?php
/******************** CONFIG ********************/
// rutas de disco (sin cambio)
const ADMIN_KEY   = '43982';
const GATES_JSON  = __DIR__ . '/../data/gates.json';
const ASSETS_DIR  = __DIR__ . '/../assets/';
const DEFAULT_THUMB_FILE = 'thumbnailnone.png';

// URL base absoluta para /gatecodes/assets/ (normaliza ../)
$APP_URL = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/'); // ej: "/gatecodes"
define('ASSETS_URL', $APP_URL . '/assets/');                     // ej: "/gatecodes/assets/"
define('DEFAULT_THUMB_URL', ASSETS_URL . DEFAULT_THUMB_FILE);    // ej: "/gatecodes/assets/thumbnailnone.png"


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
    echo json_encode(['status'=>'ok','url'=>ASSETS_URL.basename($dest)]);
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

  echo json_encode(['status'=>'ok','url'=>ASSETS_URL.basename($dest)]);
  exit;
}

/******************** ACTIONS (POST) ********************/
$action = $_POST['action'] ?? '';
$msg = '';

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
    $codes=[];
    foreach($rows as $r){
      $code=trim($r['code']??''); if($code==='') continue;
      $entry=['code'=>$code];
      foreach(['notes','details','photo'] as $k){
        $v=trim($r[$k]??'');
        if($k==='photo' && $v===''){ $v = DEFAULT_THUMB_URL; }
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
    --text:#e8eef4; --muted:#93a0ad; --line:#22303b; --radius:14px;

    /* Paleta botones */
    --btn-green-1:#2FD874;
    --btn-green-2:#12B767;
    --btn-red-1:#FF5A5F;
    --btn-red-2:#E23D3D;
    --btn-gray-1:#2B3440;
    --btn-gray-2:#1F2630;
    --btn-gray-border:#394556;
  }

  html,body{
    height:100%;
    margin:0;
    font-family:system-ui,Segoe UI,Roboto,Arial;
    color:var(--text);
    background:transparent;
  }
  body::before{
    content:"";
    position:fixed;
    inset:0;
    z-index:-1;
    background:
      radial-gradient(1000px 500px at 80% -10%, #1a2330 0%, transparent 60%),
      radial-gradient(900px 400px at -10% 90%, #11202a 0%, transparent 55%),
      var(--bg);
    background-repeat:no-repeat;
  }

  header, footer{padding:16px; text-align:center}
  h1{margin:0 0 6px 0}
  .sub{color:var(--muted)}

  .bar{max-width:1100px;margin:16px auto;display:flex;gap:8px;align-items:center;justify-content:center}

  .btn,
  .btn:link,
  .btn:visited{
    display:inline-flex; align-items:center; justify-content:center;
    height:46px; padding:0 18px; min-width:120px;
    border-radius:12px; border:1px solid var(--btn-gray-border);
    background:linear-gradient(180deg,var(--btn-gray-1),var(--btn-gray-2));
    color:#E6EDF3; font-size:16px; font-weight:700; letter-spacing:.2px;
    text-decoration:none; cursor:pointer; text-align:center; line-height:1; box-sizing:border-box;
  }
  .btn:hover,.btn:focus-visible{ background:linear-gradient(180deg,#313C4B,#222A36); outline:none; }
  .btn.primary{ border:0; background:linear-gradient(135deg,var(--btn-green-1),var(--btn-green-2)); color:#fff; }
  .btn.danger{ border:0; background:linear-gradient(135deg,var(--btn-red-1),var(--btn-red-2)); color:#fff; }
  .btn.neutral{ background:linear-gradient(180deg,var(--btn-gray-1),var(--btn-gray-2)); color:#E6EDF3; border:1px solid var(--btn-gray-border); }

  .bar .btn{ width:120px; }

  .wrap{
    max-width:1100px; margin:0 auto 28px; padding:0 12px;
    display:grid; grid-template-columns: 2fr 1fr; gap:16px; align-items:start;
  }
  .pane-list{ order:-1; }
  .pane-add  { order:0; }
  @media (max-width:1000px){
    .wrap{ grid-template-columns:1fr; }
    .pane-add  { order:-1; }
    .pane-list { order:0; }
  }

  .card{background:linear-gradient(180deg,var(--panel),var(--panel-2));border:1px solid var(--line);border-radius:var(--radius);padding:14px;overflow:hidden}
  .card h2{margin:0 0 10px 0}
  .muted{color:var(--muted)}
  .grid{display:grid;gap:12px}
  .comm{background:var(--panel-2);border:1px solid var(--line);border-radius:12px;padding:12px}
  .comm-head{display:flex;justify-content:space-between;align-items:center;gap:12px}
  .comm-name{
    font-weight:800;
    font-size: 20px;
    line-height: 1.2;
    }
  .comm-actions{display:flex;gap:8px;flex-wrap:wrap}
  .codes{display:grid;gap:8px;margin-top:10px}
  .code-row{display:flex;gap:12px;align-items:flex-start;background:#0f141a;border:1px solid var(--line);border-radius:12px;padding:10px}
  .c-left{flex:1;display:flex;flex-direction:column;align-items:flex-start;text-align:left}
  .code{font-family:ui-monospace,Menlo,Consolas,monospace}
  .note{color:#9fb0be;font-size:13px}
  .thumb{ width:80px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--line);background:#000; cursor: zoom-in; }

  .form{display:grid;gap:12px}
  .field{
    width:100%; box-sizing:border-box; padding:12px 14px; border-radius:12px; border:1px solid #2a3340;
    background:linear-gradient(180deg,#0f141a,#0c1116); color:#e8eef4;
    outline:none; transition:border-color .15s ease, box-shadow .15s ease;
  }
  .field::placeholder{ color:#8aa1b2 }
  .field:focus{ border-color:#3bdd82; box-shadow:0 0 0 3px rgba(59,221,130,.15) }
  textarea.field{ resize:vertical; min-height:90px }

  .lbl > span{ display:block; margin-bottom:6px; color:#e8eef4; font-weight:600 }
  .codes-editor{display:grid;gap:10px}

  .code-edit-new{
    display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr) auto;
    gap:10px; align-items:start; background:#0f141a; border:1px solid var(--line);
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
  .modal img{ max-width:min(90vw,1000px); max-height:80vh; object-fit:contain; border-radius:12px; border:1px solid #2a3340; background:#000; box-shadow:0 10px 40px rgba(0,0,0,.6); }
  .modal .close{ position:absolute; top:14px; right:18px; font-size:28px; line-height:1; color:#e8eef4; cursor:pointer; user-select:none; background:transparent; border:none; padding:6px 10px; }

  .mini{font-size:12px;color:#93a0ad}
  .hr{height:1px;background:var(--line);margin:10px 0}
  .flash{margin:10px auto;max-width:1100px;background:#0f141a;border:1px solid var(--line);padding:10px;border-radius:10px;text-align:center}

  .actions-stack{
    width:100%; margin:0 auto; display:flex; flex-direction:column; gap:10px;
  }
  .actions-stack .btn{ width:100%; }
</style>
</head>
<body>
<header>
  <h1>Admin Dashboard</h1>
  <div class="sub">Edit <code>gates.json</code> · Search · Add/Modify/Delete</div>
  <?php if($msg): ?><div class="flash"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
</header>

<!-- Search bar -->
<form class="bar" method="get">
  <input type="hidden" name="key" value="<?=htmlspecialchars(ADMIN_KEY)?>">
  <input class="field" type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Search by community or code">
  <button class="btn" type="submit">Search</button>
  <a class="btn" href="?key=<?=urlencode(ADMIN_KEY)?>">Reset</a>
</form>

<div class="wrap">
  <!-- LIST / EDIT  (Communities) -->
  <section class="card pane-list">
    <h2><?= $edit ? 'Edit community' : 'Communities' ?></h2>

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

          <div class="mini">Codes (add/remove as needed)</div>
          <div id="codesEditor" class="codes-editor">
            <?php foreach(($c['codes']??[]) as $idx=>$row):
              $photo = web_photo($row['photo'] ?? '');
            ?>
              <div class="code-edit-new">
                <input class="field" name="codes[<?= $idx ?>][code]" placeholder="e.g., #54839*"
                       value="<?=htmlspecialchars($row['code']??'')?>"
                       required maxlength="14" pattern="[A-Za-z0-9#*]{1,14}"
                       title="Up to 14 characters: letters, numbers, # or *">

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

          <div><button class="btn" type="button" onclick="addRowEdit()">+ Add code</button></div>
          <div class="hr"></div>

          <div class="actions-stack">
            <button class="btn primary" type="submit" id="btnSave">Save changes</button>
            <button class="btn danger" type="submit" id="btnDelete">Delete Community</button>
            <a class="btn neutral" href="?key=<?=urlencode(ADMIN_KEY)?>">Cancel</a>
          </div>
        </form>
      <?php endif; ?>

    <?php else: ?>
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
                    <div class="code"><?= htmlspecialchars($code['code'] ?? '') ?></div>
                    <?php if(!empty($code['notes'])): ?><div class="note"><?= htmlspecialchars($code['notes']) ?></div><?php endif; ?>
                    <?php if(!empty($code['details'])): ?><div class="note"><?= htmlspecialchars($code['details']) ?></div><?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- ADD NEW -->
  <section class="card pane-add">
    <h2>Add community</h2>

    <form class="form" method="post" id="addForm">
      <input type="hidden" name="key" value="<?=htmlspecialchars(ADMIN_KEY)?>">
      <input type="hidden" name="action" value="add">
      <label class="lbl"><span>Community Name</span>
        <input class="field" name="community" placeholder="e.g., Water Oaks" required>
      </label>

      <div class="mini">Codes (add as many as you need)</div>
      <div id="codesNew" class="codes-editor"></div>
      <div><button class="btn" type="button" id="btnAddRow">+ Add code</button></div>

      <button class="btn primary" id="btnAddCommunity" type="button">Add New Entry</button>
    </form>
  </section>
</div>

<!-- ===== Image Modal ===== -->
<div id="imgModal" class="modal" aria-hidden="true">
  <button class="close" type="button" aria-label="Close">&times;</button>
  <img id="imgModalPic" src="" alt="photo">
</div>

<footer>© <?=date('Y')?> Made by Alejandro</footer>
<script>
const DEFAULT_THUMB_URL = "<?=htmlspecialchars(DEFAULT_THUMB_URL)?>";

/* Utilidad para crear HTML */
function el(html){ const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstElementChild; }

/* Plantilla de fila NUEVA */
function rowTemplate(prefix, idx){
  return `
  <div class="code-edit-new" data-row="\${idx}">
    <input class="field" name="\${prefix}[\${idx}][code]"
           placeholder="e.g., #54839*"
           required maxlength="14" pattern="[A-Za-z0-9#*]{1,14}"
           title="Up to 14 characters: letters, numbers, # or *">

    <input class="field" name="\${prefix}[\${idx}][notes]"
           placeholder="Entrance type">

    <textarea class="field row-wide" name="\${prefix}[\${idx}][details]" placeholder="Details"></textarea>

    <div class="row-wide">
      <label class="lbl"><span>Location photo (JPG/PNG/WebP/HEIC)</span>
        <input class="field file-row" type="file" accept="image/*">
      </label>
      <div class="preview-box">
        <img class="mini-thumb" src="" alt="preview">
        <input type="hidden" name="\${prefix}[\${idx}][photo]" value="">
      </div>
      <div class="mini up-note"></div>
    </div>

    <div class="row-wide">
      <button class="btn danger" type="button" style="width:100%;"
              onclick="this.closest('.code-edit-new').remove()">Remove</button>
    </div>
  </div>`;
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

  rows.forEach((row)=>{
    const hidden = row.querySelector('.preview-box input[type="hidden"]');
    if (hidden && !hidden.value) hidden.value = DEFAULT_THUMB_URL;
  });
}

/* Crear filas en “Add” */
let idxNew  = 0;
const codesNewBox = document.getElementById('codesNew');
function addRowNew(){
  const row = el(rowTemplate('codes', idxNew++));
  codesNewBox.appendChild(row);
  wireRow(row);
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
}
/* Wire inicial para filas existentes */
document.querySelectorAll('#codesEditor .code-edit-new').forEach(wireRow);

/* Submit “Add New Entry” */
const addBtn = document.getElementById('btnAddCommunity');
if (addBtn){
  addBtn.addEventListener('click', async ()=>{
    for (const inp of document.querySelectorAll('#codesNew input[name*="[code]"]')) {
      const v = inp.value.trim();
      if (v.length === 0 || v.length > 14) { inp.focus(); return; }
    }
    await uploadQueuedRows(document.getElementById('codesNew'));
    document.getElementById('addForm').requestSubmit();
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
</script>
</body>
</html>
