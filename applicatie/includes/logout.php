<?php
// Eindigd sessie en verwijst gebruiker naar de homepage.php
session_start();
session_unset();
session_destroy();
header('Location: ../homepage.php');
exit();

