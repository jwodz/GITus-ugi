<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

$q = get_string('q');
$where = '';
$params = [];
if ($q !== '') {
    $where = " WHERE d.opis LIKE ? OR d.akcja LIKE ? OR CONCAT(u.imie, ' ', u.nazwisko) LIKE ?";
    $params = [like_pattern($q), like_pattern($q), like_pattern($q)];
}

$stmt = db()->prepare('SELECT COUNT(*) FROM dziennik_operacji d LEFT JOIN uzytkownicy u ON u.id = d.uzytkownik_id' . $where);
$stmt->execute($params);
$pagination = paginate((int) $stmt->fetchColumn(), 20);

$stmt = db()->prepare(
    'SELECT d.akcja, d.opis, d.utworzono, u.imie, u.nazwisko
     FROM dziennik_operacji d LEFT JOIN uzytkownicy u ON u.id = d.uzytkownik_id'
    . $where . ' ORDER BY d.utworzono DESC, d.id DESC' . sql_limit($pagination)
);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$tytul = 'Dziennik operacji';
$zakladka = 'dziennik';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Dziennik operacji</h1>
<p class="podtytul">Kto, co i kiedy zmienił w panelu administratora.</p>

<form method="get" class="filtry">
    <div class="pole">
        <label for="q">Szukaj</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Treść, rodzaj operacji lub osoba">
    </div>
    <button type="submit" class="przycisk przycisk--maly">Szukaj</button>
    <?php if ($q !== ''): ?><a href="<?= e(url('admin/dziennik.php')) ?>">Wyczyść</a><?php endif; ?>
</form>

<section class="karta karta--tabela">
    <?php if (!$entries): ?>
        <p class="pusto">Brak wpisów<?= $q !== '' ? ' pasujących do wyszukiwania' : '' ?>.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Kiedy</th><th>Kto</th><th>Rodzaj</th><th>Opis</th></tr></thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="tabela__nowrap"><?= e(date('d.m.Y H:i', strtotime($entry['utworzono']))) ?></td>
                        <td><?= $entry['imie'] !== null ? e($entry['imie'] . ' ' . $entry['nazwisko']) : '<span class="pusto">konto usunięte</span>' ?></td>
                        <td><code class="kod"><?= e($entry['akcja']) ?></code></td>
                        <td><?= e($entry['opis']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= pagination_html($pagination) ?>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
