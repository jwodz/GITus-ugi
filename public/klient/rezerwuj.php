<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('klient');

function booking_url(array $params): string
{
    $params = array_filter($params, fn($value) => $value !== '' && $value !== 0 && $value !== null);
    return 'klient/rezerwuj.php' . ($params ? '?' . http_build_query($params) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('klient/rezerwuj.php');

    $serviceId = post_int('usluga');
    $employeeId = post_int('pracownik');
    $date = post_string('data');
    $time = post_string('godzina');
    $comment = post_string('komentarz');

    $service = bookable_service($serviceId);
    if (!$service) {
        flash_set('blad', 'Wybrana usługa jest niedostępna. Wybierz usługę ponownie.');
        redirect('klient/rezerwuj.php');
    }
    if (!bookable_employee($serviceId, $employeeId)) {
        flash_set('blad', 'Wybrany pracownik nie wykonuje tej usługi. Wybierz pracownika ponownie.');
        redirect(booking_url(['usluga' => $serviceId]));
    }
    if (!is_valid_date($date) || $date < date('Y-m-d') || $date > booking_last_day()) {
        flash_set('blad', 'Wybierz poprawną datę z kalendarza.');
        redirect(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId]));
    }
    if (!is_valid_time($time)) {
        flash_set('blad', 'Wybierz godzinę z listy wolnych terminów.');
        redirect(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId, 'data' => $date]));
    }
    if (mb_strlen($comment) > 500) {
        flash_set('blad', 'Komentarz może mieć najwyżej 500 znaków.');
        redirect(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId, 'data' => $date, 'godzina' => $time]));
    }

    $result = create_reservation((int) $user['id'], $service, $employeeId, $date, $time, $comment);

    if (is_string($result)) {
        flash_set('blad', $result);
        redirect(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId, 'data' => $date]));
    }

    flash_set('sukces', 'Zarezerwowano: ' . $service['nazwa'] . ', ' . format_date($date) . ' o ' . $time . '. Rezerwacja czeka na potwierdzenie.');
    redirect('klient/index.php');
}

$serviceId = get_int('usluga');
$employeeId = get_int('pracownik');
$date = get_string('data');
$time = get_string('godzina');

$service = false;
$employee = false;

if ($serviceId > 0) {
    $service = bookable_service($serviceId);
    if (!$service) {
        flash_set('blad', 'Ta usługa jest niedostępna. Wybierz inną.');
        redirect('klient/rezerwuj.php');
    }
}

if ($service && $employeeId > 0) {
    $employee = bookable_employee($serviceId, $employeeId);
    if (!$employee) {
        flash_set('blad', 'Ten pracownik nie wykonuje wybranej usługi.');
        redirect(booking_url(['usluga' => $serviceId]));
    }
}

$calendar = [];
$slots = [];
if ($employee) {
    $calendar = array_map('count', available_slots_range($employeeId, (int) $service['czas_trwania_min'], date('Y-m-d'), booking_last_day()));

    if ($date !== '') {
        if (!is_valid_date($date) || !isset($calendar[$date])) {
            flash_set('blad', 'Wybierz dzień z kalendarza (od dziś do ' . format_date(booking_last_day()) . ').');
            redirect(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId]));
        }
        $slots = available_slots($employeeId, (int) $service['czas_trwania_min'], $date);

        if ($time !== '' && !in_array($time, $slots, true)) {
            flash_set('blad', 'Godzina ' . $time . ' jest już niedostępna. Wybierz inną.');
            redirect(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId, 'data' => $date]));
        }
    } else {
        $time = '';
    }
}

if (!$service) {
    $step = 1;
} elseif (!$employee) {
    $step = 2;
} elseif ($date === '' || $time === '') {
    $step = $date === '' ? 3 : 4;
} else {
    $step = 5;
}

