<?php

const SLOT_STEP = 15;
const BOOKING_DAYS = 60;

function time_to_minutes(string $time): int
{
    [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));
    return $hours * 60 + $minutes;
}

function minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function add_minutes(string $time, int $minutes): string
{
    return minutes_to_time(time_to_minutes($time) + $minutes);
}

function generate_slots(array $workHours, array $busy, int $duration, ?string $notBefore = null): array
{
    $slots = [];

    foreach ($workHours as [$from, $to]) {
        $start = time_to_minutes($from);
        $end = time_to_minutes($to);

        for ($t = $start; $t + $duration <= $end; $t += SLOT_STEP) {
            $slotStart = minutes_to_time($t);
            $slotEnd = minutes_to_time($t + $duration);

            if ($notBefore !== null && $slotStart <= $notBefore) {
                continue;
            }

            $free = true;
            foreach ($busy as [$busyFrom, $busyTo]) {
                if (ranges_overlap($slotStart, $slotEnd, $busyFrom, $busyTo)) {
                    $free = false;
                    break;
                }
            }

            if ($free) {
                $slots[] = $slotStart;
            }
        }
    }

    sort($slots);
    return array_values(array_unique($slots));
}

function booking_last_day(): string
{
    return date('Y-m-d', strtotime('+' . (BOOKING_DAYS - 1) . ' days'));
}

function available_slots_range(int $employeeId, int $duration, string $from, string $to, int $ignoreReservationId = 0): array
{
    $today = date('Y-m-d');
    $from = max($from, $today);
    $to = min($to, booking_last_day());
    if ($from > $to) {
        return [];
    }

    $db = db();

    $stmt = $db->prepare('SELECT dzien_tygodnia, godzina_od, godzina_do FROM dostepnosc_pracownikow WHERE pracownik_id = ?');
    $stmt->execute([$employeeId]);
    $hoursByDay = [];
    foreach ($stmt->fetchAll() as $row) {
        $hoursByDay[(int) $row['dzien_tygodnia']][] = [format_time($row['godzina_od']), format_time($row['godzina_do'])];
    }

    $stmt = $db->prepare('SELECT data_od, data_do FROM dni_wolne WHERE pracownik_id = ? AND data_od <= ? AND data_do >= ?');
    $stmt->execute([$employeeId, $to, $from]);
    $daysOff = $stmt->fetchAll();

    $stmt = $db->prepare(
        "SELECT data_rezerwacji, godzina_od, godzina_do FROM rezerwacje
         WHERE pracownik_id = ? AND data_rezerwacji BETWEEN ? AND ? AND status <> 'anulowana' AND id <> ?"
    );
    $stmt->execute([$employeeId, $from, $to, $ignoreReservationId]);
    $busyByDate = [];
    foreach ($stmt->fetchAll() as $row) {
        $busyByDate[$row['data_rezerwacji']][] = [format_time($row['godzina_od']), format_time($row['godzina_do'])];
    }

    $result = [];
    for ($day = $from; $day <= $to; $day = date('Y-m-d', strtotime($day . ' +1 day'))) {
        $result[$day] = [];

        foreach ($daysOff as $off) {
            if ($day >= $off['data_od'] && $day <= $off['data_do']) {
                continue 2;
            }
        }

        $weekday = (int) date('N', strtotime($day));
        if (empty($hoursByDay[$weekday])) {
            continue;
        }

        $notBefore = $day === $today ? date('H:i') : null;
        $result[$day] = generate_slots($hoursByDay[$weekday], $busyByDate[$day] ?? [], $duration, $notBefore);
    }

    return $result;
}

function available_slots(int $employeeId, int $duration, string $date, int $ignoreReservationId = 0): array
{
    return available_slots_range($employeeId, $duration, $date, $date, $ignoreReservationId)[$date] ?? [];
}

function bookable_service(int $serviceId): array|false
{
    $stmt = db()->prepare(
        'SELECT s.id, s.nazwa, s.opis, s.czas_trwania_min, s.cena, k.nazwa AS kategoria
         FROM uslugi s JOIN kategorie_uslug k ON k.id = s.kategoria_id
         WHERE s.id = ? AND s.aktywna = 1 AND k.aktywna = 1'
    );
    $stmt->execute([$serviceId]);
    return $stmt->fetch();
}

function employees_for_service(int $serviceId): array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.stanowisko, p.opis, u.imie, u.nazwisko
         FROM pracownicy_uslugi pu
         JOIN pracownicy p ON p.id = pu.pracownik_id
         JOIN uzytkownicy u ON u.id = p.uzytkownik_id
         WHERE pu.usluga_id = ? AND p.aktywny = 1 AND u.aktywny = 1
         ORDER BY u.nazwisko, u.imie'
    );
    $stmt->execute([$serviceId]);
    return $stmt->fetchAll();
}

