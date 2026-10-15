<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('klient');

$stmt = db()->prepare(
    "SELECT COUNT(*) FROM rezerwacje
     WHERE uzytkownik_id = ? AND data_rezerwacji >= ? AND status IN ('oczekujaca', 'potwierdzona')"
);
$stmt->execute([$user['id'], date('Y-m-d')]);
$upcoming = (int) $stmt->fetchColumn();

$tytul = 'Panel klienta';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Dzień dobry, <?= e($user['imie']) ?>!</h1>
<p class="podtytul">Panel klienta</p>

<div class="siatka siatka--3">
    <section class="karta kafel">
        <p class="kafel__liczba"><?= $upcoming ?></p>
        <p class="kafel__opis">nadchodzące rezerwacje</p>
    </section>

    <section class="karta kafel">
        <h2>Moje konto</h2>
        <p><?= e($user['imie'] . ' ' . $user['nazwisko']) ?><br><?= e($user['email']) ?><br><?= e($user['telefon']) ?></p>
        <a href="<?= e(url('profil.php')) ?>">Edytuj profil</a>
    </section>

    <section class="karta kafel kafel--wkrotce">
        <h2>Rezerwacja terminu</h2>
        <p>Wybór usługi, pracownika i terminu – moduł w przygotowaniu.</p>
    </section>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
