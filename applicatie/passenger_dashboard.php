<?php
session_start();
require_once 'db_connectie.php';

// Check of gebruiker is ingelogd
if (!isset($_SESSION['passenger'])) {
    header('Location: login.php');
    exit();
}

// Fetch gegevens van sessie. Belangrijk is dat niet iedere passagier de meest recente passagiernummer kan krijgen. Dit komt omdat dezelfde gebruiker vaker in de passagier tabel voorkomt
// Ook als een php query/script zoekt naar de meest recente passagiernummer voor een komende vlucht. Als passagier inlog gegevens en passagiernummer verdeeld waren, kon er makkelijker gezocht worden naar vluchten
$passenger = $_SESSION['passenger'];
$passengerId = $passenger['passagiernummer'];

$flights = [];
$error = '';

// Veilig om DB query te schrijven omdat een login vereiste is
try {
    $db = maakVerbinding();

    // Query die vluchten zoek die aan passagier gelinkt zijn
    $stmt = $db->prepare("
        SELECT v.vluchtnummer, v.vertrektijd, v.gatecode, l.naam AS luchthaven_naam
        FROM Vlucht v
        JOIN Luchthaven l ON v.bestemming = l.luchthavencode
        JOIN Passagier p ON v.vluchtnummer = p.vluchtnummer
        WHERE p.passagiernummer = :passagiernummer
        AND v.vertrektijd > CURRENT_TIMESTAMP
        ORDER BY v.vertrektijd DESC
    ");
    $stmt->bindParam(':passagiernummer', $passengerId);
    $stmt->execute();
    $flights = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$flights) {
        $error = "No flights found for this passenger.";
    }

    // Check of de passagier al heeft ingecheckt voor de vlucht
    foreach ($flights as &$flight) {
        $stmtCheckCheckedIn = $db->prepare("
            SELECT COUNT(*) AS num_bags
            FROM BagageObject
            WHERE passagiernummer = :passagiernummer
            AND objectvolgnummer = 0
        ");
        $stmtCheckCheckedIn->bindParam(':passagiernummer', $passengerId);
        $stmtCheckCheckedIn->execute();
        $numBags = $stmtCheckCheckedIn->fetchColumn();
        // boolean om kolom ACTIONS te beheren
        if ($numBags > 0) {
            $flight['checked_in'] = true;
        } else {
            $flight['checked_in'] = false;
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
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
                            <?php if ($flight['checked_in']): ?>
                                <span class="text-green-600">User is checked in</span>
                            <?php elseif (strtotime($flight['vertrektijd']) >= time() && strtotime($flight['vertrektijd']) <= strtotime('+7 days', time())): ?>
                                <a href="bag_checkin.php?vluchtnummer=<?= htmlspecialchars($flight['vluchtnummer'], ENT_QUOTES, 'UTF-8') ?>" class="text-blue-600 hover:text-blue-900">Check-in Bags</a>
                            <?php else: ?>
                                Flight in past
                            <?php endif; ?>
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
