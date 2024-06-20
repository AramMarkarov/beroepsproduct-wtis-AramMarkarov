<?php
session_start();
require_once 'db_connectie.php';

// Variabelen
$error = '';
$flightDetails = [];

// Check als een form opgestuurd word
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['flightNumber'])) {
    $flightNumber = $_GET['flightNumber'];

    $db = maakVerbinding();

    if ($db) {
        $stmt = $db->prepare(
            "SELECT v.vluchtnummer, v.bestemming, v.vertrektijd, iv.balienummer, v.gatecode, l.naam AS luchthaven_naam
             FROM vlucht v
             JOIN luchthaven l ON v.bestemming = l.luchthavencode
             JOIN IncheckenVlucht iv ON v.vluchtnummer = iv.vluchtnummer
             WHERE v.vluchtnummer = :flightNumber AND v.vertrektijd > CURRENT_TIMESTAMP"
        );
        $stmt->bindParam(':flightNumber', $flightNumber);

        // Debugging: Error logging
        try {
            if ($stmt->execute()) {
                $flightDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($flightDetails)) {
                    $error = "No future flights found for this flight number.";
                }
            } else {
                // Log SQL error
                $errorInfo = $stmt->errorInfo();
                $error = "SQL error: " . $errorInfo[2];
            }
        } catch (PDOException $e) {
            $error = "An issue occurred during the search of the flight: " . $e->getMessage();
        }
    } else {
        $error = "Database connection failed.";
    }
}

// Format the datetime for display
if (!empty($flightDetails)) {
    try {
        $datetime = new DateTime($flightDetails[0]['vertrektijd']);
        $formattedTime = $datetime->format('d/m/Y H:i');
    } catch (Exception $e) {
        $error = "An error occurred while processing the departure time: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>GelreAirport</title>

    <!-- Tailwind CSS script -->
    <?php include 'includes/tailwind_script.php'; ?>
</head>
<body class="bg-background text-text text-body">
<!-- Nav -->
<?php include 'includes/nav.php'; ?>
<div class="mx-[20%] w-[60%] bg-nav flex flex-col items-center justify-center rounded-lg mt-4 mb-4">
    <div class="text-center p-4">
        <h1 class="text-headline font-bold mb-4">Welcome to GelreAir</h1>
        <p class="text-subheader mb-4">Your gateway to the world. Experience comfort and convenience with GelreAir.</p>
        <form class="mt-8 space-y-6 w-full" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get">
            <label for="flightNumber"></label>
            <input id="flightNumber" name="flightNumber" type="text" required placeholder="Flightnumber" class="w-full px-3 py-2 rounded-md">
            <button type="submit" class="w-full py-2 px-4 text-white hover:text-text bg-button hover:bg-hover rounded-md transition duration-150 ease-in-out">Search flight</button>
        </form>
    </div>

    <?php if (!empty($error)): ?>
        <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if (!empty($flightDetails)): ?>
        <div class="mt-8 p-6 rounded-lg w-full max-w-lg">
            <table class="table-auto w-full">
                <thead>
                <tr>
                    <th class="px-4 py-2">Vluchtnummer</th>
                    <th class="px-4 py-2">Bestemming</th>
                    <th class="px-4 py-2">Vertrektijd</th>
                    <th class="px-4 py-2">Balie</th>
                    <th class="px-4 py-2">Gate</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($flightDetails as $flight): ?>
                    <tr>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['vluchtnummer']); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['luchthaven_naam']); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($formattedTime); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['balienummer']); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['gatecode']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Container om footer aan de vloer te plakken -->
<div class="min-h-screen"></div>

<!-- Footer -->
<?php include 'includes/footer.php'; ?>
</body>
</html>
