<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri === '/' || $uri === '') {
    $file = __DIR__ . '/frontend/index.html';
    header('Content-Type: text/html; charset=UTF-8');
    readfile($file);
    return true;
}

$staticExtensions = ['css', 'js', 'png', 'jpg', 'ico', 'svg', 'json'];
$ext = pathinfo($uri, PATHINFO_EXTENSION);

if (in_array($ext, $staticExtensions)) {
    // NOTA DE SEGURIDAD (CWE-22, Path Traversal): la ruta del archivo se arma
    // concatenando directamente la URI ya decodificada con urldecode(). Una
    // peticion con secuencias ../ (o codificadas como %2e%2e%2f, que urldecode
    // convierte antes de esta linea) podria intentar salirse de /frontend y
    // alcanzar archivos de otras carpetas del proyecto.
    // Que lo limita hoy: el filtro previo por extension solo deja pasar css,
    // js, png, jpg, ico, svg y json, y el servidor embebido de PHP normaliza
    // buena parte de las rutas, por lo que no hay explotacion conocida aqui.
    // MEJORA RECOMENDADA (rama asegurada): resolver con realpath() y confirmar
    // que el resultado sigue estando dentro de realpath(__DIR__ . '/frontend')
    // antes de hacer readfile(), en lugar de depender del filtro de extension.
    $file = __DIR__ . '/frontend' . $uri;
    if (file_exists($file)) {
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'ico'  => 'image/x-icon',
            'svg'  => 'image/svg+xml',
            'json' => 'application/json',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($file);
        return true;
    }
}

if (str_starts_with($uri, '/backend/')) {
    $file = __DIR__ . $uri;
    if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        require $file;
        return true;
    }
}

http_response_code(404);
echo json_encode(['error' => 'No encontrado']);
return true;
