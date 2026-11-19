<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('klient');

$stmt = db()->prepare(
    'SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.status, s.nazwa AS usluga,
            pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko'
    . RESERVATION_JOINS . ' WHERE r.id = ? AND r.uzytkownik_id = ?'
);
$stmt->execute([get_int('id'), $user['id']]);
$r = $stmt->fetch();

if (!$r || !is_upcoming($r)) {
    flash_set('blad', 'Do kalendarza można dodać tylko swoją nadchodzącą rezerwację.');
    redirect('klient/index.php');
}

function ics_text(string $text): string
{
    return str_replace(['\\', ';', ',', "\r", "\n"], ['\\\\', '\\;', '\\,', '', '\\n'], $text);
}

function ics_time(string $date, string $time): string
{
    return (new DateTime($date . ' ' . format_time($time), new DateTimeZone('Europe/Warsaw')))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Ymd\THis\Z');
}

$host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'swisscar.local');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//SwissCar Import//Rezerwacje//PL',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:rezerwacja-' . $r['id'] . '@' . $host,
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . ics_time($r['data_rezerwacji'], $r['godzina_od']),
    'DTEND:' . ics_time($r['data_rezerwacji'], $r['godzina_do']),
    'SUMMARY:' . ics_text('SwissCar Import: ' . $r['usluga']),
    'DESCRIPTION:' . ics_text('Rezerwacja #' . $r['id'] . ', specjalista: ' . $r['pracownik_imie'] . ' ' . $r['pracownik_nazwisko'] . ', status: ' . status_name($r['status'])),
    'BEGIN:VALARM',
    'TRIGGER:-PT1H',
    'ACTION:DISPLAY',
    'DESCRIPTION:' . ics_text('Za godzinę wizyta w SwissCar Import'),
    'END:VALARM',
    'END:VEVENT',
    'END:VCALENDAR',
];

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="wizyta-' . $r['id'] . '.ics"');
echo implode("\r\n", $lines) . "\r\n";
