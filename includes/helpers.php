<?php

function config(string $key, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $file = __DIR__ . '/../config/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Brak pliku config/config.php. Skopiuj config/config.example.php jako config/config.php (instrukcja w README).');
        }
        $config = require $file;
    }

    return $config[$key] ?? $default;
}

function e(?string $text): string
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $public = str_replace('\\', '/', realpath(__DIR__ . '/../public'));
        $file = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']));
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);

        $insidePublic = substr($file, strlen($public));
        $base = substr($script, 0, strlen($script) - strlen($insidePublic));
    }

    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['typ' => $type, 'tresc' => $message];
}

function flash_get(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function post_password(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="pole__blad" id="' . e($field) . '-blad">' . e($errors[$field]) . '</p>';
}

function field_attrs(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return 'aria-invalid="true" aria-describedby="' . e($field) . '-blad"';
}

function role_name(string $role): string
{
    return match ($role) {
        'administrator' => 'Administrator',
        'pracownik' => 'Pracownik',
        default => 'Klient',
    };
}
