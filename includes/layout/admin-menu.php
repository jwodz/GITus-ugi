<?php
$zakladki = [
    'pulpit' => ['admin/index.php', 'Pulpit'],
    'rezerwacje' => ['admin/rezerwacje.php', 'Rezerwacje'],
    'kategorie' => ['admin/kategorie.php', 'Kategorie'],
    'uslugi' => ['admin/uslugi.php', 'Usługi'],
    'pracownicy' => ['admin/pracownicy.php', 'Pracownicy'],
    'uzytkownicy' => ['admin/uzytkownicy.php', 'Użytkownicy'],
    'dziennik' => ['admin/dziennik.php', 'Dziennik operacji'],
];
?>
<nav class="admin-menu" aria-label="Menu administratora">
    <?php foreach ($zakladki as $klucz => [$adres, $nazwa]): ?>
        <?php if ($klucz === $zakladka): ?>
            <a href="<?= e(url($adres)) ?>" class="aktywna" aria-current="page"><?= e($nazwa) ?></a>
        <?php else: ?>
            <a href="<?= e(url($adres)) ?>"><?= e($nazwa) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
