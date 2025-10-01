<?php
// === Config ===
$ADMIN_KEY   = 'mi-clave-segura-123';                    // la misma clave del dashboard
$GATECODE_ROOT = realpath(__DIR__ . '/../../gatecode');  // .../gatecode
if ($GATECODE_ROOT === false) {
  $GATECODE_ROOT = realpath(dirname(__DIR__));           // fallback
}
$ASSETS_DIR = $GATECODE_ROOT . '/assets/';               // ruta física
$ASSETS_URL = '/gatecode/assets/';                       // URL pública

// === Auth mínima ===
$key = $_POST['key'] ?? '';
if ($key !== $ADMIN_KEY) { http_response_code(403); exit('Forbidden'); }

// === Helpers ===
function respond_and_exit($msg, $extra = []) {
  header('Content-Type: application/json');
  echo json_encode(['status' => $msg] + $extra);
  exit;
}

// === Verificaciones previas ===
if (!isset($_FILES['photos'])) {
  respond_and_exit('no_files');
}

// Crear carpeta si no existe
if (!is_dir($ASSETS_DIR)) {
  @mkdir($ASSETS_DIR, 0775, true);
}

// Comprobar permisos de escritura
if (!is_writable($ASSETS_DIR)) {
  respond_and_exit('assets_not_writable', [
    'assets_dir' => $ASSETS_DIR,
    'suggestion' => 'En Windows: verifica permisos de la carpeta; en Linux/Mac: chmod 775 o 777 temporalmente'
  ]);
}

// Mostrar límites actuales (útil para depurar)
$limits = [
  'file_uploads'      => ini_get('file_uploads'),
  'upload_max_filesize' => ini_get('upload_max_filesize'),
  'post_max_size'       => ini_get('post_max_size'),
  'max_file_uploads'    => ini_get('max_file_uploads'),
  'upload_tmp_dir'      => ini_get('upload_tmp_dir'),
];

// === Procesar ===
$urls = [];
$errors = [];

foreach ($_FILES['photos']['error'] as $i => $err) {
  $name    = $_FILES['photos']['name'][$i] ?? '';
  $tmp     = $_FILES['photos']['tmp_name'][$i] ?? '';
  $size    = $_FILES['photos']['size'][$i] ?? 0;

  if ($err !== UPLOAD_ERR_OK) {
    $map = [
      UPLOAD_ERR_INI_SIZE   => 'El archivo excede upload_max_filesize',
      UPLOAD_ERR_FORM_SIZE  => 'El archivo excede MAX_FILE_SIZE del form',
      UPLOAD_ERR_PARTIAL    => 'Subida incompleta',
      UPLOAD_ERR_NO_FILE    => 'No se subió archivo',
      UPLOAD_ERR_NO_TMP_DIR => 'Falta upload_tmp_dir',
      UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir en disco',
      UPLOAD_ERR_EXTENSION  => 'Interrumpido por extensión de PHP'
    ];
    $errors[] = "Error con '$name': " . ($map[$err] ?? "err=$err");
    continue;
  }

  if (!is_uploaded_file($tmp)) {
    $errors[] = "No es un archivo subido válido: $name";
    continue;
  }

  // Tipo MIME (seguro pero flexible)
  $mime = @mime_content_type($tmp);
  $ext  = ($mime === 'image/png') ? 'png' : (($mime === 'image/jpeg') ? 'jpg' : '');
  if (!$ext) {
    $errors[] = "Tipo no permitido ($mime) en $name. Usa JPG o PNG.";
    continue;
  }

  // Nombre destino
  $destName = 'gate_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
  $destPath = $ASSETS_DIR . $destName;

  if (!move_uploaded_file($tmp, $destPath)) {
    $errors[] = "Fallo al mover a $destPath";
    continue;
  }

  $urls[] = $ASSETS_URL . $destName;
}

// Respuesta JSON para que el dashboard pueda mostrar resultado
respond_and_exit($urls ? 'ok' : 'fail', [
  'urls' => $urls,
  'errors' => $errors,
  'limits' => $limits
]);