$services = [];
if ($step === 1) {
    $services = db()->query(
        'SELECT s.id, s.nazwa, s.opis, s.czas_trwania_min, s.cena, k.nazwa AS kategoria
         FROM uslugi s JOIN kategorie_uslug k ON k.id = s.kategoria_id
         WHERE s.aktywna = 1 AND k.aktywna = 1
           AND EXISTS (SELECT 1 FROM pracownicy_uslugi pu JOIN pracownicy p ON p.id = pu.pracownik_id
                       JOIN uzytkownicy u ON u.id = p.uzytkownik_id
                       WHERE pu.usluga_id = s.id AND p.aktywny = 1 AND u.aktywny = 1)
         ORDER BY k.nazwa, s.nazwa'
    )->fetchAll();
}

$employees = [];
if ($step === 2) {
    foreach (employees_for_service($serviceId) as $e) {
        $e['najblizszy'] = null;
        foreach (available_slots_range((int) $e['id'], (int) $service['czas_trwania_min'], date('Y-m-d'), booking_last_day()) as $day => $daySlots) {
            if ($daySlots) {
                $e['najblizszy'] = [$day, $daySlots[0]];
                break;
            }
        }
        $employees[] = $e;
    }
}

$steps = [1 => 'Usługa', 2 => 'Pracownik', 3 => 'Data', 4 => 'Godzina', 5 => 'Potwierdzenie'];
$stepLinks = [
    1 => booking_url([]),
    2 => booking_url(['usluga' => $serviceId]),
    3 => booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId]),
    4 => booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId, 'data' => $date]),
];

$tytul = 'Rezerwacja terminu';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Rezerwacja terminu</h1>

<ol class="kroki">
    <?php foreach ($steps as $number => $label): ?>
        <li class="kroki__krok<?= $number === $step ? ' kroki__krok--aktywny' : ($number < $step ? ' kroki__krok--zrobiony' : '') ?>">
            <?php if ($number < $step): ?>
                <a href="<?= e(url($stepLinks[$number])) ?>"><span class="kroki__numer"><?= $number ?></span> <?= e($label) ?></a>
            <?php else: ?>
                <span class="kroki__numer"><?= $number ?></span> <?= e($label) ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>

<?php if ($service && $step > 1): ?>
    <div class="wybor-podsumowanie">
        <span><strong><?= e($service['nazwa']) ?></strong> · <?= (int) $service['czas_trwania_min'] ?> min · <?= e(format_price($service['cena'])) ?></span>
        <?php if ($employee): ?><span>Pracownik: <strong><?= e($employee['imie'] . ' ' . $employee['nazwisko']) ?></strong></span><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($step === 1): ?>
    <h2 class="tytul-sekcji">1. Wybierz usługę</h2>
    <?php if (!$services): ?>
        <p class="pusto">Obecnie żadna usługa nie jest dostępna do rezerwacji.</p>
    <?php endif; ?>
    <?php $lastCategory = null; ?>
    <div class="siatka siatka--3">
        <?php foreach ($services as $s): ?>
            <?php if ($s['kategoria'] !== $lastCategory): $lastCategory = $s['kategoria']; ?>
                <h3 class="siatka__grupa"><?= e($s['kategoria']) ?></h3>
            <?php endif; ?>
            <a class="karta karta-wyboru" href="<?= e(url(booking_url(['usluga' => $s['id']]))) ?>">
                <strong class="karta-wyboru__tytul"><?= e($s['nazwa']) ?></strong>
                <span class="karta-wyboru__opis"><?= e($s['opis'] ?? '') ?></span>
                <span class="karta-wyboru__meta"><?= (int) $s['czas_trwania_min'] ?> min · <strong><?= e(format_price($s['cena'])) ?></strong></span>
            </a>
        <?php endforeach; ?>
    </div>

