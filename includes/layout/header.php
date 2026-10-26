<?php
$zalogowany = current_user();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tytul ?? 'SwissCar Import') ?> – SwissCar Import</title>
    <link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
    <script src="<?= e(url('js/formularze.js')) ?>" defer></script>
</head>
<body>
<header class="naglowek">
    <div class="naglowek__srodek">
        <a class="logo" href="<?= e(url('index.php')) ?>">
            <span class="logo__znak" aria-hidden="true">+</span>
            SwissCar <span class="logo__dopisek">Import</span>
        </a>

        <nav class="menu" aria-label="Menu główne">
            <a href="<?= e(url('index.php')) ?>">Strona główna</a>
            <?php if ($zalogowany): ?>
                <a href="<?= e(url(panel_url($zalogowany['rola']))) ?>">Mój panel</a>
                <a href="<?= e(url('profil.php')) ?>">Mój profil</a>
                <span class="menu__uzytkownik">
                    <?= e($zalogowany['imie']) ?>
                    <span class="plakietka"><?= e(role_name($zalogowany['rola'])) ?></span>
                </span>
                <form method="post" action="<?= e(url('wyloguj.php')) ?>" class="menu__wyloguj">
                    <?= csrf_field() ?>
                    <button type="submit" class="przycisk przycisk--maly przycisk--obrys">Wyloguj</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('logowanie.php')) ?>">Zaloguj się</a>
                <a class="przycisk przycisk--maly" href="<?= e(url('rejestracja.php')) ?>">Załóż konto</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="strona">
    <?php foreach (flash_get() as $komunikat): ?>
        <div class="komunikat komunikat--<?= e($komunikat['typ']) ?>" role="status"><?= e($komunikat['tresc']) ?></div>
    <?php endforeach; ?>
