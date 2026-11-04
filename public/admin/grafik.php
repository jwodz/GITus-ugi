<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

$id = get_int('id');
$stmt = db()->prepare(
    'SELECT p.id, p.stanowisko, u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id WHERE p.id = ?'
);
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    flash_set('blad', 'Nie znaleziono pracownika.');
    redirect('admin/pracownicy.php');
}

$name = $employee['imie'] . ' ' . $employee['nazwisko'];
$back = 'admin/grafik.php?id=' . $id;
$hoursForm = ['dzien' => 1, 'od' => '09:00', 'do' => '17:00'];
$hoursErrors = [];
$offForm = ['data_od' => '', 'data_do' => '', 'powod' => ''];
$offErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($back);
    $action = post_string('akcja');

    if ($action === 'dodaj_godziny') {
        $hoursForm = ['dzien' => post_int('dzien'), 'od' => post_string('od'), 'do' => post_string('do')];

        if (!isset(DAYS[$hoursForm['dzien']])) {
            $hoursErrors['dzien'] = 'Wybierz dzień tygodnia.';
        }
        if (!is_valid_time($hoursForm['od']) || !is_valid_time($hoursForm['do'])) {
            $hoursErrors['od'] = 'Podaj godziny w formacie GG:MM.';
        } elseif ($hoursForm['do'] <= $hoursForm['od']) {
            $hoursErrors['od'] = 'Godzina zakończenia musi być późniejsza niż rozpoczęcia.';
        }

        if (!$hoursErrors) {
            $stmt = db()->prepare('SELECT godzina_od, godzina_do FROM dostepnosc_pracownikow WHERE pracownik_id = ? AND dzien_tygodnia = ?');
            $stmt->execute([$id, $hoursForm['dzien']]);
            foreach ($stmt->fetchAll() as $existing) {
                if (ranges_overlap($hoursForm['od'], $hoursForm['do'], $existing['godzina_od'], $existing['godzina_do'])) {
                    $hoursErrors['od'] = 'Te godziny nachodzą na istniejący przedział '
                        . format_time($existing['godzina_od']) . '–' . format_time($existing['godzina_do']) . '.';
                    break;
                }
            }
        }

        if (!$hoursErrors) {
            $stmt = db()->prepare('INSERT INTO dostepnosc_pracownikow (pracownik_id, dzien_tygodnia, godzina_od, godzina_do) VALUES (?, ?, ?, ?)');
            $stmt->execute([$id, $hoursForm['dzien'], $hoursForm['od'], $hoursForm['do']]);
            $text = day_name($hoursForm['dzien']) . " {$hoursForm['od']}–{$hoursForm['do']}";
            log_action('grafik_dodany', "Dodano godziny pracy: {$name}, {$text}.");
            flash_set('sukces', "Dodano godziny pracy: {$text}.");
            redirect($back);
        }
    } elseif ($action === 'usun_godziny') {
        $stmt = db()->prepare('SELECT dzien_tygodnia, godzina_od, godzina_do FROM dostepnosc_pracownikow WHERE id = ? AND pracownik_id = ?');
        $stmt->execute([post_int('wpis'), $id]);
        $entry = $stmt->fetch();

        if ($entry) {
            $stmt = db()->prepare('DELETE FROM dostepnosc_pracownikow WHERE id = ? AND pracownik_id = ?');
            $stmt->execute([post_int('wpis'), $id]);
            $text = day_name((int) $entry['dzien_tygodnia']) . ' ' . format_time($entry['godzina_od']) . '–' . format_time($entry['godzina_do']);
            log_action('grafik_usuniety', "Usunięto godziny pracy: {$name}, {$text}.");
            flash_set('sukces', "Usunięto godziny pracy: {$text}.");
        } else {
            flash_set('blad', 'Nie znaleziono tego wpisu w grafiku.');
        }
        redirect($back);
    } elseif ($action === 'dodaj_wolne') {
        $offForm = ['data_od' => post_string('data_od'), 'data_do' => post_string('data_do'), 'powod' => post_string('powod')];

        if (!is_valid_date($offForm['data_od']) || !is_valid_date($offForm['data_do'])) {
            $offErrors['data_od'] = 'Podaj poprawne daty.';
        } elseif ($offForm['data_do'] < $offForm['data_od']) {
            $offErrors['data_od'] = 'Data końca nie może być wcześniejsza niż data początku.';
        } elseif ($offForm['data_do'] < date('Y-m-d')) {
            $offErrors['data_od'] = 'Nie można dodać dni wolnych, które już minęły.';
        }
        if (mb_strlen($offForm['powod']) > 150) {
            $offErrors['powod'] = 'Powód może mieć najwyżej 150 znaków.';
        }

        if (!$offErrors) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM dni_wolne WHERE pracownik_id = ? AND data_od <= ? AND data_do >= ?');
            $stmt->execute([$id, $offForm['data_do'], $offForm['data_od']]);
            if ((int) $stmt->fetchColumn() > 0) {
                $offErrors['data_od'] = 'Ten okres nachodzi na już dodane dni wolne.';
            }
        }

        if (!$offErrors) {
            $stmt = db()->prepare('INSERT INTO dni_wolne (pracownik_id, data_od, data_do, powod) VALUES (?, ?, ?, ?)');
            $stmt->execute([$id, $offForm['data_od'], $offForm['data_do'], $offForm['powod'] ?: null]);

            $text = format_date($offForm['data_od']) . '–' . format_date($offForm['data_do']);
            log_action('dzien_wolny_dodany', "Dodano dni wolne: {$name}, {$text}.");
            flash_set('sukces', "Dodano dni wolne: {$text}.");

            $stmt = db()->prepare(
                "SELECT COUNT(*) FROM rezerwacje WHERE pracownik_id = ? AND data_rezerwacji BETWEEN ? AND ? AND status IN ('oczekujaca', 'potwierdzona')"
            );
            $stmt->execute([$id, $offForm['data_od'], $offForm['data_do']]);
            $conflicts = (int) $stmt->fetchColumn();
            if ($conflicts > 0) {
                flash_set('info', "Uwaga: w tym okresie {$name} ma " . plural($conflicts, 'umówioną wizytę', 'umówione wizyty', 'umówionych wizyt') . '. Przełóż je lub anuluj w zakładce Rezerwacje.');
            }
            redirect($back);
        }
    } elseif ($action === 'usun_wolne') {
        $stmt = db()->prepare('DELETE FROM dni_wolne WHERE id = ? AND pracownik_id = ?');
        $stmt->execute([post_int('wpis'), $id]);
        if ($stmt->rowCount() > 0) {
            log_action('dzien_wolny_usuniety', "Usunięto dni wolne pracownika {$name}.");
            flash_set('sukces', 'Usunięto dni wolne.');
        } else {
            flash_set('blad', 'Nie znaleziono tego wpisu.');
        }
        redirect($back);
    }
}

