<?php
require_once __DIR__ . '/../includes/bootstrap.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verify_csrf('index.php');
logout_user();
flash_set('sukces', 'Wylogowano.');
redirect('logowanie.php');
