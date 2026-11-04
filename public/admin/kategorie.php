<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/kategorie.php');

    $stmt = db()->prepare('SELECT id, nazwa, aktywna FROM kategorie_uslug WHERE id = ?');
    $stmt->execute([post_int('id')]);
    $category = $stmt->fetch();

    if (!$category) {
        flash_set('blad', 'Nie znaleziono kategorii.');
    } elseif (post_string('akcja') === 'przelacz') {
        $newState = (int) $category['aktywna'] === 1 ? 0 : 1;
        $stmt = db()->prepare('UPDATE kategorie_uslug SET aktywna = ? WHERE id = ?');
        $stmt->execute([$newState, $category['id']]);

        $word = $newState ? 'aktywowana' : 'dezaktywowana';
        log_action('kategoria_aktywnosc', "Kategoria „{$category['nazwa']}” została {$word}.");
        flash_set('sukces', "Kategoria „{$category['nazwa']}” została {$word}.");
    } elseif (post_string('akcja') === 'usun') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM uslugi WHERE kategoria_id = ?');
        $stmt->execute([$category['id']]);
        $services = (int) $stmt->fetchColumn();

        if ($services > 0) {
            flash_set('blad', "Nie można usunąć kategorii „{$category['nazwa']}”, bo należą do niej usługi (" . plural($services, 'usługa', 'usługi', 'usług') . "). Przenieś usługi do innej kategorii albo dezaktywuj kategorię.");
        } else {
            $stmt = db()->prepare('DELETE FROM kategorie_uslug WHERE id = ?');
            $stmt->execute([$category['id']]);
            log_action('kategoria_usunieta', "Usunięto kategorię „{$category['nazwa']}”.");
            flash_set('sukces', "Kategoria „{$category['nazwa']}” została usunięta.");
        }
    }

    redirect('admin/kategorie.php');
}

$q = get_string('q');
$where = '';
$params = [];
if ($q !== '') {
    $where = ' WHERE k.nazwa LIKE ? OR k.opis LIKE ?';
    $params = [like_pattern($q), like_pattern($q)];
}

$stmt = db()->prepare('SELECT COUNT(*) FROM kategorie_uslug k' . $where);
$stmt->execute($params);
$pagination = paginate((int) $stmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT k.id, k.nazwa, k.opis, k.aktywna,
            (SELECT COUNT(*) FROM uslugi s WHERE s.kategoria_id = k.id) AS liczba_uslug
     FROM kategorie_uslug k' . $where . ' ORDER BY k.nazwa' . sql_limit($pagination)
);
$stmt->execute($params);
$categories = $stmt->fetchAll();

$tytul = 'Kategorie usług';
$zakladka = 'kategorie';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="sekcja__naglowek">
    <h1 class="tytul-strony">Kategorie usług</h1>
    <a class="przycisk" href="<?= e(url('admin/kategoria.php')) ?>">+ Dodaj kategorię</a>
</div>

<form method="get" class="filtry">
    <div class="pole">
        <label for="q">Szukaj</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nazwa lub opis">
    </div>
    <button type="submit" class="przycisk przycisk--maly">Szukaj</button>
    <?php if ($q !== ''): ?><a href="<?= e(url('admin/kategorie.php')) ?>">Wyczyść</a><?php endif; ?>
</form>

<section class="karta karta--tabela">
    <?php if (!$categories): ?>
        <p class="pusto">Brak kategorii<?= $q !== '' ? ' pasujących do wyszukiwania' : '' ?>.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Nazwa</th><th>Opis</th><th>Usługi</th><th>Status</th><th>Akcje</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td><strong><?= e($c['nazwa']) ?></strong></td>
                        <td class="tabela__opis"><?= e($c['opis']) ?></td>
                        <td><a href="<?= e(url('admin/uslugi.php?kategoria=' . $c['id'])) ?>"><?= (int) $c['liczba_uslug'] ?></a></td>
                        <td><?= active_badge((int) $c['aktywna']) ?></td>
                        <td class="tabela__akcje">
                            <a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('admin/kategoria.php?id=' . $c['id'])) ?>">Edytuj</a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="akcja" value="przelacz">
                                <button type="submit" class="przycisk przycisk--maly przycisk--szary"><?= (int) $c['aktywna'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
                            </form>
                            <form method="post" data-potwierdz="Usunąć kategorię „<?= e($c['nazwa']) ?>”? Tej operacji nie można cofnąć.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
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