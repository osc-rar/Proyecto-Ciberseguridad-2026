<?php
// ---------------------------------------------------------------------------
// Cabeceras de respuesta
// ---------------------------------------------------------------------------

// Cabecera CORS permisiva: permite peticiones desde cualquier origen.
header("Access-Control-Allow-Origin: *");
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

// VULNERABLE: Se leen los datos del POST sin ningún tipo de sanitización previa.
$nombre          = $_POST['nombre']          ?? '';
$apellido        = $_POST['apellido']        ?? '';
$bio             = $_POST['bio']              ?? '';

// VECTOR DE ATAQUE SSRF — el campo url_repositorio es el punto de entrada.
// El atacante puede enviar cualquier URL, incluyendo direcciones internas
// como http://localhost:5000, http://127.0.0.1:22, http://192.168.x.x, etc.
$url_repositorio = $_POST['url_repositorio'] ?? '';

// Validación mínima de campos obligatorios (solo verifica que no estén vacíos).
if (empty($nombre) || empty($url_repositorio)) {
    http_response_code(400);
    echo json_encode(['error' => 'Los campos nombre y url_repositorio son obligatorios.']);
    exit;
}

// ---------------------------------------------------------------------------
// [VULNERABILIDAD SSRF — CWE-918]
// Petición HTTP saliente usando directamente el valor del POST sin validación.
// ---------------------------------------------------------------------------

// VULNERABLE: file_get_contents() acepta cualquier URL válida, incluyendo
// esquemas como file://, http://, y direcciones de red interna.
$contexto = stream_context_create([
    'http' => [
        // Tiempo de espera corto para que diferencias en latencia sean
        // observables por el atacante durante el escaneo de puertos.
        'timeout' => 5,
        'ignore_errors' => true, // Devuelve el cuerpo incluso en errores HTTP (4xx/5xx).
    ],
]);

// VULNERABLE: $url_repositorio proviene directamente de $_POST sin ningún filtro.
// El valor se pasa íntegro a file_get_contents(), permitiendo SSRF completo.
$contenido_externo = @file_get_contents($url_repositorio, false, $contexto);

// Se captura el estado HTTP de la respuesta para devolverlo al cliente.
$estado_http = null;
if (isset($http_response_header) && is_array($http_response_header)) {
    // La primera línea del array contiene el status line (ej. "HTTP/1.1 200 OK").
    $estado_http = $http_response_header[0];
}

// Si file_get_contents falla completamente (conexión rechazada, timeout),
// $contenido_externo será false. Esto también filtra información de timing:
// un timeout indica puerto filtrado; un rechazo inmediato indica puerto cerrado.
if ($contenido_externo === false) {
    $preview_repositorio = null;
    $estado_conexion     = 'error_conexion'; // FALTA: no exponer este estado al cliente.
} else {
    // Se limita el preview a los primeros 500 caracteres del contenido externo.
    // VULNERABLE: el contenido NO está sanitizado. Si la URL apunta a un servicio
    //             interno que devuelve HTML o JSON, ese contenido se reenvía tal cual.
    //             FALTA: htmlspecialchars() o strip_tags() sobre $contenido_externo.
    $preview_repositorio = substr($contenido_externo, 0, 500);
    $estado_conexion     = 'ok';
}

// ---------------------------------------------------------------------------
// Persistencia en usuarios.txt
// ---------------------------------------------------------------------------

// Ruta al archivo de almacenamiento (relativa a este script).
$ruta_datos = __DIR__ . '/../data/usuarios.txt';

// Construcción del registro a guardar.
// VULNERABLE: $nombre, $apellido, $bio y $url_repositorio se guardan tal cual,
//             sin sanitizar. Un atacante podría inyectar saltos de línea en los
//             campos para corromper el formato del archivo.
//             FALTA: sanitizar cada campo con str_replace(["\n", "\r"], ' ', $campo).
$registro = json_encode([
    'nombre'           => $nombre,
    'apellido'         => $apellido,
    'bio'              => $bio,
    'url_repositorio'  => $url_repositorio,
    'fecha_registro'   => date('Y-m-d H:i:s'),
]) . PHP_EOL;

// VULNERABLE: no se usa locking de archivo (LOCK_EX), lo que puede producir
//             condiciones de carrera si múltiples peticiones escriben en simultáneo.
file_put_contents($ruta_datos, $registro, FILE_APPEND);

// ---------------------------------------------------------------------------
// Respuesta al cliente
// ---------------------------------------------------------------------------

// VULNERABLE: se devuelve $estado_http y $preview_repositorio (contenido externo
//             no filtrado) directamente en la respuesta JSON. Esto permite al
//             atacante usar este endpoint como oráculo para escanear la red interna:
//             diferencias en el contenido y en el estado confirman puertos abiertos.
http_response_code(201);
echo json_encode([
    'status'               => 'perfil_creado',
    'mensaje'              => 'Perfil guardado correctamente.',
    'url_solicitada'       => $url_repositorio,
    'estado_conexion'      => $estado_conexion,
    'estado_http_externo'  => $estado_http,
    'preview_repositorio'  => $preview_repositorio,
]);
