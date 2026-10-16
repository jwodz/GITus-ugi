<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_logged_in();

$data = ['imie' => '', 'nazwisko' => '', 'email' => '', 'telefon' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('rejestracja.php');

    $data = user_data_from_post();
    $password = post_password('haslo');
    $password2 = post_password('haslo2');

    $errors = validate_user_data($data);

    $error = validate_password($password);
    if ($error) {
        $errors['haslo'] = $error;
    }
    if ($password2 === '') {
        $errors['haslo2'] = 'Powtórz hasło.';
    } elseif ($password !== $password2) {
        $errors['haslo2'] = 'Hasła nie są takie same.';
    }

    if (!isset($errors['email']) && email_taken($data['email'])) {
        $errors['email'] = 'Konto z tym adresem e-mail już istnieje.';
    }

    if (!$errors) {
        try {
         
            $stmt = db()->prepare(
                "INSERT INTO uzytkownicy (imie, nazwisko, email, haslo_hash, telefon, rola)
                 VALUES (?, ?, ?, ?, ?, 'klient')"
            );
            $stmt->execute([
                $data['imie'],
                $data['nazwisko'],
                $data['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $data['telefon'],
            ]);

            flash_set('sukces', 'Konto zostało utworzone. Możesz się zalogować.');
            redirect('logowanie.php');
        } catch (PDOException $e) {
            if (!is_duplicate_error($e)) {
                throw $e;
            }
            $errors['email'] = 'Konto z tym adresem e-mail już istnieje.';
        }
    }
}

$tytul = 'Rejestracja';
require __DIR__ . '/../includes/layout/header.php';
?>
<section class="karta karta--formularz">
    <h1>Załóż konto</h1>
    <p class="karta__opis">Konto pozwala rezerwować terminy i śledzić swoje rezerwacje.</p>

    <?php if ($errors): ?>
        <div class="komunikat komunikat--blad" role="alert">Popraw zaznaczone pola.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('rejestracja.php')) ?>" class="formularz">
        <?= csrf_field() ?>

        <div class="formularz__wiersz">
            <div class="pole">
                <label for="imie">Imię</label>
                <input type="text" id="imie" name="imie" value="<?= e($data['imie']) ?>"
                       required minlength="2" maxlength="50" autocomplete="given-name" <?= field_attrs($errors, 'imie') ?>>
                <?= field_error($errors, 'imie') ?>
            </div>
            <div class="pole">
                <label for="nazwisko">Nazwisko</label>
                <input type="text" id="nazwisko" name="nazwisko" value="<?= e($data['nazwisko']) ?>"
                       required minlength="2" maxlength="80" autocomplete="family-name" <?= field_attrs($errors, 'nazwisko') ?>>
                <?= field_error($errors, 'nazwisko') ?>
            </div>
        </div>

        <div class="pole">
            <label for="email">Adres e-mail</label>
            <input type="email" id="email" name="email" value="<?= e($data['email']) ?>"
                   required maxlength="150" autocomplete="email" <?= field_attrs($errors, 'email') ?>>
            <?= field_error($errors, 'email') ?>
        </div>

        <div class="pole">
            <label for="telefon">Telefon</label>
            <input type="tel" id="telefon" name="telefon" value="<?= e($data['telefon']) ?>"
                   required maxlength="20" autocomplete="tel" placeholder="np. 500 100 200" <?= field_attrs($errors, 'telefon') ?>>
            <?= field_error($errors, 'telefon') ?>
        </div>

        <div class="formularz__wiersz">
            <div class="pole">
                <label for="haslo">Hasło</label>
                <input type="password" id="haslo" name="haslo" required minlength="8" maxlength="72"
                       autocomplete="new-password" <?= field_attrs($errors, 'haslo') ?>>
                <p class="pole__podpowiedz">Min. 8 znaków, w tym litera i cyfra.</p>
                <?= field_error($errors, 'haslo') ?>
            </div>
            <div class="pole">
                <label for="haslo2">Powtórz hasło</label>
                <input type="password" id="haslo2" name="haslo2" required data-takie-samo-jak="haslo"
                       autocomplete="new-password" <?= field_attrs($errors, 'haslo2') ?>>
                <?= field_error($errors, 'haslo2') ?>
            </div>
        </div>

        <button type="submit" class="przycisk przycisk--pelny">Załóż konto</button>
    </form>

    <p class="karta__stopka">Masz już konto? <a href="<?= e(url('logowanie.php')) ?>">Zaloguj się</a></p>
</section>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
