<?php
/**
 * Router for PHP's built-in web server (`php -S`) on Termux.
 *
 * The project normally runs under Apache, which:
 *   1) uses the root .htaccess to deny access to *.tpl, *.sql and *.log, and
 *   2) sets the working directory to the directory of the executing script.
 *
 * The built-in server does neither, and much of the codebase uses relative
 * includes (e.g. install/index.php includes "templates/script.tpl", the root
 * index.php includes "GameEngine/config.php"). This router reproduces both
 * behaviours so the game runs unchanged.
 *
 * Start with (from the project root):
 *   php -S 0.0.0.0:8080 termux/router.php
 */

$docRoot = realpath(__DIR__ . '/..');
$uri     = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// --- Deny access to protected file types (mirrors root .htaccess) ------------
if (preg_match('/\.(tpl|sql|log)$/i', $uri)) {
    http_response_code(403);
    echo 'Forbidden';
    return true;
}

$target = $docRoot . $uri;

// --- Resolve the request to a concrete script or static file -----------------
if ($uri === '/' || is_dir($target)) {
    // Directory (or site root) -> its index.php, if present.
    $script = rtrim($target, '/') . '/index.php';
    if (!is_file($script)) {
        // No index in this directory; let the server return a 404 listing.
        return false;
    }
} elseif (is_file($target)) {
    // A real file was requested.
    if (!preg_match('/\.php$/i', $target)) {
        // Static asset (image, css, js, ...): let the built-in server serve it.
        return false;
    }
    $script = $target;
} else {
    // Nothing matches -> 404 handled by the built-in server.
    return false;
}

// --- Run the PHP script the way Apache would ---------------------------------
// Apache executes the script with the CWD set to the script's own directory;
// replicate that so the many relative include() paths keep working.
$scriptDir = dirname($script);
chdir($scriptDir);

$_SERVER['SCRIPT_FILENAME'] = $script;
$_SERVER['SCRIPT_NAME']     = substr($script, strlen($docRoot));
$_SERVER['PHP_SELF']        = $_SERVER['SCRIPT_NAME'];

require $script;
return true;
