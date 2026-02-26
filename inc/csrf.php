<?php
// CSRF helper sederhana untuk endpoint/api dan form
// Gunakan csrf_token() di view dan kirimkan melalui header `X-CSRF-TOKEN`
// atau field tersembunyi `csrf_token` pada POST.

if (session_status() === PHP_SESSION_NONE) session_start();

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_protect_json() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!csrf_verify($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        exit(json_encode(['ok' => false, 'msg' => 'CSRF token tidak valid']));
    }
}

function csrf_protect_redirect() {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(403);
        exit('CSRF token tidak valid');
    }
}