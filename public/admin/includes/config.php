<?php
/******************** CONFIG ********************/
const ADMIN_KEY   = '43982';
const APP_VERSION = '1.1.5';
const GATES_JSON  = __DIR__ . '/../../data/gates.json';
const SUGGEST_JSON = __DIR__ . '/../../data/suggest.json';
const PIN_JSON = __DIR__ . '/../../data/pin.json';
const ASSETS_DIR  = __DIR__ . '/../../assets/';
const TEMP_ASSETS_DIR = __DIR__ . '/../../temp_assets/';
const DEFAULT_THUMB_FILE = 'thumbnailnone.png';

$APP_URL = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
define('ASSETS_URL', $APP_URL . '/assets/');
define('TEMP_ASSETS_URL', $APP_URL . '/temp_assets/');
define('ASSETS_RELATIVE', 'assets/');
define('DEFAULT_THUMB_URL', ASSETS_RELATIVE . DEFAULT_THUMB_FILE);

/* Minimal auth */
function require_key(){
  $k = $_GET['key'] ?? $_POST['key'] ?? '';
  if ($k !== ADMIN_KEY) { http_response_code(403); exit('Forbidden'); }
}

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

function web_photo($p){
  $p = trim((string)$p);
  if ($p === '') return DEFAULT_THUMB_URL;
  if (preg_match('#^(https?:)?//#', $p)) return $p;
  if ($p[0] === '/') return $p;
  $p = ltrim($p, './');
  if (stripos($p, 'assets/') === 0) $p = substr($p, 7);
  if (stripos($p, '../assets/') === 0) $p = substr($p, 10);
  return ASSETS_URL . ltrim($p, '/');
}

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

/******************** COUNT SUGGESTIONS ********************/
$suggest_count = 0;
if(file_exists(SUGGEST_JSON)){
  $suggestions = read_json(SUGGEST_JSON);
  $suggest_count = count($suggestions);
}
