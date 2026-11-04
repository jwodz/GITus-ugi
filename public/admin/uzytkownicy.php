<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = require_role('administrator');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/uzytkownicy.php');

    $stmt = db()->prepare('SELECT id, imie, nazwisko, email, aktywny FROM uzytkownicy WHERE id = ?');
    $stmt->execute([post_int('id')]);
    $account = $stmt->fetch();

    if (!$account || post_string('akcja') !== 'przelacz') {
        flash_set('blad', 'Nie znaleziono użytkownika.');
    } elseif ((int) $account['id'] === (int) $admin['id']) {
        flash_set('blad', 'Nie możesz zablokować własnego konta.');
    } else {
        $newState = (int) $account['aktywny'] === 1 ? 0 : 1;
        $stmt = db()->prepare('UPDATE uzytkownicy SET aktywny = ? WHERE id = ?');
        $stmt->execute([$newState, $account['id']]);

        $word = $newState ? 'odblokowane' : 'zablokowane';
        log_action('uzytkownik_blokada', "Konto {$account['email']} zostało {$word}.");
        flash_set('sukces', "Konto {$account['imie']} {$account['nazwisko']} zostało {$word}.");
    }

    redirect('admin/uzytkownicy.php');
}

$q = get_string('q');
$role = get_string('rola');
if (!isset(ROLE_PANELS[$role])) {
    $role = '';
}

$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(CONCAT(u.imie, ' ', u.nazwisko) LIKE ? OR u.email LIKE ? OR u.telefon LIKE ?)";
    array_push($params, like_pattern($q), like_pattern($q), like_pattern($q));
}
if ($role !== '') {
    $where[] = 'u.rola = ?';
    $params[] = $role;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare('SELECT COUNT(*) FROM uzytkownicy u' . $whereSql);
$stmt->execute($params);
$pagination = paginate((int) $stmt->fetchColumn());

$stmt = db()->prepare(
    'SELECT u.id, u.imie, u.nazwisko, u.email, u.telefon, u.rola, u.aktywny, u.utworzono, p.id AS pracownik_id,
            (SELECT COUNT(*) FROM rezerwacje r WHERE r.uzytkownik_id = u.id) AS liczba_rezerwacji
     FROM uzytkownicy u LEFT JOIN pracownicy p ON p.uzytkownik_id = u.id'
    . $whereSql . ' ORDER BY u.utworzono DESC, u.id DESC' . sql_limit($pagination)
);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$tytul = 'Użytkownicy';
$zakladka = 'uzytkownicy';
require __DIR__ . '/../../includes/layout/header.php';
?>
<h1 class="tytul-strony">Użytkownicy</h1>
<p class="podtytul">Klientów zakłada rejestracja, pracowników – zakładka Pracownicy.</p>

<form method="get" class="filtry">
    <div class="pole">
        <label for="q">Szukaj</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Imię i nazwisko, e-mail, telefon">
    </div>
    <div class="pole">
        <label for="rola">Rola</label>
        <select id="rola" name="rola">
            <option value="">Wszystkie</option>
            <?php foreach (array_keys(ROLE_PANELS) as $r): ?>
                <option value="<?= e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= e(role_name($r)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="przycisk przycisk--maly">Filtruj</button>
    <?php if ($q !== '' || $role !== ''): ?><a href="<?= e(url('admin/uzytkownicy.php')) ?>">Wyczyść</a><?php endif; ?>
</form>

<section class="karta karta--tabela">
    <?php if (!$accounts): ?>
        <p class="pusto">Brak użytkowników pasujących do filtrów.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Użytkownik</th><th>Kontakt</th><th>Rola</th><th>Rezerwacje</th><th>Konto</th><th>Data rejestracji</th><th>Akcje</th></tr></thead>
                <tbody>
                <?php foreach ($accounts as $a): ?>
                    <tr>
                        <td><strong><?= e($a['imie'] . ' ' . $a['nazwisko']) ?></strong></td>
                        <td class="tabela__opis"><?= e($a['email']) ?><br><?= e($a['telefon']) ?></td>
                        <td><?= e(role_name($a['rola'])) ?></td>
                        <td>
                            <?php if ($a['rola'] === 'klient' && (int) $a['liczba_rezerwacji'] > 0): ?>
                                <a href="<?= e(url('admin/rezerwacje.php?q=' . urlencode($a['email']))) ?>"><?= (int) $a['liczba_rezerwacji'] ?></a>
                            <?php else: ?>
                                <?= (int) $a['liczba_rezerwacji'] ?>
                            <?php endif; ?>
                        </td>
                        <td><?= active_badge((int) $a['aktywny'], 'Aktywne', 'Zablokowane') ?></td>
                        <td><?= e(date('d.m.Y', strtotime($a['utworzono']))) ?></td>
                        <td class="tabela__akcje">
                            <?php if ($a['pracownik_id']): ?>
                                <a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('admin/pracownik.php?id=' . $a['pracownik_id'])) ?>">Edytuj</a>
                            <?php endif; ?>
                            <?php if ((int) $a['id'] === (int) $admin['id']): ?>
                                <span class="pusto">to Ty</span>
                            <?php else: ?>
                                <form method="post" data-potwierdz="<?= (int) $a['aktywny'] ? 'Zablokować' : 'Odblokować' ?> konto <?= e($a['email']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <input type="hidden" name="akcja" value="przelacz">
                                    <button type="submit" class="przycisk przycisk--maly <?= (int) $a['aktywny'] ? 'przycisk--usun' : 'przycisk--szary' ?>"><?= (int) $a['aktywny'] ? 'Zablokuj' : 'Odblokuj' ?></button>
                                </form>
                            <?php endif; ?>
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