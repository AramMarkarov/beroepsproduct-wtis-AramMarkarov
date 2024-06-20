<?php
session_start();
require_once 'db_connectie.php';
require_once 'includes/data_functies.php';
// Variabelen
$error = '';
$flightDetails = [];


// Check of een form is opgestuurd
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['flightNumber'])) {
    // Voor andere pagina's die alleen vluchtnummer of passagiernummer zoeken, is deze functie niet meer gebruikt. Dat komt omdat er een dropdown menu is gebruikt en dus geen
    // sanitize functie vereist is. Hier is het, om het te laten zien, weergeven voor OWASP.
    $flightNumber = sanitizeInput($_GET['flightNumber']);

    // Valideer vlucht nummer op juiste format, als het klopt, maak DB verbinding
    if (!preg_match('/^\d+$/', $flightNumber)) {
        $error = "Invalid flight number format.";
    } else {
        try {
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

                // Error logging en handeling
                if ($stmt->execute()) {
                    $flightDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($flightDetails)) {
                        $error = "No future flights found for this flight number.";
                    }
                } else {
                    error_log("SQL error: " . implode(", ", $stmt->errorInfo()));
                    $error = "An error occurred while searching for the flight.";
                }
            } else {
                $error = "Database connection failed.";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $error = "An error occurred while processing your request.";
        }
    }
}

// Format vertrektijd om seconden niet te weergeven
if (!empty($flightDetails)) {
    try {
        $datetime = new DateTime($flightDetails[0]['vertrektijd']);
        $formattedTime = htmlspecialchars($datetime->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8');
    } catch (Exception $e) {
        error_log("Datetime error: " . $e->getMessage());
        $error = "An error occurred while processing the departure time.";
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
        <p class="text-red-500 mt-4"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
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
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['vluchtnummer'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['luchthaven_naam'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($formattedTime, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['balienummer'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="border px-4 py-2"><?php echo htmlspecialchars($flight['gatecode'], ENT_QUOTES, 'UTF-8'); ?></td>
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
