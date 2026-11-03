<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('administrator');

$db = db();
$today = date('Y-m-d');

$stats = [
    'klienci' => (int) $db->query("SELECT COUNT(*) FROM uzytkownicy WHERE rola = 'klient'")->fetchColumn(),
    'pracownicy' => (int) $db->query('SELECT COUNT(*) FROM pracownicy WHERE aktywny = 1')->fetchColumn(),
    'uslugi' => (int) $db->query('SELECT COUNT(*) FROM uslugi WHERE aktywna = 1')->fetchColumn(),
    'rezerwacje' => (int) $db->query('SELECT COUNT(*) FROM rezerwacje')->fetchColumn(),
];

$stmt = $db->prepare("SELECT COUNT(*) FROM rezerwacje WHERE data_rezerwacji = ? AND status <> 'anulowana'");
$stmt->execute([$today]);
$stats['dzisiaj'] = (int) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(cena), 0) FROM rezerwacje
     WHERE status = 'zrealizowana' AND data_rezerwacji BETWEEN ? AND ?"
);
$stmt->execute([date('Y-m-01'), date('Y-m-t')]);
$revenue = (float) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT r.id, r.godzina_od, r.godzina_do, r.status,
            u.imie AS klient_imie, u.nazwisko AS klient_nazwisko,
            pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko, s.nazwa AS usluga"
    . RESERVATION_JOINS .
    " WHERE r.data_rezerwacji = ? ORDER BY r.godzina_od"
);
$stmt->execute([$today]);
$todayList = $stmt->fetchAll();

$end = date('Y-m-d', strtotime('+13 days'));
$stmt = $db->prepare(
    "SELECT data_rezerwacji, COUNT(*) AS ile FROM rezerwacje
     WHERE data_rezerwacji BETWEEN ? AND ? AND status <> 'anulowana'
     GROUP BY data_rezerwacji"
);
$stmt->execute([$today, $end]);
$perDay = array_column($stmt->fetchAll(), 'ile', 'data_rezerwacji');

$days = [];
for ($i = 0; $i < 14; $i++) {
    $date = date('Y-m-d', strtotime("+{$i} days"));
    $days[$date] = (int) ($perDay[$date] ?? 0);
}
$maxDay = max(1, max($days));

$perStatus = array_fill_keys(array_keys(STATUSES), 0);
foreach ($db->query('SELECT status, COUNT(*) AS ile FROM rezerwacje GROUP BY status') as $row) {
    $perStatus[$row['status']] = (int) $row['ile'];
}
$maxStatus = max(1, max($perStatus));

$topServices = $db->query(
    "SELECT s.nazwa, COUNT(*) AS ile FROM rezerwacje r JOIN uslugi s ON s.id = r.usluga_id
     WHERE r.status <> 'anulowana'
     GROUP BY s.id, s.nazwa ORDER BY ile DESC, s.nazwa LIMIT 5"
)->fetchAll();
$maxService = max(1, (int) ($topServices[0]['ile'] ?? 1));

$lastLog = $db->query(
    'SELECT d.opis, d.utworzono, u.imie, u.nazwisko FROM dziennik_operacji d
     LEFT JOIN uzytkownicy u ON u.id = d.uzytkownik_id
     ORDER BY d.utworzono DESC, d.id DESC LIMIT 5'
)->fetchAll();

$tytul = 'Panel administratora';
$zakladka = 'pulpit';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Pulpit</h1>
<p class="podtytul">Zalogowano jako <?= e($user['imie'] . ' ' . $user['nazwisko']) ?> · <?= e(format_date($today)) ?></p>

