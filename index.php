<?php
session_start();

// -----------------------------
// Suppress deprecation notices (PHP 8.1+)
// -----------------------------
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

// -----------------------------
// Simple dynamic router
// -----------------------------
$request = $_SERVER['REQUEST_URI'];

// Remove query string
$request = strtok($request, '?');

// Trim leading/trailing slashes
$page = trim($request, '/');

// Default page
if ($page === '') {
    $page = 'login';
}

// Sanitize page name
$page = basename($page);

// Public pages
$publicPages = ['login', 'logout'];

// If not logged in
if (!isset($_SESSION['logged_in']) && !in_array($page, $publicPages)) {
    header('Location: /login');
    exit;
}

// If logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {

    // Redirect admin from dashboard to billuser
    if ($_SESSION['role'] === 'admin' && $page === 'dashboard') {
        header('Location: /billuser');
        exit;
    }

    // Restrict admin access
    if ($_SESSION['role'] === 'admin') {
        $allowedPagesForAdmin = ['billuser', 'users', 'login', 'logout'];

        if (!in_array($page, $allowedPagesForAdmin)) {
            http_response_code(403);
            require __DIR__ . "/pages/403.php";
            exit;
        }
    }
}

// Load requested page
$pageFile = __DIR__ . "/pages/$page.php";

if (file_exists($pageFile)) {
    require $pageFile;
} else {
    http_response_code(404);
    echo "<h1>404 - Page Not Found</h1>";
}
?>
