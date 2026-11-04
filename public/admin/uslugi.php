<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/uslugi.php');

    $stmt = db()->prepare('SELECT id, nazwa, aktywna FROM uslugi WHERE id = ?');
    $stmt->execute([post_int('id')]);
    $service = $stmt->fetch();

    if (!$service) {
        flash_set('blad', 'Nie znaleziono usługi.');
    } elseif (post_string('akcja') === 'przelacz') {
        $newState = (int) $service['aktywna'] === 1 ? 0 : 1;
        $stmt = db()->prepare('UPDATE uslugi SET aktywna = ? WHERE id = ?');
        $stmt->execute([$newState, $service['id']]);

        $word = $newState ? 'aktywowana' : 'dezaktywowana';
        log_action('usluga_aktywnosc', "Usługa „{$service['nazwa']}” została {$word}.");
        flash_set('sukces', "Usługa „{$service['nazwa']}” została {$word}.");
    } elseif (post_string('akcja') === 'usun') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM rezerwacje WHERE usluga_id = ?');
        $stmt->execute([$service['id']]);
        $reservations = (int) $stmt->fetchColumn();

        if ($reservations > 0) {
            flash_set('blad', "Nie można usunąć usługi „{$service['nazwa']}”, bo ma w historii " . plural($reservations, 'rezerwację', 'rezerwacje', 'rezerwacji') . ". Zamiast tego ją dezaktywuj – zniknie z oferty, a historia zostanie.");
        } else {
            $stmt = db()->prepare('DELETE FROM uslugi WHERE id = ?');
            $stmt->execute([$service['id']]);
            log_action('usluga_usunieta', "Usunięto usługę „{$service['nazwa']}”.");
            flash_set('sukces', "Usługa „{$service['nazwa']}” została usunięta.");
        }
    }

    redirect('admin/uslugi.php');
}

$q = get_string('q');
$categoryId = get_int('kategoria');
$state = get_string('stan');

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(s.nazwa LIKE ? OR s.opis LIKE ?)';
    array_push($params, like_pattern($q), like_pattern($q));
}
if ($categoryId > 0) {
    $where[] = 's.kategoria_id = ?';
    $params[] = $categoryId;
}
if ($state === 'aktywne' || $state === 'nieaktywne') {
    $where[] = 's.aktywna = ?';
    $params[] = $state === 'aktywne' ? 1 : 0;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare('SELECT COUNT(*) FROM uslugi s' . $whereSql);
$stmt->execute($params);
$pagination = paginate((int) $stmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT s.id, s.nazwa, s.czas_trwania_min, s.cena, s.aktywna, k.nazwa AS kategoria,
            (SELECT COUNT(*) FROM pracownicy_uslugi pu WHERE pu.usluga_id = s.id) AS liczba_pracownikow,
            (SELECT COUNT(*) FROM rezerwacje r WHERE r.usluga_id = s.id) AS liczba_rezerwacji
     FROM uslugi s JOIN kategorie_uslug k ON k.id = s.kategoria_id'
    . $whereSql . ' ORDER BY k.nazwa, s.nazwa' . sql_limit($pagination)
);
$stmt->execute($params);
$services = $stmt->fetchAll();

$categories = db()->query('SELECT id, nazwa FROM kategorie_uslug ORDER BY nazwa')->fetchAll();

$tytul = 'Usługi';
$zakladka = 'uslugi';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="sekcja__naglowek">
    <h1 class="tytul-strony">Usługi</h1>
    <a class="przycisk" href="<?= e(url('admin/usluga.php')) ?>">+ Dodaj usługę</a>
</div>

<form method="get" class="filtry">
    <div class="pole">
        <label for="q">Szukaj</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nazwa lub opis">
    </div>
    <div class="pole">
        <label for="kategoria">Kategoria</label>
        <select id="kategoria" name="kategoria">
            <option value="">Wszystkie</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nazwa']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="pole">
        <label for="stan">Status</label>
        <select id="stan" name="stan">
            <option value="">Wszystkie</option>
            <option value="aktywne" <?= $state === 'aktywne' ? 'selected' : '' ?>>Aktywne</option>
            <option value="nieaktywne" <?= $state === 'nieaktywne' ? 'selected' : '' ?>>Nieaktywne</option>
        </select>
    </div>
    <button type="submit" class="przycisk przycisk--maly">Filtruj</button>
    <?php if ($q !== '' || $categoryId || $state !== ''): ?><a href="<?= e(url('admin/uslugi.php')) ?>">Wyczyść</a><?php endif; ?>
</form>

<section class="karta karta--tabela">
    <?php if (!$services): ?>
        <p class="pusto">Brak usług pasujących do filtrów.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Usługa</th><th>Kategoria</th><th>Czas</th><th>Cena</th><th>Pracownicy</th><th>Rezerwacje</th><th>Status</th><th>Akcje</th></tr></thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><strong><?= e($s['nazwa']) ?></strong></td>
                        <td><?= e($s['kategoria']) ?></td>
                        <td><?= (int) $s['czas_trwania_min'] ?> min</td>
                        <td class="tabela__liczba"><?= e(format_price($s['cena'])) ?></td>
                        <td><?= (int) $s['liczba_pracownikow'] ?></td>
                        <td><?= (int) $s['liczba_rezerwacji'] ?></td>
                        <td><?= active_badge((int) $s['aktywna']) ?></td>
                        <td class="tabela__akcje">
                            <a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('admin/usluga.php?id=' . $s['id'])) ?>">Edytuj</a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <input type="hidden" name="akcja" value="przelacz">
                                <button type="submit" class="przycisk przycisk--maly przycisk--szary"><?= (int) $s['aktywna'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                            </form>
                            <form method="post" data-potwierdz="Usunąć usługę „<?= e($s['nazwa']) ?>”? Tej operacji nie można cofnąć.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <input type="hidden" name="akcja" value="usun">
                                <button type="submit" class="przycisk przycisk--maly przycisk--usun">Usuń</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= pagination_html($pagination) ?>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>