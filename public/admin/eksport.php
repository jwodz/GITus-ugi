<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

$filters = reservation_filters();

$stmt = db()->prepare(
    'SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.cena, r.status, r.komentarz,
            u.imie AS klient_imie, u.nazwisko AS klient_nazwisko, u.email AS klient_email, u.telefon AS klient_telefon,
            pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko, s.nazwa AS usluga'
    . RESERVATION_JOINS . $filters['sql'] . ' ORDER BY r.data_rezerwacji, r.godzina_od'
);
$stmt->execute($filters['params']);
$rows = $stmt->fetchAll();

function csv_safe(?string $value): string
{
    $value = $value ?? '';
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

log_action('eksport', 'Wyeksportowano ' . count($rows) . ' rezerwacji do pliku CSV.');

$fileName = 'rezerwacje-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Nr', 'Data', 'Od', 'Do', 'Klient', 'E-mail', 'Telefon', 'Usługa', 'Pracownik', 'Cena (zł)', 'Status', 'Komentarz'], ';', '"', '');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        format_date($r['data_rezerwacji']),
        format_time($r['godzina_od']),
        format_time($r['godzina_do']),
        csv_safe($r['klient_imie'] . ' ' . $r['klient_nazwisko']),
        csv_safe($r['klient_email']),
        csv_safe($r['klient_telefon']),
        csv_safe($r['usluga']),
        csv_safe($r['pracownik_imie'] . ' ' . $r['pracownik_nazwisko']),
        number_format((float) $r['cena'], 2, ',', ''),
        status_name($r['status']),
        csv_safe($r['komentarz']),
    ], ';', '"', '');
}

fclose($out);
