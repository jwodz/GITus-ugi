<?php

const STATUSES = [
    'oczekujaca' => 'Oczekująca',
    'potwierdzona' => 'Potwierdzona',
    'zrealizowana' => 'Zrealizowana',
    'anulowana' => 'Anulowana',
];

const STATUS_CHANGES = [
    'oczekujaca' => ['potwierdzona', 'zrealizowana', 'anulowana'],
    'potwierdzona' => ['oczekujaca', 'zrealizowana', 'anulowana'],
    'zrealizowana' => ['potwierdzona'],
    'anulowana' => [],
];

const DAYS = [
    1 => 'Poniedziałek',
    2 => 'Wtorek',
    3 => 'Środa',
    4 => 'Czwartek',
    5 => 'Piątek',
    6 => 'Sobota',
    7 => 'Niedziela',
];

const RESERVATION_JOINS = '
    FROM rezerwacje r
    JOIN uzytkownicy u ON u.id = r.uzytkownik_id
    JOIN pracownicy p ON p.id = r.pracownik_id
    JOIN uzytkownicy pu ON pu.id = p.uzytkownik_id
    JOIN uslugi s ON s.id = r.usluga_id';

function status_name(string $status): string
{
    return STATUSES[$status] ?? $status;
}

function status_badge(string $status): string
{
    return '<span class="status status--' . e($status) . '">' . e(status_name($status)) . '</span>';
}

function day_name(int $day): string
{
    return DAYS[$day] ?? '?';
}

function format_date(string $date): string
{
    return date('d.m.Y', strtotime($date));
}

function format_time(string $time): string
{
    return substr($time, 0, 5);
}

function format_price(string|float $price): string
{
    return number_format((float) $price, 2, ',', ' ') . ' zł';
}

function ranges_overlap(string $startA, string $endA, string $startB, string $endB): bool
{
    return format_time($startA) < format_time($endB) && format_time($endA) > format_time($startB);
}

function status_change_error(array $reservation, string $newStatus): ?string
{
    if (!isset(STATUSES[$newStatus])) {
        return 'Wybierz poprawny status.';
    }
    if ($reservation['status'] === $newStatus) {
        return 'Rezerwacja ma już ten status.';
    }
    if (!in_array($newStatus, STATUS_CHANGES[$reservation['status']], true)) {
        return 'Nie można zmienić statusu z „' . status_name($reservation['status'])
            . '” na „' . status_name($newStatus) . '”.';
    }
    if ($newStatus === 'zrealizowana' && $reservation['data_rezerwacji'] > date('Y-m-d')) {
        return 'Nie można oznaczyć jako zrealizowanej wizyty, która jeszcze się nie odbyła.';
    }
    return null;
}

function change_reservation_status(int $reservationId, string $oldStatus, string $newStatus, int $changedBy): void
{
    $db = db();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare('UPDATE rezerwacje SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $reservationId]);

        $stmt = $db->prepare(
            'INSERT INTO historia_statusow (rezerwacja_id, stary_status, nowy_status, zmienil_id) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$reservationId, $oldStatus, $newStatus, $changedBy]);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function reservation_filters(): array
{
    $values = [
        'od' => get_date('od'),
        'do' => get_date('do'),
        'status' => isset(STATUSES[get_string('status')]) ? get_string('status') : '',
        'pracownik' => get_int('pracownik'),
        'q' => get_string('q'),
    ];

    $where = [];
    $params = [];

    if ($values['od'] !== '') {
        $where[] = 'r.data_rezerwacji >= ?';
        $params[] = $values['od'];
    }
    if ($values['do'] !== '') {
        $where[] = 'r.data_rezerwacji <= ?';
        $params[] = $values['do'];
    }
    if ($values['status'] !== '') {
        $where[] = 'r.status = ?';
        $params[] = $values['status'];
    }
    if ($values['pracownik'] > 0) {
        $where[] = 'r.pracownik_id = ?';
        $params[] = $values['pracownik'];
    }
    if ($values['q'] !== '') {
        $where[] = "(CONCAT(u.imie, ' ', u.nazwisko) LIKE ? OR u.email LIKE ? OR u.telefon LIKE ?)";
        $pattern = like_pattern($values['q']);
        array_push($params, $pattern, $pattern, $pattern);
    }

    return [
        'values' => $values,
        'sql' => $where ? ' WHERE ' . implode(' AND ', $where) : '',
        'params' => $params,
    ];
}