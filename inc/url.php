<?php

function app_base_path(): string {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $marker = '/public/';
    $pos = strpos($script, $marker);
    if ($pos === false) return '';
    $base = substr($script, 0, $pos);
    return rtrim($base, '/');
}

function app_url(string $path): string {
    $path = '/' . ltrim($path, '/');
    return app_base_path() . $path;
}

