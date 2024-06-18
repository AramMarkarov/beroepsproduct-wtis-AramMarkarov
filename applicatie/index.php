<?php
require_once 'db_connectie.php';

ini_set('max_execution_time', '0');
$db = maakVerbinding();
// Dit alles is genomen van Fritz en aangepast door Quirijn
// Update wachtwoord kolommen naar varchar(255)
$alter = "ALTER table Balie ALTER COLUMN wachtwoord varchar(255)";
$db->query($alter);
$alter = "ALTER table Passagier ALTER COLUMN wachtwoord varchar(255)";
$db->query($alter);

// Functie om te controleren of een wachtwoord gehashed is
function isHashed($password) {
    return isset($password[0]) && $password[0] === '$';
}

// Update wachtwoorden in de Balie tabel
$balies = "SELECT balienummer, wachtwoord from Balie";
$data = $db->query($balies);
foreach($data as $rij) {
    if (isHashed($rij['wachtwoord'])) {
        continue;
    }
    $balienummer = $rij['balienummer'];
    $hashed = password_hash($rij['wachtwoord'], PASSWORD_DEFAULT); // Hash het bestaande wachtwoord
    $sql = "UPDATE balie SET wachtwoord = :wachtwoord WHERE balienummer = :bn";
    $query = $db->prepare($sql);
    $query->execute([":wachtwoord" => $hashed, ":bn" => $balienummer]);
}

// Update wachtwoorden in de Passagier tabel
$passagiers = "SELECT passagiernummer, wachtwoord from Passagier";
$data = $db->query($passagiers);
foreach($data as $rij) {
    if (isHashed($rij['wachtwoord'])) {
        continue;
    }
    $passagiernummer = $rij['passagiernummer'];
    $hashed = password_hash($rij['wachtwoord'], PASSWORD_DEFAULT); // Hash het bestaande wachtwoord
    $sql = "UPDATE Passagier SET wachtwoord = :wachtwoord WHERE passagiernummer = :pn";
    $query = $db->prepare($sql);
    $query->execute([":wachtwoord" => $hashed, ":pn" => $passagiernummer]);
}

echo "Wachtwoorden zijn bijgewerkt!";

// Redirect naar homepage nadat alle wachtwoorden zijn gehashed
header("Location: homepage.php");
exit();