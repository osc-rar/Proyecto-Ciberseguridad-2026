<?php
// ---------------------------------------------------------------------------
// Cabeceras de respuesta
// ---------------------------------------------------------------------------

// Cabecera CORS permisiva: cualquier origen puede consumir este endpoint.
header("Access-Control-Allow-Origin: *");
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

        // VULNERABLE: json_decode() sobre datos leídos del archivo sin verificar
        //             la integridad del contenido. Un atacante que logre escribir
        //             en usuarios.txt podría controlar esta variable.
        $perfil = json_decode($ultima_linea, true);
    }
}

// ---------------------------------------------------------------------------
// [VULNERABILIDAD: Consumo No Seguro de APIs — OWASP API10:2023 / CWE-20]
// Consulta a la API externa sin validación de integridad de la respuesta.
// ---------------------------------------------------------------------------

// URL de la API mock. Hardcodeada apuntando a localhost:5000.
$url_api_mock = 'http://localhost:5001/repos';

$contexto_api = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'timeout' => 5,
        'ignore_errors' => true,
    ],
]);

// VULNERABLE: se confía ciegamente en que el servicio en localhost:5000 es seguro.
// Si la API mock fue comprometida (por ejemplo mediante el SSRF de crear_perfil.php
// o un ataque directo al endpoint /update_repos), este call devolverá datos maliciosos.
$respuesta_cruda = @file_get_contents($url_api_mock, false, $contexto_api);

if ($respuesta_cruda === false) {
    http_response_code(502);
    echo json_encode([
        'error'   => 'No se pudo contactar la API de repositorios.',
        'detalle' => 'Asegúrese de que api_mock.py está corriendo en el puerto 5000.',
    ]);
    exit;
}

// VULNERABLE: se decodifica el JSON recibido de la API sin validar su estructura.
// Un JSON malicioso podría tener campos inesperados que el frontend renderice.
$repos = json_decode($respuesta_cruda, true);

if (!is_array($repos)) {
    http_response_code(502);
    echo json_encode(['error' => 'La API devolvió un formato inesperado.']);
    exit;
}

// ---------------------------------------------------------------------------
// [PUNTO DE INYECCIÓN — CWE-79: Cross-Site Scripting]
// Los campos del JSON se transfieren al frontend SIN sanitizar.
// ---------------------------------------------------------------------------

// VULNERABLE: el array $repos se reenvía tal cual al frontend. El campo
// 'descripcion' de cada repositorio puede contener HTML/JS arbitrario inyectado previamente en la API mock.
// Cuando el frontend asigna este valor a innerHTML, el navegador lo ejecuta.

// ---------------------------------------------------------------------------
// Respuesta final al cliente
// ---------------------------------------------------------------------------

// Se construye el payload de respuesta combinando el perfil del usuario y los
// repositorios de la API, todos sin sanitizar.
$payload = [
    'perfil' => $perfil,     // Datos del perfil (de usuarios.txt, sin sanitizar).
    'repos'  => $repos,      // Repositorios de la API (sin sanitizar — vector XSS).
];

// VULNERABLE: json_encode() serializa los datos incluyendo cualquier payload
// malicioso que haya en $repos['descripcion']. El frontend recibirá este JSON,
// lo parseará y asignará los campos directamente a innerHTML, ejecutando el
// código inyectado en el navegador del usuario/víctima.
http_response_code(200);
echo json_encode($payload);