<?php elseif ($step === 2): ?>
    <h2 class="tytul-sekcji">2. Wybierz pracownika</h2>
    <?php if (!$employees): ?>
        <p class="pusto">Tej usługi nie wykonuje obecnie żaden pracownik.</p>
    <?php endif; ?>
    <div class="siatka siatka--3">
        <?php foreach ($employees as $p): ?>
            <a class="karta karta-wyboru" href="<?= e(url(booking_url(['usluga' => $serviceId, 'pracownik' => $p['id']]))) ?>">
                <strong class="karta-wyboru__tytul"><?= e($p['imie'] . ' ' . $p['nazwisko']) ?></strong>
                <span class="karta-wyboru__podtytul"><?= e($p['stanowisko']) ?></span>
                <span class="karta-wyboru__opis"><?= e($p['opis'] ?? '') ?></span>
                <span class="karta-wyboru__meta">
                    <?php if ($p['najblizszy']): ?>
                        Najbliższy wolny termin: <strong><?= e(format_date($p['najblizszy'][0]) . ', ' . $p['najblizszy'][1]) ?></strong>
                    <?php else: ?>
                        Brak wolnych terminów w najbliższych <?= BOOKING_DAYS ?> dniach
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

<?php elseif ($step === 3 || $step === 4): ?>
    <h2 class="tytul-sekcji">3. Wybierz dzień i godzinę</h2>
    <?php
    $dniKalendarza = $calendar;
    $wybranaData = $date;
    $wybraneGodziny = $slots;
    $czasTrwania = (int) $service['czas_trwania_min'];
    $linkDnia = url(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId])) . '&data=__DATA__#godziny';
    $linkGodziny = url(booking_url(['usluga' => $serviceId, 'pracownik' => $employeeId])) . '&data=__DATA__&godzina=__GODZINA__';
    $apiUrl = url('api/godziny.php?' . http_build_query(['usluga' => $serviceId, 'pracownik' => $employeeId]));
    require __DIR__ . '/../../includes/layout/kalendarz.php';
    ?>

<?php else: ?>
    <h2 class="tytul-sekcji">5. Sprawdź i potwierdź</h2>
    <section class="karta karta--formularz">
        <dl class="szczegoly">
            <dt>Usługa</dt><dd><?= e($service['nazwa']) ?> (<?= e($service['kategoria']) ?>)</dd>
            <dt>Pracownik</dt><dd><?= e($employee['imie'] . ' ' . $employee['nazwisko']) ?>, <?= e($employee['stanowisko']) ?></dd>
            <dt>Termin</dt><dd><strong><?= e(day_name((int) date('N', strtotime($date))) . ', ' . format_date($date)) ?>, <?= e($time . '–' . add_minutes($time, (int) $service['czas_trwania_min'])) ?></strong></dd>
            <dt>Cena</dt><dd><strong><?= e(format_price($service['cena'])) ?></strong></dd>
            <dt>Klient</dt><dd><?= e($user['imie'] . ' ' . $user['nazwisko']) ?>, <?= e($user['telefon']) ?></dd>
        </dl>

        <form method="post" action="<?= e(url('klient/rezerwuj.php')) ?>" class="formularz formularz--odstep">
            <?= csrf_field() ?>
            <input type="hidden" name="usluga" value="<?= (int) $serviceId ?>">
            <input type="hidden" name="pracownik" value="<?= (int) $employeeId ?>">
            <input type="hidden" name="data" value="<?= e($date) ?>">
            <input type="hidden" name="godzina" value="<?= e($time) ?>">
            <div class="pole">
                <label for="komentarz">Komentarz <span class="opcjonalne">(opcjonalnie, np. marka, model, numer VIN)</span></label>
                <textarea id="komentarz" name="komentarz" rows="3" maxlength="500"></textarea>
            </div>
            <div class="formularz__przyciski">
                <button type="submit" class="przycisk">Potwierdzam rezerwację</button>
                <a href="<?= e(url($stepLinks[4])) ?>">Zmień godzinę</a>
            </div>
        </form>
    </section>
<?php endif; ?>
<script src="<?= e(url('js/rezerwacja.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>