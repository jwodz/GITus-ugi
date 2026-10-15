<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('administrator');

$db = db();
$stats = [
    'klienci' => (int) $db->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'klient'")->fetchColumn(),
    'pracownicy' => (int) $db->query('SELECT COUNT(*) FROM pracownicy WHERE aktywny = 1')->fetchColumn(),
    'uslugi' => (int) $db->query('SELECT COUNT(*) FROM uslugi WHERE aktywna = 1')->fetchColumn(),
    'rezerwacje' => (int) $db->query('SELECT COUNT(*) FROM rezerwacje')->fetchColumn(),
];

$stmt = $db->prepare("SELECT COUNT(*) FROM rezerwacje WHERE data_rezerwacji = ? AND status <> 'anulowana'");
$stmt->execute([date('Y-m-d')]);
$stats['dzisiaj'] = (int) $stmt->fetchColumn();

$tiles = [
    'klienci' => 'klientów',
    'pracownicy' => 'aktywnych pracowników',
    'uslugi' => 'aktywnych usług',
    'rezerwacje' => 'wszystkich rezerwacji',
    'dzisiaj' => 'rezerwacji na dziś',
];

$tytul = 'Panel administratora';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Panel administratora</h1>
<p class="podtytul">Zalogowano jako <?= e($user['imie'] . ' ' . $user['nazwisko']) ?></p>

<div class="siatka siatka--5">
    <?php foreach ($tiles as $key => $label): ?>
        <section class="karta kafel">
            <p class="kafel__liczba"><?= $stats[$key] ?></p>
            <p class="kafel__opis"><?= e($label) ?></p>
        </section>
    <?php endforeach; ?>
</div>

<section class="karta kafel--wkrotce">
    <h2>Zarządzanie systemem</h2>
    <p>Kategorie, usługi, pracownicy, użytkownicy i rezerwacje – moduły w przygotowaniu.</p>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
