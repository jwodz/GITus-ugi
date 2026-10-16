<?php

function validate_name(string $value, string $label, int $max): ?string
{
    if ($value === '') {
        return "Pole „{$label}” jest wymagane.";
    }
    if (mb_strlen($value) < 2 || mb_strlen($value) > $max) {
        return "Pole „{$label}” musi mieć od 2 do {$max} znaków.";
    }
    // Litery (także polskie), spacja, myślnik i apostrof, np. "Anna-Maria", "O'Neil".
    if (!preg_match("/^[\p{L}][\p{L} '\-]*$/u", $value)) {
        return "Pole „{$label}” może zawierać tylko litery, spację i myślnik.";
    }
    return null;
}

function validate_email(string $value): ?string
{
    if ($value === '') {
        return 'Adres e-mail jest wymagany.';
    }
    if (mb_strlen($value) > 150 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
        return 'Podaj poprawny adres e-mail, np. jan@example.com.';
    }
    return null;
}


function normalize_phone(string $value): string
{
    return preg_replace('/[\s\-]/', '', $value);
}

function validate_phone(string $value): ?string
{
    if ($value === '') {
        return 'Numer telefonu jest wymagany.';
    }
    // 9-15 cyfr, opcjonalnie z + na początku (np. +41 dla Szwajcarii).
    if (!preg_match('/^\+?\d{9,15}$/', $value)) {
        return 'Podaj poprawny numer telefonu (9–15 cyfr, może zaczynać się od +).';
    }
    return null;
}

function validate_password(string $value): ?string
{
    if ($value === '') {
        return 'Hasło jest wymagane.';
    }
    if (mb_strlen($value) < 8) {
        return 'Hasło musi mieć co najmniej 8 znaków.';
    }
    // password_hash() z algorytmem bcrypt bierze pod uwagę tylko 72 pierwsze bajty.
    if (strlen($value) > 72) {
        return 'Hasło może mieć najwyżej 72 znaki.';
    }
    if (!preg_match('/\p{L}/u', $value) || !preg_match('/\d/', $value)) {
        return 'Hasło musi zawierać co najmniej jedną literę i jedną cyfrę.';
    }
    return null;
}


function validate_user_data(array $data): array
{
    $errors = [];

    $error = validate_name($data['imie'], 'Imię', 50);
    if ($error) {
        $errors['imie'] = $error;
    }

    $error = validate_name($data['nazwisko'], 'Nazwisko', 80);
    if ($error) {
        $errors['nazwisko'] = $error;
    }

    $error = validate_email($data['email']);
    if ($error) {
        $errors['email'] = $error;
    }

    $error = validate_phone($data['telefon']);
    if ($error) {
        $errors['telefon'] = $error;
    }

    return $errors;
}


function user_data_from_post(): array
{
    return [
        'imie' => post_string('imie'),
        'nazwisko' => post_string('nazwisko'),
        'email' => mb_strtolower(post_string('email')),
        'telefon' => normalize_phone(post_string('telefon')),
    ];
}


function email_taken(string $email, ?int $exceptUserId = null): bool
{
    $stmt = db()->prepare('SELECT id FROM uzytkownicy WHERE email = ?');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();

    return $id !== false && (int) $id !== $exceptUserId;
}


function is_duplicate_error(PDOException $e): bool
{
    return ($e->errorInfo[1] ?? null) === 1062;
}
