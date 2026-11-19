<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../includes/panel.php';
require __DIR__ . '/../includes/reservations.php';
require __DIR__ . '/../includes/availability.php';

date_default_timezone_set('Europe/Warsaw');

$passed = 0;
$failed = 0;

function test(string $name, mixed $actual, mixed $expected): void
{
    global $passed, $failed;

    if ($actual === $expected) {
        $passed++;
        echo "  OK    {$name}\n";
        return;
    }

    $failed++;
    echo "  BŁĄD  {$name}\n        oczekiwano: " . var_export($expected, true) . "\n        otrzymano:  " . var_export($actual, true) . "\n";
}

function section(string $title): void
{
    echo "\n{$title}\n";
}

section('Kolizje terminów (przykład z regulaminu, istniejąca wizyta 10:00–11:00)');
foreach ([['09:30', '10:30'], ['10:00', '11:00'], ['10:15', '10:45'], ['10:30', '11:30'], ['09:30', '11:30']] as [$from, $to]) {
    test("{$from}–{$to} koliduje", ranges_overlap($from, $to, '10:00', '11:00'), true);
}
foreach ([['09:00', '10:00'], ['11:00', '12:00'], ['08:00', '09:30'], ['11:30', '12:30']] as [$from, $to]) {
    test("{$from}–{$to} nie koliduje", ranges_overlap($from, $to, '10:00', '11:00'), false);
}
test('format z sekundami z bazy (10:30:00–11:30:00)', ranges_overlap('10:30:00', '11:30:00', '10:00', '11:00'), true);

section('Wyznaczanie wolnych godzin');
test(
    'praca 9–12, wizyta 60 min, zajęte 10–11',
    generate_slots([['09:00', '12:00']], [['10:00', '11:00']], 60),
    ['09:00', '11:00']
);
test(
    'wizyta 30 min co 15 minut, zajęte 10–11',
    generate_slots([['09:00', '11:30']], [['10:00', '11:00']], 30),
    ['09:00', '09:15', '09:30', '11:00']
);
test(
    'wizyta nie może wyjść poza godziny pracy',
    generate_slots([['09:00', '10:00']], [], 45),
    ['09:00', '09:15']
);
test(
    'przerwa w pracy (9–12 i 12:30–15), wizyta 120 min',
    generate_slots([['09:00', '12:00'], ['12:30', '15:00']], [], 120),
    ['09:00', '09:15', '09:30', '09:45', '10:00', '12:30', '12:45', '13:00']
);
test(
    'dziś po 10:20 – wcześniejsze godziny są niedostępne',
    generate_slots([['09:00', '12:00']], [], 60, '10:20'),
    ['10:30', '10:45', '11:00']
);
test(
    'cały dzień zajęty',
    generate_slots([['09:00', '11:00']], [['09:00', '11:00']], 30),
    []
);
test('dodawanie minut 09:45 + 90', add_minutes('09:45', 90), '11:15');
test('zamiana na minuty 13:30', time_to_minutes('13:30'), 810);

section('Zmiany statusu rezerwacji');
$future = ['status' => 'oczekujaca', 'data_rezerwacji' => date('Y-m-d', strtotime('+3 days'))];
$past = ['status' => 'potwierdzona', 'data_rezerwacji' => date('Y-m-d', strtotime('-3 days'))];
test('oczekująca → potwierdzona', status_change_error($future, 'potwierdzona'), null);
test('przyszła wizyta nie może być zrealizowana', status_change_error($future, 'zrealizowana') !== null, true);
test('przeszła wizyta może być zrealizowana', status_change_error($past, 'zrealizowana'), null);
test('anulowanej nie da się przywrócić', status_change_error(['status' => 'anulowana', 'data_rezerwacji' => $future['data_rezerwacji']], 'potwierdzona') !== null, true);
test('nieznany status', status_change_error($future, 'xyz'), 'Wybierz poprawny status.');
test('ten sam status', status_change_error($future, 'oczekujaca'), 'Rezerwacja ma już ten status.');

section('Walidacja danych');
test('poprawne hasło', validate_password('Test1234'), null);
test('hasło za krótkie', validate_password('Ab1') !== null, true);
test('hasło bez cyfry', validate_password('Abcdefghij') !== null, true);
test('telefon z odstępami', normalize_phone('500 100-200'), '500100200');
test('telefon +41', validate_phone('+41791234567'), null);
test('telefon z literami', validate_phone('12abc') !== null, true);
test('imię z polskimi znakami', validate_name('Łucja-Żaneta', 'Imię', 50), null);
test('imię z kodem HTML', validate_name('<b>Jan</b>', 'Imię', 50) !== null, true);
test('e-mail bez domeny', validate_email('jan@') !== null, true);
test('data 2026-02-30 jest błędna', is_valid_date('2026-02-30'), false);
test('data 2026-02-28 jest poprawna', is_valid_date('2026-02-28'), true);
test('godzina 24:00 jest błędna', is_valid_time('24:00'), false);
test('godzina 09:15 jest poprawna', is_valid_time('09:15'), true);

section('Pozostałe');
test('odmiana: 1 usługa', plural(1, 'usługa', 'usługi', 'usług'), '1 usługa');
test('odmiana: 3 usługi', plural(3, 'usługa', 'usługi', 'usług'), '3 usługi');
test('odmiana: 12 usług', plural(12, 'usługa', 'usługi', 'usług'), '12 usług');
test('odmiana: 22 usługi', plural(22, 'usługa', 'usługi', 'usług'), '22 usługi');
test('wyszukiwanie: % traktowany dosłownie', like_pattern('50%'), '%50\%%');
test('XSS: znaki HTML zamienione', e('<script>"x"</script>'), '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');
test('cena 1234.5', format_price('1234.5'), '1 234,50 zł');

echo "\nWynik: {$passed} zaliczonych, {$failed} niezaliczonych\n";
exit($failed === 0 ? 0 : 1);
