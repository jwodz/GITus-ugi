<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = require_role('administrator');

$id = get_int('id');

function load_reservation(int $id): array|false
{
    $stmt = db()->prepare(
        'SELECT r.*, u.imie AS klient_imie, u.nazwisko AS klient_nazwisko, u.email AS klient_email, u.telefon AS klient_telefon,
                pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko, p.stanowisko,
                s.nazwa AS usluga, s.czas_trwania_min'
        . RESERVATION_JOINS . ' WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$reservation = load_reservation($id);
if (!$reservation) {
    flash_set('blad', 'Nie znaleziono rezerwacji.');
    redirect('admin/rezerwacje.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/rezerwacja.php?id=' . $id);

    $newStatus = post_string('status');
    $error = status_change_error($reservation, $newStatus);

    if ($error === null) {
        change_reservation_status($id, $reservation['status'], $newStatus, (int) $admin['id']);
        log_action(
            'rezerwacja_status',
            "Rezerwacja #{$id} ({$reservation['klient_imie']} {$reservation['klient_nazwisko']}, "
            . format_date($reservation['data_rezerwacji']) . '): '
            . status_name($reservation['status']) . ' → ' . status_name($newStatus) . '.'
        );
        flash_set('sukces', 'Status zmieniono na „' . status_name($newStatus) . '”.');
        redirect('admin/rezerwacja.php?id=' . $id);
    }
}

$stmt = db()->prepare(
    'SELECT h.stary_status, h.nowy_status, h.utworzono, u.imie, u.nazwisko, u.rola
     FROM historia_statusow h LEFT JOIN uzytkownicy u ON u.id = h.zmienil_id
     WHERE h.rezerwacja_id = ? ORDER BY h.utworzono DESC, h.id DESC'
);
$stmt->execute([$id]);
$history = $stmt->fetchAll();

$allowed = STATUS_CHANGES[$reservation['status']];
$isPast = $reservation['data_rezerwacji'] < date('Y-m-d');

$tytul = 'Rezerwacja #' . $id;
$zakladka = 'rezerwacje';
require __DIR__ . '/../../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('admin/rezerwacje.php')) ?>">Rezerwacje</a> › #<?= $id ?></p>

<div class="sekcja__naglowek">
    <h1 class="tytul-strony">Rezerwacja #<?= $id ?></h1>
    <?= status_badge($reservation['status']) ?>
</div>

<div class="siatka siatka--2">
    <section class="karta">
        <h2>Szczegóły</h2>
        <dl class="szczegoly">
            <dt>Termin</dt>
            <dd><?= e(format_date($reservation['data_rezerwacji'])) ?>, <?= e(format_time($reservation['godzina_od']) . '–' . format_time($reservation['godzina_do'])) ?><?= $isPast ? ' <span class="znacznik znacznik--nie">minął</span>' : '' ?></dd>
            <dt>Usługa</dt>
            <dd><?= e($reservation['usluga']) ?> (<?= (int) $reservation['czas_trwania_min'] ?> min)</dd>
            <dt>Cena</dt>
            <dd><?= e(format_price($reservation['cena'])) ?> <span class="pole__podpowiedz">– cena z dnia rezerwacji</span></dd>
            <dt>Pracownik</dt>
            <dd><?= e($reservation['pracownik_imie'] . ' ' . $reservation['pracownik_nazwisko']) ?>, <?= e($reservation['stanowisko']) ?></dd>
            <dt>Klient</dt>
            <dd><?= e($reservation['klient_imie'] . ' ' . $reservation['klient_nazwisko']) ?><br>
                <a href="mailto:<?= e($reservation['klient_email']) ?>"><?= e($reservation['klient_email']) ?></a> · <?= e($reservation['klient_telefon']) ?></dd>
            <dt>Komentarz klienta</dt>
            <dd><?= $reservation['komentarz'] !== null && $reservation['komentarz'] !== '' ? nl2br(e($reservation['komentarz'])) : '<span class="pusto">brak</span>' ?></dd>
            <dt>Utworzono</dt>
            <dd><?= e(date('d.m.Y H:i', strtotime($reservation['utworzono']))) ?></dd>
        </dl>
    </section>

    <section class="karta">
        <h2>Zmiana statusu</h2>
        <?php if ($error): ?>
            <div class="komunikat komunikat--blad" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$allowed): ?>
            <p class="pusto">Anulowanej rezerwacji nie można przywrócić, bo jej termin mógł zająć ktoś inny. Klient musi zarezerwować nowy termin.</p>
        <?php else: ?>
            <form method="post" class="formularz">
                <?= csrf_field() ?>
                <div class="pole">
                    <label for="status">Nowy status</label>
                    <select id="status" name="status" required>
                        <?php foreach ($allowed as $value): ?>
                            <option value="<?= e($value) ?>"><?= e(status_name($value)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="przycisk">Zmień status</button>
            </form>

            <?php if (in_array('anulowana', $allowed, true)): ?>
                <form method="post" class="formularz formularz--odstep" data-potwierdz="Anulować rezerwację #<?= $id ?>? Termin zwolni się dla innych klientów.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="anulowana">
                    <button type="submit" class="przycisk przycisk--usun">Anuluj rezerwację</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <h2 class="formularz__sekcja">Historia zmian</h2>
        <?php if (!$history): ?>
            <p class="pusto">Brak zapisanych zmian statusu.</p>
        <?php else: ?>
            <ul class="lista-zdarzen">
                <?php foreach ($history as $h): ?>
                    <li>
                        <span class="lista-zdarzen__czas"><?= e(date('d.m.Y H:i', strtotime($h['utworzono']))) ?></span>
                        <?php if ($h['stary_status'] === null): ?>
                            utworzono jako <?= status_badge($h['nowy_status']) ?>
                        <?php else: ?>
                            <?= status_badge($h['stary_status']) ?> → <?= status_badge($h['nowy_status']) ?>
                        <?php endif; ?>
                        <span class="lista-zdarzen__kto">
                            <?= $h['imie'] !== null ? e($h['imie'] . ' ' . $h['nazwisko'] . ' (' . role_name($h['rola']) . ')') : 'konto usunięte' ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
