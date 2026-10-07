<?php
// ═══════════════════════════════════════════════════════════════════════════
// FlotaMancha · Documentos (pólizas, recibos de seguros, multas y sus recibos)
// Recibe los PDF o fotos que se suben desde la app y los guarda en esta carpeta,
// en subcarpetas por tipo y año, con un nombre imposible de adivinar.
// © FlotaMancha · Desarrollada y diseñada por Rubén Díaz. Todos los derechos reservados.
// ═══════════════════════════════════════════════════════════════════════════

$CLAVE    = 'LVLcjgwfJ5ExVEFBGnLjLOUMK2LtkEDo';     // la misma que deca/subir.php y la app
$ORIGENES = ['https://rubenmanchatrans-design.github.io'];
$MAX      = 20 * 1024 * 1024;                        // 20 MB por archivo
$CARPETAS = ['seguros', 'polizas', 'multas'];
$TIPOS    = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'];

$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origen, $ORIGENES, true)) { header('Access-Control-Allow-Origin: ' . $origen); header('Vary: Origin'); }
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function responder($c, $d) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') responder(204, []);

// Prueba: https://manchatrans.com/documentos/subir.php?prueba=1
if (isset($_GET['prueba'])) responder(200, ['ok' => true, 'php' => PHP_VERSION, 'carpeta_escribible' => is_writable(__DIR__), 'max_subida' => ini_get('upload_max_filesize')]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'error' => 'Solo POST']);
if (!hash_equals($CLAVE, (string)($_POST['clave'] ?? ''))) responder(403, ['ok' => false, 'error' => 'Clave incorrecta']);

// ── Borrar un documento ──
if (($_POST['accion'] ?? '') === 'borrar') {
  $ruta = (string)($_POST['ruta'] ?? '');
  if (!preg_match('#^(seguros|polizas|multas)/\d{4}/[a-z0-9]{16}-[a-z0-9_-]{0,60}\.(pdf|jpg|png|webp|heic)$#', $ruta)) responder(400, ['ok' => false, 'error' => 'Ruta no válida']);
  $f = __DIR__ . '/' . $ruta;
  if (is_file($f)) @unlink($f);
  responder(200, ['ok' => true]);
}

// ── Subir ──
$carpeta = (string)($_POST['carpeta'] ?? '');
if (!in_array($carpeta, $CARPETAS, true)) responder(400, ['ok' => false, 'error' => 'Carpeta no válida']);
if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
  $err = $_FILES['archivo']['error'] ?? -1;
  responder(400, ['ok' => false, 'error' => $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'El archivo es demasiado grande para el servidor' : 'No ha llegado el archivo']);
}
if ($_FILES['archivo']['size'] > $MAX) responder(400, ['ok' => false, 'error' => 'El archivo pasa de 20 MB']);
$mime = @mime_content_type($_FILES['archivo']['tmp_name']) ?: '';
if (!isset($TIPOS[$mime])) responder(400, ['ok' => false, 'error' => 'Solo se admiten PDF o fotos (JPG, PNG)']);

// nombre: 16 caracteres al azar + el nombre original simplificado
$base = strtolower(pathinfo((string)($_FILES['archivo']['name'] ?? 'documento'), PATHINFO_FILENAME));
$base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base) ?: 'documento';
$base = trim(preg_replace('/[^a-z0-9_-]+/', '-', $base), '-');
$base = substr($base !== '' ? $base : 'documento', 0, 60);
$anio = date('Y');
$dir = __DIR__ . "/$carpeta/$anio";
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) responder(500, ['ok' => false, 'error' => 'No se puede crear la carpeta en el servidor']);
$azar = substr(bin2hex(random_bytes(8)), 0, 16);
$nombre = "$azar-$base." . $TIPOS[$mime];
if (!move_uploaded_file($_FILES['archivo']['tmp_name'], "$dir/$nombre")) responder(500, ['ok' => false, 'error' => 'No se ha podido guardar el archivo']);
@chmod("$dir/$nombre", 0644);

$url = 'https://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . "/$carpeta/$anio/$nombre";
responder(200, ['ok' => true, 'url' => $url, 'ruta' => "$carpeta/$anio/$nombre", 'tamano' => (int)$_FILES['archivo']['size'], 'tipo' => $mime]);
