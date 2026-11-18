<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user = current_user();
$canBook = $user === null || $user['rola'] === 'klient';

$service = bookable_service(get_int('id'));

if (!$service) {
    http_response_code(404);
    $tytul = 'Nie znaleziono usługi';
    require __DIR__ . '/../includes/layout/header.php';
    ?>
    <section class="karta karta--srodek karta--waska">
        <h1>Nie znaleziono usługi</h1>
        <p>Ta usługa nie istnieje albo nie jest już dostępna.</p>
        <a class="przycisk" href="<?= e(url('oferta.php')) ?>">Zobacz ofertę</a>
    </section>
    <?php
    require __DIR__ . '/../includes/layout/footer.php';
    exit;
}

$duration = (int) $service['czas_trwania_min'];
$employees = [];
foreach (employees_for_service((int) $service['id']) as $employee) {
    $employee['najblizszy'] = null;
    $employee['dni'] = 0;
    foreach (available_slots_range((int) $employee['id'], $duration, date('Y-m-d'), date('Y-m-d', strtotime('+13 days'))) as $day => $slots) {
        if ($slots) {
            $employee['dni']++;
            $employee['najblizszy'] ??= [$day, $slots[0]];
        }
    }
    $employees[] = $employee;
}

$tytul = $service['nazwa'];
require __DIR__ . '/../includes/layout/header.php';
?>
<p class="okruszki"><a href="<?= e(url('oferta.php')) ?>">Oferta</a> › <?= e($service['kategoria']) ?></p>

<div class="siatka siatka--2">
    <section class="karta">
        <span class="usluga-karta__kategoria"><?= e($service['kategoria']) ?></span>
        <h1><?= e($service['nazwa']) ?></h1>
        <p><?= nl2br(e($service['opis'] ?? '')) ?></p>
        <dl class="szczegoly">
            <dt>Cena</dt><dd><span class="cena"><?= e(format_price($service['cena'])) ?></span></dd>
            <dt>Czas trwania</dt><dd><?= $duration ?> minut</dd>
            <dt>Dostępność</dt>
            <dd>
                <?php if (!$employees): ?>
                    Usługa chwilowo niedostępna – nikt jej obecnie nie wykonuje.
                <?php else: ?>
                    <?= plural(count($employees), 'specjalista', 'specjalistów', 'specjalistów') ?> wykonuje tę usługę
                <?php endif; ?>
            </dd>
        </dl>
        <?php if ($employees && $canBook): ?>
            <div class="przyciski-rzad">
                <a class="przycisk" href="<?= e(url('klient/rezerwuj.php?usluga=' . $service['id'])) ?>">Zarezerwuj termin</a>
            </div>
            <?php if ($user === null): ?>
                <p class="pole__podpowiedz">Do rezerwacji potrzebne jest konto – po zalogowaniu wrócisz do tej usługi.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="karta">
        <h2>Kto wykonuje tę usługę</h2>
        <?php if (!$employees): ?>
            <p class="pusto">Brak dostępnych pracowników.</p>
        <?php endif; ?>
        <ul class="lista-zdarzen">
            <?php foreach ($employees as $p): ?>
                <li>
                    <strong><?= e($p['imie'] . ' ' . $p['nazwisko']) ?></strong> · <?= e($p['stanowisko']) ?>
                    <?php if ($p['opis']): ?><br><span class="tabela__opis"><?= e($p['opis']) ?></span><?php endif; ?>
                    <br>
                    <?php if ($p['najblizszy']): ?>
                        <span class="znacznik znacznik--tak">najbliższy termin: <?= e(format_date($p['najblizszy'][0]) . ', ' . $p['najblizszy'][1]) ?></span>
                        <span class="tabela__opis"><?= plural($p['dni'], 'dzień', 'dni', 'dni') ?> z wolnymi godzinami w ciągu 2 tygodni</span>
                        <?php if ($canBook): ?>
                            <br><a href="<?= e(url('klient/rezerwuj.php?usluga=' . $service['id'] . '&pracownik=' . $p['id'])) ?>">Zarezerwuj u tej osoby →</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="znacznik znacznik--nie">brak wolnych terminów w ciągu 2 tygodni</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
