<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Wylogowanie tylko przez formularz POST z tokenem CSRF.
// Zwykły link (GET) mógłby zostać wywołany przez obcą stronę, np. w tagu <img>.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf('index.php');
logout_user();
flash_set('sukces', 'Wylogowano.');
redirect('logowanie.php');
