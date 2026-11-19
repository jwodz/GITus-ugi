<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('klient');

$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('id') : get_int('id');

$stmt = db()->prepare(
    'SELECT r.id, r.uzytkownik_id, r.pracownik_id, r.usluga_id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.status,
            s.nazwa AS usluga, pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko'
    . RESERVATION_JOINS . ' WHERE r.id = ? AND r.uzytkownik_id = ?'
);
$stmt->execute([$id, $user['id']]);
$reservation = $stmt->fetch();

if (!$reservation) {
    flash_set('blad', 'Nie znaleziono rezerwacji.');
    redirect('klient/index.php');
}
if (!is_upcoming($reservation)) {
    flash_set('blad', 'Można przełożyć tylko przyszłą rezerwację, która nie została anulowana ani zrealizowana.');
    redirect('klient/index.php');
}
if (!bookable_employee((int) $reservation['usluga_id'], (int) $reservation['pracownik_id'])) {
    flash_set('blad', 'Tej wizyty nie można przełożyć online, bo pracownik lub usługa są obecnie niedostępni. Skontaktuj się z nami telefonicznie.');
    redirect('klient/index.php');
}

$reservation['czas_trwania_min'] = time_to_minutes($reservation['godzina_do']) - time_to_minutes($reservation['godzina_od']);
$duration = $reservation['czas_trwania_min'];
$employeeId = (int) $reservation['pracownik_id'];
$base = 'klient/przeloz.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($base);

    $date = post_string('data');
    $time = post_string('godzina');

    if (!is_valid_date($date) || !is_valid_time($time)) {
        flash_set('blad', 'Wybierz nowy termin z kalendarza.');
        redirect($base);
    }

    $error = reschedule_reservation($reservation, $date, $time, (int) $user['id']);
    if ($error !== null) {
        flash_set('blad', $error);
        redirect($base . '&data=' . $date);
    }

    $old = format_date($reservation['data_rezerwacji']) . ' ' . format_time($reservation['godzina_od']);
    log_action('rezerwacja_przelozona', "Klient {$user['email']} przełożył rezerwację #{$id} z {$old} na " . format_date($date) . " {$time}.");
    flash_set('sukces', 'Wizyta została przełożona na ' . format_date($date) . ', ' . $time . '. Czeka na ponowne potwierdzenie.');
    redirect('klient/index.php');
}

$calendar = array_map('count', available_slots_range($employeeId, $duration, date('Y-m-d'), booking_last_day(), $id));
$date = get_string('data');
$time = get_string('godzina');
$slots = [];

if ($date !== '') {
    if (!is_valid_date($date) || !isset($calendar[$date])) {
        flash_set('blad', 'Wybierz dzień z kalendarza.');
        redirect($base);
    }
    $slots = available_slots($employeeId, $duration, $date, $id);
    if ($time !== '' && !in_array($time, $slots, true)) {
        flash_set('blad', 'Godzina ' . $time . ' jest już niedostępna. Wybierz inną.');
        redirect($base . '&data=' . $date);
    }
} else {
    $time = '';
}

$tytul = 'Przełożenie wizyty';
require __DIR__ . '/../../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('klient/index.php')) ?>">Moje rezerwacje</a> › Przełożenie wizyty</p>
<h1 class="tytul-strony">Przełożenie wizyty</h1>

<div class="wybor-podsumowanie">
    <span><strong><?= e($reservation['usluga']) ?></strong> · <?= $duration ?> min</span>
    <span>Pracownik: <strong><?= e($reservation['pracownik_imie'] . ' ' . $reservation['pracownik_nazwisko']) ?></strong></span>
    <span>Obecny termin: <strong><?= e(format_date($reservation['data_rezerwacji']) . ', ' . format_time($reservation['godzina_od']) . '–' . format_time($reservation['godzina_do'])) ?></strong></span>
</div>

<?php if ($time !== ''): ?>
    <section class="karta karta--formularz">
        <h2>Potwierdź nowy termin</h2>
        <dl class="szczegoly">
            <dt>Było</dt><dd><s><?= e(format_date($reservation['data_rezerwacji']) . ', ' . format_time($reservation['godzina_od'])) ?></s></dd>
            <dt>Będzie</dt><dd><strong><?= e(day_name((int) date('N', strtotime($date))) . ', ' . format_date($date) . ', ' . $time . '–' . add_minutes($time, $duration)) ?></strong></dd>
        </dl>
        <p class="pole__podpowiedz">Cena się nie zmienia. Po przełożeniu wizyta wraca do statusu „Oczekująca” i czeka na potwierdzenie.</p>
        <form method="post" action="<?= e(url('klient/przeloz.php')) ?>" class="formularz formularz--odstep">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="data" value="<?= e($date) ?>">
            <input type="hidden" name="godzina" value="<?= e($time) ?>">
            <div class="formularz__przyciski">
                <button type="submit" class="przycisk">Przełóż wizytę</button>
                <a href="<?= e(url($base . '&data=' . $date)) ?>">Wybierz inną godzinę</a>
            </div>
        </form>
    </section>
<?php else: ?>
    <?php
    $dniKalendarza = $calendar;
    $wybranaData = $date;
    $wybraneGodziny = $slots;
    $czasTrwania = $duration;
    $linkDnia = url($base) . '&data=__DATA__#godziny';
    $linkGodziny = url($base) . '&data=__DATA__&godzina=__GODZINA__';
    $apiUrl = url('api/godziny.php?' . http_build_query(['usluga' => $reservation['usluga_id'], 'pracownik' => $employeeId, 'pomin' => $id]));
    require __DIR__ . '/../../includes/layout/kalendarz.php';
    ?>
    <script src="<?= e(url('js/rezerwacja.js')) ?>" defer></script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
