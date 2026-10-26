<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

$data = [
    'imie' => $user['imie'],
    'nazwisko' => $user['nazwisko'],
    'email' => $user['email'],
    'telefon' => $user['telefon'],
];
$dataErrors = [];
$passwordErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('profil.php');
    $action = post_string('akcja');

    if ($action === 'dane') {
        $data = user_data_from_post();
        $dataErrors = validate_user_data($data);

        if (!isset($dataErrors['email']) && email_taken($data['email'], (int) $user['id'])) {
            $dataErrors['email'] = 'Ten adres e-mail jest już używany przez inne konto.';
        }

        if (!$dataErrors) {
            try {
                $stmt = db()->prepare(
                    'UPDATE uzytkownicy SET imie = ?, nazwisko = ?, email = ?, telefon = ? WHERE id = ?'
                );
                $stmt->execute([$data['imie'], $data['nazwisko'], $data['email'], $data['telefon'], $user['id']]);

                flash_set('sukces', 'Dane zostały zapisane.');
                redirect('profil.php');
            } catch (PDOException $e) {
                if (!is_duplicate_error($e)) {
                    throw $e;
                }
                $dataErrors['email'] = 'Ten adres e-mail jest już używany przez inne konto.';
            }
        }
    } elseif ($action === 'haslo') {
        $oldPassword = post_password('stare_haslo');
        $newPassword = post_password('nowe_haslo');
        $newPassword2 = post_password('nowe_haslo2');

        $stmt = db()->prepare('SELECT haslo_hash FROM uzytkownicy WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if ($oldPassword === '' || !password_verify($oldPassword, $hash)) {
            $passwordErrors['stare_haslo'] = 'Obecne hasło jest nieprawidłowe.';
        }

        $error = validate_password($newPassword);
        if ($error) {
            $passwordErrors['nowe_haslo'] = $error;
        } elseif ($newPassword === $oldPassword) {
            $passwordErrors['nowe_haslo'] = 'Nowe hasło musi różnić się od obecnego.';
        }

        if ($newPassword !== $newPassword2) {
            $passwordErrors['nowe_haslo2'] = 'Hasła nie są takie same.';
        }

        if (!$passwordErrors) {
            $stmt = db()->prepare('UPDATE uzytkownicy SET haslo_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);

            flash_set('sukces', 'Hasło zostało zmienione.');
            redirect('profil.php');
        }
    }
}

$tytul = 'Mój profil';
require __DIR__ . '/../includes/layout/header.php';
?>
<h1 class="tytul-strony">Mój profil</h1>

<div class="siatka siatka--2">
    <section class="karta">
        <h2>Dane konta</h2>

        <?php if ($dataErrors): ?>
            <div class="komunikat komunikat--blad" role="alert">Popraw zaznaczone pola.</div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('profil.php')) ?>" class="formularz">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="dane">

            <div class="formularz__wiersz">
                <div class="pole">
                    <label for="imie">Imię</label>
                    <input type="text" id="imie" name="imie" value="<?= e($data['imie']) ?>"
                           required minlength="2" maxlength="50" autocomplete="given-name" <?= field_attrs($dataErrors, 'imie') ?>>
                    <?= field_error($dataErrors, 'imie') ?>
                </div>
                <div class="pole">
                    <label for="nazwisko">Nazwisko</label>
                    <input type="text" id="nazwisko" name="nazwisko" value="<?= e($data['nazwisko']) ?>"
                           required minlength="2" maxlength="80" autocomplete="family-name" <?= field_attrs($dataErrors, 'nazwisko') ?>>
                    <?= field_error($dataErrors, 'nazwisko') ?>
                </div>
            </div>

            <div class="pole">
                <label for="email">Adres e-mail</label>
                <input type="email" id="email" name="email" value="<?= e($data['email']) ?>"
                       required maxlength="150" autocomplete="email" <?= field_attrs($dataErrors, 'email') ?>>
                <?= field_error($dataErrors, 'email') ?>
            </div>

            <div class="pole">
                <label for="telefon">Telefon</label>
                <input type="tel" id="telefon" name="telefon" value="<?= e($data['telefon']) ?>"
                       required maxlength="20" autocomplete="tel" <?= field_attrs($dataErrors, 'telefon') ?>>
                <?= field_error($dataErrors, 'telefon') ?>
            </div>

            <p class="pole__podpowiedz">Rola konta: <strong><?= e(role_name($user['rola'])) ?></strong> (zmienia ją tylko administrator).</p>

            <button type="submit" class="przycisk">Zapisz dane</button>
        </form>
    </section>

    <section class="karta">
        <h2>Zmiana hasła</h2>

        <?php if ($passwordErrors): ?>
            <div class="komunikat komunikat--blad" role="alert">Hasło nie zostało zmienione.</div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('profil.php')) ?>" class="formularz">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="haslo">

            <div class="pole">
                <label for="stare_haslo">Obecne hasło</label>
                <input type="password" id="stare_haslo" name="stare_haslo" required
                       autocomplete="current-password" <?= field_attrs($passwordErrors, 'stare_haslo') ?>>
                <?= field_error($passwordErrors, 'stare_haslo') ?>
            </div>

            <div class="pole">
                <label for="nowe_haslo">Nowe hasło</label>
                <input type="password" id="nowe_haslo" name="nowe_haslo" required minlength="8" maxlength="72"
                       autocomplete="new-password" <?= field_attrs($passwordErrors, 'nowe_haslo') ?>>
                <p class="pole__podpowiedz">Min. 8 znaków, w tym litera i cyfra.</p>
                <?= field_error($passwordErrors, 'nowe_haslo') ?>
            </div>

            <div class="pole">
                <label for="nowe_haslo2">Powtórz nowe hasło</label>
                <input type="password" id="nowe_haslo2" name="nowe_haslo2" required data-takie-samo-jak="nowe_haslo"
                       autocomplete="new-password" <?= field_attrs($passwordErrors, 'nowe_haslo2') ?>>
                <?= field_error($passwordErrors, 'nowe_haslo2') ?>
            </div>

            <button type="submit" class="przycisk">Zmień hasło</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
