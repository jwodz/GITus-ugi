<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('pracownik');

$stmt = db()->prepare('SELECT id, stanowisko, aktywny FROM pracownicy WHERE uzytkownik_id = ?');
$stmt->execute([$user['id']]);
$employee = $stmt->fetch();

$tytul = 'Panel pracownika';

if (!$employee) {
    require __DIR__ . '/../../includes/layout/header.php';
    echo '<div class="komunikat komunikat--info">Twoje konto nie ma jeszcze profilu pracownika. Skontaktuj się z administratorem.</div>';
    require __DIR__ . '/../../includes/layout/footer.php';
    exit;
}

$employeeId = (int) $employee['id'];
$today = date('Y-m-d');

$views = [
    'dzis' => ['Dzisiaj', 'r.data_rezerwacji = ?', [$today], 'r.godzina_od'],
    'nadchodzace' => ['Nadchodzące', 'r.data_rezerwacji > ?', [$today], 'r.data_rezerwacji, r.godzina_od'],
    'historia' => ['Historia', 'r.data_rezerwacji < ?', [$today], 'r.data_rezerwacji DESC, r.godzina_od DESC'],
];
$view = isset($views[get_string('widok')]) ? get_string('widok') : 'dzis';
[, $condition, $params, $order] = $views[$view];

$counts = [];
$active = [];
foreach ($views as $key => [, $cond, $p]) {
    $stmt = db()->prepare("SELECT COUNT(*), COALESCE(SUM(r.status <> 'anulowana'), 0) FROM rezerwacje r WHERE r.pracownik_id = ? AND {$cond}");
    $stmt->execute([$employeeId, ...$p]);
    [$all, $notCancelled] = $stmt->fetch(PDO::FETCH_NUM);
    $counts[$key] = (int) $all;
    $active[$key] = (int) $notCancelled;
}

$where = " WHERE r.pracownik_id = ? AND {$condition}";
$stmt = db()->prepare('SELECT COUNT(*)' . RESERVATION_JOINS . $where);
$stmt->execute([$employeeId, ...$params]);
$pagination = paginate((int) $stmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.status, r.komentarz,
            u.imie AS klient_imie, u.nazwisko AS klient_nazwisko, u.telefon AS klient_telefon, s.nazwa AS usluga'
    . RESERVATION_JOINS . $where . " ORDER BY {$order}" . sql_limit($pagination)
);
$stmt->execute([$employeeId, ...$params]);
$visits = $stmt->fetchAll();

$stmt = db()->prepare('SELECT dzien_tygodnia, godzina_od, godzina_do FROM dostepnosc_pracownikow WHERE pracownik_id = ? ORDER BY dzien_tygodnia, godzina_od');
$stmt->execute([$employeeId]);
$hours = [];
foreach ($stmt->fetchAll() as $row) {
    $hours[(int) $row['dzien_tygodnia']][] = format_time($row['godzina_od']) . '–' . format_time($row['godzina_do']);
}

$stmt = db()->prepare('SELECT data_od, data_do, powod FROM dni_wolne WHERE pracownik_id = ? AND data_do >= ? ORDER BY data_od LIMIT 5');
$stmt->execute([$employeeId, $today]);
$daysOff = $stmt->fetchAll();

$stmt = db()->prepare("SELECT COUNT(*) FROM rezerwacje WHERE pracownik_id = ? AND status = 'oczekujaca' AND data_rezerwacji >= ?");
$stmt->execute([$employeeId, $today]);
$toConfirm = (int) $stmt->fetchColumn();

require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Dzień dobry, <?= e($user['imie']) ?>!</h1>
<p class="podtytul">Panel pracownika · <?= e($employee['stanowisko']) ?> · <?= e(day_name((int) date('N')) . ', ' . format_date($today)) ?></p>

<?php if (!(int) $employee['aktywny']): ?>
    <div class="komunikat komunikat--info">Twój profil jest nieaktywny – klienci nie mogą teraz rezerwować u Ciebie nowych wizyt.</div>
<?php endif; ?>

<div class="siatka siatka--3">
    <section class="karta kafel"><p class="kafel__liczba"><?= $active['dzis'] ?></p><p class="kafel__opis">wizyt dzisiaj</p></section>
    <section class="karta kafel"><p class="kafel__liczba"><?= $active['nadchodzace'] ?></p><p class="kafel__opis">nadchodzących wizyt</p></section>
    <section class="karta kafel"><p class="kafel__liczba"><?= $toConfirm ?></p><p class="kafel__opis">czeka na potwierdzenie</p></section>
</div>

<nav class="zakladki" aria-label="Wizyty">
    <?php foreach ($views as $key => [$label]): ?>
        <a href="<?= e(url('pracownik/index.php?widok=' . $key)) ?>" class="<?= $view === $key ? 'aktywna' : '' ?>"><?= e($label) ?><span class="zakladki__liczba"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<section class="karta karta--tabela sekcja">
    <?php if (!$visits): ?>
        <p class="pusto">Brak wizyt w tej zakładce.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Termin</th><th>Klient</th><th>Usługa</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($visits as $v): ?>
                    <tr>
                        <td class="tabela__nowrap"><strong><?= e(format_date($v['data_rezerwacji'])) ?></strong><br><?= e(format_time($v['godzina_od']) . '–' . format_time($v['godzina_do'])) ?></td>
                        <td><?= e($v['klient_imie'] . ' ' . $v['klient_nazwisko']) ?><br><span class="tabela__opis"><?= e($v['klient_telefon']) ?></span></td>
                        <td><?= e($v['usluga']) ?><?php if ($v['komentarz']): ?><br><span class="tabela__opis"><?= e($v['komentarz']) ?></span><?php endif; ?></td>
                        <td><?= status_badge($v['status']) ?></td>
                        <td><a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('pracownik/wizyta.php?id=' . $v['id'])) ?>">Szczegóły</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= pagination_html($pagination) ?>
</section>

<div class="siatka siatka--2">
    <section class="karta">
        <h2>Mój grafik</h2>
        <table class="tabela tabela--zwarta">
            <tbody>
            <?php foreach (DAYS as $number => $dayName): ?>
                <tr<?= $number === (int) date('N') ? ' class="tabela__dzis"' : '' ?>>
                    <th scope="row"><?= e($dayName) ?></th>
                    <td><?= isset($hours[$number]) ? e(implode(', ', $hours[$number])) : '<span class="pusto">wolne</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="karta">
        <h2>Dni wolne</h2>
        <?php if (!$daysOff): ?>
            <p class="pusto">Brak zaplanowanych dni wolnych.</p>
        <?php else: ?>
            <ul class="lista-zdarzen">
                <?php foreach ($daysOff as $off): ?>
                    <li><strong><?= e(format_date($off['data_od'])) ?><?= $off['data_od'] !== $off['data_do'] ? ' – ' . e(format_date($off['data_do'])) : '' ?></strong> <?= e($off['powod'] ?? '') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="pole__podpowiedz">Grafik i dni wolne ustawia administrator.</p>
    </section>
</div>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>