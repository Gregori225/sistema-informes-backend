<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\SolicitanteController;
use App\Controllers\InventarioController;
use App\Controllers\InformeController;
use App\Utils\Response;
use App\Controllers\AuthController;

// ----------------------------------------------------------------------------
// CONFIGURACIÓN CORS (Crucial para conectar Render con Vercel)
// ----------------------------------------------------------------------------
$allowedOrigin = getenv('FRONTEND_URL') ?: '*';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Respuesta a la solicitud Preflight OPTIONS del navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ----------------------------------------------------------------------------
// ENRUTADOR BÁSICO REST
// ----------------------------------------------------------------------------
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Normalizar la ruta eliminando barras finales
$uri = rtrim($uri, '/');

// Enrutamiento
try {
    // Endpoints Solicitantes
    if ($uri === '/api/solicitantes' && $method === 'GET') {
        (new SolicitanteController())->listar();
    } elseif ($uri === '/api/solicitantes' && $method === 'POST') {
        (new SolicitanteController())->crear();
    }

    // Endpoint de Autenticación
    if ($uri === '/api/auth/login' && $method === 'POST') {
        (new AuthController())->login();
    }

    // Endpoints Inventario
    elseif ($uri === '/api/inventario/equipos' && $method === 'GET') {
        (new InventarioController())->listarEquipos();
    } elseif ($uri === '/api/inventario/herramientas' && $method === 'GET') {
        (new InventarioController())->listarHerramientas();
    } elseif ($uri === '/api/inventario/refacciones' && $method === 'GET') {
        (new InventarioController())->listarRefacciones();
    }

    // Endpoints Informes Técnicos
    elseif ($uri === '/api/informes' && $method === 'GET') {
        (new InformeController())->listar();
    } elseif ($uri === '/api/informes' && $method === 'POST') {
        (new InformeController())->crear();
    } elseif (preg_match('#^/api/informes/(\d+)$#', $uri, $matches) && $method === 'GET') {
        (new InformeController())->obtenerPorId((int)$matches[1]);
    }

    // Ruta de estado de la API (Healthcheck para Render)
    elseif ($uri === '' || $uri === '/api/health') {
        Response::success(['status' => 'online', 'service' => 'API Informes Técnicos'], 'API en funcionamiento');
    }

    // 404 Not Found
    else {
        Response::error("Ruta no encontrada: [{$method}] {$uri}", 404);
    }
} catch (\Throwable $th) {
    Response::error("Excepción interna: " . $th->getMessage(), 500);
}
