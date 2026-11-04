<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

$id = get_int('id');
$data = ['nazwa' => '', 'opis' => '', 'aktywna' => 1];

if ($id > 0) {
    $stmt = db()->prepare('SELECT nazwa, opis, aktywna FROM kategorie_uslug WHERE id = ?');
    $stmt->execute([$id]);
    $data = $stmt->fetch();

    if (!$data) {
        flash_set('blad', 'Nie znaleziono kategorii.');
        redirect('admin/kategorie.php');
    }
    $data['opis'] = $data['opis'] ?? '';
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/kategoria.php' . ($id ? '?id=' . $id : ''));

    $data = [
        'nazwa' => post_string('nazwa'),
        'opis' => post_string('opis'),
        'aktywna' => isset($_POST['aktywna']) ? 1 : 0,
    ];

    if ($data['nazwa'] === '') {
        $errors['nazwa'] = 'Podaj nazwę kategorii.';
    } elseif (mb_strlen($data['nazwa']) < 2 || mb_strlen($data['nazwa']) > 100) {
        $errors['nazwa'] = 'Nazwa musi mieć od 2 do 100 znaków.';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM kategorie_uslug WHERE nazwa = ? AND id <> ?');
        $stmt->execute([$data['nazwa'], $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors['nazwa'] = 'Kategoria o tej nazwie już istnieje.';
        }
    }

    if (mb_strlen($data['opis']) > 1000) {
        $errors['opis'] = 'Opis może mieć najwyżej 1000 znaków.';
    }

    if (!$errors) {
        try {
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE kategorie_uslug SET nazwa = ?, opis = ?, aktywna = ? WHERE id = ?');
                $stmt->execute([$data['nazwa'], $data['opis'] ?: null, $data['aktywna'], $id]);
                log_action('kategoria_zmieniona', "Zmieniono kategorię „{$data['nazwa']}”.");
                flash_set('sukces', 'Zmiany zostały zapisane.');
            } else {
                $stmt = db()->prepare('INSERT INTO kategorie_uslug (nazwa, opis, aktywna) VALUES (?, ?, ?)');
                $stmt->execute([$data['nazwa'], $data['opis'] ?: null, $data['aktywna']]);
                log_action('kategoria_dodana', "Dodano kategorię „{$data['nazwa']}”.");
                flash_set('sukces', "Dodano kategorię „{$data['nazwa']}”.");
            }
            redirect('admin/kategorie.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            $errors['nazwa'] = 'Kategoria o tej nazwie już istnieje.';
        }
    }
}

$tytul = $id ? 'Edycja kategorii' : 'Nowa kategoria';
$zakladka = 'kategorie';
require __DIR__ . '/../../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('admin/kategorie.php')) ?>">Kategorie</a> › <?= e($tytul) ?></p>

<section class="karta karta--formularz">
    <h1><?= e($tytul) ?></h1>

    <?php if ($errors): ?>
        <div class="komunikat komunikat--blad" role="alert">Popraw zaznaczone pola.</div>
    <?php endif; ?>

    <form method="post" class="formularz">
        <?= csrf_field() ?>

        <div class="pole">
            <label for="nazwa">Nazwa</label>
            <input type="text" id="nazwa" name="nazwa" value="<?= e($data['nazwa']) ?>" required minlength="2" maxlength="100" <?= field_attrs($errors, 'nazwa') ?>>
            <?= field_error($errors, 'nazwa') ?>
        </div>

        <div class="pole">
            <label for="opis">Opis <span class="opcjonalne">(opcjonalnie)</span></label>
            <textarea id="opis" name="opis" rows="3" maxlength="1000" <?= field_attrs($errors, 'opis') ?>><?= e($data['opis']) ?></textarea>
            <?= field_error($errors, 'opis') ?>
        </div>

        <label class="przelacznik">
            <input type="checkbox" name="aktywna" value="1" <?= (int) $data['aktywna'] ? 'checked' : '' ?>>
            Kategoria aktywna (widoczna w ofercie)
        </label>

        <div class="formularz__przyciski">
            <button type="submit" class="przycisk">Zapisz</button>
            <a href="<?= e(url('admin/kategorie.php')) ?>">Anuluj</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>