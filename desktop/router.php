<?php

// Router for PHP's built-in web server, used by the ShopApp desktop app.
// Copied into the packaged app root as desktop-server.php by scripts/prepare.mjs.

$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false; // let the built-in server send the static file
}

require_once $publicPath.'/index.php';
