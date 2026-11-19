<?php

const ROLE_PANELS = [
    'klient' => 'klient/index.php',
    'pracownik' => 'pracownik/index.php',
    'administrator' => 'admin/index.php',
];

const SESSION_TIMEOUT = 30 * 60;
const LOGIN_MAX_FAILS_EMAIL = 5;
const LOGIN_MAX_FAILS_IP = 20;
const LOGIN_BLOCK_MINUTES = 15;

function current_user(bool $refresh = false): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded && !$refresh) {
        return $user;
    }
    $loaded = true;
    $user = null;

    $id = $_SESSION['uzytkownik_id'] ?? null;
    if ($id === null) {
        return null;
    }

    if (time() - ($_SESSION['ostatnia_aktywnosc'] ?? 0) > SESSION_TIMEOUT) {
        logout_user();
        flash_set('info', 'Sesja wygasła z powodu braku aktywności. Zaloguj się ponownie.');
        return null;
    }
    $_SESSION['ostatnia_aktywnosc'] = time();

    $stmt = db()->prepare(
        'SELECT id, imie, nazwisko, email, telefon, rola, aktywny FROM uzytkownicy WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['aktywny'] !== 1) {
        logout_user();
        return null;
    }

    $user = $row;
    return $user;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function login_blocked(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_BLOCK_MINUTES * 60);

    $stmt = db()->prepare('SELECT COUNT(*) FROM proby_logowania WHERE email = ? AND utworzono > ?');
    $stmt->execute([$email, $since]);
    if ((int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS_EMAIL) {
        return true;
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM proby_logowania WHERE adres_ip = ? AND utworzono > ?');
    $stmt->execute([client_ip(), $since]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS_IP;
}

function register_failed_login(string $email): void
{
    $stmt = db()->prepare('INSERT INTO proby_logowania (email, adres_ip, utworzono) VALUES (?, ?, ?)');
    $stmt->execute([mb_substr($email, 0, 150), client_ip(), date('Y-m-d H:i:s')]);

    $stmt = db()->prepare('DELETE FROM proby_logowania WHERE utworzono < ?');
    $stmt->execute([date('Y-m-d H:i:s', time() - 24 * 3600)]);
}

function attempt_login(string $email, string $password): ?string
{
    $email = mb_strtolower($email);

    if (login_blocked($email)) {
        return 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . LOGIN_BLOCK_MINUTES . ' minut.';
    }

    $stmt = db()->prepare('SELECT id, haslo_hash, aktywny FROM uzytkownicy WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['haslo_hash'])) {
        register_failed_login($email);
        return 'Nieprawidłowy e-mail lub hasło.';
    }

    if ((int) $row['aktywny'] !== 1) {
        return 'To konto jest nieaktywne. Skontaktuj się z administratorem.';
    }

    if (password_needs_rehash($row['haslo_hash'], PASSWORD_DEFAULT)) {
        $stmt = db()->prepare('UPDATE uzytkownicy SET haslo_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }

    $stmt = db()->prepare('DELETE FROM proby_logowania WHERE email = ?');
    $stmt->execute([$email]);

    login_user((int) $row['id']);
    return null;
}

function login_user(int $userId): void
{
    $target = $_SESSION['po_logowaniu'] ?? null;
    session_regenerate_id(true);
    $_SESSION['uzytkownik_id'] = $userId;
    $_SESSION['ostatnia_aktywnosc'] = time();
    unset($_SESSION['csrf_token']);
    if ($target !== null) {
        $_SESSION['po_logowaniu'] = $target;
    }
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function panel_url(string $role): string
{
    return ROLE_PANELS[$role] ?? 'index.php';
}

function remember_current_page(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        return;
    }

    $base = url('');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    if (str_starts_with($uri, $base)) {
        $path = substr($uri, strlen($base));
        if (preg_match('#^[a-z0-9/_\-]+\.php(\?[^\s<>"\']*)?$#i', $path)) {
            $_SESSION['po_logowaniu'] = $path;
        }
    }
}

function after_login_url(string $role): string
{
    $target = $_SESSION['po_logowaniu'] ?? null;
    unset($_SESSION['po_logowaniu']);

    if ($target === null) {
        return panel_url($role);
    }

    $ownFolder = dirname(panel_url($role)) . '/';
    if (preg_match('#^(admin|klient|pracownik|api)/#', $target) && !str_starts_with($target, $ownFolder)) {
        return panel_url($role);
    }

    return $target;
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        remember_current_page();
        flash_set('info', 'Zaloguj się, aby zobaczyć tę stronę.');
        redirect('logowanie.php');
    }

    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();

    if (!in_array($user['rola'], $roles, true)) {
        http_response_code(403);
        $tytul = 'Brak dostępu';
        require __DIR__ . '/layout/header.php';
        require __DIR__ . '/layout/brak-dostepu.php';
        require __DIR__ . '/layout/footer.php';
        exit;
    }

    return $user;
}

function redirect_if_logged_in(): void
{
    $user = current_user();

    if ($user !== null) {
        redirect(panel_url($user['rola']));
    }
}
