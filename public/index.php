<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$tytul = 'Strona główna';
require __DIR__ . '/../includes/layout/header.php';
$user = current_user();
?>
<section class="baner">
    <div class="baner__tekst">
        <p class="baner__nadtytul">Import samochodów ze Szwajcarii</p>
        <h1>Twoje auto ze Szwajcarii, bez papierologii na głowie</h1>
        <p>Sprawdzimy historię auta, przygotujemy dokumenty do cła i pomożemy w rejestracji.
           Zarezerwuj termin u naszego specjalisty.</p>
        <div class="baner__przyciski">
            <?php if ($user): ?>
                <a class="przycisk" href="<?= e(url(panel_url($user['rola']))) ?>">Przejdź do panelu</a>
            <?php else: ?>
                <a class="przycisk" href="<?= e(url('rejestracja.php')) ?>">Załóż konto</a>
                <a class="przycisk przycisk--obrys" href="<?= e(url('logowanie.php')) ?>">Zaloguj się</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<h2 class="tytul-sekcji">Jak to działa?</h2>
<div class="siatka siatka--3">
    <section class="karta krok">
        <span class="krok__numer">1</span>
        <h3>Konsultacja</h3>
        <p>Omawiamy wybrane auto, wyliczamy koszty cła, akcyzy i transportu.</p>
    </section>
    <section class="karta krok">
        <span class="krok__numer">2</span>
        <h3>Weryfikacja i dokumenty</h3>
        <p>Sprawdzamy historię po numerze VIN i przygotowujemy dokumenty do odprawy celnej.</p>
    </section>
    <section class="karta krok">
        <span class="krok__numer">3</span>
        <h3>Rejestracja i odbiór</h3>
        <p>Pomagamy zarejestrować auto w Polsce i przekazujemy je razem z kompletem dokumentów.</p>
    </section>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
