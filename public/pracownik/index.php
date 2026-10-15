<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('pracownik');

$stmt = db()->prepare('SELECT id, stanowisko FROM pracownicy WHERE uzytkownik_id = ?');
$stmt->execute([$user['id']]);
$employee = $stmt->fetch();

$today = 0;
if ($employee) {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM rezerwacje
         WHERE pracownik_id = ? AND data_rezerwacji = ? AND status <> 'anulowana'"
    );
    $stmt->execute([$employee['id'], date('Y-m-d')]);
    $today = (int) $stmt->fetchColumn();
}

$tytul = 'Panel pracownika';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Dzień dobry, <?= e($user['imie']) ?>!</h1>
<p class="podtytul">Panel pracownika<?= $employee ? ' · ' . e($employee['stanowisko']) : '' ?></p>

<?php if (!$employee): ?>
    <div class="komunikat komunikat--info">Twoje konto nie ma jeszcze profilu pracownika. Skontaktuj się z administratorem.</div>
<?php endif; ?>

<div class="siatka siatka--3">
    <section class="karta kafel">
        <p class="kafel__liczba"><?= $today ?></p>
        <p class="kafel__opis">wizyty dzisiaj</p>
    </section>

    <section class="karta kafel kafel--wkrotce">
        <h2>Moje wizyty</h2>
        <p>Lista wizyt na dziś, przyszłych i historii – moduł w przygotowaniu.</p>
    </section>

    <section class="karta kafel">
        <h2>Moje konto</h2>
        <p><?= e($user['email']) ?></p>
        <a href="<?= e(url('profil.php')) ?>">Edytuj profil</a>
    </section>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
