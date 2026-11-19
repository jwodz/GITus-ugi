<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('klient');

$stmt = db()->prepare(
    'SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.cena, r.status, r.komentarz, r.utworzono,
            s.nazwa AS usluga, pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko, p.stanowisko'
    . RESERVATION_JOINS . ' WHERE r.id = ? AND r.uzytkownik_id = ?'
);
$stmt->execute([get_int('id'), $user['id']]);
$r = $stmt->fetch();

if (!$r) {
    flash_set('blad', 'Nie znaleziono rezerwacji.');
    redirect('klient/index.php');
}

$tytul = 'Potwierdzenie rezerwacji #' . $r['id'];
require __DIR__ . '/../../includes/layout/header.php';
?>
<article class="karta potwierdzenie">
    <div class="potwierdzenie__naglowek">
        <div>
            <p class="logo" style="color: var(--granat)"><span class="logo__znak" aria-hidden="true" style="color:#fff">+</span> SwissCar Import</p>
            <h1>Potwierdzenie rezerwacji</h1>
        </div>
        <p class="potwierdzenie__numer">Nr <strong>#<?= (int) $r['id'] ?></strong><br>z dnia <?= e(date('d.m.Y', strtotime($r['utworzono']))) ?><br><?= status_badge($r['status']) ?></p>
    </div>

    <dl class="szczegoly">
        <dt>Klient</dt><dd><?= e($user['imie'] . ' ' . $user['nazwisko']) ?><br><?= e($user['email']) ?>, <?= e($user['telefon']) ?></dd>
        <dt>Usługa</dt><dd><strong><?= e($r['usluga']) ?></strong></dd>
        <dt>Termin</dt><dd><strong><?= e(day_name((int) date('N', strtotime($r['data_rezerwacji']))) . ', ' . format_date($r['data_rezerwacji']) . ', ' . format_time($r['godzina_od']) . '–' . format_time($r['godzina_do'])) ?></strong></dd>
        <dt>Specjalista</dt><dd><?= e($r['pracownik_imie'] . ' ' . $r['pracownik_nazwisko']) ?>, <?= e($r['stanowisko']) ?></dd>
        <dt>Cena</dt><dd><strong><?= e(format_price($r['cena'])) ?></strong></dd>
        <?php if ($r['komentarz']): ?><dt>Komentarz</dt><dd><?= nl2br(e($r['komentarz'])) ?></dd><?php endif; ?>
    </dl>

    <p class="pole__podpowiedz formularz--odstep">Prosimy o przybycie kilka minut przed wizytą i zabranie dokumentów pojazdu. Wizytę można anulować lub przełożyć w panelu klienta.</p>

    <div class="przyciski-rzad">
        <button type="button" class="przycisk" onclick="window.print()">Drukuj / zapisz jako PDF</button>
        <?php if (is_upcoming($r)): ?>
            <a class="przycisk przycisk--obrys" href="<?= e(url('klient/kalendarz.php?id=' . $r['id'])) ?>">Dodaj do kalendarza</a>
        <?php endif; ?>
        <a class="przycisk przycisk--szary" href="<?= e(url('klient/index.php')) ?>">Wróć do rezerwacji</a>
    </div>
</article>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