<div class="siatka siatka--3">
    <section class="karta kafel"><p class="kafel__liczba"><?= $stats['dzisiaj'] ?></p><p class="kafel__opis">rezerwacji na dziś</p></section>
    <section class="karta kafel"><p class="kafel__liczba"><?= $stats['rezerwacje'] ?></p><p class="kafel__opis">wszystkich rezerwacji</p></section>
    <section class="karta kafel"><p class="kafel__liczba"><?= e(format_price($revenue)) ?></p><p class="kafel__opis">przychód w tym miesiącu (zrealizowane)</p></section>
    <section class="karta kafel"><p class="kafel__liczba"><?= $stats['klienci'] ?></p><p class="kafel__opis">klientów</p></section>
    <section class="karta kafel"><p class="kafel__liczba"><?= $stats['pracownicy'] ?></p><p class="kafel__opis">aktywnych pracowników</p></section>
    <section class="karta kafel"><p class="kafel__liczba"><?= $stats['uslugi'] ?></p><p class="kafel__opis">aktywnych usług</p></section>
</div>

<section class="karta sekcja">
    <div class="sekcja__naglowek">
        <h2>Rezerwacje na dziś</h2>
        <a href="<?= e(url('admin/rezerwacje.php?od=' . $today . '&do=' . $today)) ?>">Wszystkie z dzisiaj</a>
    </div>
    <?php if (!$todayList): ?>
        <p class="pusto">Na dziś nie ma żadnych rezerwacji.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Godzina</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($todayList as $r): ?>
                    <tr>
                        <td><?= e(format_time($r['godzina_od']) . '–' . format_time($r['godzina_do'])) ?></td>
                        <td><?= e($r['klient_imie'] . ' ' . $r['klient_nazwisko']) ?></td>
                        <td><?= e($r['usluga']) ?></td>
                        <td><?= e($r['pracownik_imie'] . ' ' . $r['pracownik_nazwisko']) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><a href="<?= e(url('admin/rezerwacja.php?id=' . $r['id'])) ?>">Szczegóły</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="karta sekcja">
    <h2>Wizyty w najbliższych 14 dniach</h2>
    <div class="wykres-slupki" role="img" aria-label="Liczba wizyt w kolejnych dniach">
        <?php foreach ($days as $date => $count): ?>
            <div class="slupek" title="<?= e(format_date($date)) ?>: <?= $count ?>">
                <span class="slupek__wartosc"><?= $count ?></span>
                <span class="slupek__pasek" style="height: <?= (int) round($count / $maxDay * 100) ?>%"></span>
                <span class="slupek__etykieta"><?= e(date('d.m', strtotime($date))) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="siatka siatka--2">
    <section class="karta">
        <h2>Rezerwacje według statusu</h2>
        <?php foreach ($perStatus as $status => $count): ?>
            <div class="pasek-wiersz">
                <span class="pasek-wiersz__nazwa"><?= e(status_name($status)) ?></span>
                <span class="pasek-wiersz__tor"><span class="pasek-wiersz__pasek pasek-wiersz__pasek--<?= e($status) ?>" style="width: <?= (int) round($count / $maxStatus * 100) ?>%"></span></span>
                <span class="pasek-wiersz__liczba"><?= $count ?></span>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="karta">
        <h2>Najpopularniejsze usługi</h2>
        <?php foreach ($topServices as $service): ?>
            <div class="pasek-wiersz">
                <span class="pasek-wiersz__nazwa"><?= e($service['nazwa']) ?></span>
                <span class="pasek-wiersz__tor"><span class="pasek-wiersz__pasek" style="width: <?= (int) round($service['ile'] / $maxService * 100) ?>%"></span></span>
                <span class="pasek-wiersz__liczba"><?= (int) $service['ile'] ?></span>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<section class="karta sekcja">
    <div class="sekcja__naglowek">
        <h2>Ostatnie operacje</h2>
        <a href="<?= e(url('admin/dziennik.php')) ?>">Cały dziennik</a>
    </div>
    <?php if (!$lastLog): ?>
        <p class="pusto">Brak zapisanych operacji.</p>
    <?php else: ?>
        <ul class="lista-zdarzen">
            <?php foreach ($lastLog as $entry): ?>
                <li>
                    <span class="lista-zdarzen__czas"><?= e(date('d.m.Y H:i', strtotime($entry['utworzono']))) ?></span>
                    <?= e($entry['opis']) ?>
                    <span class="lista-zdarzen__kto"><?= e(trim(($entry['imie'] ?? '') . ' ' . ($entry['nazwisko'] ?? '')) ?: 'konto usunięte') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
