<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

function json_response(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
if ($user === null || $user['rola'] !== 'klient') {
    json_response(403, ['blad' => 'Brak dostępu.']);
}

$service = bookable_service(get_int('usluga'));
if (!$service) {
    json_response(404, ['blad' => 'Ta usługa jest niedostępna.']);
}

$employeeId = get_int('pracownik');
if (!bookable_employee((int) $service['id'], $employeeId)) {
    json_response(404, ['blad' => 'Ten pracownik nie wykonuje wybranej usługi.']);
}

$date = get_date('data');
if ($date === '' || $date < date('Y-m-d') || $date > booking_last_day()) {
    json_response(422, ['blad' => 'Wybierz datę od dziś do ' . format_date(booking_last_day()) . '.']);
}

$ignore = get_int('pomin');
if ($ignore > 0) {
    $stmt = db()->prepare("SELECT id FROM rezerwacje WHERE id = ? AND uzytkownik_id = ?");
    $stmt->execute([$ignore, $user['id']]);
    if (!$stmt->fetch()) {
        $ignore = 0;
    }
}

$slots = available_slots($employeeId, (int) $service['czas_trwania_min'], $date, $ignore);

json_response(200, [
    'data' => $date,
    'dataTekst' => day_name((int) date('N', strtotime($date))) . ', ' . format_date($date),
    'czasTrwania' => (int) $service['czas_trwania_min'],
    'godziny' => array_map(fn($slot) => ['od' => $slot, 'do' => add_minutes($slot, (int) $service['czas_trwania_min'])], $slots),
]);