<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

$filters = reservation_filters();
$f = $filters['values'];

$stmt = db()->prepare('SELECT COUNT(*)' . RESERVATION_JOINS . $filters['sql']);
$stmt->execute($filters['params']);
$pagination = paginate((int) $stmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT r.id, r.data_rezerwacji, r.godzina_od, r.godzina_do, r.cena, r.status,
            u.imie AS klient_imie, u.nazwisko AS klient_nazwisko, u.email AS klient_email,
            pu.imie AS pracownik_imie, pu.nazwisko AS pracownik_nazwisko, s.nazwa AS usluga'
    . RESERVATION_JOINS . $filters['sql']
    . ' ORDER BY r.data_rezerwacji DESC, r.godzina_od DESC' . sql_limit($pagination)
);
$stmt->execute($filters['params']);
$reservations = $stmt->fetchAll();

$employees = db()->query(
    'SELECT p.id, u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id ORDER BY u.nazwisko'
)->fetchAll();

$today = date('Y-m-d');
$anyFilter = $f['od'] !== '' || $f['do'] !== '' || $f['status'] !== '' || $f['pracownik'] > 0 || $f['q'] !== '';

$exportQuery = http_build_query(array_filter(
    ['od' => $f['od'], 'do' => $f['do'], 'status' => $f['status'], 'pracownik' => $f['pracownik'] ?: '', 'q' => $f['q']],
    fn($value) => $value !== ''
));

$tytul = 'Rezerwacje';
$zakladka = 'rezerwacje';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="sekcja__naglowek">
    <h1 class="tytul-strony">Rezerwacje</h1>
    <a class="przycisk przycisk--obrys" href="<?= e(url('admin/eksport.php' . ($exportQuery ? '?' . $exportQuery : ''))) ?>">Eksportuj do CSV</a>
</div>

<p class="szybkie-filtry">
    Szybki wybór:
    <a href="<?= e(url('admin/rezerwacje.php?od=' . $today . '&do=' . $today)) ?>">dzisiaj</a>
    <a href="<?= e(url('admin/rezerwacje.php?od=' . $today)) ?>">nadchodzące</a>
    <a href="<?= e(url('admin/rezerwacje.php?status=oczekujaca')) ?>">do potwierdzenia</a>
</p>

<form method="get" class="filtry">
    <div class="pole">
        <label for="od">Od dnia</label>
        <input type="date" id="od" name="od" value="<?= e($f['od']) ?>">
    </div>
    <div class="pole">
        <label for="do">Do dnia</label>
        <input type="date" id="do" name="do" value="<?= e($f['do']) ?>">
    </div>
    <div class="pole">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">Wszystkie</option>
            <?php foreach (STATUSES as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $f['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pole">
        <label for="pracownik">Pracownik</label>
        <select id="pracownik" name="pracownik">
            <option value="">Wszyscy</option>
            <?php foreach ($employees as $p): ?>
                <option value="<?= (int) $p['id'] ?>" <?= $f['pracownik'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['imie'] . ' ' . $p['nazwisko']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pole">
        <label for="q">Klient</label>
        <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Nazwisko, e-mail, telefon">
    </div>
    <button type="submit" class="przycisk przycisk--maly">Filtruj</button>
    <?php if ($anyFilter): ?><a href="<?= e(url('admin/rezerwacje.php')) ?>">Wyczyść</a><?php endif; ?>
</form>

<section class="karta karta--tabela">
    <?php if (!$reservations): ?>
        <p class="pusto">Brak rezerwacji pasujących do filtrów.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Termin</th><th>Klient</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($reservations as $r): ?>
                    <tr<?= $r['data_rezerwacji'] === $today ? ' class="tabela__dzis"' : '' ?>>
                        <td class="tabela__nowrap"><strong><?= e(format_date($r['data_rezerwacji'])) ?></strong><br><?= e(format_time($r['godzina_od']) . '–' . format_time($r['godzina_do'])) ?></td>
                        <td><?= e($r['klient_imie'] . ' ' . $r['klient_nazwisko']) ?><br><span class="tabela__opis"><?= e($r['klient_email']) ?></span></td>
                        <td><?= e($r['usluga']) ?></td>
                        <td><?= e($r['pracownik_imie'] . ' ' . $r['pracownik_nazwisko']) ?></td>
                        <td class="tabela__liczba"><?= e(format_price($r['cena'])) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('admin/rezerwacja.php?id=' . $r['id'])) ?>">Szczegóły</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= pagination_html($pagination) ?>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
