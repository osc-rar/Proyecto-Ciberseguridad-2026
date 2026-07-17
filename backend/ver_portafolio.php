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
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use GET.']);
    exit;
}

// ---------------------------------------------------------------------------
// Lectura del perfil guardado en usuarios.txt
// ---------------------------------------------------------------------------

$ruta_datos = __DIR__ . '/../data/usuarios.txt';

$perfil = null;

if (file_exists($ruta_datos)) {
    $lineas = file($ruta_datos, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!empty($lineas)) {
        // Se toma el último registro guardado (el más reciente).
        $ultima_linea = end($lineas);
        $perfil = json_decode($ultima_linea, true);

        // CORREGIDO (antes: "VULNERABLE: json_decode() sobre datos leídos del
        // archivo sin verificar la integridad del contenido...").
        if (!perfil_es_valido($perfil)) {
            $perfil = null;
        }
    }
}

// Valida que $perfil tenga exactamente los campos de texto esperados de un
// registro creado por crear_perfil.php.
function perfil_es_valido($perfil): bool {
    if (!is_array($perfil)) {
        return false;
    }

    $campos_esperados = [
        'nombre'          => 'string',
        'apellido'        => 'string',
        'bio'             => 'string',
    ];
    foreach ($campos_esperados as $campo => $tipo) {
        if (!array_key_exists($campo, $perfil) || gettype($perfil[$campo]) !== $tipo) {
            return false;
        }
    }

    return true;
}

// ---------------------------------------------------------------------------

// URL de la API mock. Hardcodeada apuntando a localhost:5000.
$url_api_mock = 'http://localhost:5000/repos';

$contexto_api = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 5,
        'ignore_errors' => true,
    ],
]);

$respuesta_cruda = @file_get_contents($url_api_mock, false, $contexto_api);

if ($respuesta_cruda === false) {
    http_response_code(502);
    echo json_encode([
        'error'   => 'No se pudo contactar la API de repositorios.',
        'detalle' => 'Asegúrese de que api_mock.py está corriendo en el puerto 5000.',
    ]);
    exit;
}

$repos = json_decode($respuesta_cruda, true);

if (!is_array($repos)) {
    http_response_code(502);
    echo json_encode(['error' => 'La API devolvió un formato inesperado.']);
    exit;
}

// CORREGIDO (antes: Consumo No Seguro de APIs, OWASP API10:2023 / CWE-20).
function repo_es_valido($repo): bool {
    if (!is_array($repo)) {
        return false;
    }

    $campos_esperados = [
        'nombre'      => 'string',
        'descripcion' => 'string',
        'url'         => 'string',
        'lenguaje'    => 'string',
    ];
    foreach ($campos_esperados as $campo => $tipo) {
        if (!array_key_exists($campo, $repo) || gettype($repo[$campo]) !== $tipo) {
            return false;
        }
    }

    if (!filter_var($repo['url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $repo['url'])) {
        return false;
    }

    return true;
}

$repos_validados = array_values(array_filter($repos, 'repo_es_valido'));

// ---------------------------------------------------------------------------
// Respuesta final al cliente
// ---------------------------------------------------------------------------

// CORREGIDO (antes: CWE-79 Cross-Site Scripting). Codifica recursivamente los
function escapar_valores_recursivo($valor) {
    if (is_array($valor)) {
        return array_map('escapar_valores_recursivo', $valor);
    }
    if (is_string($valor)) {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return $valor;
}

$payload = [
    'perfil' => $perfil !== null ? escapar_valores_recursivo($perfil) : null,
    'repos'  => escapar_valores_recursivo($repos_validados),
];

http_response_code(200);
echo json_encode($payload);
