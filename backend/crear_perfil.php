<?php
// ---------------------------------------------------------------------------
// Cabeceras de respuesta
// ---------------------------------------------------------------------------

// CORREGIDO (antes: CORS permisivo con "*", OWASP API8:2023 Security
// Misconfiguration).
$origenes_permitidos = ['http://localhost:8080', 'http://127.0.0.1:8080'];
$origen_solicitud = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origen_solicitud, $origenes_permitidos, true)) {
    header("Access-Control-Allow-Origin: $origen_solicitud");
}
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Manejo del preflight de CORS (petición OPTIONS del navegador).
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo se acepta POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST.']);
    exit;
}

// ---------------------------------------------------------------------------
// Recepción de datos del formulario
// ---------------------------------------------------------------------------

// CORREGIDO (antes: CWE-20, datos del POST leídos sin ninguna sanitización).
function sanitizar_campo_texto(string $valor, int $longitud_maxima = 300): string {
    $valor = str_replace(["\r", "\n"], ' ', $valor);
    return mb_substr(trim($valor), 0, $longitud_maxima);
}

$nombre          = sanitizar_campo_texto($_POST['nombre']   ?? '');
$apellido        = sanitizar_campo_texto($_POST['apellido'] ?? '');
$bio             = sanitizar_campo_texto($_POST['bio']      ?? '', 1000);

// VECTOR DE ATAQUE SSRF — el campo url_repositorio es el punto de entrada.
// El atacante puede enviar cualquier URL, incluyendo direcciones internas
// como http://localhost:5000, http://127.0.0.1:22, http://192.168.x.x, etc.
// (Corregido más abajo con url_repositorio_es_segura()).
$url_repositorio = trim($_POST['url_repositorio'] ?? '');

// Validación mínima de campos obligatorios (solo verifica que no estén vacíos).
if (empty($nombre) || empty($url_repositorio)) {
    http_response_code(400);
    echo json_encode(['error' => 'Los campos nombre y url_repositorio son obligatorios.']);
    exit;
}

// ---------------------------------------------------------------------------
// Petición HTTP saliente — CORREGIDO (antes: SSRF, CWE-918 / OWASP API7:2023)
// ---------------------------------------------------------------------------

const DOMINIOS_REPOSITORIO_PERMITIDOS = [
    'github.com', 'www.github.com',
    'gitlab.com', 'www.gitlab.com',
    'bitbucket.org', 'www.bitbucket.org',
];

function url_repositorio_es_segura(string $url): bool {
    $partes = parse_url($url);
    if ($partes === false || empty($partes['host']) || empty($partes['scheme'])) {
        return false;
    }

    // Bloquea esquemas distintos de http/https (file://, gopher://, dict://,
    // etc., usados históricamente para explotar SSRF).
    if (!in_array(strtolower($partes['scheme']), ['http', 'https'], true)) {
        return false;
    }

    $host = strtolower($partes['host']);
    $puerto = $partes['port'] ?? (strtolower($partes['scheme']) === 'https' ? 443 : 80);

    if (!in_array($host, DOMINIOS_REPOSITORIO_PERMITIDOS, true)) {
        return false;
    }

    $ip = gethostbyname($host);
    if ($ip === $host || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        // No se pudo resolver el host, o resolvió a una IP privada/reservada/loopback.
        return false;
    }

    return true;
}

if (!url_repositorio_es_segura($url_repositorio)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'La URL de repositorio no es válida. Solo se permiten enlaces públicos a github.com, gitlab.com o bitbucket.org.',
    ]);
    exit;
}

$contexto = stream_context_create([
    'http' => [
        'timeout' => 5,
        'ignore_errors' => true,
        // Se desactiva el seguimiento de redirecciones: sin esto, un host
        // permitido podría responder con un 3xx hacia una IP interna y
        // saltarse la validación anterior (bypass de allowlist vía redirect).
        'follow_location' => 0,
    ],
]);

$contenido_externo = @file_get_contents($url_repositorio, false, $contexto);

$estado_http = null;
if (isset($http_response_header) && is_array($http_response_header)) {
    $estado_http = $http_response_header[0];
}

// CORREGIDO (antes: "FALTA: htmlspecialchars() o strip_tags() sobre
// $contenido_externo").
if ($contenido_externo === false) {
    $preview_repositorio = null;
    // CORREGIDO (antes: "FALTA: no exponer este estado al cliente"). 
    $estado_conexion     = 'error_conexion';
} else {
    // Se limita el preview a los primeros 500 caracteres del contenido externo.
    $preview_repositorio = htmlspecialchars(substr($contenido_externo, 0, 500), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $estado_conexion     = 'ok';
}

// ---------------------------------------------------------------------------
// Persistencia en usuarios.txt
// ---------------------------------------------------------------------------

// Ruta al archivo de almacenamiento (relativa a este script).
$ruta_datos = __DIR__ . '/../data/usuarios.txt';

// CORREGIDO: $nombre, $apellido y $bio ya pasaron por sanitizar_campo_texto()

$registro = json_encode([
    'nombre'           => $nombre,
    'apellido'         => $apellido,
    'bio'              => $bio,
    'url_repositorio'  => $url_repositorio,
    'fecha_registro'   => date('Y-m-d H:i:s'),
]) . PHP_EOL;

// CORREGIDO (antes: "VULNERABLE: no se usa locking de archivo (LOCK_EX)").
file_put_contents($ruta_datos, $registro, FILE_APPEND | LOCK_EX);

// ---------------------------------------------------------------------------
// Respuesta al cliente
// ---------------------------------------------------------------------------

// CORREGIDO (consecuencia directa del fix de SSRF más arriba)
// CORREGIDO (antes: "FALTA: no exponer este estado al cliente").
http_response_code(201);
echo json_encode([
    'status'               => 'perfil_creado',
    'mensaje'              => 'Perfil guardado correctamente.',
    'url_solicitada'       => $url_repositorio,
    'estado_conexion'      => $estado_conexion === 'ok',
    'estado_http_externo'  => $estado_http,
    'preview_repositorio'  => $preview_repositorio,
]);
