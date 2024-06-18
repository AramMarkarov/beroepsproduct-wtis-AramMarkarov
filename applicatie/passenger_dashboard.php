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

try {
    $db = maakVerbinding();

    // Query die alle vluchten van de passagier ophaalt
    $stmt = $db->prepare("
        SELECT v.vluchtnummer, v.vertrektijd, v.gatecode, l.naam AS luchthaven_naam, p.passagiernummer
        FROM Vlucht v
        JOIN Luchthaven l ON v.bestemming = l.luchthavencode
        JOIN Passagier p ON v.vluchtnummer = p.vluchtnummer
        WHERE p.passagiernummer = :passagiernummer
        ORDER BY v.vertrektijd DESC
    ");
    $stmt->bindParam(':passagiernummer', $passengerId);
    $stmt->execute();
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$flights) {
        $error = "No flights found for this passenger.";
    }

} catch (PDOException $e) {
    $error = "An error occurred while fetching flight details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">
<!-- Nav -->
<?php include 'includes/nav.php'; ?>
<div class="mx-[20%] w-[60%] flex flex-col items-center justify-center">
    <div class="text-center mb-8">
        <h1 class="text-headline font-bold mb-4">Passenger Dashboard</h1>
    </div>

    <div class="w-full bg-nav rounded-md shadow-md p-6 mb-6">
        <h2 class="text-subheader font-bold mb-4">Your Flights</h2>
        <?php if (!empty($flights)): ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-body text-text uppercase tracking-wider">Flight Number</th>
                    <th scope="col" class="px-6 py-3 text-left text-body text-text uppercase tracking-wider">Destination</th>
                    <th scope="col" class="px-6 py-3 text-left text-body text-text uppercase tracking-wider">Departure Time</th>
                    <th scope="col" class="px-6 py-3 text-left text-body text-text uppercase tracking-wider">Gate</th>
                    <th scope="col" class="px-6 py-3 text-left text-body text-text uppercase tracking-wider">Actions</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($flights as $flight): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($flight['vluchtnummer']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($flight['luchthaven_naam']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= date('d-m-y H:i', strtotime($flight['vertrektijd'])) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($flight['gatecode']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php
                            // Check of de passagier al is ingecheckt
                            $stmtCheckCheckedIn = $db->prepare("
                                SELECT COUNT(*) AS num_bags
                                FROM BagageObject
                                WHERE passagiernummer = :passagiernummer
                                AND objectvolgnummer IN (0)
                            ");
                            $stmtCheckCheckedIn->bindParam(':passagiernummer', $passengerId);
                            $stmtCheckCheckedIn->execute();
                            $numBags = $stmtCheckCheckedIn->fetchColumn();

                            if ($numBags > 0) {
                                echo '<span class="text-green-600">User is checked in</span>';
                            } else {
                                // Check of de vertrektijd binnen nu en zeven dagen is
                                $departureTime = strtotime($flight['vertrektijd']);
                                $currentDate = time();
                                $sevenDaysLater = strtotime('+7 days', $currentDate);

                                if ($departureTime >= $currentDate && $departureTime <= $sevenDaysLater) {
                                    // Weergeef checkin knop
                                    echo '<a href="bag_checkin.php?vluchtnummer=' . htmlspecialchars($flight['vluchtnummer']) . '" class="text-blue-600 hover:text-blue-900">Check-in Bags</a>';
                                } else {
                                    echo 'Flight in past';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-text text-body italic">No flights found for this passenger.</p>
        <?php endif; ?>
    </div>
</div>

<div class="min-h-screen"></div>

<!-- Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
