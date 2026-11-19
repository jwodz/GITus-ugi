<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('pracownik');

$id = get_int('id');

$stmt = db()->prepare(
    'SELECT r.*, u.imie AS klient_imie, u.nazwisko AS klient_nazwisko, u.email AS klient_email, u.telefon AS klient_telefon,
            s.nazwa AS usluga
     FROM rezerwacje r
     JOIN pracownicy p ON p.id = r.pracownik_id
     JOIN uzytkownicy u ON u.id = r.uzytkownik_id
     JOIN uslugi s ON s.id = r.usluga_id
     WHERE r.id = ? AND p.uzytkownik_id = ?'
);
$stmt->execute([$id, $user['id']]);
$visit = $stmt->fetch();

if (!$visit) {
    flash_set('blad', 'Nie znaleziono wizyty.');
    redirect('pracownik/index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('pracownik/wizyta.php?id=' . $id);

    $newStatus = post_string('status');
    $error = status_change_error($visit, $newStatus);

    if ($error === null) {
        change_reservation_status($id, $visit['status'], $newStatus, (int) $user['id']);
        log_action('wizyta_status', "Pracownik {$user['email']} zmienił status wizyty #{$id}: " . status_name($visit['status']) . ' → ' . status_name($newStatus) . '.');
        flash_set('sukces', 'Status zmieniono na „' . status_name($newStatus) . '”.');
        redirect('pracownik/wizyta.php?id=' . $id);
    }
}

$stmt = db()->prepare(
    'SELECT h.stary_status, h.nowy_status, h.utworzono, u.imie, u.nazwisko, u.rola
     FROM historia_statusow h LEFT JOIN uzytkownicy u ON u.id = h.zmienil_id
     WHERE h.rezerwacja_id = ? ORDER BY h.utworzono DESC, h.id DESC'
);
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$allowed = array_values(array_filter(
    STATUS_CHANGES[$visit['status']],
    fn($status) => status_change_error($visit, $status) === null
));

$tytul = 'Wizyta #' . $id;
require __DIR__ . '/../../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('pracownik/index.php')) ?>">Moje wizyty</a> › #<?= $id ?></p>
<div class="sekcja__naglowek">
    <h1 class="tytul-strony">Wizyta #<?= $id ?></h1>
    <?= status_badge($visit['status']) ?>
</div>

<div class="siatka siatka--2">
    <section class="karta">
        <h2>Szczegóły</h2>
        <dl class="szczegoly">
            <dt>Termin</dt><dd><strong><?= e(day_name((int) date('N', strtotime($visit['data_rezerwacji']))) . ', ' . format_date($visit['data_rezerwacji'])) ?></strong>, <?= e(format_time($visit['godzina_od']) . '–' . format_time($visit['godzina_do'])) ?></dd>
            <dt>Usługa</dt><dd><?= e($visit['usluga']) ?></dd>
            <dt>Cena</dt><dd><?= e(format_price($visit['cena'])) ?></dd>
            <dt>Klient</dt><dd><?= e($visit['klient_imie'] . ' ' . $visit['klient_nazwisko']) ?></dd>
            <dt>Telefon</dt><dd><a href="tel:<?= e($visit['klient_telefon']) ?>"><?= e($visit['klient_telefon']) ?></a></dd>
            <dt>E-mail</dt><dd><a href="mailto:<?= e($visit['klient_email']) ?>"><?= e($visit['klient_email']) ?></a></dd>
            <dt>Komentarz klienta</dt><dd><?= $visit['komentarz'] ? nl2br(e($visit['komentarz'])) : '<span class="pusto">brak</span>' ?></dd>
        </dl>
    </section>

    <section class="karta">
        <h2>Zmiana statusu</h2>
        <?php if ($error): ?>
            <div class="komunikat komunikat--blad" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (!$allowed): ?>
            <p class="pusto"><?= $visit['status'] === 'anulowana' ? 'Wizyta jest anulowana.' : 'Obecnie nie można zmienić statusu tej wizyty.' ?></p>
        <?php else: ?>
            <div class="przyciski-rzad">
                <?php foreach ($allowed as $status): ?>
                    <form method="post"<?= $status === 'anulowana' ? ' data-potwierdz="Anulować tę wizytę? Termin zwolni się dla innych klientów."' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= e($status) ?>">
                        <button type="submit" class="przycisk przycisk--maly <?= $status === 'anulowana' ? 'przycisk--usun' : ($status === 'potwierdzona' || $status === 'zrealizowana' ? '' : 'przycisk--szary') ?>">
                            <?= e(['potwierdzona' => 'Potwierdź', 'zrealizowana' => 'Oznacz jako zrealizowaną', 'anulowana' => 'Anuluj wizytę', 'oczekujaca' => 'Cofnij do oczekującej'][$status]) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="formularz__sekcja">Historia zmian</h2>
        <?php if (!$history): ?>
            <p class="pusto">Brak zapisanych zmian.</p>
        <?php else: ?>
            <ul class="lista-zdarzen">
                <?php foreach ($history as $h): ?>
                    <li>
                        <span class="lista-zdarzen__czas"><?= e(date('d.m.Y H:i', strtotime($h['utworzono']))) ?></span>
                        <?= $h['stary_status'] === null ? 'utworzono jako ' . status_badge($h['nowy_status']) : status_badge($h['stary_status']) . ' → ' . status_badge($h['nowy_status']) ?>
                        <span class="lista-zdarzen__kto"><?= $h['imie'] !== null ? e($h['imie'] . ' ' . $h['nazwisko'] . ' (' . role_name($h['rola']) . ')') : 'konto usunięte' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>