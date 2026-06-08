<?php
/**
 * router.php - Router script for PHP built-in web server.
 * Simulates Apache's mod_rewrite rules by checking if the requested path
 * corresponds to a real file, and if not, routing the request to index.php.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $_SERVER['DOCUMENT_ROOT'] . urldecode($uri);

// If the requested resource is an existing file, let the server serve it directly
if (is_file($file)) {
    return false;
}

// Otherwise, route the request to our front controller
require_once __DIR__ . '/index.php';
