<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
$canBook = $user === null || $user['rola'] === 'klient';

$categories = db()->query(
    'SELECT k.id, k.nazwa, COUNT(s.id) AS liczba
     FROM kategorie_uslug k JOIN uslugi s ON s.kategoria_id = k.id AND s.aktywna = 1
     WHERE k.aktywna = 1
     GROUP BY k.id, k.nazwa ORDER BY k.nazwa'
)->fetchAll();

$categoryId = get_int('kategoria');
$q = get_string('q');

$where = ['s.aktywna = 1', 'k.aktywna = 1'];
$params = [];
if ($categoryId > 0) {
    $where[] = 's.kategoria_id = ?';
    $params[] = $categoryId;
}
if ($q !== '') {
    $where[] = '(s.nazwa LIKE ? OR s.opis LIKE ?)';
    array_push($params, like_pattern($q), like_pattern($q));
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

$stmt = db()->prepare('SELECT COUNT(*) FROM uslugi s JOIN kategorie_uslug k ON k.id = s.kategoria_id' . $whereSql);
$stmt->execute($params);
$pagination = paginate((int) $stmt->fetchColumn(), 12);

$stmt = db()->prepare(
    'SELECT s.id, s.nazwa, s.opis, s.czas_trwania_min, s.cena, k.nazwa AS kategoria,
            (SELECT COUNT(*) FROM pracownicy_uslugi pu JOIN pracownicy p ON p.id = pu.pracownik_id
             JOIN uzytkownicy u ON u.id = p.uzytkownik_id
             WHERE pu.usluga_id = s.id AND p.aktywny = 1 AND u.aktywny = 1) AS liczba_pracownikow
     FROM uslugi s JOIN kategorie_uslug k ON k.id = s.kategoria_id'
    . $whereSql . ' ORDER BY k.nazwa, s.nazwa' . sql_limit($pagination)
);
$stmt->execute($params);
$services = $stmt->fetchAll();

$tytul = 'Oferta';
require __DIR__ . '/../includes/layout/header.php';
?>
<h1 class="tytul-strony">Oferta</h1>
<p class="podtytul">Wybierz usługę i zarezerwuj termin u naszego specjalisty.</p>

<nav class="oferta-kategorie" aria-label="Kategorie">
    <a href="<?= e(url('oferta.php' . ($q !== '' ? '?q=' . urlencode($q) : ''))) ?>" class="<?= $categoryId === 0 ? 'aktywna' : '' ?>">Wszystkie</a>
    <?php foreach ($categories as $c): ?>
        <a href="<?= e(url('oferta.php?' . http_build_query(array_filter(['kategoria' => $c['id'], 'q' => $q])))) ?>"
           class="<?= $categoryId === (int) $c['id'] ? 'aktywna' : '' ?>"><?= e($c['nazwa']) ?> (<?= (int) $c['liczba'] ?>)</a>
    <?php endforeach; ?>
</nav>

<form method="get" class="filtry">
    <?php if ($categoryId): ?><input type="hidden" name="kategoria" value="<?= $categoryId ?>"><?php endif; ?>
    <div class="pole">
        <label for="q">Szukaj usługi</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="np. VIN, rejestracja, odprawa">
    </div>
    <button type="submit" class="przycisk przycisk--maly">Szukaj</button>
    <?php if ($q !== ''): ?><a href="<?= e(url('oferta.php' . ($categoryId ? '?kategoria=' . $categoryId : ''))) ?>">Wyczyść</a><?php endif; ?>
</form>

<?php if (!$services): ?>
    <p class="pusto">Brak usług pasujących do wyszukiwania.</p>
<?php endif; ?>

<div class="siatka siatka--3">
    <?php foreach ($services as $s): ?>
        <article class="karta usluga-karta">
            <span class="usluga-karta__kategoria"><?= e($s['kategoria']) ?></span>
            <h2><a href="<?= e(url('usluga.php?id=' . $s['id'])) ?>"><?= e($s['nazwa']) ?></a></h2>
            <p class="usluga-karta__opis"><?= e($s['opis'] ?? '') ?></p>
            <div class="usluga-karta__dol">
                <span><span class="cena"><?= e(format_price($s['cena'])) ?></span> · <?= (int) $s['czas_trwania_min'] ?> min</span>
                <?php if ((int) $s['liczba_pracownikow'] === 0): ?>
                    <span class="znacznik znacznik--nie">chwilowo niedostępna</span>
                <?php elseif ($canBook): ?>
                    <a class="przycisk przycisk--maly" href="<?= e(url('klient/rezerwuj.php?usluga=' . $s['id'])) ?>">Zarezerwuj</a>
                <?php else: ?>
                    <a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('usluga.php?id=' . $s['id'])) ?>">Szczegóły</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($pagination['stron'] > 1): ?>
    <div class="karta karta--tabela"><?= pagination_html($pagination) ?></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
