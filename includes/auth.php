<?php

const ROLE_PANELS = [
    'klient' => 'klient/index.php',
    'pracownik' => 'pracownik/index.php',
    'administrator' => 'admin/index.php',
];

const SESSION_TIMEOUT = 30 * 60;


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


function attempt_login(string $email, string $password): ?string
{
    $stmt = db()->prepare('SELECT id, haslo_hash, aktywny FROM uzytkownicy WHERE email = ?');
    $stmt->execute([mb_strtolower($email)]);
    $row = $stmt->fetch();

   
    if (!$row || !password_verify($password, $row['haslo_hash'])) {
        return 'Nieprawidłowy e-mail lub hasło.';
    }

    if ((int) $row['aktywny'] !== 1) {
        return 'To konto jest nieaktywne. Skontaktuj się z administratorem.';
    }

    // Jeśli PHP zmieni domyślny algorytm, przy logowaniu zapisujemy nowy hash.
    if (password_needs_rehash($row['haslo_hash'], PASSWORD_DEFAULT)) {
        $stmt = db()->prepare('UPDATE uzytkownicy SET haslo_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }

    login_user((int) $row['id']);
    return null;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['uzytkownik_id'] = $userId;
    $_SESSION['ostatnia_aktywnosc'] = time();
    unset($_SESSION['csrf_token']);
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

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
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
