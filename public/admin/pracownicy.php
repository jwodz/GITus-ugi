<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/pracownicy.php');

    $stmt = db()->prepare(
        'SELECT p.id, p.aktywny, u.imie, u.nazwisko FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id WHERE p.id = ?'
    );
    $stmt->execute([post_int('id')]);
    $employee = $stmt->fetch();

    if (!$employee || post_string('akcja') !== 'przelacz') {
        flash_set('blad', 'Nie znaleziono pracownika.');
        redirect('admin/pracownicy.php');
    }

    $name = $employee['imie'] . ' ' . $employee['nazwisko'];
    $newState = (int) $employee['aktywny'] === 1 ? 0 : 1;
    $stmt = db()->prepare('UPDATE pracownicy SET aktywny = ? WHERE id = ?');
    $stmt->execute([$newState, $employee['id']]);

    $word = $newState ? 'aktywowany' : 'dezaktywowany';
    log_action('pracownik_aktywnosc', "Pracownik {$name} został {$word}.");
    flash_set('sukces', "Pracownik {$name} został {$word}.");

    if ($newState === 0) {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM rezerwacje WHERE pracownik_id = ? AND data_rezerwacji >= ? AND status IN ('oczekujaca', 'potwierdzona')"
        );
        $stmt->execute([$employee['id'], date('Y-m-d')]);
        $upcoming = (int) $stmt->fetchColumn();
        if ($upcoming > 0) {
            flash_set('info', "Uwaga: {$name} ma " . plural($upcoming, 'nadchodzącą wizytę', 'nadchodzące wizyty', 'nadchodzących wizyt') . '. Sprawdź je w zakładce Rezerwacje.');
        }
    }

    redirect('admin/pracownicy.php');
}

$q = get_string('q');
$where = '';
$params = [];
if ($q !== '') {
    $where = " WHERE CONCAT(u.imie, ' ', u.nazwisko) LIKE ? OR p.stanowisko LIKE ? OR u.email LIKE ?";
    $params = [like_pattern($q), like_pattern($q), like_pattern($q)];
}

$stmt = db()->prepare('SELECT COUNT(*) FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id' . $where);
$stmt->execute($params);
$pagination = paginate((int) $stmt->fetchColumn());

$stmt = db()->prepare(
    "SELECT p.id, p.stanowisko, p.aktywny, u.imie, u.nazwisko, u.email, u.telefon, u.aktywny AS konto_aktywne,
            (SELECT COUNT(*) FROM pracownicy_uslugi pu WHERE pu.pracownik_id = p.id) AS liczba_uslug,
            (SELECT COUNT(*) FROM rezerwacje r WHERE r.pracownik_id = p.id AND r.data_rezerwacji >= ?
                AND r.status IN ('oczekujaca', 'potwierdzona')) AS nadchodzace
     FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id"
    . $where . ' ORDER BY u.nazwisko, u.imie' . sql_limit($pagination)
);
$stmt->execute([date('Y-m-d'), ...$params]);
$employees = $stmt->fetchAll();

$tytul = 'Pracownicy';
$zakladka = 'pracownicy';
require __DIR__ . '/../../includes/layout/header.php';
?>
<div class="sekcja__naglowek">
    <h1 class="tytul-strony">Pracownicy</h1>
    <a class="przycisk" href="<?= e(url('admin/pracownik.php')) ?>">+ Dodaj pracownika</a>
</div>

<form method="get" class="filtry">
    <div class="pole">
        <label for="q">Szukaj</label>
        <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Imię, nazwisko, stanowisko, e-mail">
    </div>
    <button type="submit" class="przycisk przycisk--maly">Szukaj</button>
    <?php if ($q !== ''): ?><a href="<?= e(url('admin/pracownicy.php')) ?>">Wyczyść</a><?php endif; ?>
</form>

<section class="karta karta--tabela">
    <?php if (!$employees): ?>
        <p class="pusto">Brak pracowników<?= $q !== '' ? ' pasujących do wyszukiwania' : '' ?>.</p>
    <?php else: ?>
        <div class="tabela-kontener">
            <table class="tabela">
                <thead><tr><th>Pracownik</th><th>Stanowisko</th><th>Kontakt</th><th>Usługi</th><th>Nadchodzące wizyty</th><th>Status</th><th>Akcje</th></tr></thead>
                <tbody>
                <?php foreach ($employees as $p): ?>
                    <tr>
                        <td>
                            <strong><?= e($p['imie'] . ' ' . $p['nazwisko']) ?></strong>
                            <?php if (!(int) $p['konto_aktywne']): ?><br><span class="znacznik znacznik--nie">konto zablokowane</span><?php endif; ?>
                        </td>
                        <td><?= e($p['stanowisko']) ?></td>
                        <td class="tabela__opis"><?= e($p['email']) ?><br><?= e($p['telefon']) ?></td>
                        <td><?= (int) $p['liczba_uslug'] ?></td>
                        <td><a href="<?= e(url('admin/rezerwacje.php?pracownik=' . $p['id'] . '&od=' . date('Y-m-d'))) ?>"><?= (int) $p['nadchodzace'] ?></a></td>
                        <td><?= active_badge((int) $p['aktywny'], 'Aktywny', 'Nieaktywny') ?></td>
                        <td class="tabela__akcje">
                            <a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('admin/pracownik.php?id=' . $p['id'])) ?>">Edytuj</a>
                            <a class="przycisk przycisk--maly przycisk--obrys" href="<?= e(url('admin/grafik.php?id=' . $p['id'])) ?>">Grafik</a>
                            <form method="post" <?= (int) $p['aktywny'] ? 'data-potwierdz="Dezaktywować pracownika ' . e($p['imie'] . ' ' . $p['nazwisko']) . '? Nie będzie można go wybrać przy nowej rezerwacji."' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="akcja" value="przelacz">
                                <button type="submit" class="przycisk przycisk--maly przycisk--szary"><?= (int) $p['aktywny'] ? 'Dezaktywuj' : 'Aktywuj' ?></button>
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
