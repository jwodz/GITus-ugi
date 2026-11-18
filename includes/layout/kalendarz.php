<?php
$dzisiaj = date('Y-m-d');
$tygodnie = calendar_weeks($dzisiaj, 9);
$naglowkiDni = ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'Sb', 'Nd'];
?>
<div class="kalendarz-uklad">
    <section class="karta kalendarz" data-api="<?= e($apiUrl) ?>" data-link-godziny="<?= e($linkGodziny) ?>" data-czas="<?= (int) $czasTrwania ?>">
        <div class="kalendarz__legenda">
            <span><i class="kalendarz__kropka kalendarz__kropka--wolny"></i> są wolne terminy</span>
            <span><i class="kalendarz__kropka kalendarz__kropka--brak"></i> brak terminów</span>
        </div>
        <div class="kalendarz__siatka" role="grid" aria-label="Kalendarz dostępnych dni">
            <?php foreach ($naglowkiDni as $naglowek): ?>
                <div class="kalendarz__naglowek" role="columnheader"><?= $naglowek ?></div>
            <?php endforeach; ?>
            <?php foreach ($tygodnie as $tydzien): ?>
                <?php foreach ($tydzien as $dzien): ?>
                    <?php
                    $liczba = $dniKalendarza[$dzien] ?? null;
                    $klasy = ['kalendarz__dzien'];
                    if ($liczba === null) {
                        $klasy[] = 'kalendarz__dzien--poza';
                    } elseif ($liczba === 0) {
                        $klasy[] = 'kalendarz__dzien--brak';
                    } else {
                        $klasy[] = 'kalendarz__dzien--wolny';
                    }
                    if ($dzien === $dzisiaj) {
                        $klasy[] = 'kalendarz__dzien--dzis';
                    }
                    if ($dzien === $wybranaData) {
                        $klasy[] = 'kalendarz__dzien--wybrany';
                    }
                    $etykieta = (int) date('j', strtotime($dzien));
                    $miesiac = date('j', strtotime($dzien)) === '1' || $dzien === $tygodnie[0][0] ? date('m', strtotime($dzien)) : '';
                    ?>
                    <?php if ($liczba): ?>
                        <a href="<?= e(str_replace('__DATA__', $dzien, $linkDnia)) ?>" class="<?= implode(' ', $klasy) ?>" data-data="<?= e($dzien) ?>"
                           aria-label="<?= e(format_date($dzien) . ', wolne terminy: ' . $liczba) ?>"<?= $dzien === $wybranaData ? ' aria-current="date"' : '' ?>>
                            <span class="kalendarz__numer"><?= $etykieta ?><?= $miesiac !== '' ? '.' . $miesiac : '' ?></span>
                            <span class="kalendarz__ile"><?= $liczba ?></span>
                        </a>
                    <?php else: ?>
                        <span class="<?= implode(' ', $klasy) ?>">
                            <span class="kalendarz__numer"><?= $etykieta ?><?= $miesiac !== '' ? '.' . $miesiac : '' ?></span>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <p class="pole__podpowiedz">Terminy można rezerwować od dziś do <?= e(format_date(booking_last_day())) ?>. Liczba w kratce to liczba wolnych godzin.</p>
    </section>

    <section class="karta godziny-wybor" id="godziny" aria-live="polite">
        <?php if ($wybranaData === ''): ?>
            <h2>Wolne godziny</h2>
            <p class="pusto">Wybierz dzień w kalendarzu.</p>
        <?php else: ?>
            <h2><?= e(day_name((int) date('N', strtotime($wybranaData))) . ', ' . format_date($wybranaData)) ?></h2>
            <?php if (!$wybraneGodziny): ?>
                <p class="pusto">W tym dniu nie ma już wolnych godzin.</p>
            <?php else: ?>
                <div class="godziny-wybor__lista">
                    <?php foreach ($wybraneGodziny as $godzina): ?>
                        <a class="przycisk-godzina<?= $godzina === ($wybranaGodzina ?? '') ? ' przycisk-godzina--wybrana' : '' ?>"
                           href="<?= e(str_replace(['__DATA__', '__GODZINA__'], [$wybranaData, $godzina], $linkGodziny)) ?>">
                            <?= e($godzina) ?><small>–<?= e(add_minutes($godzina, (int) $czasTrwania)) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
