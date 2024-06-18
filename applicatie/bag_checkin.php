<?php
session_start();
require_once 'db_connectie.php';

// Check of de gebruiker een passagier is
if (!isset($_SESSION['passenger'])) {
    header('Location: login.php');
    exit();
}

// Fetch passagier details
$passenger = $_SESSION['passenger'];
$passengerId = $passenger['passagiernummer'];

// Fetch vluchtnummer from the URL parameter
if (!isset($_GET['vluchtnummer']) || !is_numeric($_GET['vluchtnummer'])) {
    die("Invalid vluchtnummer parameter.");
}
$flightNumber = $_GET['vluchtnummer'];

try {
    $db = maakVerbinding();

    // Max gewicht en max tassen (objecten) query
    $stmtFlight = $db->prepare("
        SELECT v.vluchtnummer, v.vertrektijd, v.gatecode, l.naam AS luchthaven_naam, v.max_gewicht_pp, v.maatschappijcode, m.max_objecten_pp
        FROM Vlucht v
        JOIN Luchthaven l ON v.bestemming = l.luchthavencode
        JOIN Maatschappij m ON v.maatschappijcode = m.maatschappijcode
        WHERE v.vluchtnummer = :vluchtnummer
    ");
    $stmtFlight->bindParam(':vluchtnummer', $flightNumber);
    $stmtFlight->execute();
    $flight = $stmtFlight->fetch(PDO::FETCH_ASSOC);

    if (!$flight) {
        die("Flight details not found.");
    }

    // Check of de vlucht binnen zeven dagen vertrekt, in het geval dat de gebruiker een URL aanpast om eerder te kunnen inchecken
    $departureTime = strtotime($flight['vertrektijd']);
    $currentDate = time();
    $sevenDaysLater = strtotime('+7 days', $currentDate);

    if ($departureTime < $currentDate || $departureTime > $sevenDaysLater) {
        die("You can only check in for flights within 7 days before departure.");
    }

    // Check of de passagier al is ingecheckt
    $stmtCheckedIn = $db->prepare("
        SELECT COUNT(*) AS num_bags
        FROM BagageObject
        WHERE passagiernummer = :passagiernummer
        AND objectvolgnummer BETWEEN 0 AND :max_objecten_pp
    ");
    $stmtCheckedIn->bindParam(':passagiernummer', $passengerId);
    $stmtCheckedIn->bindValue(':max_objecten_pp', $flight['max_objecten_pp'], PDO::PARAM_INT);
    $stmtCheckedIn->execute();
    $numBagsCheckedIn = $stmtCheckedIn->fetchColumn();

    if ($numBagsCheckedIn > 0) {
        die("You have already checked in your bags for this flight.");
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Validatie en proces voor iedere tas
        $maxWeightAllowed = $flight['max_gewicht_pp'];
        $totalWeight = 0;
        $errors = [];

        // Een loop die loopt over het aantal tassen toegestaan per maatschappij
        for ($i = 0; $i < $flight['max_objecten_pp']; $i++) {
            $weightKey = 'gewicht_' . $i;
            if (!empty($_POST[$weightKey])) {
                $weight = floatval($_POST[$weightKey]);
                if ($weight <= 0) {
                    $errors[] = "Weight for bag " . ($i + 1) . " must be a positive number.";
                } else {
                    $totalWeight += $weight;
                    if ($totalWeight > $maxWeightAllowed) {
                        $errors[] = "Total weight of bags exceeds allowed limit.";
                        break; // Einde check
                    }
                }
            }
        }

        // Als er geen errors zijn, loopt de query
        if (empty($errors)) {
            for ($i = 0; $i < $flight['max_objecten_pp']; $i++) {
                $weightKey = 'gewicht_' . $i;
                if (!empty($_POST[$weightKey])) {
                    $weight = floatval($_POST[$weightKey]);
                    $stmtInsertBag = $db->prepare("
                        INSERT INTO BagageObject (passagiernummer, objectvolgnummer, gewicht)
                        VALUES (:passagiernummer, :objectvolgnummer, :gewicht)
                    ");
                    $stmtInsertBag->bindParam(':passagiernummer', $passengerId);
                    $stmtInsertBag->bindParam(':objectvolgnummer', $i);
                    $stmtInsertBag->bindParam(':gewicht', $weight);
                    $stmtInsertBag->execute();
                }
            }
            // Wanneer succesvol, een redirect naar het dashboard
            header('Location: passenger_dashboard.php');
            exit();
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bag Check-in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">
<!-- Nav -->
<?php include 'includes/nav.php'; ?>
<div class="mx-[20%] w-[60%] flex flex-col items-center justify-center">
    <div class="text-center mb-8">
        <h1 class="text-headline font-bold mb-4">Bag Check-in for Flight <?= htmlspecialchars($flightNumber) ?></h1>
        <p>Destination: <?= htmlspecialchars($flight['luchthaven_naam']) ?></p>
        <p>Departure Time: <?= date('d-m-y H:i', strtotime($flight['vertrektijd'])) ?></p>
        <p>Maximum Allowed Weight per Passenger: <?= htmlspecialchars($flight['max_gewicht_pp']) ?> kg</p>
        <p>Maximum Number of Bags Allowed: <?= htmlspecialchars($flight['max_objecten_pp']) ?></p>
    </div>

    <div class="w-full bg-nav rounded-md shadow-md p-6 mb-6">
        <h2 class="text-subheader font-bold mb-4">Check-in Bags</h2>
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?vluchtnummer=' . htmlspecialchars($flightNumber); ?>">
            <?php for ($i = 0; $i < $flight['max_objecten_pp']; $i++): ?>
                <div class="mb-4">
                    <label for="gewicht_<?php echo $i; ?>" class="block text-sm font-medium text-gray-700">Weight for Bag <?= ($i + 1) ?></label>
                    <input type="text" id="gewicht_<?php echo $i; ?>" name="gewicht_<?php echo $i; ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter weight in kg">
                </div>
            <?php endfor; ?>

            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Check-in</button>
            </div>
        </form>

        <?php if (!empty($errors)): ?>
            <div class="mt-4 p-2 bg-red-100 text-red-700 border border-red-400 rounded">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="min-h-screen"></div>

<!-- Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
