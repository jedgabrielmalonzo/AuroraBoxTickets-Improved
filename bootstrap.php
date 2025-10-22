<?php
// Central bootstrap to load Composer autoload and environment variables

// Load Composer autoloader if present
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Load .env if phpdotenv is available
if (class_exists('Dotenv\\Dotenv')) {
    try {
        $dotenv = call_user_func(['\\Dotenv\\Dotenv', 'createImmutable'], __DIR__);
        if (is_object($dotenv) && method_exists($dotenv, 'safeLoad')) {
            $dotenv->safeLoad();
        } elseif (is_object($dotenv) && method_exists($dotenv, 'load')) {
            $dotenv->load();
        }
    } catch (Throwable $e) {
        // Non-fatal: fallback to server env vars
        error_log('Dotenv load failed: ' . $e->getMessage());
    }
}

// Helper to read env with default
if (!function_exists('envv')) {
    function envv(string $key, $default = null) {
        if (array_key_exists($key, $_ENV)) return $_ENV[$key];
        if (array_key_exists($key, $_SERVER)) return $_SERVER[$key];
        $val = getenv($key);
        return ($val === false || $val === null) ? $default : $val;
    }
}

?>