$stmt = db()->prepare('SELECT id, dzien_tygodnia, godzina_od, godzina_do FROM dostepnosc_pracownikow WHERE pracownik_id = ? ORDER BY dzien_tygodnia, godzina_od');
$stmt->execute([$id]);
$hoursByDay = [];
foreach ($stmt->fetchAll() as $row) {
    $hoursByDay[(int) $row['dzien_tygodnia']][] = $row;
}

$stmt = db()->prepare('SELECT id, data_od, data_do, powod FROM dni_wolne WHERE pracownik_id = ? AND data_do >= ? ORDER BY data_od');
$stmt->execute([$id, date('Y-m-d')]);
$daysOff = $stmt->fetchAll();

$tytul = 'Grafik: ' . $name;
$zakladka = 'pracownicy';
require __DIR__ . '/../../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('admin/pracownicy.php')) ?>">Pracownicy</a> › Grafik</p>
<div class="sekcja__naglowek">
    <div>
        <h1 class="tytul-strony"><?= e($name) ?></h1>
        <p class="podtytul"><?= e($employee['stanowisko']) ?></p>
    </div>
    <a href="<?= e(url('admin/pracownik.php?id=' . $id)) ?>">Dane i usługi →</a>
</div>

<div class="siatka siatka--2">
    <section class="karta">
        <h2>Godziny pracy w tygodniu</h2>
        <table class="tabela tabela--zwarta">
            <tbody>
            <?php foreach (DAYS as $number => $dayName): ?>
                <tr>
                    <th scope="row"><?= e($dayName) ?></th>
                    <td>
                        <?php if (empty($hoursByDay[$number])): ?>
                            <span class="pusto">wolne</span>
                        <?php else: ?>
                            <?php foreach ($hoursByDay[$number] as $h): ?>
                                <form method="post" class="godziny" data-potwierdz="Usunąć godziny <?= e(format_time($h['godzina_od']) . '–' . format_time($h['godzina_do'])) ?> (<?= e($dayName) ?>)?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="akcja" value="usun_godziny">
                                    <input type="hidden" name="wpis" value="<?= (int) $h['id'] ?>">
                                    <?= e(format_time($h['godzina_od']) . '–' . format_time($h['godzina_do'])) ?>
                                    <button type="submit" class="godziny__usun" aria-label="Usuń te godziny">×</button>
                                </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3 class="formularz__sekcja">Dodaj godziny</h3>
        <form method="post" class="formularz formularz--wiersz">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="dodaj_godziny">
            <div class="pole">
                <label for="dzien">Dzień</label>
                <select id="dzien" name="dzien" <?= field_attrs($hoursErrors, 'dzien') ?>>
                    <?php foreach (DAYS as $number => $dayName): ?>
                        <option value="<?= $number ?>" <?= (int) $hoursForm['dzien'] === $number ? 'selected' : '' ?>><?= e($dayName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="pole">
                <label for="od">Od</label>
                <input type="time" id="od" name="od" value="<?= e($hoursForm['od']) ?>" required <?= field_attrs($hoursErrors, 'od') ?>>
            </div>
            <div class="pole">
                <label for="do">Do</label>
                <input type="time" id="do" name="do" value="<?= e($hoursForm['do']) ?>" required>
            </div>
            <button type="submit" class="przycisk przycisk--maly">Dodaj</button>
        </form>
        <?= field_error($hoursErrors, 'dzien') ?>
        <?= field_error($hoursErrors, 'od') ?>
        <p class="pole__podpowiedz">Przerwę w pracy zapisz jako dwa przedziały, np. 9:00–12:00 i 12:30–15:00.</p>
    </section>

    <section class="karta">
        <h2>Dni wolne i urlopy</h2>
        <?php if (!$daysOff): ?>
            <p class="pusto">Brak zaplanowanych dni wolnych.</p>
        <?php else: ?>
            <table class="tabela tabela--zwarta">
                <thead><tr><th>Okres</th><th>Powód</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($daysOff as $off): ?>
                    <tr>
                        <td><?= e(format_date($off['data_od'])) ?><?= $off['data_od'] !== $off['data_do'] ? ' – ' . e(format_date($off['data_do'])) : '' ?></td>
                        <td><?= e($off['powod'] ?? '') ?></td>
                        <td>
                            <form method="post" data-potwierdz="Usunąć te dni wolne?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="akcja" value="usun_wolne">
                                <input type="hidden" name="wpis" value="<?= (int) $off['id'] ?>">
                                <button type="submit" class="przycisk przycisk--maly przycisk--usun">Usuń</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 class="formularz__sekcja">Dodaj dni wolne</h3>
        <form method="post" class="formularz">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="dodaj_wolne">
            <div class="formularz__wiersz">
                <div class="pole">
                    <label for="data_od">Od dnia</label>
                    <input type="date" id="data_od" name="data_od" value="<?= e($offForm['data_od']) ?>" min="<?= date('Y-m-d') ?>" required <?= field_attrs($offErrors, 'data_od') ?>>
                </div>
                <div class="pole">
                    <label for="data_do">Do dnia</label>
                    <input type="date" id="data_do" name="data_do" value="<?= e($offForm['data_do']) ?>" min="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <?= field_error($offErrors, 'data_od') ?>
            <div class="pole">
                <label for="powod">Powód <span class="opcjonalne">(opcjonalnie)</span></label>
                <input type="text" id="powod" name="powod" value="<?= e($offForm['powod']) ?>" maxlength="150" placeholder="np. urlop, szkolenie" <?= field_attrs($offErrors, 'powod') ?>>
                <?= field_error($offErrors, 'powod') ?>
            </div>
            <button type="submit" class="przycisk przycisk--maly">Dodaj dni wolne</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
