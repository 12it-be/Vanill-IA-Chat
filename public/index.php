<?php

declare(strict_types=1);

use VanillIA\Core\Env;
use VanillIA\Core\Router;

spl_autoload_register(function ($class) {
    $prefix = 'VanillIA\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

Env::load(__DIR__ . '/../.env');

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$lockFile    = __DIR__ . '/../storage/installed.lock';
if (!file_exists($lockFile) && !str_ends_with($requestPath, '/install') && !str_ends_with($requestPath, '/install.php')) {
    header('Location: /install');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

$router = new Router();

// Installation
$router->get('/install', function() { require __DIR__ . '/install.php'; });
$router->post('/install', function() { require __DIR__ . '/install.php'; });

// Authentification & Préférences (Langue / Thème)
$router->get('/login', [\VanillIA\Controllers\AuthController::class, 'showLogin']);
$router->post('/api/auth/login', [\VanillIA\Controllers\AuthController::class, 'login']);
$router->get('/auth/entra', [\VanillIA\Controllers\AuthController::class, 'redirectToEntra']);
$router->get('/auth/callback', [\VanillIA\Controllers\AuthController::class, 'handleEntraCallback']);
$router->get('/logout', [\VanillIA\Controllers\AuthController::class, 'logout']);
$router->post('/api/preferences', [\VanillIA\Controllers\AuthController::class, 'updatePreferences']);
$router->get('/lang/{lang}', [\VanillIA\Controllers\AuthController::class, 'switchLang']);

// Chat, Streaming SSE, Quota, Export & Fichiers
$router->get('/', [\VanillIA\Controllers\ChatController::class, 'index']);
$router->post('/api/chat/stream', [\VanillIA\Controllers\ChatController::class, 'stream']);
$router->get('/api/quota/usage', [\VanillIA\Controllers\ChatController::class, 'myUsage']);
$router->get('/api/conversations', [\VanillIA\Controllers\ChatController::class, 'listConversations']);
$router->get('/api/conversations/{id}', [\VanillIA\Controllers\ChatController::class, 'getConversation']);
$router->get('/api/conversations/{id}/export', [\VanillIA\Controllers\ChatController::class, 'exportConversation']);
$router->post('/api/conversations/{id}/prompt', [\VanillIA\Controllers\ChatController::class, 'updateConversationPrompt']);
$router->post('/api/conversations/{id}/rename', [\VanillIA\Controllers\ChatController::class, 'renameConversation']);
$router->post('/api/conversations/{id}/pin', [\VanillIA\Controllers\ChatController::class, 'togglePinConversation']);
$router->post('/api/conversations/{id}/delete', [\VanillIA\Controllers\ChatController::class, 'deleteConversation']);
$router->post('/api/upload', [\VanillIA\Controllers\FileController::class, 'upload']);

// Souvenirs (Memory Engine)
$router->get('/api/memories', [\VanillIA\Controllers\MemoryController::class, 'list']);
$router->post('/api/memories', [\VanillIA\Controllers\MemoryController::class, 'create']);
$router->post('/api/memories/{id}/toggle', [\VanillIA\Controllers\MemoryController::class, 'toggle']);
$router->post('/api/memories/{id}/delete', [\VanillIA\Controllers\MemoryController::class, 'delete']);

// Cockpit Administrateur
$router->get('/admin', [\VanillIA\Controllers\AdminController::class, 'index']);
$router->post('/api/admin/user', [\VanillIA\Controllers\AdminController::class, 'updateUser']);
$router->get('/api/admin/users/{id}/usage', [\VanillIA\Controllers\AdminController::class, 'userUsageDetails']);
$router->post('/api/admin/model', [\VanillIA\Controllers\AdminController::class, 'updateModel']);
$router->post('/api/admin/settings', [\VanillIA\Controllers\AdminController::class, 'updateSettings']);
$router->post('/api/admin/knowledge', [\VanillIA\Controllers\AdminController::class, 'uploadKnowledge']);
$router->post('/api/admin/knowledge/{id}/delete', [\VanillIA\Controllers\AdminController::class, 'deleteKnowledge']);
$router->get('/api/admin/logs/export', [\VanillIA\Controllers\AdminController::class, 'exportLogsCsv']);

$router->dispatch();
