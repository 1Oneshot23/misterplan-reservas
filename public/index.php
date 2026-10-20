<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Reserva.php';
require __DIR__ . '/../src/ReservaRepository.php';

$config = require __DIR__ . '/../config.php';

// Función única para responder en JSON: pone el código HTTP, escribe el JSON y termina
function responder(int $codigo, array $datos): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Lee el cuerpo de la petición (JSON) y lo convierte en array. Devuelve null si es inválido
function leerJson(): ?array
{
    $datos = json_decode(file_get_contents('php://input'), true);
    return is_array($datos) ? $datos : null;
}

// Indica si el cliente es un navegador que pide una página HTML
function quiereHtml(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html');
}

// Manejo centralizado de errores: cualquier fallo inesperado acaba aquí
try {
    $db   = new Database($config);
    $repo = new ReservaRepository($db->getConnection());

    // Twig: motor de plantillas con autoescape activado (protección XSS)
    $twig = new \Twig\Environment(
        new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates'),
        ['autoescape' => 'html']
    );

    // 1) Averiguar método y ruta (quitando la carpeta base del proyecto)
    $metodo = $_SERVER['REQUEST_METHOD'];
    $ruta   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');   // ej: /misterplan/public
    if ($base !== '' && str_starts_with($ruta, $base)) {
        $ruta = substr($ruta, strlen($base));
    }
    $ruta = '/' . trim(str_replace('/index.php', '', $ruta), '/');

    // 2) Página: listado (GET /)
    if ($ruta === '/' && $metodo === 'GET') {
        echo $twig->render('lista.html.twig', [
            'base_url' => $base,
            'reservas' => $repo->obtenerTodas(),
        ]);
        exit;
    }

    // 3) GET /reservas  -> lista JSON con filtros opcionales
    if ($ruta === '/reservas' && $metodo === 'GET') {
        $filtros = [
            'estado'      => $_GET['estado'] ?? '',
            'nombre'      => $_GET['nombre'] ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
        ];

        if ($filtros['estado'] !== '' && Reserva::validarEstado($filtros['estado']) !== null) {
            responder(400, ['error' => Reserva::validarEstado($filtros['estado'])]);
        }
        responder(200, ['datos' => $repo->obtenerTodas($filtros)]);
    }

    // 4) POST /reservas  -> crear una reserva
    if ($ruta === '/reservas' && $metodo === 'POST') {
        $datos = leerJson();
        if ($datos === null) {
            responder(400, ['error' => 'El cuerpo debe ser un JSON válido']);
        }

        // Validar en el servidor (nunca confiamos en el navegador)
        $errores = Reserva::validarCreacion($datos);
        if (!empty($errores)) {
            responder(400, ['error' => 'Datos no válidos', 'errores' => $errores]);
        }

        $id = $repo->crear(Reserva::limpiar($datos));
        responder(201, ['datos' => $repo->obtenerPorId($id)]);
    }

    // 5) GET /reservas/{id}  -> detalle (HTML para navegador, JSON para la API)
    if (preg_match('#^/reservas/(\d+)$#', $ruta, $m) && $metodo === 'GET') {
        $reserva = $repo->obtenerPorId((int) $m[1]);
        if ($reserva === null) {
            if (quiereHtml()) {
                http_response_code(404);
                echo 'Reserva no encontrada';
                exit;
            }
            responder(404, ['error' => 'Reserva no encontrada']);
        }
        $reserva['eventos'] = $repo->obtenerEventos((int) $m[1]);

        if (quiereHtml()) {
            echo $twig->render('detalle.html.twig', [
                'base_url' => $base,
                'reserva'  => $reserva,
            ]);
            exit;
        }
        responder(200, ['datos' => $reserva]);
    }

    // 6) PUT /reservas/{id}/estado  -> confirmar o cancelar
    if (preg_match('#^/reservas/(\d+)/estado$#', $ruta, $m) && $metodo === 'PUT') {
        $id      = (int) $m[1];
        $reserva = $repo->obtenerPorId($id);
        if ($reserva === null) {
            responder(404, ['error' => 'Reserva no encontrada']);
        }

        $datos  = leerJson();
        $estado = $datos['estado'] ?? null;

        $error = Reserva::validarEstado($estado);
        if ($error !== null) {
            responder(400, ['error' => $error]);
        }
        if ($reserva['estado'] === $estado) {
            responder(400, ['error' => 'La reserva ya está en estado ' . $estado]);
        }

        $repo->cambiarEstado($id, $estado);
        responder(200, ['datos' => $repo->obtenerPorId($id)]);
    }

    // 7) Ninguna ruta coincide
    responder(404, ['error' => 'Ruta no encontrada']);

} catch (Throwable $e) {
    // Guardamos el detalle real en el log del servidor...
    error_log($e->getMessage());
    // ...pero al cliente solo le damos un mensaje genérico
    responder(500, ['error' => 'Error interno del servidor']);
}