<?php
require_once __DIR__ . '/../includes/bootstrap.php';
redirect_if_logged_in();

$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('logowanie.php');

    $email = post_string('email');
    $password = post_password('haslo');

    if ($email === '' || $password === '') {
        $error = 'Podaj adres e-mail i hasło.';
    } else {
        $error = attempt_login($email, $password);
    }

    if ($error === null) {
        $user = current_user(true);
        flash_set('sukces', 'Witaj, ' . $user['imie'] . '!');
        redirect(panel_url($user['rola']));
    }
}

$tytul = 'Logowanie';
require __DIR__ . '/../includes/layout/header.php';
?>
<section class="karta karta--formularz karta--waska">
    <h1>Zaloguj się</h1>

    <?php if ($error): ?>
        <div class="komunikat komunikat--blad" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('logowanie.php')) ?>" class="formularz">
        <?= csrf_field() ?>

        <div class="pole">
            <label for="email">Adres e-mail</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
        </div>

        <div class="pole">
            <label for="haslo">Hasło</label>
            <input type="password" id="haslo" name="haslo" required autocomplete="current-password">
        </div>

        <button type="submit" class="przycisk przycisk--pelny">Zaloguj się</button>
    </form>

    <p class="karta__stopka">Nie masz konta? <a href="<?= e(url('rejestracja.php')) ?>">Zarejestruj się</a></p>
</section>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
