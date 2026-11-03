<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('administrator');

$id = get_int('id');
$userId = 0;
$data = ['imie' => '', 'nazwisko' => '', 'email' => '', 'telefon' => '', 'stanowisko' => '', 'opis' => '', 'aktywny' => 1];
$selectedServices = [];

if ($id > 0) {
    $stmt = db()->prepare(
        'SELECT p.uzytkownik_id, p.stanowisko, p.opis, p.aktywny, u.imie, u.nazwisko, u.email, u.telefon
         FROM pracownicy p JOIN uzytkownicy u ON u.id = p.uzytkownik_id WHERE p.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    if (!$row) {
        flash_set('blad', 'Nie znaleziono pracownika.');
        redirect('admin/pracownicy.php');
    }

    $userId = (int) $row['uzytkownik_id'];
    $data = $row;
    $data['opis'] = $data['opis'] ?? '';

    $stmt = db()->prepare('SELECT usluga_id FROM pracownicy_uslugi WHERE pracownik_id = ?');
    $stmt->execute([$id]);
    $selectedServices = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$services = db()->query(
    'SELECT s.id, s.nazwa, s.aktywna, k.nazwa AS kategoria FROM uslugi s
     JOIN kategorie_uslug k ON k.id = s.kategoria_id ORDER BY k.nazwa, s.nazwa'
)->fetchAll();
$serviceIds = array_map('intval', array_column($services, 'id'));
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('admin/pracownik.php' . ($id ? '?id=' . $id : ''));

    $data = user_data_from_post() + [
        'stanowisko' => post_string('stanowisko'),
        'opis' => post_string('opis'),
        'aktywny' => isset($_POST['aktywny']) ? 1 : 0,
    ];

    $posted = $_POST['uslugi'] ?? [];
    $selectedServices = [];
    if (is_array($posted)) {
        foreach ($posted as $value) {
            if (is_string($value) && ctype_digit($value) && in_array((int) $value, $serviceIds, true)) {
                $selectedServices[] = (int) $value;
            }
        }
    }
    $selectedServices = array_values(array_unique($selectedServices));

    $errors = validate_user_data($data);

    if (!isset($errors['email']) && email_taken($data['email'], $userId ?: null)) {
        $errors['email'] = 'Ten adres e-mail jest już używany przez inne konto.';
    }

    if (mb_strlen($data['stanowisko']) < 2 || mb_strlen($data['stanowisko']) > 100) {
        $errors['stanowisko'] = 'Podaj stanowisko (od 2 do 100 znaków).';
    }
    if (mb_strlen($data['opis']) > 1000) {
        $errors['opis'] = 'Opis może mieć najwyżej 1000 znaków.';
    }

    $password = '';
    if ($id === 0) {
        $password = post_password('haslo');
        $error = validate_password($password);
        if ($error) {
            $errors['haslo'] = $error;
        }
    }

    if (!$errors) {
        $db = db();
        $db->beginTransaction();

        try {
            if ($id === 0) {
                $stmt = $db->prepare(
                    "INSERT INTO uzytkownicy (imie, nazwisko, email, haslo_hash, telefon, rola) VALUES (?, ?, ?, ?, ?, 'pracownik')"
                );
                $stmt->execute([$data['imie'], $data['nazwisko'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['telefon']]);
                $userId = (int) $db->lastInsertId();

                $stmt = $db->prepare('INSERT INTO pracownicy (uzytkownik_id, stanowisko, opis, aktywny) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $data['stanowisko'], $data['opis'] ?: null, $data['aktywny']]);
                $employeeId = (int) $db->lastInsertId();
            } else {
                $employeeId = $id;
                $stmt = $db->prepare('UPDATE uzytkownicy SET imie = ?, nazwisko = ?, email = ?, telefon = ? WHERE id = ?');
                $stmt->execute([$data['imie'], $data['nazwisko'], $data['email'], $data['telefon'], $userId]);

                $stmt = $db->prepare('UPDATE pracownicy SET stanowisko = ?, opis = ?, aktywny = ? WHERE id = ?');
                $stmt->execute([$data['stanowisko'], $data['opis'] ?: null, $data['aktywny'], $id]);
            }

            $stmt = $db->prepare('DELETE FROM pracownicy_uslugi WHERE pracownik_id = ?');
            $stmt->execute([$employeeId]);
            $stmt = $db->prepare('INSERT INTO pracownicy_uslugi (pracownik_id, usluga_id) VALUES (?, ?)');
            foreach ($selectedServices as $serviceId) {
                $stmt->execute([$employeeId, $serviceId]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            if (!($e instanceof PDOException && is_duplicate_error($e))) {
                throw $e;
            }
            $errors['email'] = 'Ten adres e-mail jest już używany przez inne konto.';
        }

        if (!$errors) {
            $name = $data['imie'] . ' ' . $data['nazwisko'];
            $count = count($selectedServices);
            if ($id > 0) {
                log_action('pracownik_zmieniony', "Zmieniono dane pracownika {$name} (usług: {$count}).");
                flash_set('sukces', 'Zmiany zostały zapisane.');
                redirect('admin/pracownicy.php');
            }
            log_action('pracownik_dodany', "Dodano pracownika {$name} (usług: {$count}).");
            flash_set('sukces', "Dodano pracownika {$name}. Ustaw teraz jego godziny pracy.");
            redirect('admin/grafik.php?id=' . $employeeId);
        }
    }
}

$tytul = $id ? 'Edycja pracownika' : 'Nowy pracownik';
$zakladka = 'pracownicy';
require __DIR__ . '/../../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('admin/pracownicy.php')) ?>">Pracownicy</a> › <?= e($tytul) ?></p>

<section class="karta karta--formularz karta--szeroka">
    <div class="sekcja__naglowek">
        <h1><?= e($tytul) ?></h1>
        <?php if ($id): ?><a href="<?= e(url('admin/grafik.php?id=' . $id)) ?>">Grafik i dni wolne →</a><?php endif; ?>
    </div>

    <?php if ($errors): ?>
        <div class="komunikat komunikat--blad" role="alert">Popraw zaznaczone pola.</div>
    <?php endif; ?>

    <form method="post" class="formularz">
        <?= csrf_field() ?>

        <h2 class="formularz__sekcja">Dane osobowe i konto</h2>
        <div class="formularz__wiersz">
            <div class="pole">
                <label for="imie">Imię</label>
                <input type="text" id="imie" name="imie" value="<?= e($data['imie']) ?>" required minlength="2" maxlength="50" <?= field_attrs($errors, 'imie') ?>>
                <?= field_error($errors, 'imie') ?>
            </div>
            <div class="pole">
                <label for="nazwisko">Nazwisko</label>
                <input type="text" id="nazwisko" name="nazwisko" value="<?= e($data['nazwisko']) ?>" required minlength="2" maxlength="80" <?= field_attrs($errors, 'nazwisko') ?>>
                <?= field_error($errors, 'nazwisko') ?>
            </div>
        </div>

        <div class="formularz__wiersz">
            <div class="pole">
                <label for="email">E-mail (login)</label>
                <input type="email" id="email" name="email" value="<?= e($data['email']) ?>" required maxlength="150" <?= field_attrs($errors, 'email') ?>>
                <?= field_error($errors, 'email') ?>
            </div>
            <div class="pole">
                <label for="telefon">Telefon</label>
                <input type="tel" id="telefon" name="telefon" value="<?= e($data['telefon']) ?>" required maxlength="20" <?= field_attrs($errors, 'telefon') ?>>
                <?= field_error($errors, 'telefon') ?>
            </div>
        </div>

        <?php if (!$id): ?>
            <div class="pole">
                <label for="haslo">Hasło startowe</label>
                <input type="password" id="haslo" name="haslo" required minlength="8" maxlength="72" autocomplete="new-password" <?= field_attrs($errors, 'haslo') ?>>
                <p class="pole__podpowiedz">Min. 8 znaków, w tym litera i cyfra. Pracownik zmieni je w swoim profilu.</p>
                <?= field_error($errors, 'haslo') ?>
            </div>
        <?php endif; ?>

        <h2 class="formularz__sekcja">Stanowisko</h2>
        <div class="pole">
            <label for="stanowisko">Stanowisko</label>
            <input type="text" id="stanowisko" name="stanowisko" value="<?= e($data['stanowisko']) ?>" required minlength="2" maxlength="100" placeholder="np. Doradca importowy" <?= field_attrs($errors, 'stanowisko') ?>>
            <?= field_error($errors, 'stanowisko') ?>
        </div>

        <div class="pole">
            <label for="opis">Opis dla klientów <span class="opcjonalne">(opcjonalnie)</span></label>
            <textarea id="opis" name="opis" rows="3" maxlength="1000" <?= field_attrs($errors, 'opis') ?>><?= e($data['opis']) ?></textarea>
            <?= field_error($errors, 'opis') ?>
        </div>

        <label class="przelacznik">
            <input type="checkbox" name="aktywny" value="1" <?= (int) $data['aktywny'] ? 'checked' : '' ?>>
            Pracownik aktywny (można go wybrać przy rezerwacji)
        </label>

        <h2 class="formularz__sekcja">Wykonywane usługi</h2>
        <div class="lista-wyboru">
            <?php $lastCategory = null; ?>
            <?php foreach ($services as $s): ?>
                <?php if ($s['kategoria'] !== $lastCategory): $lastCategory = $s['kategoria']; ?>
                    <p class="lista-wyboru__grupa"><?= e($s['kategoria']) ?></p>
                <?php endif; ?>
                <label class="przelacznik">
                    <input type="checkbox" name="uslugi[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $selectedServices, true) ? 'checked' : '' ?>>
                    <?= e($s['nazwa']) ?><?= (int) $s['aktywna'] ? '' : ' <span class="znacznik znacznik--nie">nieaktywna</span>' ?>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="formularz__przyciski">
            <button type="submit" class="przycisk">Zapisz</button>
            <a href="<?= e(url('admin/pracownicy.php')) ?>">Anuluj</a>
        </div>
    </form>
</section>
<?php require __DIR__ . '/../../includes/layout/footer.php'; ?>
