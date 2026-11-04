<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

$id = get_int('id');
$data = ['kategoria_id' => 0, 'nazwa' => '', 'opis' => '', 'czas_trwania_min' => '60', 'cena' => '', 'aktywna' => 1];

if ($id > 0) {
    $stmt = db()->prepare('SELECT kategoria_id, nazwa, opis, czas_trwania_min, cena, aktywna FROM uslugi WHERE id = ?');
    $stmt->execute([$id]);
    $data = $stmt->fetch();

    if (!$data) {
        flash_set('blad', 'Nie znaleziono usługi.');
        redirect('admin/uslugi.php');
    }
    $data['opis'] = $data['opis'] ?? '';
}

$categories = db()->query('SELECT id, nazwa, aktywna FROM kategorie_uslug ORDER BY nazwa')->fetchAll();
$categoryIds = array_map('intval', array_column($categories, 'id'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/usluga.php' . ($id ? '?id=' . $id : ''));

    $data = [
        'kategoria_id' => post_int('kategoria_id'),
        'nazwa' => post_string('nazwa'),
        'opis' => post_string('opis'),
        'czas_trwania_min' => post_string('czas_trwania_min'),
        'cena' => str_replace(',', '.', post_string('cena')),   // "150,50" -> "150.50"
        'aktywna' => isset($_POST['aktywna']) ? 1 : 0,
    ];

    if (!in_array($data['kategoria_id'], $categoryIds, true)) {
        $errors['kategoria_id'] = 'Wybierz kategorię z listy.';
    }

    if ($data['nazwa'] === '') {
        $errors['nazwa'] = 'Podaj nazwę usługi.';
    } elseif (mb_strlen($data['nazwa']) < 2 || mb_strlen($data['nazwa']) > 150) {
        $errors['nazwa'] = 'Nazwa musi mieć od 2 do 150 znaków.';
    } elseif (!isset($errors['kategoria_id'])) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM uslugi WHERE kategoria_id = ? AND nazwa = ? AND id <> ?');
        $stmt->execute([$data['kategoria_id'], $data['nazwa'], $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors['nazwa'] = 'W tej kategorii jest już usługa o takiej nazwie.';
        }
    }

    if (mb_strlen($data['opis']) > 1000) {
        $errors['opis'] = 'Opis może mieć najwyżej 1000 znaków.';
    }

    if (!ctype_digit($data['czas_trwania_min'])) {
        $errors['czas_trwania_min'] = 'Podaj czas trwania w minutach (liczba całkowita).';
    } elseif ((int) $data['czas_trwania_min'] < 5 || (int) $data['czas_trwania_min'] > 480) {
        $errors['czas_trwania_min'] = 'Czas trwania musi wynosić od 5 do 480 minut.';
    } elseif ((int) $data['czas_trwania_min'] % 5 !== 0) {
        $errors['czas_trwania_min'] = 'Czas trwania musi być wielokrotnością 5 minut.';
    }

    if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $data['cena'])) {
        $errors['cena'] = 'Podaj cenę, np. 150 albo 149,99 (od 0 do 99 999,99 zł).';
    }

    if (!$errors) {
        $values = [$data['kategoria_id'], $data['nazwa'], $data['opis'] ?: null, (int) $data['czas_trwania_min'], $data['cena'], $data['aktywna']];

        try {
            if ($id > 0) {
                $stmt = db()->prepare(
                    'UPDATE uslugi SET kategoria_id = ?, nazwa = ?, opis = ?, czas_trwania_min = ?, cena = ?, aktywna = ? WHERE id = ?'
                );
                $stmt->execute([...$values, $id]);
                log_action('usluga_zmieniona', "Zmieniono usługę „{$data['nazwa']}” (cena {$data['cena']} zł, {$data['czas_trwania_min']} min).");
                flash_set('sukces', 'Zmiany zostały zapisane. Ceny w istniejących rezerwacjach się nie zmieniają.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO uslugi (kategoria_id, nazwa, opis, czas_trwania_min, cena, aktywna) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute($values);
                log_action('usluga_dodana', "Dodano usługę „{$data['nazwa']}”.");
                flash_set('sukces', "Dodano usługę „{$data['nazwa']}”. Przypisz ją teraz pracownikom w zakładce Pracownicy.");
            }
            redirect('admin/uslugi.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            $errors['nazwa'] = 'W tej kategorii jest już usługa o takiej nazwie.';
        }
    }
}

$tytul = $id ? 'Edycja usługi' : 'Nowa usługa';
$zakladka = 'uslugi';
require __DIR__ . '/../../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('admin/uslugi.php')) ?>">Usługi</a> › <?= e($tytul) ?></p>

<section class="karta karta--formularz">
    <h1><?= e($tytul) ?></h1>

    <?php if ($errors): ?>
        <div class="komunikat komunikat--blad" role="alert">Popraw zaznaczone pola.</div>
    <?php endif; ?>

    <form method="post" class="formularz">
        <?= csrf_field() ?>

        <div class="pole">
            <label for="kategoria_id">Kategoria</label>
            <select id="kategoria_id" name="kategoria_id" required <?= field_attrs($errors, 'kategoria_id') ?>>
                <option value="">– wybierz –</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $data['kategoria_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['nazwa']) ?><?= (int) $c['aktywna'] ? '' : ' (nieaktywna)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'kategoria_id') ?>
        </div>

        <div class="pole">
            <label for="nazwa">Nazwa usługi</label>
            <input type="text" id="nazwa" name="nazwa" value="<?= e($data['nazwa']) ?>" required minlength="2" maxlength="150" <?= field_attrs($errors, 'nazwa') ?>>
            <?= field_error($errors, 'nazwa') ?>
        </div>

        <div class="pole">
            <label for="opis">Opis <span class="opcjonalne">(opcjonalnie)</span></label>
            <textarea id="opis" name="opis" rows="3" maxlength="1000" <?= field_attrs($errors, 'opis') ?>><?= e($data['opis']) ?></textarea>
            <?= field_error($errors, 'opis') ?>
        </div>

        <div class="formularz__wiersz">
            <div class="pole">
                <label for="czas_trwania_min">Czas trwania (minuty)</label>
                <input type="number" id="czas_trwania_min" name="czas_trwania_min" value="<?= e((string) $data['czas_trwania_min']) ?>"
                       required min="5" max="480" step="5" <?= field_attrs($errors, 'czas_trwania_min') ?>>
                <?= field_error($errors, 'czas_trwania_min') ?>
            </div>
            <div class="pole">
                <label for="cena">Cena (zł)</label>
                <input type="text" id="cena" name="cena" value="<?= e((string) $data['cena']) ?>" required inputmode="decimal"
                       placeholder="np. 150,00" <?= field_attrs($errors, 'cena') ?>>
                <?= field_error($errors, 'cena') ?>
            </div>
        </div>

        <label class="przelacznik">
            <input type="checkbox" name="aktywna" value="1" <?= (int) $data['aktywna'] ? 'checked' : '' ?>>
            Usługa aktywna (widoczna w ofercie dla klientów)
        </label>

        <div class="formularz__przyciski">
            <button type="submit" class="przycisk">Zapisz</button>
            <a href="<?= e(url('admin/uslugi.php')) ?>">Anuluj</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>