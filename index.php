<?php
session_start();

// -----------------------------
// Suppress deprecation notices (PHP 8.1+)
// -----------------------------
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

// -----------------------------
// API v1 routes - bypass session, go direct
// -----------------------------
$request = $_SERVER['REQUEST_URI'];
$requestPath = strtok($request, '?');

if (preg_match('#^/api/v1/(auth|routers|router|whitelist|billing|plans|router_connect|sync)\.php$#', $requestPath, $m)) {
    $apiFile = __DIR__ . '/api/v1/' . $m[1] . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}

// Non-v1 API endpoints (mikrotik, vouchers, etc.)
if (preg_match('#^/api/(mikrotik|vouchers|control|billing|plans|qos|routers|bill|dump|hotspot_login|wireguard_register)\.php$#', $requestPath, $m)) {
    $apiFile = __DIR__ . '/api/' . $m[1] . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}

// Cron worker endpoint
if ($requestPath === '/api/cron.php') {
    require __DIR__ . '/api/cron.php';
    exit;
}

// Captive portal page - public, no auth needed
if ($requestPath === '/captive' || $requestPath === '/captive/') {
    require __DIR__ . '/pages/captive.php';
    exit;
}

// Provision script endpoint - serves .rsc files to MikroTik routers
if (preg_match('#^/provision/(.+)$#', $requestPath, $m)) {
    $token = $m[1];
    $_GET['action'] = 'provision_script';
    $_GET['token'] = $token;
    require __DIR__ . '/api/mikrotik.php';
    exit;
}

// -----------------------------
// Simple dynamic router
// -----------------------------
$page = trim($requestPath, '/');

if ($page === '') {
    $page = 'home';
}

$page = basename($page);

// Public pages
$publicPages = ['home', 'login', 'logout', 'register', 'captive'];

if (!isset($_SESSION['logged_in']) && !in_array($page, $publicPages)) {
    header('Location: /login');
    exit;
}

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        $allowedPagesForAdmin = ['home', 'dashboard', 'billuser', 'users', 'login', 'logout', 'register', 'connect_mikrotik', 'mikrotik_devices', 'vouchers', 'revenue', 'support', 'reports', 'plans', 'billing', 'mikrotik', 'add_router', 'view'];
        if (!in_array($page, $allowedPagesForAdmin)) {
            http_response_code(403);
            require __DIR__ . "/pages/403.php";
            exit;
        }
    }
}

$pageFile = __DIR__ . "/pages/$page.php";

if (file_exists($pageFile)) {
    require $pageFile;
} else {
    http_response_code(404);
    echo "<h1>404 - Page Not Found</h1>";
}
