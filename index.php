<?php
session_start();

// -----------------------------
// Suppress deprecation notices (PHP 8.1+)
// -----------------------------
error_reporting(E_ALL & ~E_DEPRECATED);

// Optional: Display all errors except deprecations
//ini_set('display_errors', '1');

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
    $page = 'dashboard'; // Default page is dashboard
}

// Sanitize page name to prevent directory traversal
$page = basename($page); // Only get the last part of the URL

// Check if the page file exists
$pageFile = __DIR__ . "/pages/$page.php";

// Check if file exists
if (file_exists($pageFile)) {
    require $pageFile;
} else {
    http_response_code(404);
    echo "<h1>404 - Page Not Found</h1>";
}
