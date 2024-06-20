<?php
session_start();
require_once 'db_connectie.php';

// Check of de balienummer correct is, als er geen balienummer is ingelogd in de sessie, redirect naar de login.
if (!isset($_SESSION['employee'])) {
    header('Location: employeelogin.php');
    exit();
}

// Een dubbelcheck of de vluchtnummer volldedig nummers zijn en of de vluchtnummer in de URL zit
if (!isset($_GET['vluchtnummer']) || !is_numeric($_GET['vluchtnummer'])) {
    die("Invalid vluchtnummer parameter.");
}
$flightNumber = $_GET['vluchtnummer'];

// Variabelen
$errors = [];
$confirmation = '';

try {
    $db = maakVerbinding();

    // Vluchtinfo ophalen
    $stmtFlight = $db->prepare("
        SELECT v.vluchtnummer, v.vertrektijd, v.gatecode, l.naam AS luchthaven_naam, v.max_gewicht_pp, v.maatschappijcode, m.max_objecten_pp, p.passagiernummer
        FROM Vlucht v
        JOIN Luchthaven l ON v.bestemming = l.luchthavencode
        JOIN Maatschappij m ON v.maatschappijcode = m.maatschappijcode
        LEFT JOIN Passagier p ON v.vluchtnummer = p.vluchtnummer
        WHERE v.vluchtnummer = :vluchtnummer
    ");
    $stmtFlight->bindParam(':vluchtnummer', $flightNumber);
    $stmtFlight->execute();
    $flight = $stmtFlight->fetch(PDO::FETCH_ASSOC);

    if (!$flight) {
        die("Flight details not found.");
    }

    // Check of de vlucht vertrekt binnen 7 dagen, alleen dan kan er bagage ingecheckt worden (normaal is het veel korter, maar voor testredenen 7 dagen)
    $departureTime = strtotime($flight['vertrektijd']);
    $currentDate = time();
    $sevenDaysLater = strtotime('+7 days', $currentDate);

    if ($departureTime < $currentDate || $departureTime > $sevenDaysLater) {
        die("You can only check in for flights within 7 days before departure.");
    }

    // Check of een POST request binnenkomt, zo ja, dan runt deze code
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $maxWeightAllowed = $flight['max_gewicht_pp'];
        $totalWeight = 0;

        // Validatie voor alle specificaties van de tassen, waarvan; totaal gewicht en het limiet van tassen
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
                        break;
                    }
                }
            }
        }

        // Check of passagier al is ingecheckt
        $passengerId = $flight['passagiernummer'];
        $stmtCheckCheckedIn = $db->prepare("
            SELECT COUNT(*) AS countCheckedIn
            FROM BagageObject
            WHERE passagiernummer = :passagiernummer
        ");
        $stmtCheckCheckedIn->bindParam(':passagiernummer', $passengerId);
        $stmtCheckCheckedIn->execute();
        $checkedIn = $stmtCheckCheckedIn->fetch(PDO::FETCH_ASSOC);

        if ($checkedIn && $checkedIn['countCheckedIn'] > 0) {
            $errors[] = "Passenger is already checked in for this flight.";
        }

        // Als er geen errors zijn, dan zal de insert statement runnen.
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
            $confirmation = "Bag check-in successful.";
        }
    }

} catch (PDOException $e) {
    // Database error handling
    if ($e->getCode() == '23000') {
        $errorMessage = "Databasefout: Passenger is already checked in for this flight.";
    } else {
        $errorMessage = "Databasefout: " . $e->getMessage();
    }
    die($errorMessage);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Bag Check-in</title>
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
        <?php if (!empty($confirmation)) : ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Success:</strong> <?= $confirmation ?>
            </div>
            <div class="flex justify-end mb-4">
                <a href="employee_dashboard.php" class="text-center w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Return to Dashboard</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?vluchtnummer=' . htmlspecialchars($flightNumber); ?>">
            <div class="mb-4">
                <label for="passenger_name" class="block text-sm font-medium text-gray-700">Passenger Name</label>
                <input type="text" id="passenger_name" name="passenger_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter passenger's name">
            </div>

            <?php for ($i = 0; $i < $flight['max_objecten_pp']; $i++) : ?>
                <div class="mb-4">
                    <label for="gewicht_<?php echo $i; ?>" class="block text-sm font-medium text-gray-700">Weight for Bag <?= ($i + 1) ?></label>
                    <input type="text" id="gewicht_<?php echo $i; ?>" name="gewicht_<?php echo $i; ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Enter weight in kg">
                </div>
            <?php endfor; ?>

            <div class="flex justify-end">
                <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Check-in</button>
            </div>
        </form>

        <?php if (!empty($errors)) : ?>
            <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error:</strong>
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error) : ?>
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
