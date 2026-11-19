<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('klient');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('klient/index.php');

    $stmt = db()->prepare(
        'SELECT r.id, r.data_rezerwacji, r.godzina_od, r.status, s.nazwa AS usluga
         FROM rezerwacje r JOIN uslugi s ON s.id = r.usluga_id
         WHERE r.id = ? AND r.uzytkownik_id = ?'
    );
    $stmt->execute([post_int('id'), $user['id']]);
    $reservation = $stmt->fetch();

    if (!$reservation || post_string('akcja') !== 'anuluj') {
        flash_set('blad', 'Nie znaleziono rezerwacji.');
    } elseif (!is_upcoming($reservation)) {
        flash_set('blad', 'Można anulować tylko przyszłą rezerwację, która nie została jeszcze anulowana ani zrealizowana.');
    } else {
        change_reservation_status((int) $reservation['id'], $reservation['status'], 'anulowana', (int) $user['id']);
        log_action('rezerwacja_anulowana_klient', "Klient {$user['email']} anulował rezerwację #{$reservation['id']} (" . format_date($reservation['data_rezerwacji']) . ').');
        flash_set('sukces', 'Rezerwacja „' . $reservation['usluga'] . '” z dnia ' . format_date($reservation['data_rezerwacji']) . ' została anulowana.');
    }

    redirect('klient/index.php');
}

$stmt = db()->prepare(
    'SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.cena, r.status, r.komentarz,
            s.nazwa AS usluga, pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko'
    . RESERVATION_JOINS . ' WHERE r.uzytkownik_id = ? ORDER BY r.data_rezerwacji, r.godzina_od'
);
$stmt->execute([$user['id']]);

$upcoming = [];
$finished = [];
foreach ($stmt->fetchAll() as $row) {
    if (is_upcoming($row)) {
        $upcoming[] = $row;
    } else {
        $finished[] = $row;
    }
}
$finished = array_reverse($finished);

$view = get_string('widok') === 'historia' ? 'historia' : 'nadchodzace';
$list = $view === 'historia' ? $finished : $upcoming;

$tytul = 'Moje rezerwacje';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="sekcja__naglowek">
    <div>
        <h1 class="tytul-strony">Dzień dobry, <?= e($user['imie']) ?>!</h1>
        <p class="podtytul">Twoje rezerwacje</p>
    </div>
    <a class="przycisk" href="<?= e(url('klient/rezerwuj.php')) ?>">+ Zarezerwuj termin</a>
</div>

<div class="siatka siatka--3">
    <section class="karta kafel">
        <p class="kafel__liczba"><?= count($upcoming) ?></p>
        <p class="kafel__opis">nadchodzące rezerwacje</p>
    </section>
    <section class="karta kafel">
        <?php if ($upcoming): ?>
            <p class="kafel__opis">Najbliższa wizyta</p>
            <p><strong><?= e(format_date($upcoming[0]['data_rezerwacji']) . ', ' . format_time($upcoming[0]['godzina_od'])) ?></strong><br><?= e($upcoming[0]['usluga']) ?></p>
        <?php else: ?>
            <p class="kafel__opis">Nie masz zaplanowanych wizyt.</p>
            <a href="<?= e(url('oferta.php')) ?>">Zobacz ofertę</a>
        <?php endif; ?>
    </section>
    <section class="karta kafel">
        <p class="kafel__opis">Moje konto</p>
        <p><?= e($user['imie'] . ' ' . $user['nazwisko']) ?><br><?= e($user['email']) ?><br><?= e($user['telefon']) ?></p>
        <a href="<?= e(url('profil.php')) ?>">Edytuj profil</a>
    </section>
</div>

<nav class="zakladki" aria-label="Rodzaj rezerwacji">
    <a href="<?= e(url('klient/index.php')) ?>" class="<?= $view === 'nadchodzace' ? 'aktywna' : '' ?>">Nadchodzące<span class="zakladki__liczba"><?= count($upcoming) ?></span></a>
    <a href="<?= e(url('klient/index.php?widok=historia')) ?>" class="<?= $view === 'historia' ? 'aktywna' : '' ?>">Zakończone i anulowane<span class="zakladki__liczba"><?= count($finished) ?></span></a>
</nav>

<section class="karta karta--tabela">
    <?php if (!$list): ?>
        <p class="pusto"><?= $view === 'historia' ? 'Nie masz jeszcze zakończonych ani anulowanych rezerwacji.' : 'Nie masz nadchodzących rezerwacji.' ?></p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Termin</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th><th>Akcje</th></tr></thead>
                <tbody>
                <?php foreach ($list as $r): ?>
                    <tr>
                        <td class="tabela__nowrap"><strong><?= e(format_date($r['data_rezerwacji'])) ?></strong><br><?= e(format_time($r['godzina_od']) . '–' . format_time($r['godzina_do'])) ?></td>
                        <td><?= e($r['usluga']) ?><?php if ($r['komentarz']): ?><br><span class="tabela__opis"><?= e($r['komentarz']) ?></span><?php endif; ?></td>
                        <td><?= e($r['pracownik_imie'] . ' ' . $r['pracownik_nazwisko']) ?></td>
                        <td class="tabela__liczba"><?= e(format_price($r['cena'])) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="tabela__akcje">
                            <?php if ($view === 'nadchodzace'): ?>
                                <a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('klient/przeloz.php?id=' . $r['id'])) ?>">Przełóż</a>
                                <a class="przycisk przycisk--maly przycisk--szary" href="<?= e(url('klient/potwierdzenie.php?id=' . $r['id'])) ?>">Potwierdzenie</a>
                                <form method="post" data-potwierdz="Anulować rezerwację „<?= e($r['usluga']) ?>” z dnia <?= e(format_date($r['data_rezerwacji'])) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="akcja" value="anuluj">
                                    <button type="submit" class="przycisk przycisk--maly przycisk--usun">Anuluj</button>
                                </form>
                            <?php elseif ($r['status'] === 'zrealizowana'): ?>
                                <a class="przycisk przycisk--maly przycisk--szary" href="<?= e(url('klient/potwierdzenie.php?id=' . $r['id'])) ?>">Potwierdzenie</a>
                            <?php else: ?>
                                <span class="pusto">–</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
