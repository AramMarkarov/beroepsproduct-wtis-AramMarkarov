<?php
// Deze pagina is eenmaal gerunt, dus is hiermee zijn alle balie en passagier wachtwoorden hashed.
require_once 'db_connectie.php';
ini_set('max_execution_time', '0');
$db = maakVerbinding();
$default_password = "unsafe-pass";

$alter = "ALTER table Balie ALTER COLUMN wachtwoord varchar(255)";
$db->query($alter);

$balies = "SELECT balienummer from Balie";
$data = $db->query($balies);

foreach ($data as $rij) {
    $balienummer = $rij['balienummer'];
    $sql = "UPDATE balie SET wachtwoord = :wachtwoord WHERE balienummer = :bn";
    $query = $db->prepare($sql);
    $hashed = password_hash($default_password, PASSWORD_DEFAULT);
    $query->execute([":wachtwoord" => $hashed, ":bn" => $balienummer]);
}

$alter = "ALTER table Passagier ALTER COLUMN wachtwoord varchar(255)";
$db->query($alter);

$passagiers = "SELECT passagiernummer from Passagier";
$data = $db->query($passagiers);

foreach ($data as $rij) {
    $passagiernummer = $rij['passagiernummer'];
    $sql = "UPDATE Passagier SET wachtwoord = :wachtwoord WHERE passagiernummer = :pn";
    $query = $db->prepare($sql);
    $hashed = password_hash($default_password, PASSWORD_DEFAULT);
    $query->execute([":wachtwoord" => $hashed, ":pn" => $passagiernummer]);
}

echo "Wachtwoorden zijn gehashed!";