function bookable_employee(int $serviceId, int $employeeId): array|false
{
    foreach (employees_for_service($serviceId) as $employee) {
        if ((int) $employee['id'] === $employeeId) {
            return $employee;
        }
    }
    return false;
}

function client_has_overlap(int $clientId, string $date, string $from, string $to, int $ignoreReservationId = 0): bool
{
    $stmt = db()->prepare(
        "SELECT godzina_od, godzina_do FROM rezerwacje
         WHERE uzytkownik_id = ? AND data_rezerwacji = ? AND status <> 'anulowana' AND id <> ?"
    );
    $stmt->execute([$clientId, $date, $ignoreReservationId]);

    foreach ($stmt->fetchAll() as $row) {
        if (ranges_overlap($from, $to, $row['godzina_od'], $row['godzina_do'])) {
            return true;
        }
    }
    return false;
}

function lock_employee(int $employeeId): void
{
    $stmt = db()->prepare('SELECT id FROM pracownicy WHERE id = ? FOR UPDATE');
    $stmt->execute([$employeeId]);
}

function create_reservation(int $clientId, array $service, int $employeeId, string $date, string $time, string $comment): int|string
{
    $db = db();
    $db->beginTransaction();

    try {
        lock_employee($employeeId);

        $duration = (int) $service['czas_trwania_min'];
        if (!in_array($time, available_slots($employeeId, $duration, $date), true)) {
            $db->rollBack();
            return 'Ten termin został przed chwilą zajęty lub jest już niedostępny. Wybierz inną godzinę.';
        }

        $end = add_minutes($time, $duration);
        if (client_has_overlap($clientId, $date, $time, $end)) {
            $db->rollBack();
            return 'Masz już inną wizytę, która nakłada się na ten termin.';
        }

        $stmt = $db->prepare(
            "INSERT INTO rezerwacje (uzytkownik_id, pracownik_id, usluga_id, data_rezerwacji, godzina_od, godzina_do, cena, status, komentarz)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'oczekujaca', ?)"
        );
        $stmt->execute([$clientId, $employeeId, $service['id'], $date, $time, $end, $service['cena'], $comment !== '' ? $comment : null]);
        $id = (int) $db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO historia_statusow (rezerwacja_id, stary_status, nowy_status, zmienil_id) VALUES (?, NULL, 'oczekujaca', ?)");
        $stmt->execute([$id, $clientId]);

        $db->commit();
        return $id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function reschedule_reservation(array $reservation, string $date, string $time, int $changedBy): ?string
{
    $db = db();
    $db->beginTransaction();

    try {
        lock_employee((int) $reservation['pracownik_id']);

        $duration = (int) $reservation['czas_trwania_min'];
        $slots = available_slots((int) $reservation['pracownik_id'], $duration, $date, (int) $reservation['id']);
        if (!in_array($time, $slots, true)) {
            $db->rollBack();
            return 'Ten termin został przed chwilą zajęty lub jest już niedostępny. Wybierz inną godzinę.';
        }

        $end = add_minutes($time, $duration);
        if (client_has_overlap((int) $reservation['uzytkownik_id'], $date, $time, $end, (int) $reservation['id'])) {
            $db->rollBack();
            return 'Masz już inną wizytę, która nakłada się na ten termin.';
        }

        $stmt = $db->prepare("UPDATE rezerwacje SET data_rezerwacji = ?, godzina_od = ?, godzina_do = ?, status = 'oczekujaca' WHERE id = ?");
        $stmt->execute([$date, $time, $end, $reservation['id']]);

        if ($reservation['status'] !== 'oczekujaca') {
            $stmt = $db->prepare('INSERT INTO historia_statusow (rezerwacja_id, stary_status, nowy_status, zmienil_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$reservation['id'], $reservation['status'], 'oczekujaca', $changedBy]);
        }

        $db->commit();
        return null;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function is_upcoming(array $reservation): bool
{
    $start = $reservation['data_rezerwacji'] . ' ' . format_time($reservation['godzina_od']);
    return in_array($reservation['status'], ['oczekujaca', 'potwierdzona'], true) && $start > date('Y-m-d H:i');
}

function calendar_weeks(string $from, int $weeks): array
{
    $monday = date('Y-m-d', strtotime($from . ' -' . ((int) date('N', strtotime($from)) - 1) . ' days'));
    $days = [];
    for ($i = 0; $i < $weeks * 7; $i++) {
        $days[] = date('Y-m-d', strtotime($monday . " +{$i} days"));
    }
    return array_chunk($days, 7);
}

function month_name(string $date): string
{
    $months = [1 => 'styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec', 'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień'];
    return $months[(int) date('n', strtotime($date))] . ' ' . date('Y', strtotime($date));
}